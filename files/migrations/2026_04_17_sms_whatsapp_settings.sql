-- Migration: add SMS and WhatsApp provider settings.
-- Run once against the existing database:
--   mysql -u<user> -p <db> < 2026_04_17_sms_whatsapp_settings.sql
-- INSERT IGNORE so re-running is safe.

INSERT IGNORE INTO settings (key_name, value, type, group_name) VALUES
  ('sms_provider',             '',     'string', 'integrations'),
  ('sms_twilio_sid',           '',     'string', 'integrations'),
  ('sms_twilio_token',         '',     'string', 'integrations'),
  ('sms_twilio_from',          '',     'string', 'integrations'),
  ('sms_clickatell_apikey',    '',     'string', 'integrations'),
  ('sms_clickatell_from',      '',     'string', 'integrations'),
  ('sms_custom_url',           '',     'string', 'integrations'),
  ('sms_custom_auth_header',   '',     'string', 'integrations'),
  ('sms_custom_to_field',      'to',   'string', 'integrations'),
  ('sms_custom_text_field',    'text', 'string', 'integrations'),
  ('sms_custom_extra',         '',     'string', 'integrations'),
  ('sms_custom_json',          '0',    'bool',   'integrations'),
  ('whatsapp_provider',        'meta',             'string', 'integrations'),
  ('whatsapp_meta_phone_id',   '',                 'string', 'integrations'),
  ('whatsapp_meta_token',      '',                 'string', 'integrations'),
  ('whatsapp_meta_template',   'otp_verification', 'string', 'integrations'),
  ('whatsapp_meta_lang',       'en',               'string', 'integrations'),
  ('whatsapp_meta_version',    'v20.0',            'string', 'integrations');
