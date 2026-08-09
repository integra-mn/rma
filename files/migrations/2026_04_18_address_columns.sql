-- Add address columns referenced by supplier / customer forms.
-- Safe to re-run.

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'suppliers' AND column_name = 'city');
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER address', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'suppliers' AND column_name = 'zip_code');
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN zip_code VARCHAR(30) DEFAULT NULL AFTER city', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'suppliers' AND column_name = 'country');
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER zip_code', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = 'zip_code');
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN zip_code VARCHAR(30) DEFAULT NULL AFTER city', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- device_categories.sku_prefix (used for part SKU generation)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'device_categories' AND column_name = 'sku_prefix');
SET @s := IF(@c = 0, 'ALTER TABLE device_categories ADD COLUMN sku_prefix VARCHAR(6) DEFAULT NULL AFTER name', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- locations.code (prefix for RMA numbering)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'locations' AND column_name = 'code');
SET @s := IF(@c = 0, 'ALTER TABLE locations ADD COLUMN code VARCHAR(10) DEFAULT NULL AFTER name, ADD INDEX idx_code (code)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- rma_requests: service box + accessories + warranty refusal fields
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'rma_requests' AND column_name = 'service_box');
SET @s := IF(@c = 0,
    'ALTER TABLE rma_requests
       ADD COLUMN service_box       VARCHAR(100) DEFAULT NULL AFTER diagnosis,
       ADD COLUMN accessories       JSON         DEFAULT NULL AFTER service_box,
       ADD COLUMN accessories_other VARCHAR(255) DEFAULT NULL AFTER accessories,
       ADD COLUMN warranty_refusal  JSON         DEFAULT NULL AFTER is_warranty',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- devices.capacity
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'devices' AND column_name = 'capacity');
SET @s := IF(@c = 0, 'ALTER TABLE devices ADD COLUMN capacity VARCHAR(30) DEFAULT NULL AFTER color', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
