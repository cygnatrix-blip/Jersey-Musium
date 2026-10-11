-- =====================================================================
-- One-time codes (email OTP) for customers and admins, in one table.
--   subject_type: 'user' | 'admin'
--   purpose:      'verify_email' (customer sign-up) | 'admin_login' (admin 2-step)
-- Codes are stored only as an HMAC, expire in 10 minutes, max 5 attempts.
-- =====================================================================

CREATE TABLE otp_codes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_type  ENUM('user','admin') NOT NULL,
    subject_id    BIGINT UNSIGNED NOT NULL,
    purpose       VARCHAR(30) NOT NULL,
    code_hash     CHAR(64) NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at    DATETIME NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_subject (subject_type, subject_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE admin_otps;

-- Accounts created before email verification existed are treated as verified.
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL AND status <> 'deleted';
