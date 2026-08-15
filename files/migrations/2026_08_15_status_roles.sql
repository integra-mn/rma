-- Which desk owns which RMA status.
--
-- The dropdown on the RMA page offered all thirteen statuses to anyone with
-- rma.edit, so reception could set "Na popravci" and a technician could set
-- "Zatvoreno". It matters more since notifications went live: four statuses
-- message the customer, so reception could mark a case Popravljeno and text
-- somebody to come and collect a device still on the bench.
--
-- Same shape as the `notify` column beside it — a property of the status, not
-- a grid of settings somewhere else, so a status added tomorrow carries its
-- own answer.
--
-- Comma-separated role codes ('reception', 'technician'). Empty or NULL means
-- every role, which is deliberately the default: a new status is usable until
-- somebody narrows it. Admin and Super Admin bypass the list entirely, so an
-- absent technician never leaves the counter stuck.
--
-- Repair statuses get no such column. `repair_statuses` is the state of the
-- bench job, which only technicians and admins touch in the first place.

ALTER TABLE rma_statuses
    ADD COLUMN IF NOT EXISTS roles VARCHAR(120) NULL;

-- The counter: taking the case in, and handing the device back out.
UPDATE rma_statuses
   SET roles = 'reception'
 WHERE code IN ('draft', 'submitted', 'awaiting_device', 'device_received',
                'dispatched', 'closed', 'cancelled');

-- The bench: everything between those two, plus the two verdicts only a
-- technician can reach — Popravljeno and Nepopravljivo.
--
-- Popravljeno sits here rather than with reception because it is the
-- technician saying the work is done; reception then moves it on to
-- Otpremljeno or Zatvoreno at the handover. It is also set automatically by
-- repairController::sync_rma_from_repair() when a repair job completes, so
-- the counter is never waiting on somebody to remember.
UPDATE rma_statuses
   SET roles = 'technician'
 WHERE code IN ('in_diagnosis', 'awaiting_parts', 'in_repair',
                'awaiting_approval', 'repaired', 'unrepairable');
