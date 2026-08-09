-- Per-user vendor credentials.
-- Shop-wide defaults live in vendor_adapters.credentials. Individual users
-- (typically technicians) can override with their own creds — used when a
-- vendor like Apple GSX requires the submitting technician to be identified
-- by their own bearer token / Apple ID. Lookups (warranty / coverage) keep
-- using the shop default.
--
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS user_vendor_credentials (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  vendor_id   INT UNSIGNED NOT NULL,
  credentials TEXT NOT NULL,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_vendor (user_id, vendor_id),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
