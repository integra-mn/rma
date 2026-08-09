-- Rename the role taxonomy to a cleaner scheme:
--   main_admin + admin_type='main'  ->  super_admin  (unique per install)
--   main_admin + admin_type='lite'  ->  admin
--   lite_admin                      ->  admin
--   reception / technician / partner remain the same names
--   customer: dropped (no UI path; any existing row -> partner)
--
-- Drops the users.admin_type column — distinction is now encoded in role.
-- Safe to re-run after initial execution (later runs become no-ops).

ALTER TABLE users MODIFY COLUMN role ENUM(
  'main_admin','lite_admin','customer',
  'super_admin','admin','reception','technician','partner'
) NOT NULL;

UPDATE users
   SET role = 'super_admin'
 WHERE role = 'main_admin' AND admin_type = 'main';

UPDATE users
   SET role = 'admin'
 WHERE role IN ('main_admin', 'lite_admin');

UPDATE users SET role = 'partner' WHERE role = 'customer';

SET @has_col := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'users'
                   AND column_name = 'admin_type');
SET @ddl := IF(@has_col = 1, 'ALTER TABLE users DROP COLUMN admin_type', 'DO 0');
PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;

ALTER TABLE users MODIFY COLUMN role ENUM(
  'super_admin','admin','reception','technician','partner'
) NOT NULL;

ALTER TABLE security_policies MODIFY COLUMN role ENUM(
  'main_admin','lite_admin','reception','technician','partner','customer',
  'super_admin','admin'
) NOT NULL;
UPDATE security_policies SET role = 'super_admin' WHERE role = 'main_admin';
UPDATE security_policies SET role = 'admin'       WHERE role = 'lite_admin';
DELETE FROM security_policies WHERE role = 'customer';
ALTER TABLE security_policies MODIFY COLUMN role ENUM(
  'super_admin','admin','reception','technician','partner'
) NOT NULL;
