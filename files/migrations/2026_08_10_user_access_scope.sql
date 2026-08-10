-- Per-user network restriction.
--
--   'any' — sign in from anywhere (default, existing behaviour)
--   'lan' — local network only; enforced at login AND on every request, so a
--           session cannot be carried off-site on a laptop or phone
--
-- Postgres: ALTER TABLE ... ADD COLUMN IF NOT EXISTS is supported.
-- MySQL:    drop the IF NOT EXISTS if running against an older server.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS access_scope VARCHAR(10) NOT NULL DEFAULT 'any';

-- Existing accounts keep unrestricted access.
UPDATE users SET access_scope = 'any' WHERE access_scope IS NULL OR access_scope = '';
