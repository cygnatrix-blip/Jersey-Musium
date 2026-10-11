<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;
use App\Services\OtpService;

final class AuthController extends Controller
{
    public function loginForm(Request $request): Response
    {
        seo()->title('Login')->noindex();
        return view('store/auth/login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email', 'password' => 'required|max:200']);
        $key = 'login|' . strtolower($data['email']);

        // Per-account lockout on top of per-IP throttle: 5 failures → 15 minutes.
        if (!RateLimiter::hit($key, 5, 900)) {
            return back()->withInput()->with('error', 'Too many failed attempts. Try again in 15 minutes or reset your password.');
        }
        $user = auth()->attempt($data['email'], $data['password']);
        if (!$user) {
            return back()->withInput()->withErrors(['email' => ['Invalid email or password.']]);
        }
        RateLimiter::clear($key);
        auth()->login($user);

        $intended = (string) session()->get('intended', '/account');
        session()->forget('intended');
        return redirect(str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : '/account')
            ->with('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
    }

    public function registerForm(Request $request): Response
    {
        seo()->title('Create account')->noindex();
        return view('store/auth/register');
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'     => 'required|min:2|max:120',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|phone',
            'password' => 'required|min:8|max:200|confirmed',
        ]);
        if (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
            return back()->withInput()->withErrors(['password' => ['Password must contain letters and numbers.']]);
        }
        $id = db()->insert('users', [
            'name'          => $data['name'],
            'email'         => strtolower($data['email']),
            'phone'         => $data['phone'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        $user = db()->first('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        auth()->login($user);
        // Account stays unverified until the 6-digit code emailed now is entered (welcome email follows).
        OtpService::issue('user', (int) $user['id'], 'verify_email', $user['email'], $user['name']);
        return redirect('/verify-email')->with('success', 'Account created! We emailed a 6-digit code to ' . OtpService::mask($user['email']) . '.');
    }

    public function logout(Request $request): Response
    {
        auth()->logout();
        return redirect('/')->with('success', 'You have been logged out.');
    }

    public function forgotForm(Request $request): Response
    {
        seo()->title('Forgot password')->noindex();
        return view('store/auth/forgot');
    }

    public function forgot(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email']);
        $email = strtolower($data['email']);
        $user = db()->first("SELECT * FROM users WHERE email = :e AND status = 'active'", ['e' => $email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db()->execute('DELETE FROM password_resets WHERE email = :e', ['e' => $email]);
            db()->insert('password_resets', [
                'email'      => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);
            NotificationService::passwordReset($email, url('/reset-password/' . $token));
        }
        // Same response whether or not the account exists (prevents email enumeration).
        return back()->with('success', 'If an account exists for that email, we have sent a reset link.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        seo()->title('Reset password')->noindex();
        return view('store/auth/reset', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token'    => 'required|max:64',
            'password' => 'required|min:8|max:200|confirmed',
        ]);
        $row = db()->first('SELECT * FROM password_resets WHERE token_hash = :t AND expires_at > NOW()', ['t' => hash('sha256', $data['token'])]);
        if (!$row) {
            return redirect('/forgot-password')->with('error', 'This reset link is invalid or has expired.');
        }
        db()->update('users', ['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)], 'email = :e', ['e' => $row['email']]);
        db()->execute('DELETE FROM password_resets WHERE email = :e', ['e' => $row['email']]);
        RateLimiter::clear('login|' . $row['email']);
        return redirect('/login')->with('success', 'Password updated. Please log in.');
    }
}
