-- Consolidate column names the application actually uses.
-- The codebase referenced `parts.internal_sku`, `parts.min_stock` and
-- `part_usage.unit_cost` in most places, while the schema had them as
-- `sku`, `reorder_level`, `unit_price`. This aligns the DB with the code.
--
-- Safe to run once. Re-running will fail silently on already-renamed
-- columns (MySQL has no RENAME COLUMN IF EXISTS); check with DESCRIBE
-- first if unsure.

-- parts.sku -> parts.internal_sku (only if old column exists)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'sku');
SET @ddl := IF(@c = 1,
    'ALTER TABLE parts RENAME COLUMN sku TO internal_sku',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- parts.reorder_level -> parts.min_stock
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'reorder_level');
SET @ddl := IF(@c = 1,
    'ALTER TABLE parts RENAME COLUMN reorder_level TO min_stock',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- part_usage.unit_price -> part_usage.unit_cost
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'part_usage'
             AND column_name = 'unit_price');
SET @ddl := IF(@c = 1,
    'ALTER TABLE part_usage RENAME COLUMN unit_price TO unit_cost',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
