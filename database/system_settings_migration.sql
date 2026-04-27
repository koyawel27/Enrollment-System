-- BPC iEnroll - System Settings Table
-- Run this in phpMyAdmin on database bpc_ienroll

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default values
INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('school_name', 'Bulacan Polytechnic College'),
  ('school_address', 'Malolos, Bulacan'),
  ('school_email', 'admission@bpc.edu.ph'),
  ('school_phone', ''),
  ('academic_year', '2025-2026'),
  ('semester', '1st Semester'),
  ('application_open', '1'),
  ('default_passing_score', '75'),
  ('max_upload_size_mb', '5')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
