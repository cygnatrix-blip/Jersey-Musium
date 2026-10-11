<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function redirect(string $to, int $status = 302): self
    {
        // Only allow relative paths or our own absolute URL (prevents open redirects).
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            if (!str_starts_with($to, config('app.url'))) {
                $to = '/';
            }
        }
        return new self('', $status, ['Location' => $to]);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** Flash a message (success|error|info) for the next request. */
    public function with(string $key, string $message): self
    {
        session()->flash($key, $message);
        return $this;
    }

    public function withErrors(array $errors): self
    {
        session()->flash('_errors', $errors);
        if ($errors && !session()->hasNewFlash('error')) {
            session()->flash('error', 'Please fix the highlighted fields.');
        }
        return $this;
    }

    public function withInput(?array $input = null): self
    {
        $input ??= request()->all();
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['current_password']);
        session()->flash('_old', $input);
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            if (!isset($this->headers['Content-Type'])) {
                $this->headers['Content-Type'] = 'text/html; charset=utf-8';
            }
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
