-- Administration becomes its own permission module.
--
-- Until now the whole Administration section was guarded by settings.edit —
-- the same permission as Settings — so the two could not be granted apart. The
-- sidebar link was worse: it was gated on a hardcoded role list
-- (super_admin/admin/main_admin/lite_admin), which Settings → Permissions could
-- not reach at all. Ticking boxes there had no effect on it.
--
--   administration.view   see the section and its tabs
--   administration.edit   locations, device catalogue, couriers, statuses
--   administration.users  create/edit/deactivate accounts, reset someone's 2FA
--
-- users is separate because it is the one that can hand out access.
--
-- THIS GRANT IS WHAT KEEPS EXISTING ADMINS WORKING. The guards move from
-- settings.* to administration.* in the same deploy, so without it every
-- non-super-admin would lose Administration the moment the code lands.
-- Anyone who can administer today (i.e. holds settings.edit) keeps the same
-- access; nobody gains anything.

INSERT INTO role_permissions (role, module, action)
SELECT DISTINCT rp.role, 'administration', a.action
  FROM role_permissions rp
 CROSS JOIN (VALUES ('view'), ('edit'), ('users')) AS a(action)
 WHERE rp.module = 'settings' AND rp.action = 'edit'
ON CONFLICT DO NOTHING;
