-- SQL 02: Add indexes and foreign keys for better performance and integrity

-- Indexes on customer_id for faster lookups
CREATE INDEX IF NOT EXISTS idx_roro_pet_customer_id ON RORO_PET(customer_id);
CREATE INDEX IF NOT EXISTS idx_roro_address_customer_id ON RORO_ADDRESS(customer_id);
CREATE INDEX IF NOT EXISTS idx_roro_user_link_wp_customer_id ON RORO_USER_LINK_WP(customer_id);

-- Foreign key: pets belong to customers, cascade delete on customer removal
ALTER TABLE RORO_PET
ADD CONSTRAINT fk_roro_pet_customer FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(id) ON DELETE CASCADE;

-- Foreign key: address belongs to customers, cascade delete on customer removal
ALTER TABLE RORO_ADDRESS
ADD CONSTRAINT fk_roro_address_customer FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(id) ON DELETE CASCADE;

-- Foreign key: link table references WP user and customer (no cascade here because deletion is handled in code)
-- NOTE: WordPress core tables typically use MyISAM or InnoDB; foreign keys may not be enforced depending on DB engine.
