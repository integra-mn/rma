-- Partner potvrdio prijem: the partner's own step in the flow.
--
-- Rajo's argument, and it is the right one: the app should close a case after
-- the last human step, whoever takes it. For a partner who does not confirm,
-- that step is Otpremljeno and Zatvoreno follows by itself. For a partner who
-- does confirm, the last human step is the confirmation - so it should be a
-- status like any other, with Zatvoreno following it the same way.
--
-- Without this the confirmation was a note attached to Zatvoreno, which made
-- the two routes look different in the timeline when they are the same shape.
--
-- Set by the partner alone. Recepcija and Servis never see it in their
-- dropdowns, because it is not theirs to claim on a partner's behalf.

UPDATE rma_statuses SET sort_order = sort_order + 1 WHERE sort_order >= 14;

INSERT INTO rma_statuses
    (code, label, label_me, color, sort_order, is_terminal, is_terminal_job,
     is_system, notify, roles, can_recur, applies_to)
SELECT 'partner_confirmed', 'Partner confirmed receipt', 'Partner potvrdio prijem',
       '#378ADD', 14,
       -- Not final: Zatvoreno follows within the same request.
       0, 0, 1,
       -- No message: the partner has just told us, so telling them back is noise.
       0,
       'partner', 0, 'rma'
 WHERE NOT EXISTS (SELECT 1 FROM rma_statuses WHERE code = 'partner_confirmed');
