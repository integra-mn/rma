-- Add parts.cost_price — the weighted-average purchase cost that the
-- goods-receipt confirm step reads and updates. The column was referenced
-- in code (goodsReceiptController::confirm) but never existed in the schema,
-- so confirming a receipt fatally errored. This adds it.
-- Safe to re-run.

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'cost_price');
SET @s := IF(@c = 0,
    'ALTER TABLE parts
       ADD COLUMN cost_price DECIMAL(10,4) DEFAULT 0.0000 AFTER unit_price',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
