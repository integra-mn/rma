-- Whether this partner confirms receipt before the case closes.
--
-- It began as one setting for the whole app, which assumed every partner uses
-- the portal. Rajo: there is no guarantee they will. A partner who never signs
-- in would leave every one of their cases parked at Otpremljeno forever,
-- waiting for a confirmation nobody was ever going to give.
--
-- So it belongs to the partner, beside notify_customer, which is the same kind
-- of fact: something this particular company does or does not do.
--
-- Default 0. Off means Otpremljeno closes the case outright, which is what
-- happens today and what most partners will want; ticking it is a deliberate
-- statement that this partner works the portal.

ALTER TABLE partners
    ADD COLUMN IF NOT EXISTS confirms_receipt SMALLINT NOT NULL DEFAULT 0;
