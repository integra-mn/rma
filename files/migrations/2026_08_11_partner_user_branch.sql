-- Which poslovnica a partner-side employee sits in.
--
-- Lives on partner_users (the row that already links a person to their partner)
-- rather than on users.location_id. Two reasons:
--
--   1. users.location_id means "which INTEGRA service point" — it drives parts
--      stock, invoicing, technician rotas and RMA visibility. A partner branch
--      has none of that, and letting the two share a column is how they end up
--      confused with each other again.
--   2. The branch only makes sense in the context of a partner, and that
--      context is exactly what this row already carries.
--
-- Nullable: a partner may have no branches recorded, and head-office staff may
-- not belong to any single one.

ALTER TABLE partner_users
  ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_pu_branch ON partner_users (branch_id);
