-- Missing column: profileController and views/profile/index.php both read and
-- write users.preferred_2fa_channel, but it was never added to the schema, so
-- saving anything on Moj profil (phone, language, theme) failed with
-- "column preferred_2fa_channel does not exist".
--
-- NULL means "no preference" — the 2FA screen then offers the first channel
-- allowed for the role, which is what the view already falls back to.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS preferred_2fa_channel VARCHAR(10) DEFAULT NULL;
