-- One status list, step 1 of 3: the columns, describing today exactly.
--
-- Rajo's decision: Recepcija and Servis should read from one list rather than
-- two, because the two lists say the same things in different words — "U toku"
-- against "Na dijagnostici", "Zavrseno" against "Popravljeno" — and nothing on
-- screen explains why the same case has two vocabularies.
--
-- This step adds the columns and fills them so the app behaves exactly as it
-- does now. Nothing reads them yet. Step 2 repoints repair_jobs at this table
-- and retires repair_statuses; step 3 drops it once a week has passed.

-- Where a status may be used. Splitting this out is what lets one list serve
-- both desks: Otpremljeno belongs to the counter alone, Na popravci to the
-- bench, and Nema kvara to both.
ALTER TABLE rma_statuses
    ADD COLUMN IF NOT EXISTS applies_to VARCHAR(10) NOT NULL DEFAULT 'rma';

-- Zavrsni, asked of the bench rather than of the case — and the reason one
-- flag could not survive the merge. Popravljeno finishes the WORK while the
-- case carries on to Otpremljeno and Zatvoreno; a single is_terminal would
-- have to lie to one of them. Two ticks, two honest answers.
ALTER TABLE rma_statuses
    ADD COLUMN IF NOT EXISTS is_terminal_job SMALLINT NOT NULL DEFAULT 0;

-- Shared by both desks. Each of these is where a repair status lands today
-- under sync_rma_from_repair, so the mapping is already the app's own:
--   Na cekanju -> Uredjaj primljen      Pauzirano -> Ceka se dio
--   U toku     -> Na dijagnostici       Zavrseno  -> Popravljeno
--   Nema kvara -> Nema kvara            Otkazano  -> Otkazano
-- Na popravci and Ceka se odobrenje join them: the bench has no word for
-- either today, which is why "U toku" has to stand for both diagnosing and
-- repairing.
UPDATE rma_statuses SET applies_to = 'both'
 WHERE code IN ('device_received', 'in_diagnosis', 'in_repair', 'awaiting_parts',
                'awaiting_approval', 'no_fault', 'repaired', 'cancelled');

-- Counter only: a repair job is never submitted, never dispatched, never closed.
UPDATE rma_statuses SET applies_to = 'rma'
 WHERE code IN ('submitted', 'awaiting_device', 'dispatched', 'closed');

-- Nepopravljivo stays case-only for now. It is a technician's verdict, so it
-- may well belong on the bench too — left for Rajo to decide rather than
-- guessed at, since it is also the one terminal status a device survives.
UPDATE rma_statuses SET applies_to = 'rma' WHERE code = 'unrepairable';

-- Exactly what repair_statuses.is_terminal says today: Zavrseno, Otkazano and
-- Nema kvara end the work, the rest do not.
UPDATE rma_statuses SET is_terminal_job = 1
 WHERE code IN ('repaired', 'cancelled', 'no_fault');

-- Partner joins Recepcija and Servis on the one status the portal produces.
-- No behaviour follows today: partners hold rma.view and rma.create but not
-- rma.edit, so they never reach a status dropdown. The tick records the rule
-- truthfully, which is the point of having the column at all.
UPDATE rma_statuses
   SET roles = CASE WHEN roles IS NULL OR trim(roles) = '' THEN 'partner'
                    ELSE roles || ',partner' END
 WHERE code = 'submitted' AND (roles IS NULL OR roles NOT LIKE '%partner%');
