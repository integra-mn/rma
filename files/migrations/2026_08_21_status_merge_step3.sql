-- One status list, step 3 of 3: the old table goes.
--
-- repair_statuses held the second vocabulary - Na cekanju, U toku, Zavrseno -
-- that made Popravke disagree with Administracija. Step 2 moved every job onto
-- rma_statuses and repointed the foreign key; the table has sat unread since.
--
-- Checked before running: no foreign key references it, no row anywhere holds
-- one of its ids, and the last caller (status_label) was changed and deployed
-- first. That order mattered - status_label catches a failed lookup by blanking
-- its whole map, so dropping the table under the old code would have turned
-- every status badge in the app into a raw code without raising anything.
--
-- This is the one step that cannot be undone by pointing the code back. The
-- day's pg_dump holds the table if it is ever wanted.

DROP TABLE IF EXISTS repair_statuses;
