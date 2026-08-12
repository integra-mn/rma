-- Status-history notes written by the app become translation keys.
--
-- Istorija showed "RMA created" and "Auto-updated from repair job
-- (job_created)" in English regardless of the reader's language, because the
-- sentence was baked into the row when the status changed. Storing a key
-- instead lets the page render it in whichever language the viewer uses — and
-- lets the same row read correctly for staff in Montenegrin and for a partner
-- in English.
--
-- The trailing code in brackets goes too. It named the internal trigger
-- (job_created, job_completed …) and meant nothing to anyone reading the
-- history; the audit_log row still records it for anyone who needs it.
--
-- Notes typed by a person are untouched — history_note() prints anything that
-- is not a history.* key verbatim.

UPDATE rma_status_history
   SET note = 'history.created'
 WHERE note IN ('RMA created.', 'RMA created');

UPDATE rma_status_history
   SET note = 'history.auto_sync'
 WHERE note LIKE 'Auto-updated from repair job%';
