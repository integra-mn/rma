-- Phase 1 logistics builds on the pre-existing `couriers` and
-- `delivery_shipments` tables. Additions:
--   * delivery_shipments.cost  — optional courier cost (billing is not enabled
--     yet, but we record it so charging can be turned on later);
--   * delivery_shipments.notes — free-text note per shipment;
--   * partners.default_courier_id — the process usually starts from a partner,
--     so each partner can have a preferred courier that pre-fills new shipments.
-- Also seeds a few starter couriers (edit under Administration → Couriers).

ALTER TABLE couriers ADD COLUMN phone VARCHAR(60) DEFAULT NULL;

ALTER TABLE delivery_shipments ADD COLUMN cost  DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE delivery_shipments ADD COLUMN notes VARCHAR(500)  DEFAULT NULL;

ALTER TABLE partners ADD COLUMN default_courier_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE partners ADD FOREIGN KEY (default_courier_id) REFERENCES couriers(id) ON DELETE SET NULL;

INSERT INTO couriers (name, slug, tracking_url, is_active) VALUES
  ('Pošta Crne Gore', 'posta-cg',  'https://www.postacg.me/track?id={tracking}',       1),
  ('DHL',             'dhl',       'https://www.dhl.com/track?tracking-id={tracking}', 1),
  ('Ručna dostava',   'in-person', NULL, 1);
