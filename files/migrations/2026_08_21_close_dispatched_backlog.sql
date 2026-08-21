-- The cases that were dispatched and never closed.
--
-- Otpremljeno now closes a case by itself, but that only fires on a new
-- dispatch. Nine cases were already sitting there - dispatched between 17 and
-- 21 August 2026, one as long ago as a week - because the manual step after
-- otprema was reliably forgotten. That is the backlog the new rule was written
-- for, and it counts against open work every day it stays.
--
-- Narrow on purpose: dispatched, not deleted, and belonging to a partner who
-- does not confirm receipt (or to no partner at all). A partner who works the
-- portal is left alone - their cases are legitimately waiting on somebody.
--
-- Safe to re-run: after the first pass nothing matches.

CREATE TEMP TABLE to_close AS
SELECT r.id
  FROM rma_requests r
  JOIN rma_statuses s ON s.id = r.status_id
  LEFT JOIN partners p ON p.id = r.partner_id
 WHERE r.deleted_at IS NULL
   AND s.code = 'dispatched'
   AND COALESCE(p.confirms_receipt, 0) = 0;

UPDATE rma_requests
   SET status_id = (SELECT id FROM rma_statuses WHERE code = 'closed')
 WHERE id IN (SELECT id FROM to_close);

-- Recorded like any other status change, and said plainly: this was done
-- afterwards, by nobody, because the device had already gone.
INSERT INTO rma_status_history (rma_id, status_id, changed_by, note)
SELECT id, (SELECT id FROM rma_statuses WHERE code = 'closed'), NULL, 'history.closed_backfill'
  FROM to_close;

DROP TABLE to_close;
