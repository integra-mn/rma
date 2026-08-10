-- Authenticator-app 2FA (TOTP — Google Authenticator, Authy, Microsoft
-- Authenticator, 1Password …).
--
-- totp_secret is as sensitive as a password: anyone holding it can generate
-- valid codes indefinitely. It is never shown again after enrolment.
--
-- totp_confirmed_at is set only once the user has typed a code back correctly.
-- Until then the secret exists but the channel is not offered — otherwise a
-- half-finished setup would lock someone out of their own account.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) DEFAULT NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS totp_confirmed_at TIMESTAMP DEFAULT NULL;

-- Let every role offer the authenticator app. The per-user column above decides
-- whether it actually appears: only enrolled users see it.
UPDATE security_policies
   SET allowed_2fa_channels = 'totp,' || allowed_2fa_channels
 WHERE allowed_2fa_channels NOT LIKE '%totp%';
