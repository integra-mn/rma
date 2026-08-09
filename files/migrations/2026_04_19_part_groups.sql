-- Parts taxonomy (separate from device_categories).
-- device_categories.name  = Phones / Tablets / Laptops / …  (a DEVICE kind)
-- part_groups.name        = Battery / LCD / Cable / …       (a PART kind)
--
-- Tagging a part with a part_group lets the repair page offer a second,
-- user-facing filter to narrow within the automatic brand + device-kind
-- scope. Safe to re-run.

CREATE TABLE IF NOT EXISTS part_groups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order SMALLINT UNSIGNED DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO part_groups (name, slug, sort_order) VALUES
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

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'parts'
             AND column_name = 'part_group_id');
SET @s := IF(@c = 0,
    'ALTER TABLE parts
       ADD COLUMN part_group_id INT UNSIGNED DEFAULT NULL AFTER category_id,
       ADD INDEX idx_parts_group (part_group_id),
       ADD CONSTRAINT fk_parts_group
         FOREIGN KEY (part_group_id) REFERENCES part_groups(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
