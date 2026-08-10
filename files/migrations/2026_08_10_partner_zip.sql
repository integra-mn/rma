-- The "Novi partner" form has always had a postal-code field, but the column
-- was never created and the controller never read it — anything typed there was
-- silently discarded on save.
--
-- Named zip_code to match the form field and the customers table (locations use
-- postal_code; that inconsistency predates this and is left alone).

ALTER TABLE partners
  ADD COLUMN IF NOT EXISTS zip_code VARCHAR(30) DEFAULT NULL;
