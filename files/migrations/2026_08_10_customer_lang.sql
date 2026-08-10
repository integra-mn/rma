-- Language for everything the customer receives: the receipt email and the
-- printed receipt.
--
-- Defaults to 'me' — walk-in customers are Montenegrin unless someone says
-- otherwise — and is switchable per customer for the occasional foreign one.
--
-- Deliberately NOT the same as settings.default_lang, which is the starting
-- language for new STAFF accounts. Staff choose their own in Moj profil; that
-- choice must not change what a customer reads.

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS lang VARCHAR(2) NOT NULL DEFAULT 'me';

UPDATE customers SET lang = 'me' WHERE lang IS NULL OR lang = '';
