-- Notification templates — English (email)
INSERT INTO notification_templates (code, channel, lang, subject, body) VALUES
('rma_created',     'email', 'en', 'RMA :number has been created',
 "Dear :customer,\n\nYour RMA request has been received.\n\nRMA number: :number\nDevice: :device\nComplaint: :complaint\n\nTrack your repair: :tracking_url\n\nIntegra RMA team"),

('status_changed',  'email', 'en', 'RMA :number — status update',
 "Dear :customer,\n\nThe status of your RMA :number has been updated to: :status\n\n:note\n\nTrack your repair: :tracking_url\n\nIntegra RMA team"),

('device_received', 'email', 'en', 'RMA :number — device received',
 "Dear :customer,\n\nWe have received your device (:device).\n\nRMA: :number\nEst. completion: :est_completion\n\nTrack your repair: :tracking_url\n\nIntegra RMA team"),

('repair_complete', 'email', 'en', 'RMA :number — repair completed',
 "Dear :customer,\n\nYour device has been repaired and is ready.\n\nRMA: :number\nDevice: :device\n\nTrack your repair: :tracking_url\n\nIntegra RMA team"),

('csat',            'email', 'en', 'How was your repair experience?',
 "Dear :customer,\n\nYour repair is complete. Please rate our service (1–5):\n:survey_url\n\nThank you,\nIntegra RMA team"),

('otp',             'email', 'en', 'Your verification code',
 "Your Integra RMA verification code is: :code\n\nExpires in 10 minutes.");

-- Notification templates — English (whatsapp)
INSERT INTO notification_templates (code, channel, lang, body) VALUES
('rma_created',     'whatsapp', 'en',
 "Hi :customer, your RMA :number has been received. Track it here: :tracking_url"),

('status_changed',  'whatsapp', 'en',
 "Hi :customer, your RMA :number status is now: *:status*. Track: :tracking_url"),

('device_received', 'whatsapp', 'en',
 "Hi :customer, we received your :device. RMA: :number. Est. completion: :est_completion. Track: :tracking_url"),

('repair_complete', 'whatsapp', 'en',
 "Hi :customer, great news! Your :device is repaired and ready. RMA: :number. Track: :tracking_url"),

('csat',            'whatsapp', 'en',
 "Hi :customer, your repair is done! Please rate our service: :survey_url"),

('otp',             'whatsapp', 'en',
 "Your Integra RMA code is: *:code*. Valid for 10 minutes.");

-- Notification templates — Montenegrin (email)
INSERT INTO notification_templates (code, channel, lang, subject, body) VALUES
('rma_created',     'email', 'me', 'RMA nalog :number je kreiran',
 "Poštovani :customer,\n\nVaš RMA zahtjev je primljen.\n\nBroj RMA: :number\nUredjaj: :device\nPritužba: :complaint\n\nPratite servis: :tracking_url\n\nIntegra RMA tim"),

('status_changed',  'email', 'me', 'RMA :number — ažuriranje statusa',
 "Poštovani :customer,\n\nStatus vašeg RMA :number je ažuriran na: :status\n\n:note\n\nPratite servis: :tracking_url\n\nIntegra RMA tim"),

('device_received', 'email', 'me', 'RMA :number — uredjaj primljen',
 "Poštovani :customer,\n\nPrimili smo vaš uredjaj (:device).\n\nRMA: :number\nPredvidjeni završetak: :est_completion\n\nPratite servis: :tracking_url\n\nIntegra RMA tim"),

('repair_complete', 'email', 'me', 'RMA :number — servis završen',
 "Poštovani :customer,\n\nVaš uredjaj je popravljen i spreman za preuzimanje.\n\nRMA: :number\nUredjaj: :device\n\nPratite servis: :tracking_url\n\nIntegra RMA tim"),

('csat',            'email', 'me', 'Kako ste zadovoljni servisom?',
 "Poštovani :customer,\n\nServis je završen. Ocijenite naš servis (1–5):\n:survey_url\n\nHvala,\nIntegra RMA tim"),

('otp',             'email', 'me', 'Vaš verifikacioni kod',
 "Vaš Integra RMA verifikacioni kod je: :code\n\nIstječe za 10 minuta.");

-- Notification templates — Montenegrin (whatsapp)
INSERT INTO notification_templates (code, channel, lang, body) VALUES
('rma_created',     'whatsapp', 'me',
 "Pozdrav :customer, vaš RMA nalog :number je primljen. Pratite ovdje: :tracking_url"),

('status_changed',  'whatsapp', 'me',
 "Pozdrav :customer, status vašeg RMA :number je sada: *:status*. Pratite: :tracking_url"),

('device_received', 'whatsapp', 'me',
 "Pozdrav :customer, primili smo vaš :device. RMA: :number. Predvidjeni završetak: :est_completion. Pratite: :tracking_url"),

('repair_complete', 'whatsapp', 'me',
 "Pozdrav :customer, vaš :device je popravljen i spreman! RMA: :number. Pratite: :tracking_url"),

('csat',            'whatsapp', 'me',
 "Pozdrav :customer, servis je završen! Ocijenite nas: :survey_url"),

('otp',             'whatsapp', 'me',
 "Vaš Integra RMA kod je: *:code*. Važi 10 minuta.");
