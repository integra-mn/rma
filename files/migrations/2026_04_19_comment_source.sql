-- Add `source` to rma_comments — tracks *who said it* (staff vs customer),
-- independently of the existing `visibility` flag (who can see it).
-- Safe to re-run.

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name = 'rma_comments'
             AND column_name = 'source');
SET @s := IF(@c = 0,
    'ALTER TABLE rma_comments
       ADD COLUMN source ENUM(''staff'',''customer'') NOT NULL DEFAULT ''staff'' AFTER visibility',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
