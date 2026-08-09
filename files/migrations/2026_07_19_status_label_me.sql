-- Add a Montenegrin label to the status tables so translations are editable in
-- Administration -> Statuses (instead of living only in the language files).

ALTER TABLE rma_statuses    ADD COLUMN label_me VARCHAR(100) NULL AFTER label;
ALTER TABLE repair_statuses ADD COLUMN label_me VARCHAR(100) NULL AFTER label;

UPDATE rma_statuses SET label_me = CASE code
  WHEN 'draft'             THEN 'Nacrt'
  WHEN 'submitted'         THEN 'Podneseno'
  WHEN 'awaiting_device'   THEN 'Čeka se uredjaj'
  WHEN 'device_received'   THEN 'Uredjaj primljen'
  WHEN 'in_diagnosis'      THEN 'Na dijagnostici'
  WHEN 'awaiting_parts'    THEN 'Čeka se dio'
  WHEN 'in_repair'         THEN 'Na popravci'
  WHEN 'awaiting_approval' THEN 'Čeka se odobrenje'
  WHEN 'repaired'          THEN 'Popravljeno'
  WHEN 'dispatched'        THEN 'Otpremljeno'
  WHEN 'closed'            THEN 'Zatvoreno'
  WHEN 'cancelled'         THEN 'Otkazano'
  WHEN 'unrepairable'      THEN 'Nepopravljivo'
  ELSE label_me
END
WHERE label_me IS NULL OR label_me = '';

UPDATE repair_statuses SET label_me = CASE code
  WHEN 'pending'     THEN 'Na čekanju'
  WHEN 'in_progress' THEN 'U toku'
  WHEN 'on_hold'     THEN 'Pauzirano'
  WHEN 'completed'   THEN 'Završeno'
  WHEN 'cancelled'   THEN 'Otkazano'
  ELSE label_me
END
WHERE label_me IS NULL OR label_me = '';
