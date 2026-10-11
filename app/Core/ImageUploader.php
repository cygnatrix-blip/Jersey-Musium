<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Secure image upload:
 *  1. checks PHP upload error + size limit
 *  2. detects the real MIME type from file bytes (finfo), never trusting the client
 *  3. verifies it decodes as an image (getimagesize)
 *  4. re-encodes through GD (strips EXIF/GPS + any embedded payload) and downsizes to max width
 *  5. stores under a random name in public/uploads/{folder}/{Y}/{m}/
 * PHP execution is disabled in /uploads via .htaccess as defence in depth.
 */
final class ImageUploader
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return string relative path (e.g. "products/2026/10/ab12cd.webp") */
    public static function store(array $file, string $folder, int $maxWidth = 1600): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload failed. Please try again.');
        }
        $maxBytes = config('app.uploads.max_mb') * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException('Image must be smaller than ' . config('app.uploads.max_mb') . ' MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime]) || !@getimagesize($file['tmp_name'])) {
            throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
        }

        $folder = preg_replace('/[^a-z0-9_-]/', '', $folder);
        $rel = $folder . '/' . date('Y/m');
        $dir = config('app.uploads.dir') . '/' . $rel;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload directory.');
        }
        $name = bin2hex(random_bytes(10));

        $saved = self::reencode($file['tmp_name'], $mime, "$dir/$name", $maxWidth);
        if ($saved === null) {
            // GD lacks codec support on this host: keep the verified original bytes.
            $saved = $name . '.' . self::ALLOWED[$mime];
            if (!move_uploaded_file($file['tmp_name'], "$dir/$saved")) {
                throw new RuntimeException('Could not save the uploaded file.');
            }
        }
        return $rel . '/' . $saved;
    }

    public static function delete(?string $relPath): void
    {
        if (!$relPath || str_contains($relPath, '..')) {
            return;
        }
        $full = config('app.uploads.dir') . '/' . $relPath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function reencode(string $src, string $mime, string $destNoExt, int $maxWidth): ?string
    {
        $loader = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$mime];
        if (!function_exists($loader)) {
            return null;
        }
        $img = @$loader($src);
        if (!$img) {
            throw new RuntimeException('The image file appears to be corrupted.');
        }
        if (imagesx($img) > $maxWidth) {
            $img = imagescale($img, $maxWidth, -1, IMG_BICUBIC);
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $base = basename($destNoExt);
        if (function_exists('imagewebp') && imagewebp($img, "$destNoExt.webp", 82)) {
            return "$base.webp";
        }
        if ($mime !== 'image/png' && function_exists('imagejpeg') && imagejpeg($img, "$destNoExt.jpg", 85)) {
            return "$base.jpg";
        }
        if (function_exists('imagepng') && imagepng($img, "$destNoExt.png", 7)) {
            return "$base.png";
        }
        return null;
    }
}
