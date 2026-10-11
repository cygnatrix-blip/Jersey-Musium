<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message ?: match ($status) {
            400 => 'Bad request',
            403 => 'Forbidden',
            404 => 'Page not found',
            405 => 'Method not allowed',
            419 => 'Your session expired. Please refresh and try again.',
            429 => 'Too many attempts. Please wait a moment and try again.',
            default => 'Something went wrong',
        }, $status);
    }
}
