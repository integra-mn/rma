-- Deleting an evidence photo becomes a permission of its own.
--
-- Until now `/evidence/{id}/delete` asked only for a login: no role test, no
-- time limit, and a photo tied to neither an RMA nor a repair job skipped even
-- the location check. Partners were kept out by accident rather than by rule —
-- they have no Integra location, so the scope test failed for them.
--
-- The tick below is the "who". The "how long" is the evidence_delete_hours
-- setting (default 24), because a window is a number and the permission matrix
-- is ticks. Super Admin passes both, as it does everywhere.
--
-- Reception, technician and admin keep what they can do today. Partner is left
-- out deliberately: a partner never deletes evidence of a device's condition.
-- Super Admin is never stored — it bypasses the matrix entirely.
--
-- Written delete-then-insert so it can be run twice without tripping the
-- (role, module, action) primary key.

DELETE FROM role_permissions WHERE module = 'evidence' AND action = 'delete';

INSERT INTO role_permissions (role, module, action) VALUES
  ('admin',      'evidence', 'delete'),
  ('reception',  'evidence', 'delete'),
  ('technician', 'evidence', 'delete');
