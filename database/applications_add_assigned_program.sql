-- BPC iEnroll - Add assigned_program column to applications
-- Run this in phpMyAdmin on database bpc_ienroll

ALTER TABLE applications
  ADD COLUMN assigned_program VARCHAR(20) NULL AFTER third_choice;

