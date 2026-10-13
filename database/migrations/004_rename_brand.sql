-- =====================================================================
-- Brand spelling fix: "Jersey Musium" → "Jersey Museum" in stored content
-- (store settings, product brand/texts, categories, banners, coupons, pages).
-- Safe to run more than once; on a fresh install it changes nothing.
-- Customer/admin e-mail addresses (login IDs) are deliberately left untouched.
-- =====================================================================

UPDATE settings SET `value` = REPLACE(REPLACE(REPLACE(`value`, 'Musium', 'Museum'), 'MUSIUM', 'MUSEUM'), 'jerseymusium.com', 'jerseymuseum.com')
 WHERE `value` LIKE '%usium%' OR `value` LIKE '%USIUM%';

UPDATE products SET
    name              = REPLACE(name, 'Musium', 'Museum'),
    brand             = REPLACE(brand, 'Musium', 'Museum'),
    team              = REPLACE(team, 'Musium', 'Museum'),
    short_description = REPLACE(short_description, 'Musium', 'Museum'),
    description       = REPLACE(description, 'Musium', 'Museum'),
    meta_title        = REPLACE(meta_title, 'Musium', 'Museum'),
    meta_description  = REPLACE(meta_description, 'Musium', 'Museum');

UPDATE categories SET
    name             = REPLACE(name, 'Musium', 'Museum'),
    description      = REPLACE(description, 'Musium', 'Museum'),
    meta_title       = REPLACE(meta_title, 'Musium', 'Museum'),
    meta_description = REPLACE(meta_description, 'Musium', 'Museum');

UPDATE banners SET
    title    = REPLACE(title, 'Musium', 'Museum'),
    subtitle = REPLACE(subtitle, 'Musium', 'Museum');

UPDATE coupons SET description = REPLACE(description, 'Musium', 'Museum');

UPDATE pages SET
    title            = REPLACE(title, 'Musium', 'Museum'),
    content          = REPLACE(REPLACE(content, 'Musium', 'Museum'), 'jerseymusium.com', 'jerseymuseum.com'),
    meta_description = REPLACE(meta_description, 'Musium', 'Museum');
