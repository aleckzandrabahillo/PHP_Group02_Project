CREATE TABLE customer_delivery_addresses (
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
