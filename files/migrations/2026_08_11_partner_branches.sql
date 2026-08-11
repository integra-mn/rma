-- Partner branches (poslovnice) as real rows, so RMAs can be counted per branch.
--
-- Replaces the free-text partners.branch added earlier the same day: that was a
-- single attribute of the partner, which cannot answer "how many RMAs came from
-- partner X's branch Y" — and free text would have made "Podgorica", "PG" and
-- "podgorica" three different branches in any report.
--
-- Nothing to migrate: no partner rows existed when this was introduced.
--
-- NOTE (locations vs branches): locations are OUR service points; branches
-- belong to the partner. An RMA carries both — where we handled it, and which
-- of their offices sent it.

CREATE TABLE IF NOT EXISTS partner_branches (
  id         SERIAL PRIMARY KEY,
  partner_id INT NOT NULL,
  name       VARCHAR(150) NOT NULL,
  city       VARCHAR(100),
  phone      VARCHAR(30),
  is_active  SMALLINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT NOW(),
  deleted_at TIMESTAMP DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_pb_partner ON partner_branches (partner_id);

-- Which branch an RMA came from. Nullable: walk-in customers have no partner,
-- and a partner may not have branches recorded.
ALTER TABLE rma_requests
  ADD COLUMN IF NOT EXISTS partner_branch_id INT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_rma_partner_branch ON rma_requests (partner_branch_id);

-- The free-text column is superseded. Dropped rather than left behind: two
-- places holding "the branch" is how they drift apart.
ALTER TABLE partners DROP COLUMN IF EXISTS branch;
