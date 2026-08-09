-- Tag parts by device brand + category so the repair page can filter to
-- only the relevant parts for the device being worked on. Both nullable:
--   brand_id   = NULL  -> part works for any brand (universal / consumable)
--   category_id = NULL -> part works for any device category (tools, adhesive)
-- Safe to re-run.

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'brand_id');
SET @s := IF(@c = 0,
    'ALTER TABLE parts
       ADD COLUMN brand_id INT UNSIGNED DEFAULT NULL AFTER supplier_id,
       ADD INDEX idx_parts_brand (brand_id),
       ADD CONSTRAINT fk_parts_brand
         FOREIGN KEY (brand_id) REFERENCES device_brands(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'category_id');
SET @s := IF(@c = 0,
    'ALTER TABLE parts
       ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER brand_id,
       ADD INDEX idx_parts_category (category_id),
       ADD CONSTRAINT fk_parts_category
         FOREIGN KEY (category_id) REFERENCES device_categories(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
