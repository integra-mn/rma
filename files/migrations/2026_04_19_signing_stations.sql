-- Signing stations: dedicated tablets parked at a shop counter that
-- auto-navigate to the signing page whenever a signature is requested
-- for an RMA at their location. Each station has a secret key used in
-- its public URL (treat the key as confidential — anyone with it can
-- see and claim signatures for that location).
--
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS signing_stations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  station_key VARCHAR(64) NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  last_poll_at DATETIME DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_location (location_id),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
