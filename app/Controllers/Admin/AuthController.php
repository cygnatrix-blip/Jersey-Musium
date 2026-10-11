<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\OtpService;

/**
 * Admin sign-in: password → (optional, default ON) 6-digit email OTP → session.
 * The OTP is stored only as a hash, expires in 10 minutes and allows 5 attempts.
 */
final class AuthController extends Controller
{

    public function loginForm(Request $request): Response
    {
        return view('admin/login', ['step' => 'password'], null);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email', 'password' => 'required|max:200']);
        $key = 'admin-login|' . strtolower($data['email']);
        if (!RateLimiter::hit($key, 5, 900)) {
            logger()->warning('Admin login locked', ['email' => $data['email'], 'ip' => $request->ip()]);
            return back()->with('error', 'Too many failed attempts. Try again in 15 minutes.');
        }
        $admin = admin_auth()->attempt($data['email'], $data['password']);
        if (!$admin) {
            logger()->warning('Admin login failed', ['email' => $data['email'], 'ip' => $request->ip()]);
            return back()->withInput()->with('error', 'Invalid credentials.');
        }
        RateLimiter::clear($key);

        if (config('app.admin_2fa')) {
            $this->sendOtp($admin);
            session()->regenerate();
            session()->set('admin_2fa', ['id' => (int) $admin['id'], 'at' => time()]);
            return redirect('/admin/login/verify')->with('info', 'We emailed a 6-digit code to ' . $this->mask($admin['email']) . '.');
        }
        return $this->complete($admin);
    }

    public function verifyForm(Request $request): Response
    {
        if (!$this->pending()) {
            return redirect('/admin/login');
        }
        return view('admin/login', ['step' => 'otp'], null);
    }

    public function verify(Request $request): Response
    {
        $p = $this->pending() ?? throw new \App\Core\HttpException(419, 'Your sign-in session expired. Please start again.');
        $result = OtpService::verify('admin', (int) $p['id'], 'admin_login', (string) $request->input('code'));
        if ($result !== 'ok') {
            logger()->warning('Admin OTP ' . $result, ['admin' => $p['id'], 'ip' => $request->ip()]);
            $left = OtpService::attemptsLeft('admin', (int) $p['id'], 'admin_login');
            $msg = $result === 'invalid' && $left > 0 ? "Incorrect code. $left attempt(s) left." : OtpService::message($result);
            return redirect('/admin/login/verify')->with('error', $msg);
        }
        session()->forget('admin_2fa');
        $admin = db()->first("SELECT * FROM admins WHERE id = :id AND status = 'active'", ['id' => $p['id']]);
        return $admin ? $this->complete($admin) : redirect('/admin/login');
    }

    public function resend(Request $request): Response
    {
        $p = $this->pending();
        if (!$p) {
            return redirect('/admin/login');
        }
        if (($wait = OtpService::cooldown('admin', (int) $p['id'], 'admin_login')) > 0) {
            return redirect('/admin/login/verify')->with('error', "Please wait $wait seconds before requesting another code.");
        }
        $admin = db()->first("SELECT * FROM admins WHERE id = :id AND status = 'active'", ['id' => $p['id']]);
        if ($admin) {
            $this->sendOtp($admin);
        }
        return redirect('/admin/login/verify')->with('info', 'A new code has been sent.');
    }

    public function logout(Request $request): Response
    {
        if (admin_auth()->check()) {
            AuditService::log('admin.logout');
        }
        admin_auth()->logout();
        return redirect('/admin/login')->with('success', 'Signed out.');
    }

    private function complete(array $admin): Response
    {
        admin_auth()->login($admin);
        AuditService::log('admin.login', null, null, ['2fa' => (bool) config('app.admin_2fa')]);
        $to = (string) session()->get('admin_intended', '/admin');
        session()->forget('admin_intended');
        return redirect(str_starts_with($to, '/admin') ? $to : '/admin');
    }

    private function sendOtp(array $admin): void
    {
        OtpService::issue('admin', (int) $admin['id'], 'admin_login', $admin['email'], $admin['name']);
    }

    /** Pending second-factor state (valid for 10 minutes after the password step). */
    private function pending(): ?array
    {
        $p = session()->get('admin_2fa');
        return is_array($p) && time() - (int) $p['at'] < OtpService::TTL ? $p : null;
    }

    private function mask(string $email): string
    {
        return OtpService::mask($email);
    }
}
