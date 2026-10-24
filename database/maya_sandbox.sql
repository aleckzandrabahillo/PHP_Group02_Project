CREATE TABLE IF NOT EXISTS maya_payments (
  order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  merchant_reference CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  checkout_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  redirect_url VARCHAR(2048) NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  state ENUM('initiating','pending','paid','failed','cancelled','expired','review') NOT NULL DEFAULT 'initiating',
  stock_state ENUM('reserved','committed','released') NOT NULL DEFAULT 'reserved',
  initiation_started_at DATETIME NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_maya_reference (merchant_reference),
  UNIQUE KEY uq_maya_checkout (checkout_id),
  INDEX idx_maya_pending (state,order_id),
  CONSTRAINT fk_maya_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maya_webhook_inbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event_status VARCHAR(32) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  lease_until DATETIME NULL,
  processed_at DATETIME NULL,
  last_error VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_maya_event (payment_id,event_status),
  INDEX idx_maya_inbox_pending (processed_at,lease_until,id),
  CONSTRAINT fk_maya_inbox_payment FOREIGN KEY (order_id) REFERENCES maya_payments(order_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
