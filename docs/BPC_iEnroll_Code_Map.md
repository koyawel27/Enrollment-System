## BPC iEnroll – Code Map & Function Reference

**Purpose:** Quick reference for explaining to your professor *which part of the code does what* (files, main handlers, and where key behaviors live). This complements `BPC_iEnroll_System_Documentation.md` by focusing on implementation locations instead of just features.

---

## 1. High-Level Entry Points

- **Landing Page**
  - File: `index.php`
  - Role: Public landing page with Bulacan Polytechnic College branding and links to registration/login.

- **Student Registration**
  - File: `app/auth/register.php`
  - What it does:
    - Handles registration form POST.
    - Validates input (email, password).
    - Uses `password_hash()` to store passwords.
    - Inserts a new row into `users`.

- **Student Login / Logout**
  - File: `app/auth/login.php`
  - What it does:
    - Authenticates credentials against `users`.
    - On success: sets `$_SESSION['user_id']` and redirects to `app/student/dashboard.php`.
  - File: `app/auth/logout.php`
  - What it does:
    - Calls `session_start()` then `session_destroy()`.
    - Redirects back to login or landing.

- **Admin Login / Logout**
  - File: `public/admin-login.php`
  - What it does:
    - Authenticates admins against `admins`.
    - On success: sets `$_SESSION['admin_id']` and redirects to `admin-app/student/dashboard.php`.
  - File: `app/auth/admin-logout.php`
  - What it does:
    - Ends admin session and redirects to `public/admin-login.php`.

- **Database Connection**
  - File: `config/db.php`
  - What it does:
    - Defines constants for host, user, password, database, **port 3307**.
    - Creates/retrieves the `mysqli` connection used by all scripts.

- **Admin Auth Guard**
  - File: `config/admin-auth-check.php`
  - What it does:
    - Ensures `$_SESSION['admin_id']` is present.
    - Redirects to `public/admin-login.php` if not authenticated.
    - Included at the top of **all admin pages and POST handlers**.

---

## 2. Applicant Workflow – Where Things Happen

### 2.1 Application Creation & Multi-Step Form

- **Dashboard (Applicant View)**
  - File: `app/student/dashboard.php`
  - Key responsibilities:
    - Reads current `applications.status` and `program_category`.
    - Decides whether to show a **7-step CHED** or **5-step TESDA** timeline.
    - Shows contextual banners:
      - Exam scheduled (CHED).
      - Interview scheduled.
      - Documents rejected / re-upload needed.
      - Admitted / Rejected messages.
    - Displays the application reference number.

- **Multi-Step Form UI**
  - File: `app/student/application-form.php`
  - Key responsibilities:
    - Renders Steps 1–7 of the application.
    - For each step, loads previously saved values from `applications`.
    - Uses standard POST forms (no AJAX) that submit to `app/handlers/save-step.php`.

- **Saving Each Step**
  - File: `app/handlers/save-step.php`
  - Key responsibilities:
    - Receives POST data from a given step (step number passed as parameter).
    - Uses prepared statements (`mysqli_prepare`, `bind_param`, `execute`) to:
      - Insert a new `applications` record when needed.
      - Update existing rows as the applicant progresses.
    - Handles **program category logic**:
      - Saves `program_category` as `'CHED'` or `'TESDA'`.
      - Stores `third_choice`, PSA registry number, LRN, and `school_type`.
    - When final step is submitted:
      - Sets status from `Draft` → `Application Submitted`.
      - Generates and saves the application reference number.

### 2.2 Document Upload & Re-Upload

- **Initial Document Upload**
  - UI: Step 6 of `app/student/application-form.php`.
  - Handler: `app/handlers/save-step.php` (Step 6 branch).
  - What it does:
    - Validates file types/sizes (e.g., 2x2 ID Photo, Report Card, PSA Birth Certificate, Transfer Credential/TOR).
    - Moves files into the `uploads/` directory with unique filenames.
    - Stores file paths in `applications` or related columns.

- **Admin Document Verification**
  - File: `app/admin/admin-application-detail.php`
  - What it does:
    - Loads a single application and its uploaded file paths.
    - Contains the **Accept/Reject** document verification UI.
    - When admin clicks:
      - Accept: Sets status to `Documents Verified`.
      - Reject: Sets status to `Documents Rejected` and records the reason.
    - Inserts a new row into `status_history` for each change.
    - **Note:** This page replaces any earlier notion of `admin-verify-app/student/documents.php`.

- **Document Re-Upload (After Rejection)**
  - File: `resubmit-app/student/documents.php`
  - What it does:
    - Allows the applicant to upload corrected documents when status is `Documents Rejected`.
    - Updates the relevant file paths.
    - Sets status to `Documents Re-submitted`.
    - Logs the status change in `status_history`.

---

## 3. Admin Workflow – Exams (CHED Only)

### 3.1 Exam Schedule Creation & Assignment

- **Exam Scheduling UI**
  - File: `app/admin/admin-exam-schedule.php`
  - What it does:
    - Lets admin create exam schedules (date, time, venue, passing score).
    - Lists eligible CHED applicants:
      - `program_category = 'CHED'` (or legacy `NULL`) and `status = 'Documents Verified'`.
    - Provides checkboxes to select multiple applicants for a chosen schedule.

- **Exam Assignment Handler**
  - File: `app/admin/admin-set-exam.php`
  - What it does:
    - Receives selected applicant IDs and an `exam_schedule_id`.
    - For each applicant:
      - Updates `applications.exam_schedule_id`.
      - Sets status from `Documents Verified` → `Exam Scheduled`.
      - Inserts a row in `status_history` with `old_status`, `new_status`, `changed_by`, and `created_at`.

### 3.2 Exam Results & Status Changes

- **Exam Result Encoding UI**
  - File: `app/admin/admin-exam-results.php`
  - What it does:
    - Lists applicants assigned to a given exam schedule.
    - Shows input fields to:
      - Mark attendance (Present / Absent).
      - Enter exam score (0–100).
    - Provides live (server-side rerendered) feedback on pass/fail vs passing score.

- **Exam Result Handler**
  - File: `app/admin/admin-encode-results.php`
  - What it does:
    - Reads attendance and score for each applicant.
    - Applies business rules:
      - Present + score ≥ passing_score → `Exam Completed`.
      - Present + score < passing_score → `Exam Failed`.
      - Absent → `No Show`.
    - Updates the `applications.status` accordingly.
    - Inserts corresponding entries into `status_history`.
    - Uses a local variable (e.g., `$marked_by`) for `bind_param()` instead of session variables directly.

---

## 4. Admin Workflow – Interviews (CHED & TESDA)

### 4.1 Interview Scheduling

- **Interview Scheduling UI**
  - File: `app/admin/admin-interview-schedule.php`
  - What it does:
    - Shows **two sections**:
      - CHED applicants with `status = 'Exam Completed'`.
      - TESDA applicants with `status = 'Documents Verified'` and `program_category = 'TESDA'`.
    - Allows:
      - Batch scheduling (assign same slot to multiple applicants).
      - Individual scheduling (per-row date/time/venue inputs).

- **Interview Scheduling Handler**
  - File: `app/admin/admin-set-interview.php`
  - What it does:
    - Receives selected applicants and schedule details (date, time, venue, type).
    - Updates each applicant:
      - Saves interview date/time/venue.
      - Sets status to `Interview Scheduled`.
      - Logs a `status_history` entry.

### 4.2 Interview Result Encoding

- **Interview Results UI**
  - File: `app/admin/admin-interview-results.php`
  - What it does:
    - Lists applicants with `status = 'Interview Scheduled'`.
    - Allows admin to:
      - Mark attendance (Present / Absent).
      - Mark result (Pass / Fail) for those who attended.
    - Shows live counters for present, absent, passed, failed.

- **Interview Results Handler**
  - File: `app/admin/admin-encode-interview-results.php`
  - What it does:
    - Applies interview rules:
      - Present + Pass → status `Interview Completed`.
      - Present + Fail → status `Rejected`.
      - Absent → status `No Show`.
    - Writes all changes to `applications.status`.
    - Inserts a new row per applicant into `status_history`.

---

## 5. Admin Workflow – Final Decision

- **Final Decision UI**
  - File: `app/admin/admin-final-decision.php`
  - What it does:
    - Lists applicants whose status is `Interview Completed` (CHED and TESDA).
    - Displays key info: exam result (for CHED), program choices, track, etc.
    - Provides two buttons per applicant: **Admit** or **Reject**.

- **Final Decision Handler**
  - File: `app/admin/admin-set-final-decision.php`
  - What it does:
    - Reads the admin’s decision for each applicant.
    - Sets:
      - Admit → `Admitted/Enrolled`.
      - Reject → `Rejected`.
    - Inserts matching `status_history` entries with decision notes where applicable.

---

## 6. Generic Status Handling & Audit Trail

- **Centralized Status Updates (Some Cases)**
  - File: `app/admin/admin-update-status.php`
  - What it does:
    - Provides a more generic way to move an application between certain allowed statuses (mostly early-phase transitions).
    - Validates allowed transitions to prevent illegal jumps.
    - Inserts a new row into `status_history` for each change.

- **Audit Trail Storage**
  - Table: `status_history`
  - Populated by:
    - `app/admin/admin-update-status.php`
    - `app/admin/admin-set-exam.php`
    - `app/admin/admin-set-interview.php`
    - `app/admin/admin-encode-results.php`
    - `app/admin/admin-encode-interview-results.php`
    - `app/admin/admin-set-final-decision.php`
    - `resubmit-app/student/documents.php`
  - How it’s used in the UI:
    - `admin-app/student/dashboard.php`: recent activity feed (last N changes).
    - `app/admin/admin-application-detail.php`: full per-application history.

---

## 7. Shared Layout & Styling

- **Admin Sidebar**
  - File: `app/shared/admin-sidebar.php`
  - What it does:
    - Renders the left-hand navigation menu for all admin pages.
    - Included using `include`/`require` in:
      - `admin-app/student/dashboard.php`
      - `app/admin/admin-application-detail.php`
      - `app/admin/admin-exam-schedule.php`
      - `app/admin/admin-exam-results.php`
      - `app/admin/admin-interview-schedule.php`
      - `app/admin/admin-interview-results.php`
      - `app/admin/admin-final-decision.php`

- **Admin Stylesheet**
  - File: `assets/admin-styles.css`
  - What it does:
    - Provides global styles for the admin layout (sidebar, cards, tables, badges, etc.).
    - Linked from the `<head>` of all admin HTML pages listed above.

---

## 8. Quick Q&A – “Where is X Implemented?”

- **Q: Where is CHED vs TESDA branching logic?**
  - `app/handlers/save-step.php` → Saves `program_category` based on chosen track.
  - `app/admin/admin-exam-schedule.php` and `app/admin/admin-exam-results.php` → Filter only CHED (`program_category = 'CHED'`).
  - `app/admin/admin-interview-schedule.php` → Separate sections for CHED (`Exam Completed`) and TESDA (`Documents Verified`, `program_category = 'TESDA'`).
  - `app/student/dashboard.php` → Chooses 5-step versus 7-step timeline based on `program_category`.

- **Q: Where does the system log status changes?**
  - In all the “set”/“encode”/“update” handlers:
    - `app/admin/admin-update-status.php`, `app/admin/admin-set-exam.php`, `app/admin/admin-set-interview.php`, `app/admin/admin-encode-results.php`, `app/admin/admin-encode-interview-results.php`, `app/admin/admin-set-final-decision.php`, `resubmit-app/student/documents.php`.

- **Q: Where are applicant documents stored and referenced?**
  - File uploads handled in `app/student/application-form.php` (Step 6) and `app/handlers/save-step.php`.
  - Physical files saved in `uploads/`.
  - Paths saved in the database and displayed in `app/admin/admin-application-detail.php`.

- **Q: Where does the database port 3307 appear in code?**
  - `config/db.php` – defines connection settings including port `3307`.

- **Q: Where is the “Admitted” banner text for successful applicants?**
  - `app/student/dashboard.php` – in the block that checks for `status = 'Admitted/Enrolled'` and renders the success message for Bulacan Polytechnic College.

---

**How to use this file with your professor:**  
When asked “how does X work?” you can:
1. Look up the feature here (registration, document verification, exam, interview, decision, etc.).
2. Point to the **specific file name(s)** and describe in words what the handler does (as summarized above).
3. Open that PHP file in the editor to show the actual code implementing the behavior.



## Directory Structure (Refactored)
- config/ for DB and shared config (paths.php, db.php).
- public/ for entry pages (public/admin-login.php), while index.php remains at root.
- pp/admin/ for admin pages/handlers.
- pp/student/ for student-facing pages.
- pp/auth/ for auth handlers.
- pp/handlers/ for process handlers.
- pp/shared/ for reusable components.
- docs/ for project documentation.
