-- Inventory counts get a document/reference number (like RMAs have an RMA number).
-- Format: POP-YYYY-NNNN (sequence resets per year), generated on start.
ALTER TABLE inventory_counts
  ADD COLUMN reference VARCHAR(30) DEFAULT NULL AFTER id;

-- Backfill existing counts with a reference derived from their start year + id,
-- so historical documents also carry a number.
UPDATE inventory_counts
   SET reference = CONCAT('POP-', YEAR(started_at), '-', LPAD(id, 4, '0'))
 WHERE reference IS NULL;
