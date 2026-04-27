-- BPC iEnroll - Applicant Dashboard Messages
-- Run this in phpMyAdmin on database bpc_ienroll
-- Students see these messages when exam/interview is scheduled, results are released, or admit/reject.

CREATE TABLE IF NOT EXISTS applicant_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  message_type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_user_created (user_id, created_at DESC),
  KEY idx_user_unread (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
