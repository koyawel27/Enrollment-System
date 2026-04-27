-- Admins RBAC & status: role, is_active, last_login_at
-- Run once. Existing admins become super_admin and stay active.

ALTER TABLE admins
  ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'super_admin',
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL;

-- Backfill existing rows (in case default didn't apply on older MySQL)
UPDATE admins SET role = 'super_admin', is_active = 1 WHERE role IS NULL OR role = '';
