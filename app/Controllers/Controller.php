<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\ValidationException;
use App\Core\Validator;

abstract class Controller
{
    /** Validate request input or throw (→ redirect back with errors & old input). */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        $v = Validator::make($request->all(), $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->errors());
        }
        return $v->validated();
    }

    /** Render a view inside the admin layout. */
    protected function admin(string $template, array $data = []): \App\Core\Response
    {
        return view('admin/' . $template, $data, 'layouts/admin');
    }
}
