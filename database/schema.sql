CREATE DATABASE IF NOT EXISTS `avela` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `avela`;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(40) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  password_changed_at DATETIME NULL DEFAULT NULL,
  password_version CHAR(64) NOT NULL DEFAULT '',
  role ENUM('customer','catalog_manager','admin') NOT NULL DEFAULT 'customer',
  status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  lock_requires_admin TINYINT UNSIGNED NOT NULL DEFAULT 0,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  activated_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_users_role_status (role, status),
  INDEX idx_users_locked_until (locked_until)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  contact_no VARCHAR(25) NOT NULL,
  delivery_address VARCHAR(500) NULL,
  profile_image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_customer_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  contact_no VARCHAR(25) NULL DEFAULT NULL,
  profile_image VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_staff_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(500) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tag_type ENUM('concern','texture','scalp','condition') NOT NULL,
  code VARCHAR(50) NOT NULL,
  display_name VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_tags_type_code (tag_type, code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
  image_path VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  routine_step ENUM('cleanse','condition','treat','finish') NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_products_category_status (category_id, status),
  INDEX idx_products_name (name),
  CONSTRAINT chk_products_price_nonnegative CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_tags (
  product_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, tag_id),
  CONSTRAINT fk_product_tags_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorites (
  user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_favorites_product (product_id)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS product_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text VARCHAR(2000) NULL,
  status ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  UNIQUE KEY uq_product_review_user (product_id, user_id),
  INDEX idx_product_reviews_product_status (product_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hair_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  hair_texture VARCHAR(30) NULL,
  scalp_type VARCHAR(30) NULL,
  hair_condition VARCHAR(60) NULL,
  treatment_history VARCHAR(500) NULL,
  concern_text VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hair_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_hair_profiles_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hair_profile_tags (
  hair_profile_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  source ENUM('assessment','nlp') NOT NULL,
  PRIMARY KEY (hair_profile_id, tag_id, source),
  CONSTRAINT fk_hair_profile_tags_profile FOREIGN KEY (hair_profile_id) REFERENCES hair_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_hair_profile_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cart_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_id, product_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cart_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  delivery_address_snapshot VARCHAR(500) NOT NULL,
  recipient_name_snapshot VARCHAR(120) NULL,
  contact_email_snapshot VARCHAR(190) NULL,
  contact_no_snapshot VARCHAR(25) NULL,
  public_reference VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  checkout_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  payment_method ENUM('cod','manual') NOT NULL DEFAULT 'cod',
  subtotal DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_orders_checkout_key (checkout_key),
  UNIQUE KEY uq_orders_public_reference (public_reference),
  INDEX idx_orders_user_status_date (user_id, status, created_at),
  INDEX idx_orders_status_date (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name_snapshot VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price_snapshot DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT chk_order_item_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  from_status ENUM('pending','processing','shipped','delivered','cancelled') NULL,
  to_status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_events_order (order_id,id),
  CONSTRAINT fk_order_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_events_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_email_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  UNIQUE KEY uq_order_email_order (order_id),
  INDEX idx_order_email_status (status,id),
  CONSTRAINT fk_order_email_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS routines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  hair_profile_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_routine_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_routine_profile FOREIGN KEY (hair_profile_id) REFERENCES hair_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS routine_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  routine_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  step_order TINYINT UNSIGNED NOT NULL,
  routine_step ENUM('cleanse','condition','treat','finish') NOT NULL,
  CONSTRAINT fk_routine_item_routine FOREIGN KEY (routine_id) REFERENCES routines(id) ON DELETE CASCADE,
  CONSTRAINT fk_routine_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_routine_step_order (routine_id, step_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS routine_contexts (
  routine_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  profile_version CHAR(64) NOT NULL,
  plan_fingerprint CHAR(64) NOT NULL,
  rules_version VARCHAR(20) NOT NULL,
  explanation_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_routine_context FOREIGN KEY (routine_id) REFERENCES routines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  purpose ENUM('activation','login', 'profile_update') NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_lookup (user_id, purpose, used_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event VARCHAR(60) NOT NULL,
  result ENUM('success','failure') NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_auth_logs_date (created_at),
  INDEX idx_auth_logs_user_event (user_id, event)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(60) NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  details VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_logs_date (created_at),
  INDEX idx_audit_logs_actor_action (actor_id, action)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_security_setting_actor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO security_settings (setting_key, setting_value) VALUES
('password_min_length','12'),
('max_login_attempts','3'),
('lockout_minutes','15'),
('session_timeout_minutes','30'),
('staff_mfa_required','1'),
('captcha_enabled','1');

INSERT IGNORE INTO tags (tag_type, code, display_name) VALUES
('texture','straight','Straight'),('texture','wavy','Wavy'),('texture','curly','Curly'),('texture','coily','Coily'),
('scalp','normal_scalp','Normal'),('scalp','oily_scalp','Oily'),('scalp','dry_scalp','Dry'),
('concern','frizz','Frizz'),('concern','dryness','Dryness'),('concern','oiliness','Oiliness'),('concern','damage','Damage'),('concern','tangling','Tangling');


INSERT IGNORE INTO categories (name, description, status) VALUES
('Shampoo','Cleansing products for regular wash days.','active'),
('Conditioner','Conditioning products for softness and manageability.','active'),
('Treatment','Masks and targeted care products.','active'),
('Serum','Leave-in serums and finishing care.','active'),
('Hair Oil','Hair oils for added softness and shine.','active'),
('Hair Tools','Everyday hair-care tools and accessories.','active');

INSERT IGNORE INTO products (category_id, sku, name, description, price, stock_qty, image_path, status, routine_step) VALUES
((SELECT id FROM categories WHERE name='Shampoo' LIMIT 1),'AVE-SMP-001','Gentle Hydrating Shampoo','A mild daily shampoo for cleansing without leaving the hair feeling stripped.',329.00,24,NULL,'active','cleanse'),
((SELECT id FROM categories WHERE name='Conditioner' LIMIT 1),'AVE-CON-001','Repair Conditioner','A smoothing conditioner made for dry, frizzy, or easily tangled hair.',349.00,18,NULL,'active','condition'),
((SELECT id FROM categories WHERE name='Treatment' LIMIT 1),'AVE-TRT-001','Intensive Hair Mask','A weekly treatment for hair that feels dry, rough, or overworked.',429.00,12,NULL,'active','treat'),
((SELECT id FROM categories WHERE name='Serum' LIMIT 1),'AVE-SER-001','Smoothing Serum','A lightweight finishing serum that helps keep frizz manageable.',299.00,20,NULL,'active','finish'),
((SELECT id FROM categories WHERE name='Hair Oil' LIMIT 1),'AVE-OIL-001','Nourishing Hair Oil','A light hair oil for adding softness and shine to the lengths and ends.',319.00,16,NULL,'active','finish'),
((SELECT id FROM categories WHERE name='Hair Tools' LIMIT 1),'AVE-TOL-001','Wide-Tooth Comb','A simple detangling comb suited for use on damp or dry hair.',149.00,30,NULL,'active',NULL);


-- Initial suitability tags used by the public shop filters.
-- These records are only for local testing and can be replaced as the catalog grows.
INSERT IGNORE INTO product_tags (product_id, tag_id)
SELECT p.id, t.id FROM products p JOIN tags t ON
    (p.sku='AVE-SMP-001' AND ((t.tag_type='concern' AND t.code IN ('dryness')) OR (t.tag_type='texture' AND t.code IN ('straight','wavy','curly','coily')) OR (t.tag_type='scalp' AND t.code IN ('normal_scalp','dry_scalp')))) OR
    (p.sku='AVE-CON-001' AND ((t.tag_type='concern' AND t.code IN ('dryness','frizz','damage','tangling')) OR (t.tag_type='texture' AND t.code IN ('straight','wavy','curly','coily')) OR (t.tag_type='scalp' AND t.code IN ('normal_scalp','dry_scalp')))) OR
    (p.sku='AVE-TRT-001' AND ((t.tag_type='concern' AND t.code IN ('dryness','frizz','damage')) OR (t.tag_type='texture' AND t.code IN ('wavy','curly','coily')) OR (t.tag_type='scalp' AND t.code IN ('normal_scalp','dry_scalp')))) OR
    (p.sku='AVE-SER-001' AND ((t.tag_type='concern' AND t.code IN ('frizz','tangling')) OR (t.tag_type='texture' AND t.code IN ('straight','wavy','curly','coily')) OR (t.tag_type='scalp' AND t.code IN ('normal_scalp')))) OR
    (p.sku='AVE-OIL-001' AND ((t.tag_type='concern' AND t.code IN ('dryness','frizz','damage')) OR (t.tag_type='texture' AND t.code IN ('wavy','curly','coily')) OR (t.tag_type='scalp' AND t.code IN ('normal_scalp','dry_scalp')))) OR
    (p.sku='AVE-TOL-001' AND ((t.tag_type='concern' AND t.code IN ('tangling')) OR (t.tag_type='texture' AND t.code IN ('wavy','curly','coily'))));

CREATE TABLE IF NOT EXISTS locked_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  username_snapshot VARCHAR(40) NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL,
  locked_at DATETIME NOT NULL,
  locked_until DATETIME NOT NULL,
  unlocked_at DATETIME NULL,
  unlocked_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_locked_account_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_locked_account_admin FOREIGN KEY (unlocked_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_locked_accounts_user_date (user_id, locked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_authenticators (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  secret_encrypted VARCHAR(512) NULL,
  recovery_hashes_json TEXT NOT NULL,
  last_used_step BIGINT NOT NULL DEFAULT -1,
  enabled_at DATETIME NULL,
  credential_version CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  CONSTRAINT fk_authenticator_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_rate_limits (
  bucket_hash CHAR(64) NOT NULL PRIMARY KEY,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  INDEX idx_security_rate_reset (reset_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activation_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_digest CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activation_user(user_id),
    CONSTRAINT fk_activation_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS order_delivery_details (         order_id BIGINT UNSIGNED PRIMARY KEY,         country ENUM('PH') NOT NULL,         first_name VARCHAR(60) NOT NULL,         last_name VARCHAR(60) NOT NULL,         address_line VARCHAR(200) NOT NULL,         barangay VARCHAR(100) NOT NULL,         postal_code CHAR(4) NOT NULL,         city VARCHAR(100) NOT NULL,         region VARCHAR(16) NOT NULL,         shipping_method ENUM('standard','priority') NOT NULL,         shipping_fee DECIMAL(10,2) NOT NULL,         CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_delivery_addresses (
  user_id BIGINT UNSIGNED NOT NULL,
  country CHAR(2) NOT NULL,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  address_line VARCHAR(200) NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  postal_code CHAR(4) NOT NULL,
  city VARCHAR(100) NOT NULL,
  region VARCHAR(16) NOT NULL,
  contact_no VARCHAR(25) NOT NULL,
  revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_delivery_address_owner FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encrypted account-owned delivery address book.
CREATE TABLE IF NOT EXISTS customer_address_book (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 payload_ciphertext TEXT NOT NULL,
 revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 is_default TINYINT UNSIGNED NULL DEFAULT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 deleted_at DATETIME NULL DEFAULT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_address_book_default (user_id,is_default),
 KEY ix_address_book_owner (user_id,deleted_at),
 CONSTRAINT fk_address_book_owner FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT ck_address_book_default CHECK (is_default IS NULL OR is_default=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
