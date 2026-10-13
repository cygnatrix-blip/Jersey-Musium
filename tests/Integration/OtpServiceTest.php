<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\OtpService;
use Tests\TestCase;

final class OtpServiceTest extends TestCase
{
    public function testCodeIsEmailedSixDigitsAndStoredOnlyAsHash(): void
    {
        $uid = $this->makeUser();
        $code = OtpService::issue('user', $uid, 'verify_email', 'otp-test@example.com', 'Tester');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $row = db()->first("SELECT * FROM otp_codes WHERE subject_type = 'user' AND subject_id = :id", ['id' => $uid]);
        $this->assertSame(64, strlen($row['code_hash']));
        $this->assertStringNotContainsString($code, $row['code_hash'], 'plain code never stored');

        $log = (string) file_get_contents(BASE_PATH . '/storage/logs/mail-' . date('Y-m-d') . '.log');
        $this->assertStringContainsString('To: otp-test@example.com | Your Jersey Museum verification code: ' . $code, $log);
    }

    public function testCorrectCodeVerifiesOnceOnly(): void
    {
        $uid = $this->makeUser();
        $code = OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        $this->assertSame('ok', OtpService::verify('user', $uid, 'verify_email', $code));
        $this->assertSame('expired', OtpService::verify('user', $uid, 'verify_email', $code), 'single use');
    }

    public function testWrongCodesLockAfterFiveAttempts(): void
    {
        $uid = $this->makeUser();
        $code = OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        for ($i = 1; $i <= 4; $i++) {
            $this->assertSame('invalid', OtpService::verify('user', $uid, 'verify_email', $wrong));
            $this->assertSame(5 - $i, OtpService::attemptsLeft('user', $uid, 'verify_email'));
        }
        $this->assertSame('locked', OtpService::verify('user', $uid, 'verify_email', $wrong));
        $this->assertSame('locked', OtpService::verify('user', $uid, 'verify_email', $code), 'even the right code is refused once locked');
    }

    public function testExpiredCodeIsRejected(): void
    {
        $uid = $this->makeUser();
        $code = OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        db()->execute("UPDATE otp_codes SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE subject_id = :id AND subject_type = 'user'", ['id' => $uid]);
        $this->assertSame('expired', OtpService::verify('user', $uid, 'verify_email', $code));
    }

    public function testNewCodeReplacesOldAndCodesAreScopedBySubjectAndPurpose(): void
    {
        $uid = $this->makeUser();
        $old = OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        $new = OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        if ($old !== $new) {
            $this->assertSame('invalid', OtpService::verify('user', $uid, 'verify_email', $old), 'previous code invalidated');
        }
        $other = $this->makeUser();
        $this->assertSame('expired', OtpService::verify('user', $other, 'verify_email', $new), 'code of another user is useless');
        $this->assertSame('expired', OtpService::verify('admin', $uid, 'admin_login', $new), 'code for another purpose is useless');
        $this->assertSame('ok', OtpService::verify('user', $uid, 'verify_email', $new));
    }

    public function testResendCooldownAndMasking(): void
    {
        $uid = $this->makeUser();
        OtpService::issue('user', $uid, 'verify_email', 'a@example.com');
        $this->assertGreaterThan(40, OtpService::cooldown('user', $uid, 'verify_email'));
        db()->execute("UPDATE otp_codes SET created_at = NOW() - INTERVAL 2 MINUTE WHERE subject_id = :id", ['id' => $uid]);
        $this->assertSame(0, OtpService::cooldown('user', $uid, 'verify_email'));
        $this->assertSame('sm•••••@example.com', OtpService::mask('smokeyy@example.com'));
    }
}
