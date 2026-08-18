-- One phone had four rows. Remove the empty ones.
--
-- The intake match is an offer — the green "Use this device" button — and a
-- case saved without pressing it created another row for the same handset.
-- IMEI 359168420215834 collected four that way, one carrying case 52152 and
-- three carrying nothing at all.
--
-- Only orphans go: a row is deleted when it shares an IMEI or a serial with a
-- row that is kept, and no RMA points at it. Nothing is repointed and no case
-- changes hands, so this cannot alter what any screen shows — the rows being
-- removed are reachable from nowhere.
--
-- A duplicate that DOES carry cases is left exactly where it is. It is not a
-- problem to solve here: device history is looked up by IMEI or serial
-- (helpers/device_history.php), so cases spread across two rows are gathered
-- either way. Merging them would mean rewriting rma_requests.device_id on live
-- records to fix something already handled.
--
-- rma_requests.device_id is the only foreign key into devices; everything else
-- reads through a join.

DELETE FROM devices d
 WHERE NOT EXISTS (
         SELECT 1 FROM rma_requests r WHERE r.device_id = d.id
       )
   AND EXISTS (
         SELECT 1 FROM devices k
          WHERE k.id <> d.id
            AND (
                  (d.imei IS NOT NULL AND d.imei <> '' AND k.imei = d.imei)
               OR (d.serial_number IS NOT NULL AND d.serial_number <> ''
                   AND k.serial_number = d.serial_number)
            )
            -- Keep exactly one of each group: the row with cases, or failing
            -- that the lowest id. Anything else in the group is an orphan.
            AND (
                  EXISTS (SELECT 1 FROM rma_requests r2 WHERE r2.device_id = k.id)
               OR k.id < d.id
            )
       );
