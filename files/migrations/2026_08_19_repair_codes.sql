-- Vendor error and resolution codes, kept per brand and per device type.
--
-- Apple GSX and TCL SmartCare both hand back coded answers — a reason the call
-- failed, or the outcome they assigned the repair. Until now those arrived as
-- raw strings in vendor_sync_log and meant nothing to anyone reading the case.
--
-- Two kinds in one table, told apart by `kind`:
--   error       — what went wrong (bad serial, no coverage, refused)
--   resolution  — what was done (replaced, repaired, no fault, returned)
--
-- Scoped by brand and device type rather than by vendor: a technician holding
-- an iPhone thinks "Apple, phone", not "GSX". Both columns are nullable and a
-- NULL means "all" — a code with neither set is offered on every device, which
-- is what the general ones (customer declined, out of warranty) need to be.
--
-- Empty on purpose. Nobody has the real lists yet — Apple's are behind the AASP
-- portal, TCL's behind SmartCare — and inventing codes that look official is
-- worse than having none: somebody would send one to a vendor.

CREATE TABLE IF NOT EXISTS repair_codes (
    id           SERIAL PRIMARY KEY,
    kind         VARCHAR(20)  NOT NULL,
    code         VARCHAR(60)  NOT NULL,
    label        VARCHAR(160) NOT NULL,
    label_me     VARCHAR(160),
    -- What the code means and, for errors, what the technician should do about
    -- it. Shown under the dropdown once a code is picked.
    note         TEXT,
    brand_id     INTEGER REFERENCES device_brands(id),
    category_id  INTEGER REFERENCES device_categories(id),
    sort_order   SMALLINT DEFAULT 0,
    is_active    SMALLINT NOT NULL DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP,
    deleted_at   TIMESTAMP
);

-- The lookup the repair screen makes on every open: this kind, this brand or
-- any brand, this type or any type.
CREATE INDEX IF NOT EXISTS idx_repair_codes_pick
    ON repair_codes (kind, brand_id, category_id, is_active);

-- The technician's two picks, beside the two boxes they already type into.
-- Nullable: a code is an aid, never a requirement, and most repairs before
-- today have none.
ALTER TABLE repair_jobs ADD COLUMN IF NOT EXISTS error_code_id      INTEGER REFERENCES repair_codes(id);
ALTER TABLE repair_jobs ADD COLUMN IF NOT EXISTS resolution_code_id INTEGER REFERENCES repair_codes(id);

-- Migrations are run as postgres, which then owns whatever they create — and
-- the app connects as integra_rma, which would be refused on its own new table.
-- The core tables (rma_statuses, repair_jobs) are owned by the app user; this
-- follows them. Both the table and the sequence SERIAL made behind it, or
-- reading works and the first insert fails.
ALTER TABLE    repair_codes        OWNER TO integra_rma;
ALTER SEQUENCE repair_codes_id_seq OWNER TO integra_rma;
