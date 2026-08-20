-- Vendor codes gain the two things TCL's real data needs.
--
-- 1. A product line. TCL scope their codes by their own product lines - Mobile,
--    Tablet PC, Watch, Accessory, Datacard, Projector, Laptop - which are not
--    our device categories and never will be: they have Accessory and Projector,
--    we have LCD TV. Storing their line verbatim and mapping it to our category
--    separately keeps the import faithful and the mapping visible. An unmapped
--    line simply never reaches the bench, which is what Rajo wants for Projector
--    and Laptop: kept for a future import, not shown today.
--
-- 2. The pairing. A symptom does not accept any solution. TCL's export is
--    10,257 symptom-solution pairs: the median symptom allows 22 of the 207
--    solutions, so choosing the symptom cuts the second list by about 90%.
--    Without this the technician scrolls 207 entries to find one of 22.
--
--    And the product line changes the answer - for 81 of the 141 symptom codes
--    the valid solutions differ by line - so the pairing hangs off the scoped
--    code rows, not off the bare code.

ALTER TABLE repair_codes ADD COLUMN IF NOT EXISTS vendor_id   INTEGER REFERENCES vendors(id);
ALTER TABLE repair_codes ADD COLUMN IF NOT EXISTS vendor_line VARCHAR(60);

-- Which of our device categories a vendor's product line means. Nullable on
-- purpose: a line with no category is stored and never offered.
CREATE TABLE IF NOT EXISTS vendor_product_lines (
    id          SERIAL PRIMARY KEY,
    vendor_id   INTEGER NOT NULL REFERENCES vendors(id),
    line        VARCHAR(60) NOT NULL,
    category_id INTEGER REFERENCES device_categories(id),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (vendor_id, line)
);

-- Which solutions are allowed for which symptom. Both sides point at
-- repair_codes rows that are already scoped to a brand and a product line, so
-- the pair inherits that scope rather than repeating it.
CREATE TABLE IF NOT EXISTS repair_code_links (
    error_code_id      INTEGER NOT NULL REFERENCES repair_codes(id) ON DELETE CASCADE,
    resolution_code_id INTEGER NOT NULL REFERENCES repair_codes(id) ON DELETE CASCADE,
    PRIMARY KEY (error_code_id, resolution_code_id)
);

-- The lookup the repair screen makes once a symptom is chosen.
CREATE INDEX IF NOT EXISTS idx_repair_code_links_error ON repair_code_links (error_code_id);

CREATE INDEX IF NOT EXISTS idx_repair_codes_vendor ON repair_codes (vendor_id, vendor_line, kind);

-- Migrations run as postgres, so postgres owns what they create while the app
-- connects as integra_rma. Learned the hard way when repair_codes itself was
-- created and the app could neither read nor write it.
ALTER TABLE    vendor_product_lines        OWNER TO integra_rma;
ALTER SEQUENCE vendor_product_lines_id_seq OWNER TO integra_rma;
ALTER TABLE    repair_code_links           OWNER TO integra_rma;

-- Rajo's mapping. Datacard is TCL's mobile-broadband dongle, which is what our
-- Routers category holds. Projector, Laptop and Accessory stay unmapped:
-- Integra does not service the first two, and Accessory is still undecided.
INSERT INTO vendor_product_lines (vendor_id, line, category_id)
SELECT v.id, m.line, c.id
  FROM vendors v
  CROSS JOIN (VALUES ('Mobile', 'Smartphones'),
                     ('Tablet PC', 'Tablets'),
                     ('Watch', 'Smartwatch'),
                     ('Datacard', 'Routers'),
                     ('Accessory', NULL),
                     ('Projector', NULL),
                     ('Laptop', NULL)) AS m(line, category_name)
  LEFT JOIN device_categories c ON c.name = m.category_name
 WHERE v.slug = 'tcl'
    ON CONFLICT (vendor_id, line) DO NOTHING;
