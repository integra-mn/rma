-- Which status changes are worth telling somebody about.
--
-- The workflow has thirteen statuses and most of them are internal
-- bookkeeping. Whether a step is worth a message is a workflow question, so it
-- belongs here beside the status rather than in a grid of channel settings —
-- and because statuses are admin-editable, a new one gets the flag for free.
--
-- How the message is delivered is the separate question, answered in
-- Podesavanja -> Komunikacija: which channels reach customers, which reach
-- partners. This column only says "this step matters to somebody outside".
--
-- Defaults to 0. Nothing starts sending because a column appeared; each status
-- is switched on deliberately.

ALTER TABLE rma_statuses
    ADD COLUMN IF NOT EXISTS notify SMALLINT NOT NULL DEFAULT 0;

ALTER TABLE repair_statuses
    ADD COLUMN IF NOT EXISTS notify SMALLINT NOT NULL DEFAULT 0;

-- The four a customer either has to act on or would otherwise be left
-- wondering about. Everything else stays off until somebody asks for it.
--   device_received  we have it
--   awaiting_approval  a decision is needed before work continues
--   repaired           come and collect it
--   unrepairable       a decision is needed about what happens to it
UPDATE rma_statuses
   SET notify = 1
 WHERE code IN ('device_received', 'awaiting_approval', 'repaired', 'unrepairable');
