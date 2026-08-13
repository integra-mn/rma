-- Who gets told about a partner's RMA.
--
-- Devices arriving through a partner have two interested parties: the partner
-- who brought it and the end user who owns it. Until now only the end user was
-- ever messaged, and the partner's own email and phone — already on the table
-- — were never used for it. Some partners would rather be the single point of
-- contact with their own customer.
--
-- The rule this column completes: an RMA with a partner always notifies the
-- partner, and notifies the end user as well only when this is on. An RMA with
-- no partner is a walk-in and always notifies the customer, untouched by any of
-- this.
--
-- Defaults to 1, which is what happens today. Nobody stops receiving messages
-- because a column appeared; switching a partner to 0 is a deliberate act.

ALTER TABLE partners
    ADD COLUMN IF NOT EXISTS notify_customer SMALLINT NOT NULL DEFAULT 1;
