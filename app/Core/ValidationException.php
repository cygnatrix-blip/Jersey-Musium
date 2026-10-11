<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by Controller::validate(); rendered as redirect-back-with-errors (or 422 JSON). */
final class ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }
}
