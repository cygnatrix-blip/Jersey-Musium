<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;

/**
 * Email one-time codes (6 digits) for customer email verification and admin two-step sign-in.
 *  - stored only as HMAC-SHA256(code|subject|purpose, APP_KEY) – a DB leak does not reveal codes
 *  - valid 10 minutes, max 5 attempts, single use; issuing a new code invalidates the previous one
 *  - resend is limited by a 45-second cooldown (plus route throttling)
 */
final class OtpService
{
    public const TTL = 600;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN = 45;

    /** Create a new code and email it. Returns the code (callers must never display it). */
    public static function issue(string $subjectType, int $subjectId, string $purpose, string $email, string $name = ''): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        db()->execute('DELETE FROM otp_codes WHERE subject_type = :t AND subject_id = :s AND purpose = :p',
            ['t' => $subjectType, 's' => $subjectId, 'p' => $purpose]);
        db()->insert('otp_codes', [
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'purpose' => $purpose,
            'code_hash' => self::hash($code, $subjectType, $subjectId, $purpose),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL),
        ]);

        [$subject, $heading, $intro] = match ($purpose) {
            'admin_login'  => ['Your admin sign-in code: ' . $code, 'Admin sign-in code', 'Use this code to finish signing in to the admin console.'],
            default        => ['Your Jersey Musium verification code: ' . $code, 'Verify your email', 'Hi ' . e($name ?: 'there') . ', use this code to verify your email address and activate your account.'],
        };
        Mailer::sendView($email, $subject, 'notification', [
            'heading' => $heading,
            'lines'   => [
                $intro,
                '<span style="display:inline-block;font-size:28px;font-weight:800;letter-spacing:8px;padding:10px 16px;background:#f5f5f6;border-radius:8px">' . $code . '</span>',
                'The code expires in 10 minutes. Requested from IP ' . e(request()->ip()) . '.',
                'If you did not request this, you can ignore this email' . ($purpose === 'admin_login' ? ' – but change your admin password.' : '.'),
            ],
        ]);
        return $code;
    }

    /**
     * Check a code. Returns 'ok' | 'invalid' | 'expired' | 'locked'.
     * A correct code is consumed (single use).
     */
    public static function verify(string $subjectType, int $subjectId, string $purpose, string $code): string
    {
        $row = db()->first(
            'SELECT * FROM otp_codes WHERE subject_type = :t AND subject_id = :s AND purpose = :p ORDER BY id DESC LIMIT 1',
            ['t' => $subjectType, 's' => $subjectId, 'p' => $purpose]
        );
        if (!$row || strtotime($row['expires_at']) < time()) {
            return 'expired';
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return 'locked';
        }
        db()->execute('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id', ['id' => $row['id']]);
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || !hash_equals($row['code_hash'], self::hash($code, $subjectType, $subjectId, $purpose))) {
            return (int) $row['attempts'] + 1 >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }
        db()->execute('DELETE FROM otp_codes WHERE id = :id', ['id' => $row['id']]);
        return 'ok';
    }

    public static function attemptsLeft(string $subjectType, int $subjectId, string $purpose): int
    {
        $a = db()->scalar('SELECT attempts FROM otp_codes WHERE subject_type = :t AND subject_id = :s AND purpose = :p ORDER BY id DESC LIMIT 1',
            ['t' => $subjectType, 's' => $subjectId, 'p' => $purpose]);
        return max(0, self::MAX_ATTEMPTS - (int) $a);
    }

    /** Seconds until another code may be sent (0 = allowed now). */
    public static function cooldown(string $subjectType, int $subjectId, string $purpose): int
    {
        $created = db()->scalar('SELECT created_at FROM otp_codes WHERE subject_type = :t AND subject_id = :s AND purpose = :p ORDER BY id DESC LIMIT 1',
            ['t' => $subjectType, 's' => $subjectId, 'p' => $purpose]);
        return $created ? max(0, self::RESEND_COOLDOWN - (time() - strtotime((string) $created))) : 0;
    }

    public static function message(string $result): string
    {
        return match ($result) {
            'expired' => 'This code has expired. Please request a new one.',
            'locked'  => 'Too many incorrect attempts. Please request a new code.',
            default   => 'Incorrect code. Please check the email and try again.',
        };
    }

    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 2) . str_repeat('•', max(1, mb_strlen($u) - 2)) . '@' . $d;
    }

    private static function hash(string $code, string $type, int $id, string $purpose): string
    {
        return hash_hmac('sha256', "$code|$type|$id|$purpose", (string) (config('app.key') ?: 'jm-otp'));
    }
}
