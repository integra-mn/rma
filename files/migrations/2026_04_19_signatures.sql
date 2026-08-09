-- Customer signatures captured via mobile device (QR flow).
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS rma_signatures (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id      INT UNSIGNED NOT NULL,
  token       VARCHAR(64) NOT NULL UNIQUE,  -- random hex for mobile URL
  filename    VARCHAR(500) DEFAULT NULL,    -- set once signed
  expires_at  DATETIME NOT NULL,
  visited_at  DATETIME DEFAULT NULL,
  signed_at   DATETIME DEFAULT NULL,
  signer_ip   VARCHAR(45) DEFAULT NULL,
  user_agent  VARCHAR(500) DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted_at  DATETIME DEFAULT NULL,
  INDEX idx_rma (rma_id),
  INDEX idx_expires (expires_at),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
