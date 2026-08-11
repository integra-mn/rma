-- Settings belongs to Super Admin alone.
--
-- Admin previously held settings.view + settings.edit, and those two were
-- pinned in ROLE_LOCKED_PERMISSIONS so the boxes could not even be unticked on
-- the Permissions screen. Both are removed: the lock list is now empty and the
-- grants are deleted here.
--
-- Admin keeps administration.* — Users, Locations, Devices, Shipments and
-- Statuses are still theirs. Only Settings (general, appearance,
-- communications, integrations, and the permission editor itself) moves.
--
-- Paired with a code change: permissions_save() now requires Super Admin.
-- Without that, an Admin could still POST to /admin/permissions/save and grant
-- the permissions straight back, making this deletion cosmetic.
--
-- Super Admin is unaffected — it bypasses the matrix entirely and is never
-- stored in role_permissions, so there is always one account that can restore
-- access. The app already refuses to demote or delete the last Super Admin.

DELETE FROM role_permissions
 WHERE role = 'admin' AND module = 'settings';
