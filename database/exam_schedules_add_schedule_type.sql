-- Add schedule_type to exam_schedules (Regular vs Makeup Exam)
-- Run once. Safe to run if column already exists (ignore error).

ALTER TABLE exam_schedules
  ADD COLUMN schedule_type VARCHAR(20) NOT NULL DEFAULT 'Regular';

-- New schedules will use Regular or Makeup; existing rows get 'Regular' from DEFAULT.
