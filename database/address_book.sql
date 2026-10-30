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
