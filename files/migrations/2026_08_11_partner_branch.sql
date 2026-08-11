-- The partner's own branch / office (poslovnica).
--
-- Not to be confused with locations, which are OUR service points. A partner
-- may send work from any of several of their own branches, and staff need to
-- know which one an RMA came from.

ALTER TABLE partners
  ADD COLUMN IF NOT EXISTS branch VARCHAR(150) DEFAULT NULL;
