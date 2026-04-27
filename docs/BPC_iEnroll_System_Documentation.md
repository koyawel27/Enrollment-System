## BPC iEnroll – Full System Technical & Functional Specification

**System Name:** BPC iEnroll – Bulacan Polytechnic College Admission and Enrollment Management System  
**Institution:** Bulacan Polytechnic College (BPC)  
**Document Purpose:** This document describes the complete technical and functional behavior of the BPC iEnroll system, sufficient for a new developer to understand architecture, data flows, and feature behavior from database configuration to UI banners.

---

## 1. Core Objective & Brand Integrity

### 1.1 Primary Objective

- **Primary Goal:** Provide a web-based admission management system for **Bulacan Polytechnic College** that manages the full applicant pipeline from initial registration through final admission decision.
- **Scope:**
  - Online applicant registration and login
  - Multi-step application data capture
  - Document upload and verification
  - Exam scheduling and result encoding (CHED only)
  - Interview scheduling and result encoding (CHED and TESDA)
  - Final admission decision recording
  - Status-driven dashboards for both applicants and admins

### 1.2 Brand Integrity & Naming

- **Official Institution Name:** All system-level and UI references must use **“Bulacan Polytechnic College”**.
- **Previous Inconsistency (Resolved by this Spec):**
  - Earlier documentation and at least one UI banner referenced **“Bestlink College of the Philippines”**.
  - Audit finding: `app/student/dashboard.php` contained an admitted banner message using “Bestlink College of the Philippines”.
- **Required Standard:**
  - All occurrences of “Bestlink” or “Bestlink College of the Philippines” in code, templates, and content **must be replaced** with **“Bulacan Polytechnic College”**.
  - Future additions must reference only **Bulacan Polytechnic College** in banners, emails, reports, and legal footers.

---

## 2. Technical Stack & Constraints

### 2.1 Platform & Runtime

- **Backend:** Pure PHP (procedural style, no PHP frameworks)
- **Frontend:** HTML5, CSS3, vanilla JavaScript (no JS frameworks, no AJAX)
- **Web Server:** Apache via XAMPP on Windows
- **Database:** MySQL, database name `bpc_ienroll`
- **Host & Port:** `localhost` on **port 3307**
  - Typical connection:
    - Host: `localhost`
    - User: `root`
    - Password: `''` (empty; may vary per environment)
    - Database: `bpc_ienroll`
    - Port: `3307`

### 2.2 File System & Deployment Layout

- **Document Root (Local Dev):** `C:\xampp\htdocs\Enrollment System\`
- **Key Directories:**
  - `auth/` – Authentication-related PHP scripts (student and admin logout)
  - `config/` – Database and authentication configuration
  - `assets/` – Static assets (CSS, images, icons)
  - `includes/` – Shared PHP includes (e.g., admin sidebar)
  - `uploads/` – All applicant-uploaded documents, stored as files on disk

### 2.3 Security & Coding Standards

- **Database Access:**
  - All queries **must** use `mysqli` prepared statements (`mysqli_prepare`, `bind_param`, `execute`).
  - Never interpolate raw user input directly into SQL strings.
- **Authentication:**
  - **Session-based** authentication (`$_SESSION`) for both applicants and admins.
  - Distinct session keys:
    - Applicants: `$_SESSION['user_id']`
    - Admins: `$_SESSION['admin_id']`
  - All admin pages must:
    - Call `session_start()` before output.
    - Include `config/admin-auth-check.php` to enforce admin login.
- **Password Management:**
  - Applicant and admin passwords stored using `password_hash()`.
  - Verification via `password_verify()`.
- **Output Encoding:**
  - All user-facing dynamic content must be wrapped in `htmlspecialchars()` to mitigate XSS.
- **File Uploads:**
  - Uploaded documents stored in `uploads/` with unique, timestamp-based filenames.
  - Validate file extension and MIME type; enforce size limits.
  - Store only file paths in the database, not binary contents.

### 2.4 Architectural Constraints

- **No AJAX:** All forms use standard HTTP POST submissions with full-page redirects. Any “live” feedback is implemented with server-side rendering on reload.
- **Status-Driven Workflow:** `applications.status` (ENUM) is the primary driver of what actions are available on each admin page and what banners/timelines appear on the applicant dashboard.
- **Port Constraint:** MySQL must run on **port 3307**. All environments must mirror this configuration or adapt `config/db.php` accordingly.
- **Filesystem Persistence:** Since documents are stored on the filesystem, deployments and backups must preserve the `uploads/` directory alongside the database.

---

## 3. Verified Feature Inventory (Actual vs Planned)

This section lists the **actual, active files** and their responsibilities, based on the master project state and audit. It explicitly clarifies discrepancies with earlier planning documents.

### 3.1 Student-Facing Files

| File | Role / Function |
|------|-----------------|
| `index.php` | Landing page for BPC iEnroll; presents college, admission timeline, key information, and call-to-action buttons for registration and login. |
| `app/auth/register.php` | Applicant registration form; captures email/password and minimal profile; hashes password and creates `users` record. |
| `app/auth/login.php` | Applicant login; validates credentials and starts `$_SESSION['user_id']`, redirecting to `app/student/dashboard.php`. |
| `app/auth/logout.php` | Destroys applicant session and redirects to `index.php` or login screen. |
| `app/student/dashboard.php` | Applicant dashboard showing application status timeline (5-step TESDA or 7-step CHED), reference number, and contextual banners (exam schedule, interview schedule, re-upload prompts, admitted/rejected messages). |
| `app/student/application-form.php` | 7-step multi-page application form. Presents sections for personal info, address, family background, educational background (with applicant type branching), program choices (CHED/TESDA), document uploads, and final review. |
| `app/handlers/save-step.php` | POST handler for saving each step of the multi-step form; persists data incrementally to `applications` and related tables; enforces locking after final submission except for document re-upload flow. |

### 3.2 Admin Authentication & Core Dashboard

| File | Role / Function |
|------|-----------------|
| `public/admin-login.php` | Admin login interface; validates admin credentials, establishes `$_SESSION['admin_id']`, redirects to `admin-app/student/dashboard.php`. |
| `app/auth/admin-logout.php` | Logs out admin users by destroying session and redirecting to `public/admin-login.php`. |
| `admin-app/student/dashboard.php` | Primary admin console. Displays KPI/stat cards, CHED/TESDA breakdowns, applicant list with filters, and recent activity from `status_history`. Serves as the main starting point for all processing flows. |
| `app/admin/admin-application-detail.php` | Detailed view of a single applicant. Handles **document verification** (Accept/Reject + reasons) and shows full application information, documents, and status history. This page supersedes any earlier plan for a separate `admin-verify-app/student/documents.php`. |

### 3.3 Admin – Exam Management (CHED Only)

| File | Role / Function |
|------|-----------------|
| `app/admin/admin-exam-schedule.php` | UI for creating exam schedules (date, time, venue, type, passing score) and assigning CHED applicants with `Documents Verified` status. Filters applicants by `program_category = 'CHED'` (or `NULL` for legacy) and proper status. |
| `app/admin/admin-set-exam.php` | POST handler for assigning selected applicants to an exam schedule (`exam_schedule_id`), updating status to `Exam Scheduled`, and logging changes to `status_history`. |
| `app/admin/admin-exam-results.php` | UI for admins to encode exam attendance (Present/Absent) and scores (0–100) for assigned CHED applicants. Provides live, server-rendered validation against passing score and outcome preview. |
| `app/admin/admin-encode-results.php` | POST handler for exam result submission. Transitions application status to `Exam Completed`, `Exam Failed`, or `No Show` based on score and attendance. Writes corresponding entries in `status_history`. |

### 3.4 Admin – Interview Management (CHED + TESDA)

| File | Role / Function |
|------|-----------------|
| `app/admin/admin-interview-schedule.php` | UI for configuring interviews. Supports batch scheduling and per-applicant scheduling. Shows separate sections for CHED exam passers (`Exam Completed`) and TESDA applicants coming directly from `Documents Verified`. |
| `app/admin/admin-set-interview.php` | POST handler for interview scheduling. Records interview date, time, venue, and type (Batch/Individual); updates status to `Interview Scheduled` and logs to `status_history`. |
| `app/admin/admin-interview-results.php` | UI for encoding interview attendance and results for scheduled applicants. Displays counters for present/absent/pass/fail. |
| `app/admin/admin-encode-interview-results.php` | POST handler for interview results. Sets final interview outcomes (`Interview Completed`, `Rejected`, `No Show`) and logs transitions in `status_history`. |

### 3.5 Admin – Final Decision Management

| File | Role / Function |
|------|-----------------|
| `app/admin/admin-final-decision.php` | UI listing applicants with `Interview Completed` status (CHED and TESDA) and providing Admit/Reject actions per applicant. Shows context (exam scores, program choices, track, etc.). |
| `app/admin/admin-set-final-decision.php` | POST handler for final decisions. Updates status to `Admitted/Enrolled` or `Rejected`, and writes entries into `status_history`. |

### 3.6 Admin – Generic Status Handling & Utilities

| File | Role / Function |
|------|-----------------|
| `app/admin/admin-update-status.php` | Generic POST handler for select status transitions (mostly earlier phases or overrides). Ensures only allowed transitions are permitted, and records status changes in `status_history`. |
| `resubmit-app/student/documents.php` | Applicant-initiated handler invoked when documents were rejected. Allows re-uploading the required files, changes status to `Documents Re-submitted`, and logs to `status_history`. |
| `config/admin-auth-check.php` | Included on all admin pages and POST handlers. Ensures that only authenticated admins access admin routes; redirects unauthenticated users to `public/admin-login.php`. |

### 3.7 Configuration, Shared Components & Assets

| File / Directory | Role / Function |
|------------------|-----------------|
| `config/db.php` | Central database configuration and connection helper. Defines constants for host, database name, user, password, and **port 3307**. Exposes a reusable connection instance or function. |
| `app/shared/admin-sidebar.php` | Reusable sidebar component included by all admin HTML pages (`admin-app/student/dashboard.php`, `app/admin/admin-application-detail.php`, `app/admin/admin-exam-schedule.php`, `app/admin/admin-exam-results.php`, `app/admin/admin-interview-schedule.php`, `app/admin/admin-interview-results.php`, `app/admin/admin-final-decision.php`). |
| `assets/admin-styles.css` | Shared CSS for admin layout, including sidebar, top navigation, and card/table styling. All admin HTML pages link to this stylesheet. |
| `assets/` | Contains BPC logo, images, icons, and possibly additional CSS files for the public-facing and applicant-facing UI. |
| `uploads/` | Stores all applicant-uploaded files (e.g., 2x2 ID photo, report card, PSA birth certificate, transfer credentials, TOR). File paths are referenced from application records. |

### 3.8 Database & Migration Scripts

| File | Role / Function |
|------|-----------------|
| `phase4b-migration.sql` | Database migration that introduces PSA registry number, LRN, school_type, third_choice, and program_category columns. |
| `fix-null-fields.sql` | Data-fix script that backfills NULL values for legacy applicants to ensure `program_category`, PSA, LRN, school_type, and third_choice are populated. |

### 3.9 Inventory Corrections (Audit-Driven)

- **Removed from Inventory:**
  - `admin-verify-app/student/documents.php`  
    - This file does **not** exist in the current codebase.
    - **Correct behavior:** Document verification is fully handled inside `app/admin/admin-application-detail.php`.
- **Clarified Implementations:**
  - Document verification (accept/reject, with reasons) is implemented as part of the application detail page, not as a standalone page.

---

## 4. Logic Flows & Branching

This section formalizes the end-to-end workflows for CHED and TESDA tracks and the rules around status changes and audit logging.

### 4.1 Tracks Overview

- **CHED Track (Degree / Higher Education):**
  - Requires both exam and interview.
  - **7 main system steps** from applicant perspective.
- **TESDA Track (Vocational / Diploma):**
  - **Bypasses the exam** entirely.
  - Requires only document verification and interview.
  - **5 main system steps** from applicant perspective.

### 4.2 Status ENUM Reference

The `applications.status` ENUM contains the following values:

- `Draft`
- `Application Submitted`
- `Documents Under Review`
- `Documents Verified`
- `Documents Rejected`
- `Documents Re-submitted`
- `Exam Scheduled`
- `Exam Completed`
- `Exam Failed`
- `No Show`
- `Interview Scheduled`
- `Interview Completed`
- `Admitted/Enrolled`
- `Rejected`

### 4.3 CHED Track – Detailed Flow (7 Steps)

**End-to-end process:**

1. **Registration**
   - Applicant registers via `app/auth/register.php` and logs in via `app/auth/login.php`.
   - System creates a `users` record and an initial `applications` record in `Draft` status.

2. **Application Form (Multi-step Data Capture)**
   - Applicant completes `app/student/application-form.php` (Steps 1–7), persisted by `app/handlers/save-step.php`.
   - On final submission, status moves from `Draft` → `Application Submitted`.

3. **Document Upload & Verification**
   - Applicant uploads documents (e.g., 2x2 ID photo, report card, PSA birth certificate, transfer credentials/TOR) in the form’s document step.
   - Admin re-views documents in `app/admin/admin-application-detail.php`:
     - **Accept:** updates status to `Documents Verified`.
     - **Reject:** updates status to `Documents Rejected` with a structured reason.
   - Applicant with `Documents Rejected` can re-upload via `resubmit-app/student/documents.php`, changing status to `Documents Re-submitted`.
   - Admin re-checks and either re-rejects or verifies.

4. **Exam Scheduling (CHED Only)**
   - Admin uses `app/admin/admin-exam-schedule.php` to create an exam schedule and select eligible CHED applicants:
     - Eligibility: `program_category = 'CHED'` and `status = 'Documents Verified'` (or equivalent legacy).
   - `app/admin/admin-set-exam.php` assigns the schedule:
     - Status: `Documents Verified` → `Exam Scheduled`.

5. **Exam Attendance & Scoring**
   - In `app/admin/admin-exam-results.php`, admin records:
     - Attendance: Present / Absent
     - Score: 0–100
   - `app/admin/admin-encode-results.php` applies rules:
     - Present + score ≥ passing_score → `Exam Completed`
     - Present + score < passing_score → `Exam Failed`
     - Absent → `No Show`

6. **Interview Scheduling & Results**
   - Eligible CHED applicants: `Exam Completed`.
   - Admin uses `app/admin/admin-interview-schedule.php` and `app/admin/admin-set-interview.php`:
     - Assigns date/time/venue and type (Batch/Individual).
     - Status: `Exam Completed` → `Interview Scheduled`.
   - Interview results encoded via `app/admin/admin-interview-results.php` / `app/admin/admin-encode-interview-results.php`:
     - Present + Pass → `Interview Completed`
     - Present + Fail → `Rejected`
     - Absent → `No Show`

7. **Final Decision**

   - `app/admin/admin-final-decision.php` lists `Interview Completed` applicants.
   - `app/admin/admin-set-final-decision.php` sets:
     - Admit → `Admitted/Enrolled`
     - Reject → `Rejected`

### 4.4 TESDA Track – Detailed Flow (5 Steps)

TESDA applicants bypass the exam entirely.

1. **Registration**
   - Same as CHED: registration + login, initial `Draft` status.

2. **Application Form**
   - Applicant selects TESDA track in program choices step; `program_category` saved as `TESDA` via `app/handlers/save-step.php`.
   - On final submission, status: `Draft` → `Application Submitted`.

3. **Document Upload & Verification**
   - Identical to CHED document flow:
     - `Application Submitted` → `Documents Under Review` → `Documents Verified` or `Documents Rejected` → `Documents Re-submitted`.

4. **Interview Scheduling & Results (No Exam)**
   - TESDA-eligible applicants for interview scheduling:
     - `status = 'Documents Verified'` and `program_category = 'TESDA'`.
   - `app/admin/admin-interview-schedule.php`/`app/admin/admin-set-interview.php`:
     - Status: `Documents Verified` → `Interview Scheduled`.
   - Interview result rules same as CHED:
     - Present + Pass → `Interview Completed`
     - Present + Fail → `Rejected`
     - Absent → `No Show`

5. **Final Decision**
   - Same final decision flow as CHED:
     - From `Interview Completed` to `Admitted/Enrolled` or `Rejected`.

### 4.5 Status Flow Summary (Textual)

- **CHED:**
  - `Draft` → `Application Submitted` → `Documents Under Review` → (`Documents Rejected` → `Documents Re-submitted` → `Documents Under Review`) or `Documents Verified` → `Exam Scheduled` → (`Exam Completed` or `Exam Failed` or `No Show`) → `Interview Scheduled` → `Interview Completed` → (`Admitted/Enrolled` or `Rejected`).

- **TESDA:**
  - `Draft` → `Application Submitted` → `Documents Under Review` → (`Documents Rejected` → `Documents Re-submitted` → `Documents Under Review`) or `Documents Verified` → `Interview Scheduled` → `Interview Completed` → (`Admitted/Enrolled` or `Rejected`).

### 4.6 Audit Trail – `status_history` Table

- **Purpose:** Provide a full audit trail of every application status change, including who performed it and when.
- **Key Columns (conceptual):**
  - `id` – Primary key
  - `application_id` – FK to `applications.id`
  - `old_status` – Previous status value
  - `new_status` – New status value
  - `changed_by` – Identifier for admin or system (e.g., admin name or ID)
  - `created_at` – Timestamp of the change
  - `notes` – Optional text (e.g., rejection reason, additional context)

- **Events that must log into `status_history`:**
  - Initial submission from `Draft` to `Application Submitted`
  - Transitions to/from:
    - `Documents Under Review`
    - `Documents Verified`
    - `Documents Rejected`
    - `Documents Re-submitted`
    - `Exam Scheduled`
    - `Exam Completed`
    - `Exam Failed`
    - `No Show`
    - `Interview Scheduled`
    - `Interview Completed`
    - `Admitted/Enrolled`
    - `Rejected`
  - Specifically implemented in:
    - `app/admin/admin-update-status.php`
    - `app/admin/admin-set-exam.php`
    - `app/admin/admin-set-interview.php`
    - `app/admin/admin-encode-results.php`
    - `app/admin/admin-encode-interview-results.php`
    - `app/admin/admin-set-final-decision.php`
    - `resubmit-app/student/documents.php`

- **Querying Rules:**
  - Always rely on `created_at` for ordering; there is no `changed_at` column.
  - Admin dashboard’s recent activity feed and per-application history viewer use this table.

### 4.7 UI Banners & Timeline Behavior

- **Applicant Dashboard (`app/student/dashboard.php`) should show:**
  - **Status Timeline:**
    - **CHED:** 7-step timeline reflecting all phases (Registration, Documents, Exam, Interview, Final Decision).
    - **TESDA:** 5-step timeline omitting the exam stage.
    - Steps visually marked with colored progress dots based on current status.
  - **Contextual Banners:**
    - When `Exam Scheduled`:
      - Banner displaying exam date, time, venue, and passing score.
    - When `Interview Scheduled`:
      - Banner with interview date, time, venue, and format (Batch/Individual).
    - When `Documents Rejected`:
      - Warning banner describing rejection and prompting for re-upload, with a clear call-to-action.
    - When `Admitted/Enrolled`:
      - Success banner congratulating the applicant for being admitted to **Bulacan Polytechnic College**.
    - When `Rejected`:
      - Polite message describing the decision and, if applicable, guidance for future attempts.
    - When `Exam Failed` or `No Show`:
      - Informational banner explaining status and implications.
  - **Reference Number:**
    - The unique application reference number is always visible to the applicant for inquiries and support.

---

## 5. Technical Debt & Trade-offs

This section documents known limitations that are accepted in the current version, and which should guide prioritization of future enhancements.

### 5.1 Missing or Simplified Features

- **Automated Email Notifications:**  
  - No system emails are currently sent for registration, exam/interview scheduling, or final decisions.
  - All notifications are expected to be communicated manually or via external systems.

- **SMS Notifications:**  
  - There is no SMS gateway integration. All communication remains within the web UI.

- **Password Reset Flow:**
  - No “Forgot Password” or self-service password reset.
  - Password resets currently require manual intervention by an admin (e.g., direct DB update).

- **Role-Based Access Control (RBAC):**
  - There is a single admin role with full access to all admin features.
  - No separation of duties (e.g., encoder vs approver, registrar vs admission officer).

- **Soft Deletes:**
  - No records are soft-deleted. Instead, status transitions (e.g., `Rejected`) indicate logical deletion or closure.
  - Historical data stays permanently in the database and file system unless manually purged.

- **Reporting & Exports:**
  - Admin dashboard provides an on-screen view of applicants and statuses but lacks built-in CSV/Excel/PDF export.
  - Formal “generated reports of all applicants” are not yet implemented as exported files.

### 5.2 Architectural Trade-offs

- **Pure PHP & No Frameworks:**
  - **Pros:** Easy to deploy on basic hosting, minimal learning curve, fewer external dependencies.
  - **Cons:** No built-in routing, ORM, or templating; increased risk of duplicated code and ad-hoc patterns.

- **No AJAX / Page Reload UX:**
  - **Pros:** Simpler debugging and request handling; no API layer required.
  - **Cons:** Less fluid user experience, especially for large forms and admin operations with many applicants.

- **Filesystem-Based Document Storage:**
  - **Pros:** Simple implementation; avoids large BLOBs in the database.
  - **Cons:** Requires careful backup and deployment processes; scaling and multi-server setups are harder.

- **Centralized `status` ENUM:**
  - **Pros:** Enforces valid state transitions at database level; easy to reason about.
  - **Cons:** Every new status requires database and code updates; not easily extensible by non-technical staff.

---

## 6. Roadmap – New Enhancements

This section defines the functional requirements for upcoming enhancements that will build on the current system.

### 6.1 Applicant Dashboard Enhancements

#### 6.1.1 My Profile Page

- **Purpose:** Allow applicants to view and update a restricted subset of their personal data outside the main application form.
- **Core Requirements:**
  - Accessible from `app/student/dashboard.php` via a “My Profile” link.
  - Editable fields (suggested):
    - Contact information (mobile number, email)
    - Emergency contact (if present in schema)
  - Non-editable fields:
    - Core identity info (name, birthdate), once the application reaches `Application Submitted` or later.
  - All updates must:
    - Validate input.
    - Be performed via POST with redirects (no AJAX).
    - Log relevant changes where they affect admission processing (optional note in `status_history` or separate change-log table).

#### 6.1.2 Document Checklist Page

- **Purpose:** Provide applicants a clear, trackable checklist of all required documents depending on track and applicant type.
- **Core Requirements:**
  - Accessible from dashboard side navigation as “Document Checklist”.
  - Displays items such as:
    - 2x2 ID Photo
    - Report Card
    - PSA Birth Certificate
    - Transfer Credential / TOR (for transferees)
  - Each item shows:
    - Required vs optional.
    - Upload status: Not Uploaded / Uploaded / Rejected / Re-submitted / Verified.
  - Links or CTAs to the relevant upload/re-upload forms.

#### 6.1.3 Admission Guidelines Page

- **Purpose:** Present official guidelines, schedules, and instructions related to BPC admissions.
- **Core Requirements:**
  - Static content page linked from dashboard (e.g., “Admission Guidelines”).
  - Content should be easily maintainable (e.g., via a simple PHP/HTML template that can be updated by developers).
  - Should explain:
    - CHED vs TESDA track differences.
    - Requirements, deadlines, and next steps for each major status (Submitted, Verified, Scheduled, Admitted, Rejected).

### 6.2 Admin Dashboard Enhancements

#### 6.2.1 KPI Summary Cards (Extended)

- **Purpose:** Provide high-level metrics for admissions at a glance.
- **Requirements:**
  - Maintain and refine the existing stat cards (e.g., Total, Submitted, Under Review, Re-submitted, Verified, Rejected, Exam Scheduled, Exam Passed, Exam Failed, No Show, Interview Scheduled, Interview Completed, Admitted, Rejected).
  - Add CHED and TESDA breakdowns:
    - Example: “Verified (CHED)” vs “Verified (TESDA)”.
  - Cards must be computed via aggregate queries over the `applications` table, filtered by `status` and `program_category`.

#### 6.2.2 Bulk Actions

- **Purpose:** Allow admins to perform operations on multiple applicants at once.
- **Requirements:**
  - Applicant listing tables (on `admin-app/student/dashboard.php` or a dedicated view) must include a checkbox per applicant, and a “select all” checkbox per page.
  - Admin can select multiple applicants and choose an action, such as:
    - Bulk schedule exam (CHED only).
    - Bulk schedule interview (CHED or TESDA, constrained by eligible status).
    - Bulk status updates for early stages (e.g., move multiple new applications from `Application Submitted` to `Documents Under Review`).
  - Backend handlers (e.g., extended `app/admin/admin-set-exam.php`, `app/admin/admin-set-interview.php`, or new bulk endpoints) must:
    - Validate that each selected application is eligible for the chosen action.
    - Perform batch updates in a loop using prepared statements.
    - Insert corresponding `status_history` rows for each affected application.

#### 6.2.3 CSV Export

- **Purpose:** Provide exportable reports without relying on database tools.
- **Requirements:**
  - Admin can export applicant lists (current filter set) as CSV from `admin-app/student/dashboard.php`.
  - At minimum, columns should include:
    - Reference number
    - Applicant name
    - Program choices
    - Track (`program_category`)
    - Current status
    - Exam schedule/result (CHED)
    - Interview schedule/result
    - Final decision
  - Implementation details:
    - Export performed via a dedicated PHP script that:
      - Checks admin auth.
      - Applies same filters as the UI.
      - Sends CSV headers and streams data (no HTML).

### 6.3 Scheduling Logic Enhancements

#### 6.3.1 Venue Capacity Limits

- **Objective:** Prevent overbooking of exam or interview venues.
- **Requirements:**
  - Extend `exam_schedules` (and any interview schedule representation) with a `capacity` field.
  - When assigning applicants to a schedule:
    - Before inserting assignment, compute the current number of assigned applicants for the given schedule.
    - If `current_assigned + new_assignments > capacity`, block the operation and return a clear error message to the admin.
  - UI should show:
    - Capacity.
    - Current occupancy.
    - Remaining slots.

#### 6.3.2 Room-Conflict Detection

- **Objective:** Avoid multiple overlapping events in the same venue and time window.
- **Requirements:**
  - For each new exam or interview schedule:
    - Check for existing schedules in the same venue where date/time ranges overlap.
    - If conflict detected:
      - Prevent schedule creation or prompt admin to adjust timing.
  - Overlap rules (baseline):
    - Two schedules conflict if they are on the same date and their time intervals intersect (e.g., `[start_time, end_time)`).
  - All such checks must be executed in PHP using queries filtering by venue and date.

---

## 7. Appendix – Database & Status Design (Reference)

### 7.1 Core Tables (Conceptual)

- **`users`**
  - `id`, `email`, `password`, `created_at`, ...

- **`applications`**
  - `id`, `user_id`, `reference_number`, `status` (ENUM), `program_category` (ENUM `'CHED' | 'TESDA'`), `exam_schedule_id`, and fields from multi-step form (personal info, address, family, education, program choices, PSA registry number, LRN, school_type, third_choice, etc.).

- **`exam_schedules`**
  - `id`, `exam_date`, `exam_time`, `exam_venue`, `passing_score`, (planned: `capacity`), metadata.

- **`status_history`**
  - `id`, `application_id`, `old_status`, `new_status`, `changed_by`, `created_at`, `notes`.

- **`admins`**
  - `id`, `name`, `email`, `password`, `created_at`, ...

### 7.2 Future Considerations

- When adding new statuses:
  - Update `applications.status` ENUM definition.
  - Ensure all admin filters, stat cards, and timelines are updated accordingly.
  - Insert status transitions into `status_history` consistently.

---

**End of BPC iEnroll System Documentation.**  
This specification supersedes previous partial descriptions and should be treated as the authoritative reference for ongoing and future development.



## Directory Structure (Refactored)
- config/ for DB and shared config (paths.php, db.php).
- public/ for entry pages (public/admin-login.php), while index.php remains at root.
- pp/admin/ for admin pages/handlers.
- pp/student/ for student-facing pages.
- pp/auth/ for auth handlers.
- pp/handlers/ for process handlers.
- pp/shared/ for reusable components.
- docs/ for project documentation.
