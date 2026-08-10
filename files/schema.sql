-- ============================================================
-- Integra Repair & RMA Management System
-- Complete Database Schema — v1.0
-- 63 tables across 16 domains
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- DOMAIN 1: IDENTITY & AUTH (7)
-- ============================================================

CREATE TABLE locations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  code        VARCHAR(10),         -- short prefix used for RMA numbering (HQ, PG, BD…)
  address     VARCHAR(255),
  postal_code VARCHAR(20),
  city        VARCHAR(100),
  country     VARCHAR(100) DEFAULT 'Montenegro',
  phone       VARCHAR(30),
  email       VARCHAR(150),
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT NOW(),
  deleted_at  DATETIME DEFAULT NULL,
  INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id         INT UNSIGNED DEFAULT NULL,
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(150) NOT NULL UNIQUE,
  phone               VARCHAR(30),
  password_hash       VARCHAR(255) NOT NULL,
  -- Super Admin is unique per install (enforced in adminController). Admin has
  -- the same permissions but can be assigned to multiple users. Reception,
  -- Technician, Partner have fixed capability sets (see helpers/permissions.php).
  role                ENUM('super_admin','admin','reception','technician','partner') NOT NULL,
  lang                VARCHAR(10) DEFAULT 'en',
  theme               VARCHAR(30) DEFAULT 'midnight',
  is_active           TINYINT(1) DEFAULT 1,
  last_login          DATETIME DEFAULT NULL,
  password_changed_at DATETIME DEFAULT NULL,
  must_change_pw      TINYINT(1) DEFAULT 0,
  require_2fa         TINYINT(1) DEFAULT 0,
  -- Which channel to offer first for a login code. NULL = no preference.
  preferred_2fa_channel VARCHAR(10) DEFAULT NULL,
  -- Authenticator app (TOTP). The secret is password-equivalent; confirmed_at
  -- is set only after the user proves the app works, so a half-finished
  -- enrolment cannot lock anyone out.
  totp_secret         VARCHAR(64) DEFAULT NULL,
  totp_confirmed_at   DATETIME DEFAULT NULL,
  -- 'any' = reachable from anywhere, 'lan' = local network only. Enforced at
  -- login and on every authenticated request (helpers/auth.php).
  access_scope        VARCHAR(10) NOT NULL DEFAULT 'any',
  created_at          DATETIME DEFAULT NOW(),
  updated_at          DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at          DATETIME DEFAULT NULL,
  archived_at         DATETIME DEFAULT NULL,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE security_policies (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role                  ENUM('super_admin','admin','reception','technician','partner') UNIQUE NOT NULL,
  require_2fa           TINYINT(1) DEFAULT 0,
  allowed_2fa_channels  SET('email','whatsapp','sms') DEFAULT 'email',
  max_login_attempts    TINYINT UNSIGNED DEFAULT 5,
  lockout_minutes       SMALLINT UNSIGNED DEFAULT 30,
  password_min_length   TINYINT UNSIGNED DEFAULT 10,
  password_expiry_days  SMALLINT UNSIGNED DEFAULT NULL,
  session_timeout_min   SMALLINT UNSIGNED DEFAULT 480,
  force_2fa_new_device  TINYINT(1) DEFAULT 1,
  updated_at            DATETIME DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE otp_codes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,
  channel     ENUM('email','whatsapp','sms') NOT NULL,
  expires_at  DATETIME NOT NULL,
  attempts    TINYINT UNSIGNED DEFAULT 0,
  used        TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auth_attempts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier  VARCHAR(255),
  ip_address  VARCHAR(45),
  success     TINYINT(1) DEFAULT 0,
  blocked     TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT NOW(),
  INDEX idx_identifier (identifier),
  INDEX idx_ip (ip_address),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auth_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED DEFAULT NULL,
  ip_address  VARCHAR(45),
  user_agent  TEXT,
  channel_2fa ENUM('email','whatsapp','sms','none') DEFAULT 'none',
  success     TINYINT(1) DEFAULT 0,
  fail_reason VARCHAR(100),
  created_at  DATETIME DEFAULT NOW(),
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trusted_devices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  device_hash VARCHAR(255) NOT NULL,
  user_agent  VARCHAR(500),
  ip_address  VARCHAR(45),
  last_seen   DATETIME DEFAULT NOW(),
  created_at  DATETIME DEFAULT NOW(),
  UNIQUE KEY uq_user_device (user_id, device_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 2: PARTNERS & CUSTOMERS (5)
-- ============================================================

CREATE TABLE customers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  email       VARCHAR(150),
  phone       VARCHAR(30),
  address     VARCHAR(255),
  city        VARCHAR(100),
  zip_code    VARCHAR(30),
  country     VARCHAR(100),
  notes       TEXT,
  -- Language for the receipt email and printed receipt. Separate from staff
  -- language: an employee working in EN must not change what a customer reads.
  lang        VARCHAR(2) NOT NULL DEFAULT 'me',
  created_at  DATETIME DEFAULT NOW(),
  updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at  DATETIME DEFAULT NULL,
  archived_at DATETIME DEFAULT NULL,
  INDEX idx_email (email),
  INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE partners (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  tax_id         VARCHAR(50),
  email          VARCHAR(150),
  phone          VARCHAR(30),
  address        VARCHAR(255),
  zip_code       VARCHAR(30),
  city           VARCHAR(100),
  country        VARCHAR(100),
  contact_person VARCHAR(150),
  notes          TEXT,
  default_courier_id INT UNSIGNED DEFAULT NULL,   -- preferred courier (FK added after couriers table)
  is_active      TINYINT(1) DEFAULT 1,
  created_at     DATETIME DEFAULT NOW(),
  updated_at     DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at     DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE partner_users (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  role       ENUM('admin','staff') DEFAULT 'staff',
  invited_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT NOW(),
  UNIQUE KEY uq_partner_user (partner_id, user_id),
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE api_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  partner_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  label      VARCHAR(100),
  last_used  DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT NOW(),
  revoked_at DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_contracts (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id          INT UNSIGNED NOT NULL,
  name                VARCHAR(150),
  start_date          DATE NOT NULL,
  end_date            DATE,
  billing_cycle       ENUM('monthly','quarterly','annual') DEFAULT 'monthly',
  billing_amount      DECIMAL(10,2),
  currency            VARCHAR(3) DEFAULT 'EUR',
  sla_response_hrs    SMALLINT UNSIGNED DEFAULT 24,
  sla_resolution_hrs  SMALLINT UNSIGNED DEFAULT 72,
  notes               TEXT,
  is_active           TINYINT(1) DEFAULT 1,
  created_at          DATETIME DEFAULT NOW(),
  updated_at          DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at          DATETIME DEFAULT NULL,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 3: DEVICE CATALOG (4)
-- ============================================================

CREATE TABLE device_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  sku_prefix VARCHAR(6)   DEFAULT NULL,  -- used by helpers/sku.php for part SKU generation
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_brands (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED DEFAULT NULL,
  name      VARCHAR(100) NOT NULL,
  slug      VARCHAR(100) NOT NULL UNIQUE,
  logo_path VARCHAR(500),
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_models (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id     INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  vendor_id    INT UNSIGNED DEFAULT NULL,
  name         VARCHAR(150) NOT NULL,
  model_number VARCHAR(100),
  release_year YEAR,
  is_active    TINYINT(1) DEFAULT 1,
  FOREIGN KEY (brand_id) REFERENCES device_brands(id),
  FOREIGN KEY (category_id) REFERENCES device_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE devices (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  model_id        INT UNSIGNED NOT NULL,
  customer_id     INT UNSIGNED DEFAULT NULL,
  partner_id      INT UNSIGNED DEFAULT NULL,
  serial_number   VARCHAR(100),
  imei            VARCHAR(20),
  purchase_date   DATE DEFAULT NULL,
  warranty_expiry DATE DEFAULT NULL,
  color           VARCHAR(50),
  capacity        VARCHAR(30),
  notes           TEXT,
  created_at      DATETIME DEFAULT NOW(),
  updated_at      DATETIME DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY uq_serial (serial_number),
  FOREIGN KEY (model_id) REFERENCES device_models(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 4: RMA CORE (6)
-- ============================================================

CREATE TABLE rma_statuses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(60) NOT NULL UNIQUE,
  label       VARCHAR(100) NOT NULL,
  label_me    VARCHAR(100) NULL,
  color       VARCHAR(20),
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  is_terminal TINYINT(1) DEFAULT 0,
  is_system   TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rma_requests (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id            INT UNSIGNED NOT NULL,
  device_id              INT UNSIGNED DEFAULT NULL,
  customer_id            INT UNSIGNED DEFAULT NULL,
  partner_id             INT UNSIGNED DEFAULT NULL,
  submitted_by           INT UNSIGNED DEFAULT NULL,
  assigned_tech          INT UNSIGNED DEFAULT NULL,
  status_id              INT UNSIGNED NOT NULL,
  vendor_id              INT UNSIGNED DEFAULT NULL,
  vendor_ra_number       VARCHAR(100) DEFAULT NULL,
  vendor_rma_ref         VARCHAR(100) DEFAULT NULL,
  vendor_warranty_status ENUM('covered','not_covered','expired','unknown') DEFAULT 'unknown',
  vendor_last_sync       DATETIME DEFAULT NULL,
  rma_number             VARCHAR(30) NOT NULL UNIQUE,
  complaint              TEXT,
  diagnosis              TEXT,
  service_box            VARCHAR(100) DEFAULT NULL,
  accessories            JSON         DEFAULT NULL,
  accessories_other      VARCHAR(255) DEFAULT NULL,
  is_warranty            TINYINT(1) DEFAULT 0,
  warranty_refusal       JSON         DEFAULT NULL,
  priority               ENUM('low','normal','high','urgent') DEFAULT 'normal',
  sla_rule_id            INT UNSIGNED DEFAULT NULL,
  sla_due_at             DATETIME DEFAULT NULL,
  sla_breached           TINYINT(1) DEFAULT 0,
  estimated_completion   DATE DEFAULT NULL,
  completed_at           DATETIME DEFAULT NULL,
  created_at             DATETIME DEFAULT NOW(),
  updated_at             DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at             DATETIME DEFAULT NULL,
  archived_at            DATETIME DEFAULT NULL,
  INDEX idx_location (location_id),
  INDEX idx_status (status_id),
  INDEX idx_customer (customer_id),
  INDEX idx_partner (partner_id),
  INDEX idx_rma_number (rma_number),
  INDEX idx_created (created_at),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
  FOREIGN KEY (status_id) REFERENCES rma_statuses(id),
  FOREIGN KEY (assigned_tech) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hands out RMA numbers atomically. scope is 'global' (never resets) or the
-- four-digit year (restarts each January), chosen by rma_number_reset_yearly.
-- next_value is the number that will be issued next.
CREATE TABLE rma_counters (
  scope      VARCHAR(20) NOT NULL PRIMARY KEY,
  next_value INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rma_status_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id     INT UNSIGNED NOT NULL,
  status_id  INT UNSIGNED NOT NULL,
  changed_by INT UNSIGNED DEFAULT NULL,
  note       TEXT,
  created_at DATETIME DEFAULT NOW(),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (status_id) REFERENCES rma_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rma_comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id     INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED DEFAULT NULL,
  body       TEXT NOT NULL,
  visibility ENUM('internal','customer') DEFAULT 'internal',
  source     ENUM('staff','customer') NOT NULL DEFAULT 'staff',
  created_at DATETIME DEFAULT NOW(),
  updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at DATETIME DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rma_attachments (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id         INT UNSIGNED NOT NULL,
  uploaded_by    INT UNSIGNED DEFAULT NULL,
  file_type      ENUM('intake_photo','repair_photo','evidence','document') NOT NULL,
  original_name  VARCHAR(255),
  original_size  INT UNSIGNED,
  processed_size INT UNSIGNED,
  file_path      VARCHAR(500) NOT NULL,
  thumbnail_path VARCHAR(500),
  mime_type      VARCHAR(100),
  width          SMALLINT UNSIGNED DEFAULT NULL,
  height         SMALLINT UNSIGNED DEFAULT NULL,
  is_processed   TINYINT(1) DEFAULT 0,
  processed_at   DATETIME DEFAULT NULL,
  created_at     DATETIME DEFAULT NOW(),
  deleted_at     DATETIME DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rma_tracking_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id     INT UNSIGNED NOT NULL UNIQUE,
  token      VARCHAR(64) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT NOW(),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 5: REPAIR JOBS & TIME (3)
-- ============================================================

CREATE TABLE repair_statuses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(60) NOT NULL UNIQUE,
  label       VARCHAR(100) NOT NULL,
  label_me    VARCHAR(100) NULL,
  color       VARCHAR(20),
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  is_terminal TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repair_jobs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id        INT UNSIGNED NOT NULL,
  location_id   INT UNSIGNED NOT NULL,
  technician_id INT UNSIGNED DEFAULT NULL,
  status_id     INT UNSIGNED NOT NULL,
  description   TEXT,
  resolution    TEXT,
  started_at    DATETIME DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  created_at    DATETIME DEFAULT NOW(),
  updated_at    DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at    DATETIME DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (status_id) REFERENCES repair_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repair_time_logs (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id    INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  minutes   SMALLINT UNSIGNED NOT NULL,
  note      VARCHAR(255),
  logged_at DATETIME DEFAULT NOW(),
  FOREIGN KEY (job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 6: LOANER DEVICES (2)
-- ============================================================

CREATE TABLE loaner_devices (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id   INT UNSIGNED NOT NULL,
  model_id      INT UNSIGNED DEFAULT NULL,
  serial_number VARCHAR(100),
  label         VARCHAR(100),
  `condition`     ENUM('new','good','fair','poor') DEFAULT 'good',
  notes         TEXT,
  is_active     TINYINT(1) DEFAULT 1,
  created_at    DATETIME DEFAULT NOW(),
  deleted_at    DATETIME DEFAULT NULL,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (model_id) REFERENCES device_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE loaner_assignments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loaner_id     INT UNSIGNED NOT NULL,
  rma_id        INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED DEFAULT NULL,
  assigned_by   INT UNSIGNED DEFAULT NULL,
  assigned_at   DATETIME DEFAULT NOW(),
  returned_at   DATETIME DEFAULT NULL,
  `condition_out` ENUM('new','good','fair','poor') DEFAULT 'good',
  `condition_in`  ENUM('new','good','fair','poor') DEFAULT NULL,
  notes         TEXT,
  FOREIGN KEY (loaner_id) REFERENCES loaner_devices(id),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 7: PARTS & SUPPLIERS (6)
-- ============================================================

CREATE TABLE suppliers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  contact    VARCHAR(150),
  email      VARCHAR(150),
  phone      VARCHAR(30),
  address    VARCHAR(255),
  city       VARCHAR(100),
  zip_code   VARCHAR(30),
  country    VARCHAR(100),
  notes      TEXT,
  is_active  TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT NOW(),
  deleted_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE part_groups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order SMALLINT UNSIGNED DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO part_groups (name, slug, sort_order) VALUES
  ('Battery',            'battery',         10),
  ('LCD / Display',      'display',         20),
  ('Touch / Digitizer',  'digitizer',       25),
  ('Camera',             'camera',          30),
  ('Speaker / Earpiece', 'speaker',         40),
  ('Microphone',         'microphone',      45),
  ('Motherboard',        'motherboard',     50),
  ('Charging port',      'charging-port',   60),
  ('Buttons',            'buttons',         70),
  ('Frame / Housing',    'frame',           80),
  ('Cable / Flex',       'cable',           90),
  ('Antenna',            'antenna',        100),
  ('SIM tray',           'sim-tray',       110),
  ('Back glass / Cover', 'back-glass',     120),
  ('Adhesive',           'adhesive',       200),
  ('Screws / Fasteners', 'screws',         210),
  ('Tools',              'tools',          220),
  ('Consumable',         'consumable',     230),
  ('Other',              'other',          900);

CREATE TABLE parts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id   INT UNSIGNED DEFAULT NULL,
  brand_id      INT UNSIGNED DEFAULT NULL,     -- NULL = any brand (universal)
  category_id   INT UNSIGNED DEFAULT NULL,     -- NULL = any device kind (phones/tablets/…)
  part_group_id INT UNSIGNED DEFAULT NULL,     -- NULL = unclassified part taxonomy
  name          VARCHAR(150) NOT NULL,
  internal_sku  VARCHAR(100) UNIQUE,           -- auto-generated (helpers/sku.php)
  supplier_sku  VARCHAR(100) DEFAULT NULL,     -- vendor's part number
  description   TEXT,
  unit_price    DECIMAL(10,2) DEFAULT 0.00,    -- retail / sell price
  cost_price    DECIMAL(10,4) DEFAULT 0.0000,  -- weighted-average purchase cost (updated on goods-receipt confirm)
  vat_rate_id   INT UNSIGNED DEFAULT NULL,
  min_stock     SMALLINT UNSIGNED DEFAULT 5,   -- reorder threshold
  is_active     TINYINT(1) DEFAULT 1,
  created_at    DATETIME DEFAULT NOW(),
  updated_at    DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at    DATETIME DEFAULT NULL,
  INDEX idx_parts_brand      (brand_id),
  INDEX idx_parts_category   (category_id),
  INDEX idx_parts_part_group (part_group_id),
  FOREIGN KEY (supplier_id)   REFERENCES suppliers(id)         ON DELETE SET NULL,
  FOREIGN KEY (brand_id)      REFERENCES device_brands(id)     ON DELETE SET NULL,
  FOREIGN KEY (category_id)   REFERENCES device_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (part_group_id) REFERENCES part_groups(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parts_stock (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  part_id     INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  quantity    SMALLINT UNSIGNED DEFAULT 0,
  updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY uq_part_location (part_id, location_id),
  FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE part_usage (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id      INT UNSIGNED NOT NULL,
  part_id     INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  quantity    SMALLINT UNSIGNED DEFAULT 1,
  unit_cost   DECIMAL(10,2),                  -- cost snapshot at time of usage
  logged_by   INT UNSIGNED DEFAULT NULL,
  created_at  DATETIME DEFAULT NOW(),
  FOREIGN KEY (job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (part_id) REFERENCES parts(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (logged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_orders (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  created_by  INT UNSIGNED DEFAULT NULL,
  status      ENUM('draft','sent','received','cancelled') DEFAULT 'draft',
  po_number   VARCHAR(50) UNIQUE,
  notes       TEXT,
  ordered_at  DATETIME DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  created_at  DATETIME DEFAULT NOW(),
  updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at  DATETIME DEFAULT NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  part_id      INT UNSIGNED NOT NULL,
  quantity     SMALLINT UNSIGNED NOT NULL,
  unit_price   DECIMAL(10,2),
  received_qty SMALLINT UNSIGNED DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (part_id) REFERENCES parts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 8: DELIVERY (2)
-- ============================================================

CREATE TABLE couriers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  slug         VARCHAR(60) NOT NULL UNIQUE,
  api_class    VARCHAR(150),
  tracking_url VARCHAR(500),
  phone        VARCHAR(60) DEFAULT NULL,
  is_active    TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO couriers (name, slug, tracking_url, is_active) VALUES
  ('Pošta Crne Gore', 'posta-cg',  'https://www.postacg.me/track?id={tracking}',       1),
  ('DHL',             'dhl',       'https://www.dhl.com/track?tracking-id={tracking}', 1),
  ('Ručna dostava',   'in-person', NULL, 1);

CREATE TABLE delivery_shipments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id          INT UNSIGNED NOT NULL,
  courier_id      INT UNSIGNED DEFAULT NULL,
  location_id     INT UNSIGNED DEFAULT NULL,
  direction       ENUM('inbound','outbound') DEFAULT 'inbound',
  tracking_number VARCHAR(150),
  label_path      VARCHAR(500),
  status          VARCHAR(100) DEFAULT 'pending',
  carrier_status  VARCHAR(255),
  cost            DECIMAL(10,2) DEFAULT NULL,
  notes           VARCHAR(500)  DEFAULT NULL,
  dispatched_at   DATETIME DEFAULT NULL,
  delivered_at    DATETIME DEFAULT NULL,
  last_polled_at  DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT NOW(),
  updated_at      DATETIME DEFAULT NOW() ON UPDATE NOW(),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (courier_id) REFERENCES couriers(id) ON DELETE SET NULL,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 9: SCHEDULING (2)
-- ============================================================

CREATE TABLE appointments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id   INT UNSIGNED NOT NULL,
  rma_id        INT UNSIGNED DEFAULT NULL,
  customer_id   INT UNSIGNED DEFAULT NULL,
  technician_id INT UNSIGNED DEFAULT NULL,
  scheduled_at  DATETIME NOT NULL,
  duration_min  SMALLINT UNSIGNED DEFAULT 30,
  type          ENUM('drop_off','pickup','callback') DEFAULT 'drop_off',
  status        ENUM('pending','confirmed','completed','cancelled','no_show') DEFAULT 'pending',
  notes         TEXT,
  created_at    DATETIME DEFAULT NOW(),
  updated_at    DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at    DATETIME DEFAULT NULL,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE technician_schedules (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 10: INVOICING & BILLING (5)
-- ============================================================

CREATE TABLE vat_rates (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(60) NOT NULL,
  rate       DECIMAL(5,2) NOT NULL,
  is_default TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id         INT UNSIGNED DEFAULT NULL,
  location_id    INT UNSIGNED NOT NULL,
  customer_id    INT UNSIGNED DEFAULT NULL,
  partner_id     INT UNSIGNED DEFAULT NULL,
  created_by     INT UNSIGNED DEFAULT NULL,
  invoice_number VARCHAR(50) NOT NULL UNIQUE,
  type           ENUM('invoice','credit_note','proforma') DEFAULT 'invoice',
  status         ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  subtotal       DECIMAL(10,2) DEFAULT 0.00,
  vat_amount     DECIMAL(10,2) DEFAULT 0.00,
  total          DECIMAL(10,2) DEFAULT 0.00,
  currency       VARCHAR(3) DEFAULT 'EUR',
  notes          TEXT,
  pdf_path       VARCHAR(500),
  due_date       DATE DEFAULT NULL,
  paid_at        DATETIME DEFAULT NULL,
  sent_at        DATETIME DEFAULT NULL,
  created_at     DATETIME DEFAULT NOW(),
  updated_at     DATETIME DEFAULT NOW() ON UPDATE NOW(),
  deleted_at     DATETIME DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity    DECIMAL(8,2) DEFAULT 1,
  unit_price  DECIMAL(10,2) NOT NULL,
  vat_rate_id INT UNSIGNED DEFAULT NULL,
  vat_amount  DECIMAL(10,2) DEFAULT 0.00,
  total       DECIMAL(10,2) NOT NULL,
  part_id     INT UNSIGNED DEFAULT NULL,
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (vat_rate_id) REFERENCES vat_rates(id) ON DELETE SET NULL,
  FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('cash','card','bank_transfer','other') DEFAULT 'bank_transfer',
  reference   VARCHAR(150),
  paid_at     DATETIME DEFAULT NOW(),
  recorded_by INT UNSIGNED DEFAULT NULL,
  notes       TEXT,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accounting_exports (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id  INT UNSIGNED DEFAULT NULL,
  exported_by  INT UNSIGNED DEFAULT NULL,
  format       ENUM('pantheon','sap','xls','csv') NOT NULL,
  date_from    DATE,
  date_to      DATE,
  file_path    VARCHAR(500),
  record_count INT UNSIGNED DEFAULT 0,
  created_at   DATETIME DEFAULT NOW(),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
  FOREIGN KEY (exported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 11: SLA & CONTRACTS (3)
-- ============================================================

CREATE TABLE sla_rules (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150) NOT NULL,
  response_hrs      SMALLINT UNSIGNED DEFAULT 24,
  resolution_hrs    SMALLINT UNSIGNED DEFAULT 72,
  applies_to        ENUM('all','warranty','out_of_warranty','partner') DEFAULT 'all',
  priority          ENUM('low','normal','high','urgent') DEFAULT 'normal',
  notify_on_breach  TINYINT(1) DEFAULT 1,
  is_active         TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contract_sla_rules (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id        INT UNSIGNED NOT NULL,
  name               VARCHAR(150),
  response_hrs       SMALLINT UNSIGNED DEFAULT 8,
  resolution_hrs     SMALLINT UNSIGNED DEFAULT 24,
  device_category_id INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (contract_id) REFERENCES service_contracts(id) ON DELETE CASCADE,
  FOREIGN KEY (device_category_id) REFERENCES device_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sla_breaches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id      INT UNSIGNED NOT NULL,
  sla_rule_id INT UNSIGNED DEFAULT NULL,
  breach_type ENUM('response','resolution') NOT NULL,
  due_at      DATETIME NOT NULL,
  breached_at DATETIME DEFAULT NOW(),
  notified    TINYINT(1) DEFAULT 0,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 12: NOTIFICATIONS (2)
-- ============================================================

CREATE TABLE notification_templates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(100) NOT NULL,
  channel     ENUM('email','whatsapp','sms') NOT NULL,
  lang        VARCHAR(10) DEFAULT 'en',
  subject     VARCHAR(255),
  body        TEXT NOT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY uq_code_channel_lang (code, channel, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id        INT UNSIGNED DEFAULT NULL,
  user_id       INT UNSIGNED DEFAULT NULL,
  channel       ENUM('email','whatsapp','sms') NOT NULL,
  recipient     VARCHAR(150),
  template_code VARCHAR(100),
  status        ENUM('queued','sent','delivered','failed') DEFAULT 'queued',
  error         TEXT,
  sent_at       DATETIME DEFAULT NULL,
  created_at    DATETIME DEFAULT NOW(),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 13: CUSTOMER EXPERIENCE (3)
-- ============================================================

CREATE TABLE csat_surveys (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id       INT UNSIGNED NOT NULL UNIQUE,
  sent_at      DATETIME DEFAULT NULL,
  channel      ENUM('whatsapp','email') DEFAULT 'whatsapp',
  token        VARCHAR(64) NOT NULL UNIQUE,
  responded_at DATETIME DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE csat_responses (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_id  INT UNSIGNED NOT NULL,
  score      TINYINT UNSIGNED NOT NULL,
  comment    TEXT,
  created_at DATETIME DEFAULT NOW(),
  FOREIGN KEY (survey_id) REFERENCES csat_surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tracking_visibility_settings (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role       ENUM('customer','partner','all') DEFAULT 'all',
  field_key  VARCHAR(60) NOT NULL,
  is_visible TINYINT(1) DEFAULT 1,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY uq_role_field (role, field_key),
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 14: VENDOR INTEGRATION (5)
-- ============================================================

CREATE TABLE vendors (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(150) NOT NULL,
  slug      VARCHAR(60) NOT NULL UNIQUE,
  logo_path VARCHAR(500),
  contact   VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_adapters (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id      INT UNSIGNED NOT NULL UNIQUE,
  adapter_class  VARCHAR(150) NOT NULL,
  endpoint_url   VARCHAR(500),
  auth_type      ENUM('oauth2','api_key','basic','custom') DEFAULT 'api_key',
  credentials    TEXT,
  sync_mode      ENUM('auto','manual','both') DEFAULT 'both',
  is_active      TINYINT(1) DEFAULT 0,
  last_tested_at DATETIME DEFAULT NULL,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user overrides for vendor credentials (e.g. each Apple-certified
-- technician has their own GSX bearer token; reception just uses the
-- shop-wide lookup account from vendor_adapters).
CREATE TABLE user_vendor_credentials (
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

CREATE TABLE vendor_rma_submissions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id          INT UNSIGNED NOT NULL,
  vendor_id       INT UNSIGNED NOT NULL,
  vendor_ref      VARCHAR(150),
  ra_number       VARCHAR(150),
  status          VARCHAR(100),
  submitted_at    DATETIME DEFAULT NULL,
  last_updated_at DATETIME DEFAULT NULL,
  submitted_by    INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_sync_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id   INT UNSIGNED NOT NULL,
  rma_id      INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(60),
  request     TEXT,
  response    TEXT,
  http_status SMALLINT UNSIGNED,
  success     TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT NOW(),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_warranty_cache (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id     INT UNSIGNED NOT NULL,
  serial_number VARCHAR(100) NOT NULL,
  status        ENUM('covered','not_covered','expired','unknown') DEFAULT 'unknown',
  expiry_date   DATE DEFAULT NULL,
  raw_response  TEXT,
  cached_at     DATETIME DEFAULT NOW(),
  expires_at    DATETIME NOT NULL,
  UNIQUE KEY uq_vendor_serial (vendor_id, serial_number),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 15: ADMIN PERMISSIONS (4)
-- ============================================================

CREATE TABLE permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module      VARCHAR(60) NOT NULL,
  action      VARCHAR(60) NOT NULL,
  label       VARCHAR(150) NOT NULL,
  description VARCHAR(255),
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  UNIQUE KEY uq_module_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permission_presets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  created_by  INT UNSIGNED DEFAULT NULL,
  is_system   TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT NOW(),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permission_preset_items (
  preset_id     INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (preset_id, permission_id),
  FOREIGN KEY (preset_id) REFERENCES permission_presets(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_profiles (
  user_id      INT UNSIGNED PRIMARY KEY,
  preset_id    INT UNSIGNED DEFAULT NULL,
  overrides    JSON DEFAULT NULL,
  location_ids JSON DEFAULT NULL,
  created_by   INT UNSIGNED DEFAULT NULL,
  updated_at   DATETIME DEFAULT NOW() ON UPDATE NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (preset_id) REFERENCES permission_presets(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DOMAIN 16: SYSTEM (4)
-- ============================================================

CREATE TABLE audit_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60),
  entity_id   INT UNSIGNED DEFAULT NULL,
  old_values  JSON DEFAULT NULL,
  new_values  JSON DEFAULT NULL,
  ip_address  VARCHAR(45),
  created_at  DATETIME DEFAULT NOW(),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id INT UNSIGNED DEFAULT NULL,
  key_name    VARCHAR(100) NOT NULL,
  value       TEXT,
  type        ENUM('string','int','bool','json') DEFAULT 'string',
  group_name  VARCHAR(60) DEFAULT 'general',
  updated_by  INT UNSIGNED DEFAULT NULL,
  updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY uq_location_key (location_id, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE languages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(10) NOT NULL UNIQUE,
  name       VARCHAR(60) NOT NULL,
  is_active  TINYINT(1) DEFAULT 1,
  is_default TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE themes (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code      VARCHAR(30) NOT NULL UNIQUE,
  name      VARCHAR(60) NOT NULL,
  css_class VARCHAR(60),
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DEFERRED FOREIGN KEYS (cross-domain references)
-- ============================================================

ALTER TABLE partners
  ADD FOREIGN KEY (default_courier_id) REFERENCES couriers(id) ON DELETE SET NULL;

ALTER TABLE device_brands
  ADD FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

ALTER TABLE device_models
  ADD FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

ALTER TABLE rma_requests
  ADD FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
  ADD FOREIGN KEY (sla_rule_id) REFERENCES sla_rules(id) ON DELETE SET NULL;

ALTER TABLE parts
  ADD FOREIGN KEY (vat_rate_id) REFERENCES vat_rates(id) ON DELETE SET NULL;

SET foreign_key_checks = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO languages (code, name, is_active, is_default) VALUES
  ('en', 'English', 1, 1),
  ('me', 'Crnogorski', 1, 0);

INSERT INTO themes (code, name, css_class) VALUES
  ('midnight', 'Midnight', 'theme-midnight'),
  ('dark',     'Dark',     'theme-dark'),
  ('ocean',    'Ocean',    'theme-ocean'),
  ('focus',    'Focus',    'theme-focus');

INSERT INTO vat_rates (label, rate, is_default) VALUES
  ('Standard 21%', 21.00, 1),
  ('Reduced 7%',    7.00, 0),
  ('Exempt 0%',     0.00, 0);

INSERT INTO rma_statuses (code, label, label_me, color, sort_order, is_terminal, is_system) VALUES
  ('draft',             'Draft',              'Nacrt',             '#888780', 1,  0, 1),
  ('submitted',         'Submitted',          'Podneseno',         '#378ADD', 2,  0, 1),
  ('awaiting_device',   'Awaiting device',    'Čeka se uredjaj',    '#EF9F27', 3,  0, 1),
  ('device_received',   'Device received',    'Uredjaj primljen',   '#1D9E75', 4,  0, 1),
  ('in_diagnosis',      'In diagnosis',       'Na dijagnostici',   '#7F77DD', 5,  0, 1),
  ('awaiting_parts',    'Awaiting parts',     'Čeka se dio',       '#EF9F27', 6,  0, 1),
  ('in_repair',         'In repair',          'Na popravci',       '#7F77DD', 7,  0, 1),
  ('awaiting_approval', 'Awaiting approval',  'Čeka se odobrenje', '#EF9F27', 8,  0, 1),
  ('repaired',          'Repaired',           'Popravljeno',       '#1D9E75', 9,  0, 1),
  ('dispatched',        'Dispatched',         'Otpremljeno',       '#378ADD', 10, 0, 1),
  ('closed',            'Closed',             'Zatvoreno',         '#3B6D11', 11, 1, 1),
  ('cancelled',         'Cancelled',          'Otkazano',          '#A32D2D', 12, 1, 1),
  ('unrepairable',      'Unrepairable',       'Nepopravljivo',     '#A32D2D', 13, 1, 1);

INSERT INTO repair_statuses (code, label, label_me, color, sort_order, is_terminal) VALUES
  ('pending',     'Pending',     'Na čekanju', '#888780', 1, 0),
  ('in_progress', 'In progress', 'U toku',     '#7F77DD', 2, 0),
  ('on_hold',     'On hold',     'Pauzirano',  '#EF9F27', 3, 0),
  ('completed',   'Completed',   'Završeno',   '#1D9E75', 4, 1),
  ('cancelled',   'Cancelled',   'Otkazano',   '#A32D2D', 5, 1);

INSERT INTO security_policies
  (role, require_2fa, allowed_2fa_channels, max_login_attempts, lockout_minutes, password_min_length, session_timeout_min, force_2fa_new_device)
VALUES
  ('super_admin', 1, 'email,whatsapp,sms', 5, 30, 12, 480,  1),
  ('admin',       1, 'email,whatsapp,sms', 5, 30, 10, 480,  1),
  ('reception',  0, 'email,sms',          5, 30, 10, 480,  0),
  ('technician', 0, 'email',             5, 30, 8,  600,  0),
  ('partner',    1, 'email,whatsapp,sms', 5, 30, 10, 480,  1);

INSERT INTO settings (key_name, value, type, group_name) VALUES
  ('app_name',          'Integra RMS', 'string', 'general'),
  ('app_url',           '',            'string', 'general'),
  ('default_lang',      'en',          'string', 'general'),
  ('img_max_width',     '1920',        'int',    'media'),
  ('img_max_height',    '1920',        'int',    'media'),
  ('img_quality',       '85',          'int',    'media'),
  ('img_thumb_size',    '400',         'int',    'media'),
  ('img_max_upload_mb', '20',          'int',    'media'),
  ('retention_months',  '60',          'int',    'data'),
  ('whatsapp_enabled',  '0',           'bool',   'integrations'),
  ('ai_enabled',        '0',           'bool',   'integrations'),
  ('smtp_host',         '',            'string', 'email'),
  ('smtp_port',         '587',         'int',    'email'),
  ('smtp_user',         '',            'string', 'email'),
  ('smtp_from',         '',            'string', 'email'),
  -- SMS gateway (set sms_provider to 'twilio', 'clickatell' or 'custom')
  ('sms_provider',             '',     'string', 'integrations'),
  ('sms_twilio_sid',           '',     'string', 'integrations'),
  ('sms_twilio_token',         '',     'string', 'integrations'),
  ('sms_twilio_from',          '',     'string', 'integrations'),
  ('sms_clickatell_apikey',    '',     'string', 'integrations'),
  ('sms_clickatell_from',      '',     'string', 'integrations'),
  ('sms_custom_url',           '',     'string', 'integrations'),
  ('sms_custom_auth_header',   '',     'string', 'integrations'),
  ('sms_custom_to_field',      'to',   'string', 'integrations'),
  ('sms_custom_text_field',    'text', 'string', 'integrations'),
  ('sms_custom_extra',         '',     'string', 'integrations'),
  ('sms_custom_json',          '0',    'bool',   'integrations'),
  -- WhatsApp via Meta Cloud API
  ('whatsapp_provider',        'meta',             'string', 'integrations'),
  ('whatsapp_meta_phone_id',   '',                 'string', 'integrations'),
  ('whatsapp_meta_token',      '',                 'string', 'integrations'),
  ('whatsapp_meta_template',   'otp_verification', 'string', 'integrations'),
  ('whatsapp_meta_lang',       'en',               'string', 'integrations'),
  ('whatsapp_meta_version',    'v20.0',            'string', 'integrations');

INSERT INTO permissions (module, action, label, sort_order) VALUES
  ('rma',          'view',    'View RMAs',             1),
  ('rma',          'create',  'Create RMA',            2),
  ('rma',          'edit',    'Edit RMA',              3),
  ('rma',          'delete',  'Delete RMA',            4),
  ('rma',          'assign',  'Assign technician',     5),
  ('repair',       'view',    'View repair jobs',      10),
  ('repair',       'create',  'Create repair job',     11),
  ('repair',       'edit',    'Edit repair job',       12),
  ('parts',        'view',    'View parts',            20),
  ('parts',        'create',  'Add parts',             21),
  ('parts',        'edit',    'Edit parts',            22),
  ('parts',        'delete',  'Delete parts',          23),
  ('invoicing',    'view',    'View invoices',         30),
  ('invoicing',    'create',  'Create invoice',        31),
  ('invoicing',    'edit',    'Edit invoice',          32),
  ('invoicing',    'export',  'Export accounting',     33),
  ('customers',    'view',    'View customers',        40),
  ('customers',    'create',  'Add customer',          41),
  ('customers',    'edit',    'Edit customer',         42),
  ('partners',     'view',    'View partners',         50),
  ('partners',     'edit',    'Edit partners',         51),
  ('users',        'view',    'View users',            60),
  ('users',        'create',  'Add users',             61),
  ('users',        'edit',    'Edit users',            62),
  ('reports',      'view',    'View reports',          70),
  ('reports',      'export',  'Export reports',        71),
  ('settings',     'view',    'View settings',         80),
  ('settings',     'edit',    'Edit settings',         81),
  ('delivery',     'view',    'View shipments',        90),
  ('delivery',     'edit',    'Manage shipments',      91),
  ('appointments', 'view',    'View appointments',     100),
  ('appointments', 'manage',  'Manage appointments',   101);

INSERT INTO permission_presets (name, description, is_system) VALUES
  ('Operations Admin', 'RMA, repair, parts, delivery — no billing or user management', 1),
  ('Billing Admin',    'Invoicing, payments, accounting export — no repair access',    1),
  ('Read-only',        'View-only access across all modules',                          1);

-- ============================================================
-- DOMAIN 11: GOODS RECEIPTS, INVENTORY COUNTS, EVIDENCE, STOCK
-- (tables referenced by controllers but added post-initial schema)
-- ============================================================

-- supplier_sku is now part of the CREATE TABLE parts definition above.
-- (The ALTER here was needed for older DBs; no-op on fresh install.)

CREATE TABLE goods_receipts (
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

CREATE TABLE goods_receipt_items (
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

CREATE TABLE inventory_counts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference    VARCHAR(30)  DEFAULT NULL,   -- document number, e.g. POP-2026-0001
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

CREATE TABLE inventory_count_items (
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

CREATE TABLE repair_evidence (
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

CREATE TABLE repair_evidence_tokens (
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

CREATE TABLE sku_sequences (
  category_id  INT UNSIGNED NOT NULL PRIMARY KEY,
  last_seq     INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES device_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_movements (
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

-- ============================================================
-- DOMAIN 17: SIGNATURES & ROLE PERMISSIONS
-- Added here from migrations so a fresh install matches a migrated one.
-- ============================================================

CREATE TABLE rma_signatures (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rma_id      INT UNSIGNED NOT NULL,
  token       VARCHAR(64) NOT NULL UNIQUE,
  filename    VARCHAR(500) DEFAULT NULL,
  expires_at  DATETIME NOT NULL,
  visited_at  DATETIME DEFAULT NULL,
  signed_at   DATETIME DEFAULT NULL,
  signer_ip   VARCHAR(45) DEFAULT NULL,
  user_agent  VARCHAR(500) DEFAULT NULL,
  created_at  DATETIME DEFAULT NOW(),
  deleted_at  DATETIME DEFAULT NULL,
  INDEX idx_rma (rma_id),
  INDEX idx_expires (expires_at),
  FOREIGN KEY (rma_id) REFERENCES rma_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE signing_stations (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  station_key  VARCHAR(64) NOT NULL UNIQUE,
  name         VARCHAR(100) NOT NULL,
  location_id  INT UNSIGNED NOT NULL,
  is_active    TINYINT(1) DEFAULT 1,
  last_poll_at DATETIME DEFAULT NULL,
  created_at   DATETIME DEFAULT NOW(),
  INDEX idx_location (location_id),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Editable role -> permission grants (Settings → Permissions).
-- Super Admin is always full-access and is never stored here.
CREATE TABLE role_permissions (
  role   VARCHAR(30) NOT NULL,
  module VARCHAR(50) NOT NULL,
  action VARCHAR(30) NOT NULL,
  PRIMARY KEY (role, module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO role_permissions (role, module, action) VALUES
  ('reception','rma','view'),('reception','rma','create'),('reception','rma','edit'),
  ('reception','repair','view'),
  ('reception','customers','view'),('reception','customers','create'),('reception','customers','edit'),
  ('reception','partners','view'),('reception','suppliers','view'),
  ('reception','reports','view'),
  ('technician','rma','view'),('technician','rma','edit'),
  ('technician','repair','view'),('technician','repair','create'),('technician','repair','edit'),
  ('technician','parts','view'),
  ('technician','shipments','view'),('technician','shipments','edit'),
  ('technician','customers','view'),
  ('technician','reports','view'),
  ('partner','rma','view'),('partner','rma','create'),
  ('partner','shipments','view'),
  ('partner','customers','view'),
  ('admin','rma','view'),('admin','rma','create'),('admin','rma','edit'),
  ('admin','repair','view'),('admin','repair','create'),('admin','repair','edit'),
  ('admin','parts','view'),('admin','parts','create'),('admin','parts','edit'),('admin','parts','delete'),
  ('admin','shipments','view'),('admin','shipments','create'),('admin','shipments','edit'),
  ('admin','customers','view'),('admin','customers','create'),('admin','customers','edit'),
  ('admin','partners','view'),('admin','partners','edit'),
  ('admin','suppliers','view'),('admin','suppliers','edit'),
  ('admin','reports','view'),
  ('admin','invoicing','view'),
  ('admin','settings','view'),('admin','settings','edit'),
  ('admin','preferences','theme'),('admin','preferences','lang'),('admin','preferences','integrations');
