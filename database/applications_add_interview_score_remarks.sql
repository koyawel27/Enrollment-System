-- BPC iEnroll — nullable interview encoding fields
ALTER TABLE applications
    ADD COLUMN interview_score INT NULL DEFAULT NULL,
    ADD COLUMN interview_remarks VARCHAR(512) NULL DEFAULT NULL;
