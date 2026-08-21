-- One status list, step 2 of 3: the repair jobs move onto it.
--
-- Step 1 gave rma_statuses the columns to serve both desks and filled them in.
-- Nothing read them, because repair_jobs still pointed at repair_statuses - a
-- second vocabulary for the same case, and the reason Popravke offered "U toku"
-- while Administracija offered "U fazi dijagnostike". Rajo: the statuses in
-- Administracija are the correct ones, the old list goes.
--
-- Mapping, agreed 2026-08-21:
--   Na cekanju      -> U fazi dijagnostike
--   U toku          -> U fazi dijagnostike
--   Pauzirano       -> Ceka rezervni dio
--   Zavrseno        -> Uredjaj popravljen
--   Otkazano        -> Reklamacija otkazana
--   Nema kvara      -> Kvar nije utvrdjen
--
-- Na cekanju is the one that is not a translation. The word says "waiting", but
-- the app assigned it to every job the moment it was created, and all five jobs
-- holding it have never been started - so "waiting for a part" would be a claim
-- nobody made. They go where they actually are: with Servis, unresolved.

-- The old id beside the new one, so the update is a join rather than six
-- statements, and so the rehearsal can print exactly what moved.
CREATE TEMP TABLE status_map AS
SELECT old.id AS old_id, new.id AS new_id, old.code AS old_code, new.code AS new_code
  FROM repair_statuses old
  JOIN rma_statuses new ON new.code = CASE old.code
        WHEN 'pending'        THEN 'in_diagnosis'
        WHEN 'in_progress'    THEN 'in_diagnosis'
        WHEN 'on_hold'        THEN 'awaiting_parts'
        WHEN 'completed'      THEN 'repaired'
        WHEN 'cancelled'      THEN 'cancelled'
        WHEN 'no_fault_found' THEN 'no_fault'
  END;

-- Every job must have a home before the foreign key moves, or the ALTER fails
-- and takes the transaction with it. Better that than a silent orphan.
DO $$
DECLARE stranded INT;
BEGIN
    SELECT COUNT(*) INTO stranded
      FROM repair_jobs j
     WHERE j.deleted_at IS NULL
       AND j.status_id NOT IN (SELECT old_id FROM status_map);
    IF stranded > 0 THEN
        RAISE EXCEPTION 'Cannot migrate: % repair job(s) hold a status with no mapping', stranded;
    END IF;
END $$;

-- The old constraint goes first. It points at repair_statuses, so the very
-- first row rewritten to an rma_statuses id would violate it and take the whole
-- transaction down - which is exactly what the rehearsal did.
ALTER TABLE repair_jobs DROP CONSTRAINT IF EXISTS repair_jobs_status_id_fkey;

UPDATE repair_jobs j
   SET status_id = m.new_id
  FROM status_map m
 WHERE j.status_id = m.old_id;

-- And the new one goes on afterwards, which also re-checks every row.
ALTER TABLE repair_jobs
    ADD CONSTRAINT repair_jobs_status_id_fkey
    FOREIGN KEY (status_id) REFERENCES rma_statuses(id);

DROP TABLE status_map;

-- repair_statuses itself stays where it is, unread, until Rajo has lived with
-- this for a week. Dropping it is step 3, and it is the one part of this that
-- cannot be undone by pointing the code back.
