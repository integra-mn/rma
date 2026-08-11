-- Sidebar reorganisation: Devices out of Administration, Suppliers into Parts.
--
-- Rajo, 2026-08-11: "Brands" in the sidebar was actually the suppliers table
-- (companies we buy parts from) while Administration → Devices had its own
-- Brands tab (device makes). Two different things under one word.
--
--   * Suppliers and Part Groups move under Parts — both only ever describe
--     parts. part_group_id is used by exactly one table, `parts`.
--   * The device catalogue becomes its own sidebar section, because new models
--     arrive constantly and entering one should not require the whole
--     Administration section.
--
-- Permission changes, in order:
--
-- 1. devices.view / devices.edit is new. Anyone who could edit the catalogue
--    before did so through administration.edit, so that is who inherits it.
--    Without this grant the catalogue becomes Super-Admin-only on deploy.
INSERT INTO role_permissions (role, module, action)
SELECT DISTINCT rp.role, 'devices', a.action
  FROM role_permissions rp
 CROSS JOIN (VALUES ('view'), ('edit')) AS a(action)
 WHERE rp.module = 'administration' AND rp.action = 'edit'
ON CONFLICT DO NOTHING;

-- 2. suppliers.* folds into parts.*. Anyone who could see or edit suppliers
--    keeps that access through the Parts permission they now need instead.
INSERT INTO role_permissions (role, module, action)
SELECT DISTINCT rp.role, 'parts', rp.action
  FROM role_permissions rp
 WHERE rp.module = 'suppliers' AND rp.action IN ('view', 'edit')
ON CONFLICT DO NOTHING;

-- 3. The suppliers module no longer exists in PERMISSION_MATRIX, so its rows
--    are dead weight — can() would never consult them again.
DELETE FROM role_permissions WHERE module = 'suppliers';
