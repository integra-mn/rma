-- Technicians may use SMS for 2FA.
--
-- Rajo asked why a technician logging in sees only Email, with no SMS and no
-- authenticator option. The answer was the per-role policy: technician was
-- seeded with allowed_2fa_channels = 'email' alone, while reception got
-- 'email,sms' and the admin roles got WhatsApp too. Nothing about the login
-- screen was wrong — it was showing exactly what the role permitted.
--
-- The authenticator is a separate matter and needs no change here: it only
-- ever appears for someone who has finished enrolling in Moj profil, since
-- offering it otherwise would strand them on a screen asking for a code no app
-- can produce.
--
-- Technician now matches reception. WhatsApp is deliberately not added — it is
-- off app-wide and needs a Meta Cloud API account.

UPDATE security_policies
   SET allowed_2fa_channels = 'totp,email,sms'
 WHERE role = 'technician';
