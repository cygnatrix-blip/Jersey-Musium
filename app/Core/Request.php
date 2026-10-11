<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?Request $current = null;

    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server,
        private ?string $rawBody = null,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method']) && in_array(strtoupper($_POST['_method']), ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = strtoupper($_POST['_method']);
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        $body = $_POST;
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        $raw = null;
        if (str_contains($ctype, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $body = json_decode($raw, true) ?: [];
        }
        return self::$current = new self($method, $path, $_GET, $body, $_FILES, $_SERVER, $raw);
    }

    public static function current(): self
    {
        return self::$current ??= self::capture();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method !== 'GET' && $this->method !== 'HEAD';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $v = $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $v = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, 'on', 'true', true, 'yes'], true);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** Only the given keys, trimmed. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k);
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    /** Normalises a multi-file input (name="images[]") into a list of single-file arrays. */
    public function files(string $key): array
    {
        $f = $this->files[$key] ?? null;
        if (!$f || !is_array($f['name'])) {
            return $f ? [$f] : [];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? ($name === 'Content-Type' ? ($this->server['CONTENT_TYPE'] ?? $default) : $default);
    }

    public function rawBody(): string
    {
        return $this->rawBody ??= (file_get_contents('php://input') ?: '');
    }

    public function ip(): string
    {
        // Client-supplied headers are spoofable: only trust CF-Connecting-IP when explicitly configured
        // (TRUST_CLOUDFLARE=true and the origin only accepts Cloudflare traffic). Otherwise REMOTE_ADDR.
        $ip = config('app.trust_cloudflare') && !empty($this->server['HTTP_CF_CONNECTING_IP'])
            ? $this->server['HTTP_CF_CONNECTING_IP']
            : ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on' || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function fullUrl(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }
}
