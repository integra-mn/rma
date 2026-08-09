-- Make the Admin role editable too. Previously Admin was full-access in code
-- (like Super Admin); now its permissions live in role_permissions so an admin
-- can tailor them. Seed Admin with the full matrix so behaviour is unchanged
-- until someone edits it. Super Admin stays full-access in code (never stored).
-- Admin always keeps settings.view/settings.edit (enforced in code) so admins
-- can never lock themselves out of the permission editor.
-- Safe to re-run (INSERT IGNORE).

INSERT IGNORE INTO role_permissions (role, module, action) VALUES
  ('admin','rma','view'),('admin','rma','create'),('admin','rma','edit'),
  ('admin','repair','view'),('admin','repair','create'),('admin','repair','edit'),
  ('admin','parts','view'),('admin','parts','create'),('admin','parts','edit'),('admin','parts','delete'),
  ('admin','customers','view'),('admin','customers','create'),('admin','customers','edit'),
  ('admin','partners','view'),('admin','partners','edit'),
  ('admin','reports','view'),
  ('admin','invoicing','view'),
  ('admin','settings','view'),('admin','settings','edit');
