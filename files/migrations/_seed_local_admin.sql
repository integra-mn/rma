-- Local-development seed data.
-- Creates one location + one super_admin user, and disables 2FA on the
-- super_admin policy so login works without configuring SMTP.
--
-- Run once after importing schema.sql:
--   mysql -urma -prma_dev integra_rma < _seed_local_admin.sql
--
-- Login: admin@integra.local / admin

INSERT IGNORE INTO locations (id, name, is_active) VALUES
  (1, 'HQ', 1);

INSERT IGNORE INTO users (id, location_id, name, email, phone, password_hash, role, lang, is_active)
VALUES (
  1,
  1,
  'Local Admin',
  'admin@integra.local',
  '+38267000000',
  '$2y$12$V5xePtj5SM1i6Wz3bw/pc.YykIneaAi8xSwTRcsFzsAkLls.E2C0G',
  'super_admin',
  'en',
  1
);

-- Disable 2FA for super_admin role in dev so we don't need SMTP.
UPDATE security_policies SET require_2fa = 0, force_2fa_new_device = 0
WHERE role = 'super_admin';
