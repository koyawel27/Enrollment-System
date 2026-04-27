# BPC iEnroll - Master Project State Report
**Date:** April 22, 2026  
**Project:** BPC iEnroll - Bulacan Polytechnic College Admission and Enrollment Management System

---

## 1. CORE OBJECTIVE

**Primary Goal:**  
Build a complete web-based admission management system for Bulacan Polytechnic College that handles the entire student admission pipeline from initial application submission through final enrollment decision.

**Key Requirements:**
- Students can register, complete 7-step application form, upload required documents
- Admin (Admission Officer) can review documents, schedule/grade exams, conduct interviews, and make final admission decisions
- System must separate CHED (degree programs) and TESDA (vocational/diploma) application tracks
- TESDA applicants skip the entrance exam and go directly to interview
- Full audit trail of status changes throughout the admission process

---

## 2. TECHNICAL STACK & CONSTRAINTS

### Technology Stack
- **Backend:** Pure PHP (no frameworks), MySQL (via mysqli)
- **Frontend:** Vanilla HTML/CSS/JavaScript (no frameworks)
- **Server:** XAMPP (Apache + MySQL on Windows)
- **Database:** `bpc_ienroll` on port 3307
- **Email/Notifications:** PHPMailer (SMTP transport)
- **File Structure:** `C:\xampp\htdocs\Enrollment System\`

### Key Technical Decisions
1. **No AJAX** — All form submissions use standard PHP POST (redirect after processing)
2. **Session-based auth** — Both student and admin use PHP sessions
3. **Status-driven workflow** — Application status determines which admin actions are available
4. **File uploads** — Stored in `uploads/` directory with unique timestamped filenames
5. **Prepared statements (primary pattern)** — Most write/query paths use mysqli prepared statements; some legacy/raw query usage still exists and should be gradually normalized
6. **Shared components** — Sidebar HTML and CSS extracted to includes for consistency
7. **Centralized routing constants** — `config/paths.php` defines `BASE_URL` for all redirects and internal route generation
8. **SMTP notifications** — Gmail SMTP with App Password is used for automated emails

### Database Schema (Key Tables)
```
users (id, email, password, created_at)
applications (id, user_id, reference_number, status, program_category, exam_schedule_id, ...)
exam_schedules (id, exam_date, exam_time, exam_venue, passing_score, ...)
status_history (id, application_id, old_status, new_status, changed_by, created_at, notes)
admins (id, name, email, password, created_at)
```

### Critical Constraints
- Port 3307 for MySQL (not default 3306)
- All admin pages must include `admin-auth-check.php`
- Student pages must include proper session checks
- File paths use forward slashes even on Windows
- `mysqli_stmt_bind_param()` requires variables by reference (not session arrays directly)

---

## 3. PROGRESS AUDIT

### ✅ COMPLETED FEATURES

#### Phase 0: Landing Page
- Responsive landing page with programs, timeline, FAQs
- Mobile-optimized design
- Call-to-action buttons

#### Phase 1: Student Registration & Login
- User registration with email/password
- Login system with session management
- Password hashing with `password_hash()`

#### Phase 2: 7-Step Application Form
- **Step 1:** Personal Information (name, birthdate, contact)
- **Step 2:** Address (permanent + current, region/province/city dropdowns)
- **Step 3:** Family Background (parents' info, siblings)
- **Step 4:** Educational Background (elementary, junior high, senior high)
- **Step 5:** Program Choices (CHED vs TESDA track selector, 1st/2nd/3rd choice with dynamic filtering)
- **Step 6:** Document Upload (Form 137, Birth Certificate, Good Moral, etc.)
- **Step 7:** Review & Submit (generates unique reference number)
- Multi-step navigation with progress indicator
- Data persistence across steps
- Form locking after submission

#### Phase 2B: New Fields Added
- PSA Registry Number
- Learner Reference Number (LRN)
- School Type (Public/Private)
- Third Choice (TESDA fallback for CHED applicants)
- Program Category (CHED/TESDA) with explicit radio button selection

#### Phase 3: Admin Panel - Document Verification
- Admin login/logout system
- Admin dashboard with filterable application list
- Document verification workflow:
  - Accept → `Documents Verified`
  - Reject → `Documents Rejected` (with reason)
- Rejection reason dropdown (Blurry, Incomplete, Wrong Format, Expired, etc.)
- Status history logging
- Application detail view for admin

#### Phase 3B: Document Re-upload Flow
- Rejected students can re-upload documents
- Status changes to `Documents Re-submitted`
- Re-submission counter tracked
- Purple "Re-submitted" badge shows priority in admin queue
- Form unlocks Step 6 only in reupload mode

#### Phase 4: Exam Scheduling (CHED only)
- Admin creates exam schedules (date, time, venue, type, passing score)
- Checkbox selection for batch scheduling
- Multi-column applicant cards
- Only shows `Documents Verified` + `CHED` applicants
- Links applicants to exam schedule via `exam_schedule_id`

#### Phase 4: Exam Results Encoding
- Admin encodes attendance (Present/Absent checkbox)
- Score input per applicant (0-100)
- Live validation against passing score
- Outcomes:
  - Present + Score ≥ Passing → `Exam Completed`
  - Present + Score < Passing → `Exam Failed`
  - Absent → `No Show`
- Result preview updates live as admin types
- Uses local variable for `bind_param()` to avoid reference errors

#### Phase 4: Interview Scheduling (Both tracks)
- **Batch Mode:** Select multiple applicants, one schedule for all
- **Individual Mode:** Per-row date/time/venue input
- Separate sections for CHED (exam passers) and TESDA (direct from verified)
- Stores: interview_date, interview_time, interview_venue, interview_type (Batch/Individual)
- Status → `Interview Scheduled`

#### Phase 4: Interview Results
- Admin encodes attendance + Pass/Fail per applicant
- Outcomes:
  - Present + Pass → `Interview Completed`
  - Present + Fail → `Rejected`
  - Absent → `No Show`
- Live counters (present/absent/pass/fail)

#### Phase 4: Final Decision
- Admin reviews `Interview Completed` applicants
- Card-based UI showing exam score, program, track
- Two buttons per applicant: Admit / Reject
- Final statuses: `Admitted/Enrolled` or `Rejected`

#### Phase 4C: CHED/TESDA Separation
- CHED workflow: Documents → Exam → Interview → Decision
- TESDA workflow: Documents → Interview → Decision (no exam)
- Filters on all admin pages enforce track separation
- Student dashboard shows 5-step timeline (TESDA) or 7-step (CHED)

#### Student Dashboard Enhancements
- Status timeline with colored progress dots
- Contextual info banners:
  - Exam scheduled (shows date/time/venue/passing score)
  - Interview scheduled (shows date/time/venue/type)
  - Admitted (congratulations message)
  - Rejected (polite notification)
  - Exam failed / No show (guidance)
- Reference number display
- Document rejection banner with re-upload CTA

#### Admin Dashboard Enhancements (Latest)
- 14 stat cards (Total, Submitted, Under Review, Re-submitted, Verified, Rejected, Exam Scheduled, Exam Passed, Exam Failed, No Show, Interview Scheduled, Interview Completed, Admitted, Rejected)
- CHED vs TESDA breakdown cards
- Recent activity feed (last 10 status changes from `status_history`)
- Quick action buttons (Schedule Exam, Encode Results, Schedule Interview, Final Decision)
- Updated filter dropdown with all statuses
- Track column showing CHED/TESDA badge per applicant

#### Code Refactoring
- ✅ Refactoring from flat file structure to organized app architecture is **100% complete**
- ✅ Created `app/shared/admin-sidebar.php` — reusable sidebar component for all admin pages
- ✅ Implemented shared admin CSS in `app/shared/` and applied consistently across admin interfaces
- ✅ Added `config/paths.php` and standardized all system-wide routing/redirects to use `BASE_URL`
- ✅ Audited and fixed dead links/redirects introduced during refactor
- ✅ Fixed `status_history.created_at` vs `changed_at` column name issue

---

## 4. KEY DECISIONS

### Architecture Decisions
1. **Pure PHP POST over AJAX** — Simplicity, no JavaScript framework dependencies, easier debugging
2. **Status-driven workflow** — `applications.status` column determines available admin actions
3. **Prepared statements as default** — Security-first approach in most handlers, with remaining legacy/raw SQL areas tracked for cleanup
4. **Redirect after POST** — Prevents form resubmission, clean URL after actions

### Database Design Decisions
1. **Separate `exam_schedules` table** — Allows multiple batches on different dates, reusable schedules
2. **`exam_schedule_id` foreign key** — Links applicants to specific exam schedule instead of storing date/time directly
3. **`status_history` table** — Complete audit trail, tracks who changed status when and why
4. **ENUM for `status`** — Enforces valid statuses at database level
5. **`program_category` ENUM('CHED','TESDA')** — Explicit track assignment

### Workflow Decisions
1. **TESDA skips exam** — Vocational programs don't require entrance exam per typical PH practice
2. **Third choice = TESDA fallback** — CHED applicants can pick TESDA program as safety net
3. **Re-upload unlocks Step 6 only** — Prevents editing other steps after initial submission
4. **Batch + Individual interview modes** — Flexibility for admin to group or schedule individually
5. **Exam Failed/Rejected still logged** — No deletion of records, full history retained

### UI/UX Decisions
1. **Multi-step form with progress bar** — Better UX than single long form
2. **Card-based admin interfaces** — Easier to scan than pure tables
3. **Live validation feedback** — Score preview, counter updates, etc.
4. **Contextual student banners** — Show relevant info based on current status
5. **Color-coded status badges** — Visual differentiation (blue=submitted, green=verified, red=rejected, etc.)

### Security Decisions
1. **Session-based auth** — No tokens, simple session checks
2. **Admin separate from students** — Different tables, different auth flows
3. **File upload validation** — Size limits, allowed extensions checked server-side
4. **Prepared statements as baseline + ongoing hardening** — Main mitigation against SQL injection, with targeted refactors still needed in legacy pages
5. **`htmlspecialchars()` on output** — Prevents XSS

### Technical Debt / Trade-offs Accepted
1. **Notification coverage still being expanded** — Centralized mail service is implemented, but some state transitions may still need unified trigger parity
2. **Password reset exists in basic form** — Forgot/change/reset utilities are present but should be reviewed for UX and token-hardening depth
3. **RBAC is implemented for admin roles** — Role checks exist (`super_admin`, `admission_officer`, `registrar`), with continued policy refinement expected
4. **Files stored on filesystem** — Not in database, simpler but less portable
5. **No soft deletes** — Records stay forever, status changes instead

---

## 5. PENDING TASKS

### Immediate Next Steps
1. **Delete dead code** — Remove `app/admin/admin-bulk-update-status.php` from disk (no UI trigger points to it; dangerous if accessed directly)  
2. **Diagnose Updates card `user_id` mismatch** — Verify `users.id` matches `applicant_messages.user_id` for test accounts and backfill/fix insert logic if mismatch  
3. **Continue end-to-end testing** — Full CHED + TESDA workflows + TESDA fallback decision flow

### Known Bugs / Issues (RESOLVED)
- ✅ `app/admin/admin-encode-results.php` bind_param error → Fixed by using local `$marked_by` variable
- ✅ `admin-app/student/dashboard.php` changed_at vs created_at → Fixed to use `status_history.created_at`
- ✅ Missing sidebar links on exam/interview pages → Fixed via shared sidebar include
- ✅ NULL program_category for old applicants → Fixed via SQL script
- ✅ Dead links/redirects after refactor → Audited and fixed

### Unresolved / Needs Testing
- [x] Test if `Interview Scheduled` ENUM value exists [RESOLVED April 26]
- [ ] Confirm uploaded files are accessible and not corrupted
- [ ] Test form behavior when switching between CHED/TESDA tracks mid-application
- [ ] Full CHED workflow test with OTP (register → verify docs → exam → interview → decision)
- [ ] Full TESDA workflow test
- [ ] TESDA fallback flow test (CHED exam fail + third choice → Awaiting Decision → Accept/Decline)

### Centralized Email System (Current State)
**Status:** Core centralized outbound email service is implemented and active.

**Current Files:**
- `app/shared/MailService.php` — centralized PHPMailer wrapper/service
- `config/mail_config.php` — SMTP credentials/settings (Gmail SMTP + App Password)

**Trigger Points (Phase 1):**
1. Application submission confirmation
2. Document rejection/approval notification
3. Exam/interview scheduling notification

### Missing Features (Out of Scope for Now)
- SMS notifications
- Printable application receipt
- Export to Excel/PDF reports (CSV export is already implemented)
- Advanced applicant search (status filter exists; richer search should be improved as needed)

### Future Enhancements Discussed
- Shared CSS file for admin pages (DONE in this session)
- Shared sidebar component (DONE in this session)
- Generate reports of all applicants (PARTIALLY DONE: CSV export implemented; richer report formats optional)
- Possible third user type (unclear from prof's requirements, pending clarification)

---

## 6. FILE INVENTORY

### Core Student-Facing Files
```
index.php                    — Landing page
app/auth/register.php        — Student registration
app/auth/login.php           — Student login
app/auth/logout.php          — Student logout
app/student/dashboard.php    — Student dashboard (timeline, status, banners)
app/student/application-form.php — 7-step multi-page form
app/handlers/save-step.php   — POST handler for form steps
config/db.php                — Database connection + helper functions
config/paths.php             — `BASE_URL` and path constants for system-wide routing
```

### Core Admin Files
```
public/admin-login.php               — Admin login entry page
app/auth/admin-login.php             — Admin login handler (POST target from public/admin-login.php)
app/auth/admin-logout.php            — Admin logout
app/admin/admin-dashboard.php        — Admin main dashboard (stats, recent activity, applicant table)
app/admin/admin-application-detail.php — View single applicant details
app/admin/admin-review-documents.php — Review/accept/reject uploaded documents
app/admin/admin-exam-schedule.php    — Create exam schedules, select CHED applicants
app/admin/admin-set-exam.php         — POST handler for exam scheduling
app/admin/admin-exam-results.php     — Encode exam attendance + scores
app/admin/admin-encode-results.php   — POST handler for exam results
app/admin/admin-interview-schedule.php — Schedule interviews (batch/individual)
app/admin/admin-set-interview.php    — POST handler for interview scheduling
app/admin/admin-interview-results.php — Encode interview attendance + pass/fail
app/admin/admin-encode-interview-results.php — POST handler for interview results
app/admin/admin-final-decision.php   — Admit/Reject final decision
app/admin/admin-set-final-decision.php — POST handler for final decision
config/admin-auth-check.php          — Admin session validation
```

### Shared Components
```
app/shared/admin-sidebar.php     — Reusable sidebar HTML for all admin pages
assets/admin-styles.css          — Shared admin CSS (sidebar + base layout styles)
app/shared/                      — Shared UI includes and helpers (PHP modules)
```

### Database Files
```
phase4b-migration.sql        — Added PSA, LRN, school_type, third_choice, program_category
fix-null-fields.sql          — Backfilled NULL values for legacy applicants
(Other migrations from Phase 3, Phase 4 exist but not documented here)
```

### Supporting Files
```
public/                      — Public entry points and web-accessible bootstrap pages
assets/                      — Static assets (CSS/images) + PHP partials
uploads/                     — User-uploaded documents (timestamped filenames) + `uploads/dept_logos/`
```

### Directory Structure (Current, Refactored - 100% Complete)
```
/app/admin/      — Admin interfaces and workflow modules
/app/student/    — Student dashboard and application flow
/app/auth/       — Authentication pages/handlers
/app/handlers/   — POST handlers and process logic
/app/shared/     — Shared layouts, styles, and reusable components
/public/         — Public entry routes (admin login and public-access pages)
```

---

## 7. DATABASE STATUS ENUM VALUES

**Current `applications.status` ENUM (confirmed):**
```
'Draft'
'Application Submitted'
'Documents Under Review'
'Documents Verified'
'Documents Rejected'
'Documents Re-submitted'
'Exam Scheduled'
'Exam Completed'
'Exam Failed'
'Exam No Show'
'Interview Scheduled'
'Interview Completed'
'Interview No Show'
'Awaiting Applicant Decision'
'Admitted/Enrolled'
'Rejected'
'Application Withdrawn'
```

**Backward compatibility note:**
- Legacy `'No Show'` records may still exist and should remain supported for older data.
- Canonical current statuses split this into `'Exam No Show'` and `'Interview No Show'`.

**Status Flow (CHED):**
```
Draft → Application Submitted → Documents Under Review →
[Rejected → Re-submitted → Under Review] OR
Documents Verified → Exam Scheduled → 
[Exam Completed → Interview Scheduled → Interview Completed → Admitted/Enrolled | Rejected]
[Exam Failed (with TESDA 3rd choice) → Awaiting Applicant Decision → Accept → Documents Verified (TESDA) → Interview Scheduled → ...]
[Exam Failed (with TESDA 3rd choice) → Awaiting Applicant Decision → Decline → Application Withdrawn]
[Exam Failed (no TESDA fallback) → Rejected]
[Exam No Show]
```

**Status Flow (TESDA):**
```
Draft → Application Submitted → Documents Under Review →
[Rejected → Re-submitted → Under Review] OR
Documents Verified → Interview Scheduled →
[Interview Completed → Admitted/Enrolled | Rejected]
[Interview No Show]
```

---

## 8. PROFESSOR REQUIREMENTS ALIGNMENT

Based on the uploaded image from the professor:

### Required User Types
1. ✅ **ADMIN** (Admission Officer)
   - ✅ User Management (implemented)
   - ✅ System Settings (implemented)
   - ✅ Administrative Tasks (verify docs, schedule exams/interviews, decide admissions)
   - ✅ Verifying of Uploaded Documents

2. ✅ **APPLICANTS** (Incoming Students)
   - ✅ Can Create Account
   - ✅ Can Submit Application, Upload Files

3. ✅ **Role-separated admin model currently active**
   - Current RBAC roles in system: `super_admin`, `admission_officer`, `registrar`, `program_head`
   - Implemented via `config/admin-permissions.php` and RBAC migration/configuration
   - Additional non-admin end-user role still depends on final professor clarification

### System Requirements
- ✅ Must have generated reports of all applicants (baseline satisfied via CSV export)
- ✅ Can schedule entrance exam (CHED applicants)
- ✅ Can schedule interview (both CHED and TESDA)

---

## 9. CRITICAL REMINDERS FOR FUTURE WORK

### When Adding New Admin Pages
1. Include `session_start()` at the top
2. Include `require_once 'config/admin-auth-check.php';`
3. Include sidebar: `<?php include 'app/shared/admin-sidebar.php'; ?>`
4. Link CSS: `<link rel="stylesheet" href="assets/admin-styles.css">`
5. Add page-specific CSS in separate `<style>` tag after the link

### When Modifying Status Workflow
1. Update `applications.status` ENUM if adding new status
2. Add entry to `status_history` when changing status
3. Update student dashboard timeline mapping
4. Update admin dashboard filter dropdown
5. Update stat card queries if needed

### When Using `bind_param()`
- **NEVER** pass session variables directly: `$_SESSION['admin_name']`
- **ALWAYS** assign to local variable first: `$marked_by = $_SESSION['admin_name'];`

### When Querying `status_history`
- Use `created_at` not `changed_at` (column doesn't exist)

### Database Port
- MySQL runs on **port 3307** not 3306
- Connection string: `mysqli_connect('localhost', 'root', '', 'bpc_ienroll', 3307)`

### Routing and Redirect Standard
- Use `config/paths.php` constants for all route generation and redirects
- Use `BASE_URL` as the canonical base path across admin, student, auth, and handler flows

---

## 10. SUCCESS METRICS

**Project is considered complete when:**
- [x] Students can register and submit complete application
- [x] Admins can verify/reject documents with reasons
- [x] Rejected students can re-upload documents
- [x] CHED applicants go through exam → interview → decision
- [x] TESDA applicants go directly to interview → decision
- [x] All status changes are logged in `status_history`
- [x] Student dashboard shows accurate timeline and status
- [x] Admin dashboard shows all applicants with filtering
- [x] Refactor to organized `/app/*` and `/public/` structure completed
- [x] Shared Sidebar/CSS refactoring applied and standardized
- [ ] Full end-to-end test passed for both CHED and TESDA workflows
- [ ] Professor requirements met (pending third user type clarification)
- [x] Reports baseline implemented (`admin-export-applications.php` CSV export)
- [x] Centralized PHPMailer-based email system implemented (`app/shared/MailService.php`)
- [ ] Full notification trigger parity validated across all state transitions

---

## 11. CURRENT STRUCTURE & FEATURE DELTA (ADDED)

This section supplements earlier notes and preserves prior decisions/history. It reflects the current file tree and implemented modules after the refactor.

### Current Directory Snapshot (Verified)
```
index.php
index.css
forms.css
form-script.js

app/
  admin/      (30 files)
  auth/       (9 files)
  handlers/   (6 files)
  shared/     (admin-sidebar.php, MailService.php)
  student/    (5 files)

assets/
config/
database/
docs/
public/       (admin-login.php)
uploads/
PHPMailer/
```

### Current `app/admin/` Pages and Handlers (Complete)
```
admin-add-note.php
admin-application-detail.php
admin-applications.php
admin-bulk-notify.php
admin-bulk-update-status.php
admin-dashboard.php
admin-delete-admin.php
admin-encode-interview-results.php
admin-encode-results.php
admin-exam-results.php
admin-exam-schedule.php
admin-exam-view.php
admin-export-applications.php
admin-final-decision.php
admin-interview-results.php
admin-interview-schedule.php
admin-manage-programs.php
admin-reevaluate-exam-failed.php
admin-reschedule-no-show.php
admin-review-documents.php
admin-save-admin.php
admin-save-program.php
admin-save-settings.php
admin-set-exam.php
admin-set-final-decision.php
admin-set-interview.php
admin-system-settings.php
admin-toggle-admin-status.php
admin-update-status.php
admin-user-management.php
```

### Additional Modules Present (Not fully reflected in older sections)
```
app/shared/MailService.php
config/mail_config.php
config/applicant-messages.php
config/programs.php
config/program-cutoffs.php
database/admins_rbac_migration.sql
database/applicant_messages_migration.sql
database/applications_add_assigned_program.sql
database/exam_schedules_add_schedule_type.sql
database/system_settings_migration.sql
```

### Implemented Admin Features That Were Previously Listed As Future/Pending
- Bulk status updates are implemented via `app/admin/admin-bulk-update-status.php`.
- Bulk applicant notifications are implemented via `app/admin/admin-bulk-notify.php`.
- Admin user management is implemented via:
  - `app/admin/admin-user-management.php`
  - `app/admin/admin-save-admin.php`
  - `app/admin/admin-delete-admin.php`
  - `app/admin/admin-toggle-admin-status.php`
- System settings are implemented via:
  - `app/admin/admin-system-settings.php`
  - `app/admin/admin-save-settings.php`
- CSV export is implemented via `app/admin/admin-export-applications.php`.
- Operational fixes are implemented via:
  - `app/admin/admin-reevaluate-exam-failed.php`
  - `app/admin/admin-reschedule-no-show.php`

### Routing/Path Standard (Current)
- Root landing remains `index.php` in project root (intentionally not moved).
- Admin login entry is `public/admin-login.php`.
- Runtime modules are under `app/*`.
- Redirects and route generation are standardized to `BASE_URL`/`config/paths.php` conventions.

### File Inventory Corrections (for clarity)
- Correct admin dashboard path is `app/admin/admin-dashboard.php` (not `app/admin/dashboard.php`).
- Document verification is handled by `app/admin/admin-application-detail.php` and `app/admin/admin-review-documents.php`; there is no active `app/admin/admin-verify-documents.php`.
- Shared admin stylesheet is `assets/admin-styles.css` (not `app/shared/admin-styles.css`).

---

**END OF MASTER PROJECT STATE REPORT**

*This document serves as the Single Source of Truth for the BPC iEnroll project.*  
*Last Updated: April 22, 2026*

---

## 12. APRIL 2026 REPOSITORY REALITY CHECK (ADDITIVE UPDATE)

This additive section preserves older narrative while correcting current-state architecture and implementation details based on full repository scan.

### Architecture Reality (Current)
- System remains **script-oriented PHP** (page endpoints + POST handlers), not a framework MVC application.
- Runtime routing is file-based (direct endpoint access + redirect), with shared path constants from `config/paths.php`.
- Admin guard/middleware pattern is enforced via `config/admin-auth-check.php`.
- Operational helper/config modules now include:
  - `config/admin-permissions.php` (RBAC and role checks)
  - `config/applicant-messages.php` (in-app applicant messaging helpers)
  - `config/programs.php` and `config/program-cutoffs.php` (program/rules metadata)

### Database/Migrations (Current files observed)
- `database/admins_rbac_migration.sql`
- `database/applicant_messages_migration.sql`
- `database/system_settings_migration.sql`
- `database/applications_add_assigned_program.sql`
- `database/exam_schedules_add_schedule_type.sql`

### Modules Confirmed as Implemented
- Admin bulk updates: `app/admin/admin-bulk-update-status.php`
- Admin bulk notifications: `app/admin/admin-bulk-notify.php`
- Admin user lifecycle: `app/admin/admin-user-management.php`, `app/admin/admin-save-admin.php`, `app/admin/admin-delete-admin.php`, `app/admin/admin-toggle-admin-status.php`
- System settings: `app/admin/admin-system-settings.php`, `app/admin/admin-save-settings.php`
- Applicant message backfill utility: `app/handlers/backfill-applicant-messages.php`
- CSV exports/report baseline: `app/admin/admin-export-applications.php`

### Corrections to Earlier File Notes
- Canonical admin dashboard file is `app/admin/admin-dashboard.php`.
- Document review actions are centered in `app/admin/admin-review-documents.php` and related status handlers.
- Shared admin stylesheet path in active use is `assets/admin-styles.css`.

### Tooling and Delivery Gaps (Explicitly Recorded)
- No automated test suite (no `phpunit` test harness detected).
- No CI workflow configuration detected (no repository CI pipeline files present).
- No SMS or payment gateway integration detected.
- Configuration remains constant-based (DB/SMTP), not full `.env`-driven secret management.

---

## 13. FULL TRACKED FILE INDEX (ADDITIVE APPENDIX)

Source of truth for this appendix is the **current working directory on disk** (project root: `C:\xampp\htdocs\Enrollment System\`) as of the most recent auto-sync.

### Root-level tracked files
```
.gitignore
folder_structure.txt
form-script.js
forms.css
image.png
index.css
index.php
```

### `assets/`
```
assets/admin-styles.css
assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png
assets/programs_accordion.php
```

### `app/auth/`
```
app/auth/admin-login.php
app/auth/admin-logout.php
app/auth/change-password.php
app/auth/forgot-password.php
app/auth/login.php
app/auth/logout.php
app/auth/register.php
app/auth/resend-otp.php
app/auth/verify-email.php
```

### `config/`
```
config/admin-auth-check.php
config/admin-permissions.php
config/applicant-messages.php
config/db.php
config/mail_config.php
config/paths.php
config/program-cutoffs.php
config/programs.php
```

### `database/`
```
database/admins_rbac_migration.sql
database/applicant_messages_migration.sql
database/applications_add_assigned_program.sql
database/applications_add_interview_score_remarks.sql
database/exam_schedules_add_schedule_type.sql
database/system_settings_migration.sql
```

### `docs/`
```
docs/AUDIT_REPORT.md
docs/BPC_iEnroll_Code_Map.md
docs/BPC_iEnroll_System_Documentation.md
docs/MASTER_PROJECT_STATE.md
docs/PROJECT_PHASE2_STATUS.md
docs/PROJECT_PHASE3_STATUS.md
```

### `public/`
```
public/admin-login.php
```

### Appendix notes
- This appendix is a **working-directory** inventory (disk is truth).
- The project includes an `uploads/` directory containing runtime-uploaded artifacts (including `uploads/dept_logos/`).

---

## 14. APRIL 18, 2026 FULL PROJECT SCAN (AUTHORITATIVE ADDITIVE UPDATE)

This section is an **additive correction** based on scanning the entire working directory (not just Git-tracked files). It preserves the historical narrative above while updating the **current reality** of the codebase, file locations, and architectural decisions.

### 14.1 Canonical runtime layout (current)

The project currently contains **two parallel layouts**:

- **Refactored canonical layout (active)**: the app is organized under `app/` and uses `config/paths.php` constants (`APP_PATH`, `CONFIG_PATH`, `BASE_URL`) for includes and redirects.
  - Evidence: `config/paths.php` defines `APP_PATH` as `BASE_PATH . '/app'`, and current pages (e.g., `app/admin/admin-dashboard.php`) require `config/paths.php` and then include config modules via `CONFIG_PATH`.

- **Legacy root-level endpoints (still present)**: many files also exist at the repository root (e.g., `admin-dashboard.php`, `dashboard.php`, `submit-application.php`), which mirror the refactored endpoints under `app/*`.
  - These appear to be **legacy duplicates** kept alongside the refactor. The `app/*` versions are the ones aligned with the refactor conventions described earlier in this document.

### 14.2 Current directory map (verified in working tree)

**Primary runtime directories:**
- **`app/`**: primary application modules (admin, student, auth, handlers, shared)
- **`config/`**: DB connection, routing constants, RBAC, program logic, mail config, messaging helpers
- **`public/`**: public entry pages (e.g., admin login UI)
- **`assets/`**: static assets (CSS/images) + a PHP partial observed (`assets/programs_accordion.php`)
- **`database/`**: schema migration scripts (`*.sql`)
- **`docs/`**: documentation (this file lives here in the working tree)
- **`uploads/`**: on-disk file upload storage; includes `uploads/dept_logos/` used by the landing/program cards
- **`PHPMailer/`**: vendored PHPMailer distribution folder in the working tree

### 14.3 File inventory corrections and additions (code files)

#### Student-facing modules (canonical, under `app/student/`)
- `app/student/dashboard.php`
- `app/student/application-form.php`
- `app/student/documents.php`
- `app/student/resubmit-documents.php`
- `app/student/profile.php`

#### Student POST handlers (canonical, under `app/handlers/`)
- `app/handlers/save-step.php`
- `app/handlers/submit-application.php`
- `app/handlers/applicant-decision.php` (used for applicant decisions when status requires input)
- `app/handlers/backfill-applicant-messages.php` (utility/backfill)
- `app/handlers/generate-hash.php` and `app/handlers/reset-passwords.php` (utilities)

#### Auth modules (canonical, under `app/auth/`)
- `app/auth/login.php`, `app/auth/register.php`, `app/auth/logout.php`
- `app/auth/admin-login.php`, `app/auth/admin-logout.php`
- `app/auth/forgot-password.php`, `app/auth/change-password.php`

#### Admin modules (canonical, under `app/admin/`)

In addition to the admin files already listed in Section 11, the working tree currently includes **at least** the following admin pages/handlers that should be treated as part of the active admin system:
- `app/admin/admin-exam-view.php` (exam schedule/details view page)
- `app/admin/admin-manage-programs.php` and `app/admin/admin-save-program.php` (program management)

#### Config/modules (canonical, under `config/`)

In addition to the config files already listed earlier:
- `config/paths.php` exists and is central to the refactored layout (`BASE_PATH`, `APP_PATH`, `CONFIG_PATH`, `PUBLIC_PATH`, `DOCS_PATH`, `BASE_URL`).
- `config/mail_config.php` exists in the working tree and is used by the mail system.

#### Public entry pages
- `public/admin-login.php` is present and is the public-facing admin login page for the refactored layout.

### 14.4 Database migrations (working tree additions)

The `database/` folder includes (in addition to the previously documented migrations):
- `database/applications_add_interview_score_remarks.sql`
- `database/applications_add_assigned_program.sql`
- `database/admins_rbac_migration.sql`
- `database/applicant_messages_migration.sql`
- `database/exam_schedules_add_schedule_type.sql`
- `database/system_settings_migration.sql`

### 14.5 RBAC model (confirmed)

Admin role-based access control is implemented in `config/admin-permissions.php` and currently defines roles including:
- `super_admin`
- `admission_officer`
- `registrar`
- `program_head`

The guard pattern remains:
- Include `config/admin-auth-check.php` for admin session validation.
- Use `require_admin_role([...])` to restrict pages by role.
- Program heads additionally apply a **program-scope data filter** for application lists via a helper that builds a SQL filter based on departments/programs assigned to the program head.

### 14.6 Security note recorded (do not ignore)

`config/mail_config.php` contains SMTP configuration used by PHPMailer. If this file includes real credentials (e.g., Gmail username/app password), it should be treated as a **secret** and managed outside of version control for production-quality hygiene (e.g., environment variables / untracked local config).

### 14.7 Git vs working tree mismatch (documentation note)

The repository currently shows a mismatch between **Git-tracked/staged files** and the **refactored working tree** (e.g., many root-level endpoints are staged while the refactored `app/` layout is present as untracked in Git status at time of scan). This document’s “project state” should therefore be read as describing the **working, refactored runtime layout**, not merely the staged Git index snapshot.

---

## 15. APRIL 22, 2026 FULL-REPO ADDITIVE CORRECTIONS (CURRENT AUTHORITATIVE STATE)

This section is additive and preserves earlier historical narrative. It captures the current file set, implemented features, and architecture decisions verified from the present working tree.

### 15.1 Verified active module/file inventory (canonical paths)

#### `app/admin/` (30 files)
```
admin-add-note.php
admin-application-detail.php
admin-applications.php
admin-bulk-notify.php
admin-bulk-update-status.php
admin-dashboard.php
admin-delete-admin.php
admin-encode-interview-results.php
admin-encode-results.php
admin-exam-results.php
admin-exam-schedule.php
admin-exam-view.php
admin-export-applications.php
admin-final-decision.php
admin-interview-results.php
admin-interview-schedule.php
admin-manage-programs.php
admin-reevaluate-exam-failed.php
admin-reschedule-no-show.php
admin-review-documents.php
admin-save-admin.php
admin-save-program.php
admin-save-settings.php
admin-set-exam.php
admin-set-final-decision.php
admin-set-interview.php
admin-system-settings.php
admin-toggle-admin-status.php
admin-update-status.php
admin-user-management.php
```

#### `app/auth/` (9 files)
```
admin-login.php
admin-logout.php
change-password.php
forgot-password.php
login.php
logout.php
register.php
resend-otp.php
verify-email.php
```

#### `app/handlers/` (6 files)
```
applicant-decision.php
backfill-applicant-messages.php
generate-hash.php
reset-passwords.php
save-step.php
submit-application.php
```

#### `app/student/` (5 files)
```
application-form.php
dashboard.php
documents.php
profile.php
resubmit-documents.php
```

#### `config/` (8 files)
```
admin-auth-check.php
admin-permissions.php
applicant-messages.php
db.php
mail_config.php
paths.php
program-cutoffs.php
programs.php
```

#### `database/` (6 files)
```
admins_rbac_migration.sql
applicant_messages_migration.sql
applications_add_assigned_program.sql
applications_add_interview_score_remarks.sql
exam_schedules_add_schedule_type.sql
system_settings_migration.sql
```

### 15.2 Current feature coverage additions/corrections

- OTP email verification is active in the auth flow (`app/auth/verify-email.php`, `app/auth/resend-otp.php`).
- Program catalog management is implemented (`app/admin/admin-manage-programs.php`, `app/admin/admin-save-program.php`) and backed by config/data logic (`config/programs.php`).
- Applicant fallback decision flow is implemented (`config/program-cutoffs.php`, `app/handlers/applicant-decision.php`), including applicant-side accept/decline handling.
- Operational recovery actions are implemented and active:
  - `app/admin/admin-reevaluate-exam-failed.php`
  - `app/admin/admin-reschedule-no-show.php`
- Messaging and notifications include centralized outbound mail (`app/shared/MailService.php`) and in-app applicant messaging helper modules (`config/applicant-messages.php`).

### 15.3 Status vocabulary correction (important)

Older sections that use generic `No Show` should be interpreted as historical shorthand. Current runtime code uses more specific statuses in active modules:

```
Exam No Show
Interview No Show
Awaiting Applicant Decision
Application Withdrawn
```

Compatibility note: legacy `'No Show'` remains supported for older records.

These appear in current admin/student workflow files (notably `app/admin/admin-dashboard.php`, `app/admin/admin-encode-results.php`, `app/student/dashboard.php`, `app/handlers/applicant-decision.php`).

### 15.4 Architecture decisions (current practical reality)

1. **Script-first PHP architecture remains intentional** — endpoint files + handler files, no framework router.
2. **Canonical runtime tree is `app/*` + `config/*` + `public/*`** — current working tree does **not** include root-level legacy admin/student/auth endpoints (beyond `index.php`); runtime endpoints are under `app/*` and `public/*`.
3. **RBAC scope now explicitly includes `program_head`** with department/program-level filtering (`config/admin-permissions.php`).
4. **Prepared statements remain the dominant pattern, but mixed SQL still exists** in selected list/filter/admin query paths and should continue to be normalized.
5. **Config remains constant-based (not full env-driven)** for DB/SMTP and path constants.

### 15.5 Security and operations notes (explicit carry-forward)

- `config/mail_config.php` should be treated as sensitive configuration if it contains real SMTP credentials.
- The project still has no documented automated test harness/CI pipeline in this repository state; manual end-to-end verification remains necessary after workflow changes.

### 15.6 Canonical interpretation rule for future updates

If any conflict exists between older sections and current runtime implementation:
- Prefer `app/*`, `config/*`, and `public/*` module evidence from latest scan.
- Preserve older entries as historical context unless explicitly superseded by this section.

---

## 16. APRIL 26, 2026 SESSION SYNC (ADDITIVE UPDATE)

This section is additive and reflects **April 26, 2026** changes and confirmations. If any contradiction exists between earlier sections and this one, treat this section as newer.

### 16.1 Files modified (April 26)
```
app/admin/admin-exam-results.php
app/admin/admin-exam-view.php
app/admin/admin-dashboard.php
app/shared/admin-sidebar.php
```

### 16.2 Key decisions (April 26)
- Score progress bar removed from `app/admin/admin-exam-view.php` (score is displayed as a colored number only).
- `app/admin/admin-bulk-update-status.php` still exists on disk, but the UI trigger is removed; deletion is deferred and explicitly prioritized for cleanup.
- `'No Show'` kept for backward compatibility; `'Exam No Show'` and `'Interview No Show'` are canonical going forward.
- Program head dashboard status filter is intentionally restricted to interview-stage-and-beyond statuses **plus** `'Documents Verified'` (TESDA applicants awaiting scheduling).
- Dashboard titles are role-aware:
  - `super_admin` → "Admission Management Console"
  - `admission_officer` → "Admission Officer Dashboard"
  - `registrar` → "Registrar Dashboard"
  - `program_head` → "Program Head Console"
- Program head subtitle shows assigned program names joined by ` / `, pulled from `get_all_programs($conn)` via `get_head_program_codes()`.

### 16.3 Program head scoping (confirmed working)
- Source table: `program_head_departments` (`head_id`, `program_code`).
- `get_head_program_codes($conn)` returns the program codes for the logged-in program head.
- `get_head_program_filter($conn)` builds: `AND a.first_choice IN (?, ?, ...)`.
- Filter applies to:
  - `app/admin/admin-dashboard.php`
  - `app/admin/admin-interview-schedule.php`
  - `app/admin/admin-interview-results.php`
  - `app/admin/admin-final-decision.php`
  - `app/admin/admin-exam-view.php`
- Why **first_choice only**: filtering on second/third choice caused cross-department leakage.

### 16.4 Program head dashboard visibility (effective status set)
Program heads can see applicants at:
```
Documents Verified
Exam Completed
Awaiting Applicant Decision
Interview Scheduled
Interview Completed
Interview No Show
Admitted/Enrolled
Rejected
```
…scoped to `first_choice IN (assigned program codes)`.

### 16.5 Exam results “Results History” visibility and badge map
`app/admin/admin-exam-results.php` Results History table includes:
```
Exam Completed
Exam Failed
Exam No Show
Awaiting Applicant Decision
Application Withdrawn
```
Badge map:
- Exam Completed → green `✓ Passed`
- Exam Failed → red `✕ Failed`
- Exam No Show → gray `— No Show`
- Awaiting Applicant Decision → amber `⟳ TESDA Offer Pending`
- Application Withdrawn → gray `✕ Withdrawn`

### 16.6 Admin logout modal pattern (shared sidebar)
Implemented in `app/shared/admin-sidebar.php`:
- Modal HTML must be outside `<script>` tags.
- Functions are exposed as `window.showAdminLogoutModal` / `window.hideAdminLogoutModal` (supports inline `onclick`).
- Close behaviors: Escape key + backdrop click.
- Submit lockout pattern uses a `submitting` flag to prevent double-submit.

### 16.7 Technical debt and diagnostics (explicit)
- `app/admin/admin-bulk-update-status.php` is dead code and should be deleted (no UI trigger; risky direct access).
- `config/mail_config.php` contains real SMTP credentials and should not be committed in production hygiene.
- No automated tests / CI pipeline.
- Pending diagnostic: Updates card `user_id` mismatch (verify `users.id` matches `applicant_messages.user_id` for affected test accounts).

### 16.8 Resolved vs pending (April 26)
✅ Resolved April 26:
- `admin-exam-results.php` Results History includes `'Awaiting Applicant Decision'` + `'Application Withdrawn'` with correct badges.
- `admin-exam-view.php` UI/UX upgrade and updated query/badge logic for TESDA fallback statuses.
- Program head dashboard filter expanded (includes `'Documents Verified'`, `'Awaiting Applicant Decision'`, `'Interview No Show'`).
- Program head scoping validated (multi-program heads supported via multiple rows).
- Role-aware dashboard header and program-head subtitle implemented.
- Admin logout modal implemented across admin roles.

❌ Pending priorities (carry-forward from April 26):
- **Priority 1**: Delete `app/admin/admin-bulk-update-status.php`.
- **Priority 2**: Diagnose Updates card `user_id` mismatch (users ↔ applicant_messages).
- **Priority 3**: Continue end-to-end testing (CHED + TESDA + TESDA fallback).

---

## Last Auto-Sync

**Timestamp:** 2026-04-26 23:59:59

### Summary of changes applied
- Synced `MASTER_PROJECT_STATE.md` with the April 26 session summary:
  - Updated status ENUM list and CHED/TESDA flows (added Exam/Interview No Show, Awaiting Applicant Decision, Application Withdrawn; documented legacy `No Show` compatibility).
  - Updated RBAC role list to include `program_head` where missing.
  - Corrected admin CSS reminder link to `assets/admin-styles.css`.
  - Updated `app/admin/` inventory list to include `admin-exam-view.php`, `admin-manage-programs.php`, `admin-save-program.php`.
  - Added a full additive section capturing April 26 file modifications, program head scoping, exam results history visibility, logout modal pattern, resolved items, and the April 26 Priority 1–3 pending list.
