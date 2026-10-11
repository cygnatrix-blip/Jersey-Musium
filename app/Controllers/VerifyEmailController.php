<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;
use App\Services\OtpService;

/** Customer email verification with a 6-digit code (sent at sign-up; resend with cooldown). */
final class VerifyEmailController extends Controller
{
    public function show(Request $request): Response
    {
        if (auth()->verified()) {
            return redirect('/account');
        }
        $user = auth()->user();
        // First visit without a live code (e.g. older session) → send one automatically.
        if (!db()->scalar("SELECT 1 FROM otp_codes WHERE subject_type = 'user' AND subject_id = :id AND purpose = 'verify_email' AND expires_at > NOW()", ['id' => $user['id']])) {
            OtpService::issue('user', (int) $user['id'], 'verify_email', $user['email'], $user['name']);
        }
        seo()->title('Verify your email')->noindex();
        return view('store/auth/verify', [
            'email'    => OtpService::mask($user['email']),
            'cooldown' => OtpService::cooldown('user', (int) $user['id'], 'verify_email'),
        ]);
    }

    public function verify(Request $request): Response
    {
        $user = auth()->user();
        if (auth()->verified()) {
            return redirect('/account');
        }
        $result = OtpService::verify('user', (int) $user['id'], 'verify_email', (string) $request->input('code'));
        if ($result !== 'ok') {
            $left = OtpService::attemptsLeft('user', (int) $user['id'], 'verify_email');
            return redirect('/verify-email')->with('error', $result === 'invalid' && $left > 0
                ? "Incorrect code. $left attempt(s) left." : OtpService::message($result));
        }
        db()->update('users', ['email_verified_at' => now()], 'id = :id', ['id' => $user['id']]);
        auth()->refresh();
        NotificationService::welcome($user);

        $to = (string) session()->get('intended', '/');
        session()->forget('intended');
        return redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/')
            ->with('success', 'Email verified – welcome to the club!');
    }

    public function resend(Request $request): Response
    {
        $user = auth()->user();
        if (auth()->verified()) {
            return redirect('/account');
        }
        if (($wait = OtpService::cooldown('user', (int) $user['id'], 'verify_email')) > 0) {
            return redirect('/verify-email')->with('error', "Please wait $wait seconds before requesting another code.");
        }
        OtpService::issue('user', (int) $user['id'], 'verify_email', $user['email'], $user['name']);
        return redirect('/verify-email')->with('success', 'A new code has been sent to ' . OtpService::mask($user['email']) . '.');
    }
}
