-- Administration gets the four standard verbs.
--
-- It had view / edit / users, where `users` covered creating accounts and
-- resetting 2FA. Rajo, 2026-08-11: that read as a contradiction — view and
-- edit already sound like the whole section — and it would have needed its own
-- row on the Permissions screen, which is ordered to match the sidebar and has
-- no Users entry there.
--
-- view / create / edit / delete matches every other module and is genuinely
-- finer-grained: someone can now correct a location's address without also
-- being able to delete it.
--
--   create  new location, courier, status or user account
--   edit    update or activate/deactivate any of them; reset someone's 2FA
--   delete  remove a location, courier or user
--
-- Anyone who could change things before (administration.edit, with or without
-- administration.users) keeps being able to, so nobody is locked out by the
-- deploy. Note this does widen `users`: creating an account and creating a
-- courier are one permission now. Only `admin` holds administration.edit
-- today, so nothing changes in practice — it matters for who you grant it to
-- later.

INSERT INTO role_permissions (role, module, action)
SELECT DISTINCT rp.role, 'administration', a.action
  FROM role_permissions rp
 CROSS JOIN (VALUES ('create'), ('delete')) AS a(action)
 WHERE rp.module = 'administration' AND rp.action = 'edit'
ON CONFLICT DO NOTHING;

-- `users` is no longer an action in PERMISSION_MATRIX, so can() would never
-- consult these rows again.
DELETE FROM role_permissions
 WHERE module = 'administration' AND action = 'users';
