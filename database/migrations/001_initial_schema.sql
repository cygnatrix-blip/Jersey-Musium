-- =====================================================================
-- Jersey Musium – initial schema (MySQL 8.0+ / MariaDB 10.6+)
-- Engine InnoDB (transactions + FKs), charset utf8mb4.
-- Money columns are DECIMAL(12,2) in INR; application maths is done in paise.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Identity: customers, admins, roles
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(15)  NULL,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','blocked') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    slug        VARCHAR(80) NOT NULL,
    permissions JSON NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    last_login_ip   VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email),
    CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addresses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    phone       VARCHAR(15)  NOT NULL,
    line1       VARCHAR(190) NOT NULL,
    line2       VARCHAR(190) NULL,
    landmark    VARCHAR(120) NULL,
    city        VARCHAR(80)  NOT NULL,
    state       VARCHAR(80)  NOT NULL,
    pincode     CHAR(6)      NOT NULL,
    type        ENUM('home','work','other') NOT NULL DEFAULT 'home',
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_addresses_user (user_id),
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catalog
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id        INT UNSIGNED NULL,
    name             VARCHAR(120) NOT NULL,
    slug             VARCHAR(140) NOT NULL,
    description      TEXT NULL,
    image            VARCHAR(255) NULL,
    meta_title       VARCHAR(190) NULL,
    meta_description VARCHAR(255) NULL,
    sort_order       INT NOT NULL DEFAULT 0,
    show_in_menu     TINYINT(1) NOT NULL DEFAULT 1,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id         INT UNSIGNED NOT NULL,
    name                VARCHAR(190) NOT NULL,
    slug                VARCHAR(210) NOT NULL,
    sku                 VARCHAR(64)  NOT NULL,
    brand               VARCHAR(80)  NULL,
    team                VARCHAR(120) NULL,
    league              VARCHAR(120) NULL,
    season              VARCHAR(20)  NULL,
    kit_type            VARCHAR(20)  NULL,
    gender              ENUM('men','women','kids','unisex') NOT NULL DEFAULT 'unisex',
    short_description   VARCHAR(500) NULL,
    description         MEDIUMTEXT NULL,
    mrp                 DECIMAL(12,2) NOT NULL,
    price               DECIMAL(12,2) NOT NULL,
    tax_rate            DECIMAL(5,2)  NOT NULL DEFAULT 5.00,   -- GST %, prices are GST-inclusive
    hsn_code            VARCHAR(12)   NULL DEFAULT '6109',
    allow_customization TINYINT(1) NOT NULL DEFAULT 0,          -- name & number printing
    customization_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    is_featured         TINYINT(1) NOT NULL DEFAULT 0,
    is_new_arrival      TINYINT(1) NOT NULL DEFAULT 0,
    meta_title          VARCHAR(190) NULL,
    meta_description    VARCHAR(255) NULL,
    avg_rating          DECIMAL(3,2) NOT NULL DEFAULT 0,
    review_count        INT UNSIGNED NOT NULL DEFAULT 0,
    sold_count          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,                           -- soft delete keeps order history intact
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_listing (is_active, deleted_at, category_id, created_at),
    KEY idx_products_team (team),
    KEY idx_products_league (league),
    KEY idx_products_price (price),
    FULLTEXT KEY ft_products_search (name, team, league, brand, short_description),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  BIGINT UNSIGNED NOT NULL,
    path        VARCHAR(255) NOT NULL,
    alt         VARCHAR(190) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    KEY idx_images_product (product_id, sort_order),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per size. Stock lives here.
CREATE TABLE product_variants (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  BIGINT UNSIGNED NOT NULL,
    size        VARCHAR(10) NOT NULL,
    sku         VARCHAR(80) NOT NULL,
    stock       INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_variant_sku (sku),
    UNIQUE KEY uq_variant_product_size (product_id, size),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT chk_variant_stock CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable stock ledger (audit of every stock change).
CREATE TABLE inventory_movements (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id  BIGINT UNSIGNED NOT NULL,
    delta       INT NOT NULL,
    reason      ENUM('order','order_cancel','payment_failed','return','manual','initial') NOT NULL,
    reference   VARCHAR(64) NULL,
    admin_id    INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inv_variant (variant_id, created_at),
    CONSTRAINT fk_inv_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_reviews (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,
    title       VARCHAR(120) NULL,
    body        TEXT NULL,
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_product (product_id, user_id),
    KEY idx_reviews_status (status, created_at),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
    user_id     BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cart (guest carts keyed by session token, merged on login)
-- ---------------------------------------------------------------------
CREATE TABLE carts (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NULL,
    session_token VARCHAR(128) NULL,
    coupon_code   VARCHAR(40) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_carts_user (user_id),
    UNIQUE KEY uq_carts_session (session_token),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id       BIGINT UNSIGNED NOT NULL,
    variant_id    BIGINT UNSIGNED NOT NULL,
    qty           INT UNSIGNED NOT NULL DEFAULT 1,
    custom_name   VARCHAR(15) NULL,
    custom_number VARCHAR(3)  NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cart_items_cart (cart_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Promotions
-- ---------------------------------------------------------------------
CREATE TABLE coupons (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(40) NOT NULL,
    description      VARCHAR(255) NULL,
    type             ENUM('percent','flat') NOT NULL,
    value            DECIMAL(12,2) NOT NULL,
    max_discount     DECIMAL(12,2) NULL,         -- cap for percent coupons
    min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    category_id      INT UNSIGNED NULL,          -- restrict to a category (incl. children)
    first_order_only TINYINT(1) NOT NULL DEFAULT 0,
    usage_limit      INT UNSIGNED NULL,          -- total redemptions
    per_user_limit   INT UNSIGNED NOT NULL DEFAULT 1,
    used_count       INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at        DATETIME NULL,
    ends_at          DATETIME NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    is_public        TINYINT(1) NOT NULL DEFAULT 1, -- shown in "available offers"
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coupons_code (code),
    CONSTRAINT fk_coupons_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(140) NOT NULL,
    subtitle      VARCHAR(255) NULL,
    cta_label     VARCHAR(40)  NULL,
    link_url      VARCHAR(255) NULL,
    image_desktop VARCHAR(255) NOT NULL,
    image_mobile  VARCHAR(255) NULL,
    placement     ENUM('home_hero','home_strip','home_grid','listing_top','announcement') NOT NULL DEFAULT 'home_hero',
    theme         ENUM('dark','light') NOT NULL DEFAULT 'dark',
    sort_order    INT NOT NULL DEFAULT 0,
    starts_at     DATETIME NULL,
    ends_at       DATETIME NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_banners_live (placement, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Orders
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number     VARCHAR(24) NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    status           ENUM('pending_payment','confirmed','packed','shipped','out_for_delivery','delivered',
                          'cancelled','return_requested','returned','payment_failed') NOT NULL DEFAULT 'pending_payment',
    payment_status   ENUM('pending','paid','failed','cod_pending','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    payment_method   ENUM('razorpay','cashfree','cod') NOT NULL,
    subtotal         DECIMAL(12,2) NOT NULL,   -- sum of line totals (GST inclusive)
    discount         DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_fee     DECIMAL(12,2) NOT NULL DEFAULT 0,
    cod_fee          DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total        DECIMAL(12,2) NOT NULL DEFAULT 0,   -- GST component included in totals
    grand_total      DECIMAL(12,2) NOT NULL,
    refunded_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    coupon_id        INT UNSIGNED NULL,
    coupon_code      VARCHAR(40) NULL,
    customer_name    VARCHAR(120) NOT NULL,
    customer_email   VARCHAR(190) NOT NULL,
    customer_phone   VARCHAR(15)  NOT NULL,
    shipping_address JSON NOT NULL,            -- immutable snapshot at time of order
    notes            VARCHAR(500) NULL,
    admin_notes      TEXT NULL,
    ip_address       VARCHAR(45) NULL,
    placed_at        DATETIME NULL,
    delivered_at     DATETIME NULL,
    cancelled_at     DATETIME NULL,
    cancel_reason    VARCHAR(255) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (status, created_at),
    KEY idx_orders_payment (payment_status),
    KEY idx_orders_created (created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NOT NULL,
    variant_id      BIGINT UNSIGNED NOT NULL,
    product_name    VARCHAR(190) NOT NULL,     -- snapshots: survive later product edits
    product_slug    VARCHAR(210) NOT NULL,
    image           VARCHAR(255) NULL,
    sku             VARCHAR(80)  NOT NULL,
    size            VARCHAR(10)  NOT NULL,
    qty             INT UNSIGNED NOT NULL,
    unit_price      DECIMAL(12,2) NOT NULL,    -- incl. customization, GST inclusive
    mrp             DECIMAL(12,2) NOT NULL,
    tax_rate        DECIMAL(5,2)  NOT NULL,
    hsn_code        VARCHAR(12)   NULL,
    custom_name     VARCHAR(15) NULL,
    custom_number   VARCHAR(3)  NULL,
    line_total      DECIMAL(12,2) NOT NULL,
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status   VARCHAR(30) NOT NULL,
    note        VARCHAR(500) NULL,
    actor_type  ENUM('system','customer','admin','gateway') NOT NULL DEFAULT 'system',
    actor_id    BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_osh_order (order_id, created_at),
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_redemptions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id   INT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    order_id    BIGINT UNSIGNED NOT NULL,
    discount    DECIMAL(12,2) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_redemption_order (order_id),
    KEY idx_redemption_user (coupon_id, user_id),
    CONSTRAINT fk_redemption_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    CONSTRAINT fk_redemption_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Payments, refunds, webhooks
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           BIGINT UNSIGNED NOT NULL,
    gateway            ENUM('razorpay','cashfree','cod') NOT NULL,
    gateway_order_id   VARCHAR(100) NULL,
    gateway_payment_id VARCHAR(100) NULL,
    payment_session_id VARCHAR(255) NULL,       -- Cashfree checkout session
    amount             DECIMAL(12,2) NOT NULL,
    currency           CHAR(3) NOT NULL DEFAULT 'INR',
    status             ENUM('created','pending','captured','failed','refunded','partially_refunded') NOT NULL DEFAULT 'created',
    method             VARCHAR(40) NULL,        -- upi / card / netbanking / wallet / cod
    error_message      VARCHAR(255) NULL,
    raw_response       JSON NULL,
    paid_at            DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_gateway_order (gateway, gateway_order_id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_gateway_payment (gateway_payment_id),
    KEY idx_payments_status (status, created_at),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE returns (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rma_number      VARCHAR(24) NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,
    order_item_id   BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            ENUM('return','exchange') NOT NULL DEFAULT 'return',
    qty             INT UNSIGNED NOT NULL DEFAULT 1,
    reason          VARCHAR(80) NOT NULL,
    details         VARCHAR(1000) NULL,
    exchange_size   VARCHAR(10) NULL,
    refund_upi      VARCHAR(80) NULL,           -- for COD orders refund destination
    status          ENUM('requested','approved','rejected','pickup_scheduled','picked_up','received','refunded','exchanged','closed') NOT NULL DEFAULT 'requested',
    admin_note      VARCHAR(1000) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_returns_rma (rma_number),
    KEY idx_returns_status (status, created_at),
    KEY idx_returns_order (order_id),
    CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_returns_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    refund_number     VARCHAR(40) NOT NULL,     -- our idempotency key sent to gateway
    order_id          BIGINT UNSIGNED NOT NULL,
    payment_id        BIGINT UNSIGNED NULL,
    return_id         BIGINT UNSIGNED NULL,
    amount            DECIMAL(12,2) NOT NULL,
    reason            VARCHAR(255) NOT NULL,
    mode              ENUM('gateway','manual') NOT NULL DEFAULT 'gateway',  -- manual = bank/UPI transfer (COD)
    status            ENUM('pending','processing','processed','failed') NOT NULL DEFAULT 'pending',
    gateway_refund_id VARCHAR(100) NULL,
    manual_reference  VARCHAR(120) NULL,         -- UTR for manual transfers
    error_message     VARCHAR(255) NULL,
    raw_response      JSON NULL,
    admin_id          INT UNSIGNED NULL,
    processed_at      DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_refunds_number (refund_number),
    KEY idx_refunds_order (order_id),
    KEY idx_refunds_gateway (gateway_refund_id),
    CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    CONSTRAINT fk_refunds_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every inbound webhook is stored first, deduplicated by event id (idempotency).
CREATE TABLE webhook_events (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway      VARCHAR(20)  NOT NULL,
    event_id     VARCHAR(120) NOT NULL,
    event_type   VARCHAR(80)  NOT NULL,
    payload      JSON NOT NULL,
    status       ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
    error        VARCHAR(500) NULL,
    processed_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webhook_event (gateway, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Delivery lifecycle
-- ---------------------------------------------------------------------
CREATE TABLE shipments (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      BIGINT UNSIGNED NOT NULL,
    courier       VARCHAR(80)  NOT NULL,
    awb_number    VARCHAR(80)  NOT NULL,
    tracking_url  VARCHAR(255) NULL,
    status        ENUM('created','picked_up','in_transit','out_for_delivery','delivered','rto','lost') NOT NULL DEFAULT 'created',
    weight_grams  INT UNSIGNED NULL,
    shipped_at    DATETIME NULL,
    delivered_at  DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_shipments_order (order_id),
    KEY idx_shipments_awb (awb_number),
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_events (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id  BIGINT UNSIGNED NOT NULL,
    status       VARCHAR(30) NOT NULL,
    location     VARCHAR(120) NULL,
    note         VARCHAR(255) NULL,
    event_at     DATETIME NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_shipment_events (shipment_id, event_at),
    CONSTRAINT fk_shipment_events FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CMS, settings, audit, infra
-- ---------------------------------------------------------------------
CREATE TABLE pages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(140) NOT NULL,
    slug             VARCHAR(140) NOT NULL,
    content          MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(255) NULL,
    show_in_footer   TINYINT(1) NOT NULL DEFAULT 1,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    `key`       VARCHAR(80) PRIMARY KEY,
    `value`     TEXT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id     INT UNSIGNED NULL,
    action       VARCHAR(80) NOT NULL,
    entity_type  VARCHAR(40) NULL,
    entity_id    BIGINT UNSIGNED NULL,
    meta         JSON NULL,
    ip_address   VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    `key`     CHAR(64) PRIMARY KEY,
    hits      INT UNSIGNED NOT NULL DEFAULT 0,
    reset_at  INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
