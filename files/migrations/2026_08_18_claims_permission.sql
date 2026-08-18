-- The claims queue gets a permission of its own.
--
-- Following claims is one person's job in the office — the counter takes
-- devices in and the bench repairs them — and the queue shows every insured
-- customer's case at once, so it is granted deliberately rather than riding on
-- rma.view.
--
-- Admin and reception get it: reception is the back-office side of the counter,
-- and whoever chases claims will almost certainly be one of those two. Take it
-- away in Podesavanja -> Dozvole if the handler is somebody else; Super Admin
-- bypasses the matrix and is never stored.
--
-- Delete-then-insert so it can be run twice.

DELETE FROM role_permissions WHERE module = 'claims';

INSERT INTO role_permissions (role, module, action) VALUES
  ('admin',     'claims', 'view'),
  ('admin',     'claims', 'edit'),
  ('reception', 'claims', 'view'),
  ('reception', 'claims', 'edit');
