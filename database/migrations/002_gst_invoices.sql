-- =====================================================================
-- GST-compliant invoicing (CGST Rules 2017, Rule 46):
-- consecutive invoice serial number unique per financial year (max 16 chars).
-- =====================================================================

ALTER TABLE orders
    ADD COLUMN invoice_number VARCHAR(16) NULL AFTER order_number,
    ADD COLUMN invoice_date   DATETIME NULL AFTER invoice_number,
    ADD UNIQUE KEY uq_orders_invoice (invoice_number);

-- One counter row per financial year, e.g. fy = '26-27'. Incremented under a row lock.
CREATE TABLE invoice_sequences (
    fy       CHAR(5) PRIMARY KEY,
    last_no  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin email OTP (second factor) for the admin console
CREATE TABLE admin_otps (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    code_hash   CHAR(64) NOT NULL,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_otps (admin_id),
    CONSTRAINT fk_admin_otps_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer accounts can be anonymised on request (DPDP Act 2023 – right to erasure)
ALTER TABLE users
    MODIFY COLUMN status ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
    ADD COLUMN admin_note VARCHAR(500) NULL AFTER status;
