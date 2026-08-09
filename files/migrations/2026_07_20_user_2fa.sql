-- Per-user 2FA opt-in.
-- The 2FA engine (OTP over email/SMS/WhatsApp) already exists and was driven
-- only by the per-role security_policies table. This adds a per-user switch so
-- individual accounts can be required to pass 2FA regardless of their role.
ALTER TABLE users
  ADD COLUMN require_2fa TINYINT(1) DEFAULT 0 AFTER must_change_pw;
