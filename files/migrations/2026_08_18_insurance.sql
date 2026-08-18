-- Insurance: the policy record and what the counter checks against.
--
-- Design and reasoning live in files/docs/OSIGURANJE.md. The short version:
--
--   product  what an insurer sells — a template, only there to save typing
--   policy   one customer's device, insured, for a period. THE RECORD THAT
--            COUNTS: samples vary, so whoever enters it corrects whatever the
--            paper says differently
--   claim    one event drawn against a policy; a policy may have several
--
-- Nothing here reaches an insurer's portal. Reporting is manual and stays
-- manual until somebody answers whether an API exists.

-- Covered items, admin-editable like RMA statuses, because "Limited" is not a
-- type — one sample covers the screen, another screen + battery + frame, and
-- theft is noted separately on the policy. Full and Limited are names people
-- use for common combinations, not rules the app can enforce.
CREATE TABLE IF NOT EXISTS insurance_coverage_items (
    id         SERIAL PRIMARY KEY,
    code       VARCHAR(40) NOT NULL UNIQUE,
    label      VARCHAR(100) NOT NULL,
    label_me   VARCHAR(100),
    sort_order SMALLINT DEFAULT 0,
    is_active  SMALLINT NOT NULL DEFAULT 1
);

INSERT INTO insurance_coverage_items (code, label, label_me, sort_order) VALUES
    ('screen',  'Screen',  'Ekran',    1),
    ('battery', 'Battery', 'Baterija', 2),
    ('frame',   'Frame',   'Ram',      3),
    ('liquid',  'Liquid damage', 'Tečnost', 4),
    ('theft',   'Theft',   'Kradja',   5),
    ('other',   'Other',   'Ostalo',   6)
ON CONFLICT (code) DO NOTHING;

CREATE TABLE IF NOT EXISTS insurers (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150),
    email          VARCHAR(150),
    phone          VARCHAR(30),
    portal_url     VARCHAR(255),
    -- How long after the incident a claim may be reported. Per insurer because
    -- it is their rule; 0 means no deadline is known yet.
    report_hours   INT NOT NULL DEFAULT 0,
    notes          TEXT,
    is_active      SMALLINT NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT NOW(),
    updated_at     TIMESTAMP DEFAULT NOW(),
    deleted_at     TIMESTAMP NULL
);

-- A template. Picking one pre-fills a policy; it is never consulted afterwards,
-- because the policy carries its own terms.
CREATE TABLE IF NOT EXISTS insurance_products (
    id                SERIAL PRIMARY KEY,
    insurer_id        INT NOT NULL REFERENCES insurers(id),
    name              VARCHAR(150) NOT NULL,
    -- Comma-separated coverage item codes, same shape as rma_statuses.roles.
    coverage          VARCHAR(255),
    participation_pct NUMERIC(5,2) NOT NULL DEFAULT 0,
    claims_allowed    SMALLINT NOT NULL DEFAULT 1,
    period_months     SMALLINT NOT NULL DEFAULT 12,
    is_active         SMALLINT NOT NULL DEFAULT 1,
    created_at        TIMESTAMP DEFAULT NOW(),
    updated_at        TIMESTAMP DEFAULT NOW(),
    deleted_at        TIMESTAMP NULL
);

-- The record everything is checked against.
--
-- Renewal does not exist: insurers issue another policy, so each is a new row
-- and last year's claims stay attached to last year's policy. A device
-- therefore accumulates policies, and the one that applies is the one whose
-- period contains the INCIDENT date — never simply the newest.
--
-- Identified by IMEI or serial like everything else about a device, with
-- device_id kept only as a convenience: one handset can hold several device
-- rows, and the paper policy names the number, not our row.
CREATE TABLE IF NOT EXISTS insurance_policies (
    id                SERIAL PRIMARY KEY,
    insurer_id        INT NOT NULL REFERENCES insurers(id),
    product_id        INT NULL REFERENCES insurance_products(id),
    policy_number     VARCHAR(60) NOT NULL,
    customer_id       INT NULL REFERENCES customers(id),
    partner_id        INT NULL REFERENCES partners(id),
    device_id         INT NULL REFERENCES devices(id),
    imei              VARCHAR(20),
    serial_number     VARCHAR(100),
    starts_on         DATE NOT NULL,
    ends_on           DATE NOT NULL,
    coverage          VARCHAR(255),
    participation_pct NUMERIC(5,2) NOT NULL DEFAULT 0,
    claims_allowed    SMALLINT NOT NULL DEFAULT 1,
    notes             TEXT,
    created_at        TIMESTAMP DEFAULT NOW(),
    updated_at        TIMESTAMP DEFAULT NOW(),
    deleted_at        TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_policies_imei   ON insurance_policies (imei);
CREATE INDEX IF NOT EXISTS idx_policies_serial ON insurance_policies (serial_number);

-- One event against a policy. The lifecycle screen comes later; what exists
-- here is what the intake check needs — how many claims a policy has used.
--
--   new       recorded, not yet reported to the insurer
--   reported  in the insurer's portal, waiting
--   more_info the insurer asked us for something (the state everyone forgets)
--   approved  work authorised
--   refused   declined — costs the customer no allowance
--   paid      settled
--   closed    finished
CREATE TABLE IF NOT EXISTS insurance_claims (
    id             SERIAL PRIMARY KEY,
    policy_id      INT NOT NULL REFERENCES insurance_policies(id),
    rma_id         INT NULL REFERENCES rma_requests(id),
    status         VARCHAR(20) NOT NULL DEFAULT 'new',
    damage_code    VARCHAR(40),
    incident_date  DATE,
    report_due_at  TIMESTAMP NULL,
    reported_at    TIMESTAMP NULL,
    reported_by    INT NULL REFERENCES users(id),
    claim_number   VARCHAR(60),
    approved_amount     NUMERIC(10,2),
    participation_amount NUMERIC(10,2),
    decided_at     TIMESTAMP NULL,
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT NOW(),
    updated_at     TIMESTAMP DEFAULT NOW(),
    deleted_at     TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_claims_policy ON insurance_claims (policy_id);
CREATE INDEX IF NOT EXISTS idx_claims_rma    ON insurance_claims (rma_id);

-- What the case itself records. The incident date is not the intake date and
-- decides everything: a device damaged on the 10th and brought in on the 20th,
-- on a policy ending the 15th, is covered.
ALTER TABLE rma_requests
    ADD COLUMN IF NOT EXISTS incident_date DATE NULL,
    ADD COLUMN IF NOT EXISTS damage_code   VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS policy_id     INT NULL REFERENCES insurance_policies(id);
