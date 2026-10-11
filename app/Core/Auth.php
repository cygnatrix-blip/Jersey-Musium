<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\CartService;

/** Customer authentication (session based). */
final class Auth
{
    private static ?Auth $instance = null;
    private ?array $user = null;
    private bool $loaded = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function user(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = session()->get('user_id');
            if ($id) {
                $this->user = db()->first("SELECT id, name, email, phone, status, email_verified_at, created_at FROM users WHERE id = :id AND status = 'active'", ['id' => $id]);
                if (!$this->user) {
                    session()->forget('user_id');
                }
            }
        }
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** Has the signed-in customer confirmed their email with the OTP? */
    public function verified(): bool
    {
        return !empty($this->user()['email_verified_at']);
    }

    /** Drop the per-request cache (after the user row changed). */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->user = null;
    }

    /** Returns user row on success, null on bad credentials. */
    public function attempt(string $email, string $password): ?array
    {
        $row = db()->first('SELECT * FROM users WHERE email = :e', ['e' => strtolower($email)]);
        // Always run password_verify to keep timing uniform when the user does not exist.
        $hash = $row['password_hash'] ?? '$2y$12$ZJsZm89MSBFHNUHzie9oNegOrzguYNZhmnF1J4c9twNJq3EEkMVgG';
        if (!password_verify($password, $hash) || !$row || $row['status'] !== 'active') {
            return null;
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            db()->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $row['id']]);
        }
        return $row;
    }

    public function login(array $user): void
    {
        session()->regenerate();
        session()->set('user_id', (int) $user['id']);
        $this->loaded = false;
        db()->update('users', ['last_login_at' => now()], 'id = :id', ['id' => $user['id']]);
        (new CartService())->mergeGuestCart((int) $user['id']);
    }

    public function logout(): void
    {
        session()->forget('user_id');
        session()->regenerate();
        $this->user = null;
    }
}
