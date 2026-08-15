-- Wording for the notifications that already go out.
--
-- notification_templates has been in the schema all along and empty all along,
-- so Podesavanja -> Sabloni rendered a screen with nothing in it and nothing in
-- the app read it. The text lived in the language files instead, which meant a
-- status added in Administracija had no wording of its own and no way to be
-- given any without a deploy.
--
-- One row per notifying status, per channel, per language. The wording here is
-- exactly what the language files produce today, so moving the code onto
-- templates changes nothing that is sent — it only makes the text editable.
--
-- WhatsApp is seeded too, matching the SMS wording, so its tab is not empty if
-- the channel is ever switched on.
--
-- Tokens: :number :status :tracking_url :customer
--
-- Line breaks are real newlines inside the quotes. Postgres treats a backslash
-- in an ordinary string literal literally, so an escape sequence would be
-- stored as two characters and printed that way in the email.

INSERT INTO notification_templates (code, channel, lang, subject, body) VALUES
-- Uredjaj primljen
('status.device_received', 'email', 'me', 'Reklamacija :number — :status',
 'Vaša reklamacija :number je sada: :status.

Status popravke pratite ovdje:
:tracking_url'),
('status.device_received', 'email', 'en', 'RMA :number — :status',
 'Your RMA :number is now: :status.

Track the repair here:
:tracking_url'),
('status.device_received', 'sms', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.device_received', 'sms', 'en', NULL,
 ':status. Track the repair: :tracking_url'),
('status.device_received', 'whatsapp', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.device_received', 'whatsapp', 'en', NULL,
 ':status. Track the repair: :tracking_url'),

-- Ceka se odobrenje
('status.awaiting_approval', 'email', 'me', 'Reklamacija :number — :status',
 'Vaša reklamacija :number je sada: :status.

Status popravke pratite ovdje:
:tracking_url'),
('status.awaiting_approval', 'email', 'en', 'RMA :number — :status',
 'Your RMA :number is now: :status.

Track the repair here:
:tracking_url'),
('status.awaiting_approval', 'sms', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.awaiting_approval', 'sms', 'en', NULL,
 ':status. Track the repair: :tracking_url'),
('status.awaiting_approval', 'whatsapp', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.awaiting_approval', 'whatsapp', 'en', NULL,
 ':status. Track the repair: :tracking_url'),

-- Popravljeno
('status.repaired', 'email', 'me', 'Reklamacija :number — :status',
 'Vaša reklamacija :number je sada: :status.

Status popravke pratite ovdje:
:tracking_url'),
('status.repaired', 'email', 'en', 'RMA :number — :status',
 'Your RMA :number is now: :status.

Track the repair here:
:tracking_url'),
('status.repaired', 'sms', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.repaired', 'sms', 'en', NULL,
 ':status. Track the repair: :tracking_url'),
('status.repaired', 'whatsapp', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.repaired', 'whatsapp', 'en', NULL,
 ':status. Track the repair: :tracking_url'),

-- Nepopravljivo
('status.unrepairable', 'email', 'me', 'Reklamacija :number — :status',
 'Vaša reklamacija :number je sada: :status.

Status popravke pratite ovdje:
:tracking_url'),
('status.unrepairable', 'email', 'en', 'RMA :number — :status',
 'Your RMA :number is now: :status.

Track the repair here:
:tracking_url'),
('status.unrepairable', 'sms', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.unrepairable', 'sms', 'en', NULL,
 ':status. Track the repair: :tracking_url'),
('status.unrepairable', 'whatsapp', 'me', NULL,
 ':status. Pratite status popravke: :tracking_url'),
('status.unrepairable', 'whatsapp', 'en', NULL,
 ':status. Track the repair: :tracking_url');
