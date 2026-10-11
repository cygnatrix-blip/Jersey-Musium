<?php

declare(strict_types=1);

namespace App\Core;

/** Admin-panel authentication + role based permissions. Separate from customer auth. */
final class AdminAuth
{
    private const IDLE_TIMEOUT = 1800;      // 30 minutes of inactivity logs the admin out
    private const ABSOLUTE_TIMEOUT = 43200; // and every admin session ends after 12 hours regardless

    private static ?AdminAuth $instance = null;
    private ?array $admin = null;
    private bool $loaded = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->admin;
        }
        $this->loaded = true;
        $id = session()->get('admin_id');
        if (!$id) {
            return null;
        }
        if (time() - (int) session()->get('admin_seen', 0) > self::IDLE_TIMEOUT
            || time() - (int) session()->get('admin_login_at', 0) > self::ABSOLUTE_TIMEOUT) {
            $this->logout();
            return null;
        }
        session()->set('admin_seen', time());
        $row = db()->first(
            "SELECT a.id, a.name, a.email, a.status, r.slug AS role_slug, r.name AS role_name, r.permissions
               FROM admins a JOIN roles r ON r.id = a.role_id
              WHERE a.id = :id AND a.status = 'active'",
            ['id' => $id]
        );
        if ($row) {
            $row['permissions'] = json_decode((string) $row['permissions'], true) ?: [];
        } else {
            session()->forget('admin_id');
        }
        return $this->admin = $row;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        $a = $this->user();
        return $a !== null && ($a['role_slug'] === 'super_admin' || in_array($permission, $a['permissions'], true));
    }

    public function attempt(string $email, string $password): ?array
    {
        $row = db()->first('SELECT * FROM admins WHERE email = :e', ['e' => strtolower($email)]);
        $hash = $row['password_hash'] ?? '$2y$12$ZJsZm89MSBFHNUHzie9oNegOrzguYNZhmnF1J4c9twNJq3EEkMVgG';
        if (!password_verify($password, $hash) || !$row || $row['status'] !== 'active') {
            return null;
        }
        return $row;
    }

    public function login(array $admin): void
    {
        session()->regenerate();
        session()->set('admin_id', (int) $admin['id']);
        session()->set('admin_seen', time());
        session()->set('admin_login_at', time());
        $this->loaded = false;
        db()->update('admins', ['last_login_at' => now(), 'last_login_ip' => request()->ip()], 'id = :id', ['id' => $admin['id']]);
    }

    public function logout(): void
    {
        session()->forget('admin_id');
        session()->forget('admin_seen');
        session()->forget('admin_login_at');
        session()->regenerate();
        $this->admin = null;
    }
}
