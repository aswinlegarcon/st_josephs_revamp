-- Migration 001 — admin_users: roles + forced password change (PHASES.md S2 / SECURITY.md SEC-08)
--
-- Additive only. Apply ONCE against an existing production database via mPanel's
-- DB tool. Fresh installs get these columns from schema.sql instead, so this file
-- is not run in dev (a fresh Docker DB already has them).
--
-- MySQL 8 has no "ADD COLUMN IF NOT EXISTS"; if a column already exists the
-- statement errors harmlessly — skip it.

ALTER TABLE admin_users
  ADD COLUMN role                 VARCHAR(20) NOT NULL DEFAULT 'owner' AFTER display_name,
  ADD COLUMN must_change_password TINYINT(1)  NOT NULL DEFAULT 0       AFTER role,
  ADD COLUMN password_changed_at  DATETIME    NULL                     AFTER must_change_password;

-- Force the existing admin to rotate the seeded/default password on next login.
UPDATE admin_users SET must_change_password = 1 WHERE password_changed_at IS NULL;
