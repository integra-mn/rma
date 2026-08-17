-- "Ceka se uredjaj" becomes "Uredjaj u dolasku".
--
-- Rajo's wording: the case is not waiting on the customer to do something, the
-- device is on its way. Montenegrin only — the English "Awaiting device" says
-- the same thing and is left alone.
--
-- Labels live on the row, so this is data. The code is untouched: everything
-- keys off `awaiting_device`, and status_label() reads label_me from here.

UPDATE rma_statuses
   SET label_me = 'Uredjaj u dolasku'
 WHERE code = 'awaiting_device';
