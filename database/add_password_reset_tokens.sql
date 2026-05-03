-- bpc_ienroll — student password reset tokens
-- Run once per environment. If columns already exist, MySQL returns "Duplicate column"; skip in that case.

ALTER TABLE `users`
  ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN `reset_token_expires` DATETIME NULL DEFAULT NULL;
