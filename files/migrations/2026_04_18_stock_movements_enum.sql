-- Expand stock_movements.type enum to cover the verbs controllers
-- actually write: 'receive' (goods receipt) and 'use' (repair job).
-- Safe to re-run.

ALTER TABLE stock_movements
  MODIFY COLUMN type ENUM(
    'in','out','receive','use','adjust','reserve','release','transfer_in','transfer_out'
  ) NOT NULL;
