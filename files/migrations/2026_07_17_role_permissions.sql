-- Make the fixed-role permission matrix editable by storing role -> permission
-- grants in a table instead of PHP constants. Seeded from the previous constant
-- values so behaviour is identical until an admin changes something.
--   - Super Admin and Admin are always full-access (handled in code, not stored).
--   - Only reception / technician / partner grants live here.
-- Safe to re-run (INSERT IGNORE skips rows already present).

CREATE TABLE IF NOT EXISTS role_permissions (
  role   VARCHAR(30) NOT NULL,
  module VARCHAR(50) NOT NULL,
  action VARCHAR(30) NOT NULL,
  PRIMARY KEY (role, module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO role_permissions (role, module, action) VALUES
  -- Reception
  ('reception','rma','view'),('reception','rma','create'),('reception','rma','edit'),
  ('reception','repair','view'),
  ('reception','customers','view'),('reception','customers','create'),('reception','customers','edit'),
  ('reception','partners','view'),
  ('reception','reports','view'),
  -- Technician
  ('technician','rma','view'),('technician','rma','edit'),
  ('technician','repair','view'),('technician','repair','create'),('technician','repair','edit'),
  ('technician','parts','view'),
  ('technician','delivery','view'),('technician','delivery','edit'),
  ('technician','appointments','view'),
  ('technician','customers','view'),
  ('technician','reports','view'),
  -- Partner
  ('partner','rma','view'),('partner','rma','create'),
  ('partner','delivery','view'),
  ('partner','customers','view');
