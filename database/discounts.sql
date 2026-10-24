CREATE TABLE IF NOT EXISTS discount_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  kind ENUM('percentage','fixed') NOT NULL,
  value_units BIGINT UNSIGNED NOT NULL,
  minimum_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'inactive',
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  customer_limit INT UNSIGNED NULL,
  revision CHAR(32) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_discount_code (code),
  INDEX idx_discount_status (status,expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_discounts (
  order_id BIGINT UNSIGNED PRIMARY KEY,
  discount_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  code_snapshot VARCHAR(32) NOT NULL,
  kind_snapshot ENUM('percentage','fixed') NOT NULL,
  value_units_snapshot BIGINT UNSIGNED NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  state ENUM('reserved','committed','released') NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_discount_usage (discount_id,state,user_id),
  CONSTRAINT fk_order_discount_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_discount_code FOREIGN KEY (discount_id) REFERENCES discount_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_discount_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
