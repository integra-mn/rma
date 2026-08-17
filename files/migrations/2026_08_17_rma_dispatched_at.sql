-- When the device left Integra.
--
-- The first step of the repeated-repair warning: a device coming back soon
-- after it went out means somebody should read what was done last time. The
-- clock starts at dispatch, not at collection — a partner may hold a phone for
-- a month and an owner may not switch it on, and neither is Integra's doing.
-- Dispatch is also the last moment Integra actually observes.
--
-- `completed_at` already exists on the table but nothing has ever written to
-- it: 0 of 28 cases carry one. It is left alone rather than repurposed, since
-- "work finished" and "device gone" are not the same day.

-- TIMESTAMP, not DATETIME: this file is run through psql on the Postgres
-- container, and db_translate() only rewrites queries, never DDL. schema.sql
-- keeps DATETIME for the MySQL fallback, as it does for every other date.
ALTER TABLE rma_requests
    ADD COLUMN IF NOT EXISTS dispatched_at TIMESTAMP NULL;

-- Backfill. Both sources are already recorded, so existing cases get their
-- date rather than the feature starting blank:
--
--   1. the first outbound shipment carrying a dispatch date;
--   2. otherwise the first time the case entered Otpremljeno, or failing that
--      Zatvoreno — a counter handover leaves no shipment behind.
--
-- Otkazano and Nepopravljivo are not treated as leaving: a cancelled case may
-- never have received the device, and an unrepairable one may still be on a
-- shelf awaiting a decision.

UPDATE rma_requests r
   SET dispatched_at = COALESCE(
       (SELECT MIN(sh.dispatched_at) FROM delivery_shipments sh
         WHERE sh.rma_id = r.id AND sh.direction = 'outbound'
           AND sh.dispatched_at IS NOT NULL),
       (SELECT MIN(h.created_at) FROM rma_status_history h
          JOIN rma_statuses s ON s.id = h.status_id
         WHERE h.rma_id = r.id AND s.code = 'dispatched'),
       (SELECT MIN(h.created_at) FROM rma_status_history h
          JOIN rma_statuses s ON s.id = h.status_id
         WHERE h.rma_id = r.id AND s.code = 'closed')
   )
 WHERE dispatched_at IS NULL;
