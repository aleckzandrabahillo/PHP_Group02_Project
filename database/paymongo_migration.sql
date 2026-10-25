-- AVELA: PayMongo online payment columns for the orders table.
--
-- Same definitions that scripts/migrate_paymongo.php checks for, so its dry run reports nothing
-- left to do once this has been applied. Back up the database first (mysqldump), and run on the
-- right database:   mysql -u root -p avela < database/paymongo_migration.sql
--
-- Existing orders keep payment_method='cod' and get payment_status='not_required'.

ALTER TABLE orders
  MODIFY COLUMN payment_method ENUM('cod','manual','paymongo') NOT NULL DEFAULT 'cod',
  ADD COLUMN payment_status ENUM('not_required','pending','paid','failed','expired') NOT NULL DEFAULT 'not_required' AFTER payment_method,
  ADD COLUMN paymongo_checkout_session_id VARCHAR(100) NULL AFTER payment_status,
  ADD COLUMN paymongo_payment_intent_id VARCHAR(100) NULL AFTER paymongo_checkout_session_id,
  ADD COLUMN paid_at DATETIME NULL AFTER paymongo_payment_intent_id,
  ADD UNIQUE INDEX uq_orders_paymongo_session (paymongo_checkout_session_id),
  ADD UNIQUE INDEX uq_orders_paymongo_intent (paymongo_payment_intent_id);
