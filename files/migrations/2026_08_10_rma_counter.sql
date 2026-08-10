-- Atomic RMA number counter.
--
-- Replaces "SELECT COUNT(*) FROM rma_requests ... + 1", which had three faults:
--   * two RMAs created at the same moment read the same count and collided
--   * permanently deleting an RMA lowered the count and reused a number
--   * the count was per-location while the format need not contain {LOC},
--     so two locations produced identical numbers
--
-- scope is either 'global' (counter never resets) or the four-digit year
-- (counter restarts each January). Which one is used depends on the
-- rma_number_reset_yearly setting.
--
-- next_value is the number that will be handed out NEXT.

CREATE TABLE IF NOT EXISTS rma_counters (
  scope      VARCHAR(20) NOT NULL,
  next_value INT NOT NULL DEFAULT 1,
  PRIMARY KEY (scope)
);

-- Seed from whatever already exists so numbering never goes backwards on an
-- installation that has RMAs from the old scheme.
INSERT INTO rma_counters (scope, next_value)
SELECT 'global', COALESCE(COUNT(*), 0) + 1 FROM rma_requests
WHERE NOT EXISTS (SELECT 1 FROM rma_counters WHERE scope = 'global');
