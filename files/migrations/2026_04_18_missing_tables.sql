-- Adds 8 tables that are referenced by the application but were missing
-- from schema.sql, plus a parts.supplier_sku column. Safe to re-run.

-- Add parts.supplier_sku only if it doesn't already exist (MySQL has no
-- ADD COLUMN IF NOT EXISTS, so we check information_schema).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'parts'
      AND column_name = 'supplier_sku'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE parts ADD COLUMN supplier_sku VARCHAR(100) DEFAULT NULL AFTER sku',
    'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Goods receipts ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS goods_receipts (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id         INT UNSIGNED NOT NULL,
  location_id         INT UNSIGNED NOT NULL,
  reference           VARCHAR(100) DEFAULT '',
  freight_cost        DECIMAL(12,2) DEFAULT 0,
  default_margin_pct  DECIMAL(6,2)  DEFAULT 0,
  notes               TEXT,
  status              ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
  created_by          INT UNSIGNED DEFAULT NULL,
  confirmed_at        DATETIME     DEFAULT NULL,
  created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_supplier (supplier_id),
  INDEX idx_location (location_id),
  INDEX idx_status   (status),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id          INT UNSIGNED NOT NULL,
  part_id             INT UNSIGNED DEFAULT NULL,
  part_name_raw       VARCHAR(255) DEFAULT '',
  sku_raw             VARCHAR(100) DEFAULT '',
  quantity            INT UNSIGNED NOT NULL DEFAULT 1,
  supplier_price      DECIMAL(12,4) DEFAULT 0,
  customs_duty_pct    DECIMAL(6,2)  DEFAULT 0,
  cost_price          DECIMAL(12,4) DEFAULT 0,
  margin_pct          DECIMAL(6,2)  DEFAULT 0,
  unit_price          DECIMAL(12,2) DEFAULT 0,
  unit_price_override DECIMAL(12,2) DEFAULT NULL,
  created_at          DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_receipt (receipt_id),
  INDEX idx_part    (part_id),
  FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
  FOREIGN KEY (part_id)    REFERENCES parts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Inventory counts ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_counts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id  INT UNSIGNED NOT NULL,
  started_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  status       ENUM('active','confirmed','cancelled') NOT NULL DEFAULT 'active',
  created_by   INT UNSIGNED DEFAULT NULL,
  confirmed_by INT UNSIGNED DEFAULT NULL,
  confirmed_at DATETIME     DEFAULT NULL,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_location (location_id),
  INDEX idx_status   (status),
  INDEX idx_started  (started_at),
  FOREIGN KEY (location_id)  REFERENCES locations(id),
  FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_count_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  count_id     INT UNSIGNED NOT NULL,
  part_id      INT UNSIGNED NOT NULL,
  system_qty   INT           NOT NULL DEFAULT 0,
  counted_qty  INT           DEFAULT NULL,
  variance     INT GENERATED ALWAYS AS (COALESCE(counted_qty, 0) - system_qty) VIRTUAL,
  note         VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_count (count_id),
  INDEX idx_part  (part_id),
  FOREIGN KEY (count_id) REFERENCES inventory_counts(id) ON DELETE CASCADE,
  FOREIGN KEY (part_id)  REFERENCES parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Repair evidence (photos) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS repair_evidence (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id  INT UNSIGNED DEFAULT NULL,
  rma_id         INT UNSIGNED DEFAULT NULL,
  stage          ENUM('reception','repair') NOT NULL DEFAULT 'repair',
  filename       VARCHAR(500) NOT NULL,
  original_name  VARCHAR(255) DEFAULT NULL,
  file_size      INT UNSIGNED DEFAULT 0,
  uploaded_by    INT UNSIGNED DEFAULT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted_at     DATETIME DEFAULT NULL,
  INDEX idx_repair_job (repair_job_id),
  INDEX idx_rma        (rma_id),
  INDEX idx_stage      (stage),
  FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (rma_id)        REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repair_evidence_tokens (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token          VARCHAR(64)  NOT NULL UNIQUE,
  repair_job_id  INT UNSIGNED DEFAULT NULL,
  rma_id         INT UNSIGNED DEFAULT NULL,
  stage          ENUM('reception','repair') NOT NULL DEFAULT 'repair',
  expires_at     DATETIME     NOT NULL,
  visited_at     DATETIME     DEFAULT NULL,
  completed_at   DATETIME     DEFAULT NULL,
  created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expires (expires_at),
  FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (rma_id)        REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SKU sequences (per device category) ────────────────────────
CREATE TABLE IF NOT EXISTS sku_sequences (
  category_id  INT UNSIGNED NOT NULL PRIMARY KEY,
  last_seq     INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES device_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Stock movements ledger ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS stock_movements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  part_id         INT UNSIGNED NOT NULL,
  location_id     INT UNSIGNED NOT NULL,
  type            ENUM('in','out','receive','use','adjust','reserve','release','transfer_in','transfer_out') NOT NULL,
  quantity        INT NOT NULL,
  reference_type  VARCHAR(50) DEFAULT NULL,
  reference_id    INT UNSIGNED DEFAULT NULL,
  reason          VARCHAR(255) DEFAULT NULL,
  created_by      INT UNSIGNED DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_part     (part_id),
  INDEX idx_location (location_id),
  INDEX idx_ref      (reference_type, reference_id),
  FOREIGN KEY (part_id)     REFERENCES parts(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
