<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use Throwable;

/**
 * One-time web installer step for hosts without SSH (database imported via phpMyAdmin):
 * creates the first Super Admin. Answers 404 unless
 *   - SETUP_TOKEN (≥ 24 chars) is set in the secrets file and given in the URL/form, and
 *   - no admin account exists yet.
 * After the first admin is created the page is permanently disabled.
 */
final class SetupController extends Controller
{
    public function show(Request $request): Response
    {
        $token = $this->guard((string) $request->query('token'));
        return view('setup', $this->state() + ['token' => $token], null);
    }

    public function store(Request $request): Response
    {
        $token = $this->guard((string) $request->input('token'));
        $state = $this->state();
        if ($state['problem']) {
            return redirect('/setup?token=' . rawurlencode($token));
        }
        $data = $this->validate($request, [
            'name'     => 'required|min:2|max:120',
            'email'    => 'required|email',
            'password' => 'required|min:12|max:200|confirmed',
        ]);
        if (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
            throw new ValidationException(['password' => ['Use letters and numbers (at least 12 characters).']]);
        }
        $id = db()->transaction(function () use ($data) {
            // Re-check inside the transaction so two simultaneous submissions cannot both succeed.
            if ((int) db()->scalar('SELECT COUNT(*) FROM admins FOR UPDATE') > 0) {
                throw new HttpException(404);
            }
            return db()->insert('admins', [
                'role_id'       => (int) db()->scalar("SELECT id FROM roles WHERE slug = 'super_admin'"),
                'name'          => $data['name'],
                'email'         => strtolower($data['email']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
        });
        AuditService::log('setup.super_admin_created', 'admin', $id, ['email' => strtolower($data['email'])]);
        logger()->info('First Super Admin created via /setup', ['email' => strtolower($data['email']), 'ip' => $request->ip()]);
        return redirect('/admin/login')->with('success', 'Super Admin created. Sign in below – then remove SETUP_TOKEN from your secrets file.');
    }

    /** 404 for everyone unless the token matches and the store has no admin yet. */
    private function guard(string $given): string
    {
        $token = (string) env('SETUP_TOKEN', '');
        if (strlen($token) < 24 || $given === '' || !hash_equals($token, $given)) {
            throw new HttpException(404);
        }
        try {
            $admins = (int) db()->scalar('SELECT COUNT(*) FROM admins');
        } catch (Throwable) {
            return $given; // tables not imported yet – state() explains
        }
        if ($admins > 0) {
            throw new HttpException(404);
        }
        return $given;
    }

    /** Pre-flight checks shown on the page. */
    private function state(): array
    {
        $problem = null;
        $newKey = null;
        try {
            if (!db()->scalar("SELECT id FROM roles WHERE slug = 'super_admin'")) {
                $problem = 'The database has no roles yet. Import database/install/install.sql in phpMyAdmin first.';
            }
        } catch (Throwable $e) {
            $problem = 'Cannot read the database tables. Check DB_* in your secrets file and import database/install/install.sql in phpMyAdmin first.';
        }
        if (!$problem && strlen((string) config('app.key')) < 32) {
            $newKey = bin2hex(random_bytes(32));
            $problem = 'APP_KEY is not set. Copy the key below into APP_KEY= in your secrets file, save it, then reload this page.';
        }
        return ['problem' => $problem, 'newKey' => $newKey];
    }
}
