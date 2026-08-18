-- Bez kvara: a case outcome of its own.
--
-- A technician who finds nothing wrong closes the repair job as "Nema kvara",
-- and until now the case itself stayed wherever it was — 52169 sat at "Na
-- dijagnostici" with its only job finished. The dashboard, which read the jobs
-- rather than the case, listed it as ready for collection while the screen
-- said it was still being diagnosed.
--
-- Rajo's rule: say the honest thing. Popravljeno would have been a lie about a
-- device that was never broken, so the outcome gets its own status.
--
-- Not terminal: the device is still on the shelf and must go back, which means
-- Otpremljeno and then Zatvoreno still follow. Technician's to set, like the
-- other workshop outcomes. Grey, because nothing happened to the device.

-- Sits before Popravljeno, not after. The auto-advance in repairController is
-- forward-only by sort_order: a device parked at Bez kvara that turns out to
-- have a fault after all can still move on to Popravljeno, whereas the reverse
-- ordering would strand it. The other direction stays blocked, which is the
-- harmless one — a repaired device is not later re-labelled fault-free.
UPDATE rma_statuses SET sort_order = sort_order + 1 WHERE sort_order >= 9;

INSERT INTO rma_statuses (code, label, label_me, color, sort_order, is_terminal, is_system, notify, roles, can_recur)
SELECT 'no_fault', 'No fault found', 'Bez kvara', '#6E6D68', 9, 0, 1, notify, 'technician', 1
  FROM rma_statuses WHERE code = 'repaired'
    ON CONFLICT (code) DO NOTHING;

-- The cases already stranded by the missing mapping. Narrow on purpose: the
-- workshop has finished with every job on the case, at least one of them found
-- no fault, and the case never moved past diagnosis. On 2026-08-18 that is
-- 52169 alone. The auto-advance only fires when a job changes, so without this
-- these cases would sit where they are until somebody noticed them again.
CREATE TEMP TABLE stranded AS
SELECT r.id
  FROM rma_requests r
  JOIN rma_statuses rs ON rs.id = r.status_id
 WHERE r.deleted_at IS NULL
   AND rs.sort_order < 9
   AND EXISTS (SELECT 1 FROM repair_jobs j
                 JOIN repair_statuses js ON js.id = j.status_id
                WHERE j.rma_id = r.id AND j.deleted_at IS NULL AND js.code = 'no_fault_found')
   AND NOT EXISTS (SELECT 1 FROM repair_jobs j
                     JOIN repair_statuses js ON js.id = j.status_id
                    WHERE j.rma_id = r.id AND j.deleted_at IS NULL AND js.is_terminal = 0);

UPDATE rma_requests SET status_id = (SELECT id FROM rma_statuses WHERE code = 'no_fault')
 WHERE id IN (SELECT id FROM stranded);

-- Recorded like any other status change, so the case history does not simply
-- jump. changed_by stays NULL: nobody clicked this.
INSERT INTO rma_status_history (rma_id, status_id, changed_by, note)
SELECT id, (SELECT id FROM rma_statuses WHERE code = 'no_fault'), NULL, 'history.auto_sync'
  FROM stranded;

DROP TABLE stranded;
