<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP templates in app/Views. Templates must escape output with e().
 * A template is rendered first, then wrapped in its layout which receives $content.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);
        return $layout ? self::capture($layout, $data + ['content' => $content]) : $content;
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [$template] not found");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
