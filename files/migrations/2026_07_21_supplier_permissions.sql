-- Suppliers (Dobavljači) becomes its own permission module in the matrix.
-- Previously the Suppliers page reused the 'partners' permission; mirror each
-- role's existing partners grants onto suppliers so access is unchanged until
-- an admin adjusts it in Settings → Permissions. Safe to re-run (INSERT IGNORE
-- + PK on role/module/action).
INSERT IGNORE INTO role_permissions (role, module, action)
SELECT role, 'suppliers', action
  FROM role_permissions
 WHERE module = 'partners' AND action IN ('view', 'edit');
