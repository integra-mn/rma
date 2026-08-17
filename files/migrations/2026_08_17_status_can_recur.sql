-- Which statuses a case can come back to, and Nacrt's removal.
--
-- The dropdown offered every status a desk owns, whether or not the case could
-- honestly return to it. Rajo's rule: hide the ones that cannot recur. Not
-- "hide anything already used" — a repair waits for a part, resumes, waits for
-- a second part, and Ceka se dio has to still be there. So it is a property of
-- the status, ticked in Administracija -> Statusi beside Obavjestenje and
-- Postavlja, and not a flow map in code.
--
-- Defaults to 1, can recur, so nothing disappears because a column appeared.
-- Every status keeps behaving exactly as it does today until somebody unticks
-- one deliberately.

ALTER TABLE rma_statuses
    ADD COLUMN IF NOT EXISTS can_recur SMALLINT NOT NULL DEFAULT 1;

-- Nacrt goes. Rajo: it earns nothing that Podneseno does not already say.
-- Safe to delete rather than hide: no case is in it and no case has ever been
-- in it (checked on the server, 2026-08-17 — 0 rows in rma_requests and 0 in
-- rma_status_history). The foreign key would refuse anyway if that changed
-- between then and running this.
--
-- portalController still names 'draft' beside 'submitted' when deciding whether
-- a partner may dispatch. That list keeps working on 'submitted' alone, and is
-- left as it is so an older record could not be locked out.
DELETE FROM rma_statuses WHERE code = 'draft';
