---
name: ienroll-structure-refactor
overview: Plan-only blueprint to migrate BPC iEnroll from a flat root into modular folders without breaking routing, includes, or MySQL connectivity.
todos:
  - id: confirm-mapping
    content: Validate and approve current->new mapping for all major PHP and MD files.
    status: pending
  - id: approve-basepath
    content: Approve BASE_PATH strategy and constants in config/paths.php.
    status: pending
  - id: approve-doc-refresh
    content: Approve markdown update scope across core and status docs.
    status: pending
  - id: approve-safe-sequence
    content: Approve execution order and DB port 3307 safety checks for go phase.
    status: pending
isProject: false
---

# BPC iEnroll Structure Refactor Plan

## Scope And Guardrails

- Planning phase only: no file moves, no code edits, no Composer/runtime actions until your go signal.
- Preserve behavior while reorganizing paths and include statements.
- Keep database connectivity unchanged (`DB_PORT` remains `3307` in `config/db.php`).

## Current To New Path Mapping (Major Files)

### Config

- `config/db.php` -> `config/db.php` (unchanged)
- `config/admin-auth-check.php` -> `config/admin-auth-check.php` (unchanged)
- `config/admin-permissions.php` -> `config/admin-permissions.php` (unchanged)
- `config/programs.php` -> `config/programs.php` (unchanged)
- `config/program-cutoffs.php` -> `config/program-cutoffs.php` (unchanged)
- `config/applicant-messages.php` -> `config/applicant-messages.php` (unchanged)
- `(new)` -> `config/paths.php`

### Public (web-entry pages)

- `index.php` -> `public/index.php`
- `admin-login.php` -> `public/admin-login.php`

### App Admin (admin views/actions)

- `admin-dashboard.php` -> `app/admin/admin-dashboard.php`
- `admin-applications.php` -> `app/admin/admin-applications.php`
- `admin-application-detail.php` -> `app/admin/admin-application-detail.php`
- `admin-review-documents.php` -> `app/admin/admin-review-documents.php`
- `admin-update-status.php` -> `app/admin/admin-update-status.php`
- `admin-bulk-update-status.php` -> `app/admin/admin-bulk-update-status.php`
- `admin-add-note.php` -> `app/admin/admin-add-note.php`
- `admin-bulk-notify.php` -> `app/admin/admin-bulk-notify.php`
- `admin-export-applications.php` -> `app/admin/admin-export-applications.php`
- `admin-exam-schedule.php` -> `app/admin/admin-exam-schedule.php`
- `admin-set-exam.php` -> `app/admin/admin-set-exam.php`
- `admin-exam-results.php` -> `app/admin/admin-exam-results.php`
- `admin-encode-results.php` -> `app/admin/admin-encode-results.php`
- `admin-reevaluate-exam-failed.php` -> `app/admin/admin-reevaluate-exam-failed.php`
- `admin-reschedule-no-show.php` -> `app/admin/admin-reschedule-no-show.php`
- `admin-interview-schedule.php` -> `app/admin/admin-interview-schedule.php`
- `admin-set-interview.php` -> `app/admin/admin-set-interview.php`
- `admin-interview-results.php` -> `app/admin/admin-interview-results.php`
- `admin-encode-interview-results.php` -> `app/admin/admin-encode-interview-results.php`
- `admin-final-decision.php` -> `app/admin/admin-final-decision.php`
- `admin-set-final-decision.php` -> `app/admin/admin-set-final-decision.php`
- `admin-user-management.php` -> `app/admin/admin-user-management.php`
- `admin-save-admin.php` -> `app/admin/admin-save-admin.php`
- `admin-delete-admin.php` -> `app/admin/admin-delete-admin.php`
- `admin-toggle-admin-status.php` -> `app/admin/admin-toggle-admin-status.php`
- `admin-system-settings.php` -> `app/admin/admin-system-settings.php`
- `admin-save-settings.php` -> `app/admin/admin-save-settings.php`

### App Student

- `dashboard.php` -> `app/student/dashboard.php`
- `application-form.php` -> `app/student/application-form.php`
- `profile.php` -> `app/student/profile.php`
- `documents.php` -> `app/student/documents.php`
- `resubmit-documents.php` -> `app/student/resubmit-documents.php`

### App Auth

- `auth/login.php` -> `app/auth/login.php`
- `auth/register.php` -> `app/auth/register.php`
- `auth/logout.php` -> `app/auth/logout.php`
- `auth/forgot-password.php` -> `app/auth/forgot-password.php`
- `auth/change-password.php` -> `app/auth/change-password.php`
- `auth/admin-login.php` -> `app/auth/admin-login.php`
- `auth/admin-logout.php` -> `app/auth/admin-logout.php`

### App Handlers

- `save-step.php` -> `app/handlers/save-step.php`
- `submit-application.php` -> `app/handlers/submit-application.php`
- `generate-hash.php` -> `app/handlers/generate-hash.php`
- `backfill-applicant-messages.php` -> `app/handlers/backfill-applicant-messages.php`
- `reset-passwords.php` -> `app/handlers/reset-passwords.php`

### App Shared

- `includes/admin-sidebar.php` -> `app/shared/admin-sidebar.php`

### Docs

- `AUDIT_REPORT.md` -> `docs/AUDIT_REPORT.md`
- `BPC_iEnroll_Code_Map.md` -> `docs/BPC_iEnroll_Code_Map.md`
- `BPC_iEnroll_System_Documentation.md` -> `docs/BPC_iEnroll_System_Documentation.md`
- `MASTER_PROJECT_STATE.md` -> `docs/MASTER_PROJECT_STATE.md`
- `PROJECT_PHASE2_STATUS.md` -> `docs/PROJECT_PHASE2_STATUS.md`
- `PROJECT_PHASE3_STATUS.md` -> `docs/PROJECT_PHASE3_STATUS.md`

## BASE_PATH Strategy (`config/paths.php`)

- Add a single root resolver:
  - Define `BASE_PATH` as project root via `realpath(__DIR__ . '/../')`.
  - Define helper constants for consistency:
    - `APP_PATH = BASE_PATH . '/app'`
    - `CONFIG_PATH = BASE_PATH . '/config'`
    - `PUBLIC_PATH = BASE_PATH . '/public'`
    - `DOCS_PATH = BASE_PATH . '/docs'`
- Every PHP file that currently uses fragile relative includes (`../config/db.php`, `includes/admin-sidebar.php`, etc.) will first include `config/paths.php`, then use absolute filesystem requires, e.g. `require_once CONFIG_PATH . '/db.php';`.
- Redirect URLs stay web-relative (or centralized as a follow-up) while `require_once` becomes filesystem-absolute to prevent breakage from deeper nesting.

## Documentation Update Plan

- Update path references and structure diagrams in:
  - `docs/BPC_iEnroll_Code_Map.md`
  - `docs/BPC_iEnroll_System_Documentation.md`
  - `docs/MASTER_PROJECT_STATE.md`
  - `docs/PROJECT_PHASE2_STATUS.md`
  - `docs/PROJECT_PHASE3_STATUS.md`
  - `docs/AUDIT_REPORT.md` (if it references old root paths)
- Perform doc cleanup tasks:
  - Replace old root-level file references with new module paths.
  - Update any architecture trees to show `config/public/app/docs` layout.
  - Add a short migration note section listing “old path -> new path” for maintainers.
  - Validate internal markdown links so they resolve after move.

## Safe Migration Steps (Execution Sequence For Go Phase)

- Step 1: Create target directories and `config/paths.php` first.
- Step 2: Move docs to `docs/` and update markdown links/references.
- Step 3: Move shared/auth/handler/student/admin files to `app/*` buckets.
- Step 4: Move public entry pages to `public/`.
- Step 5: Update all `require_once/include` statements to use `BASE_PATH` constants.
- Step 6: Run smoke checks for main flows (landing, student, admin, auth, handlers).
- Step 7: Verify DB connectivity by hitting pages that require `config/db.php`; keep `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, and especially `DB_PORT=3307` unchanged.

## MySQL Port 3307 Protection

- `config/db.php` already defines `DB_PORT` as `3307` and uses it in `mysqli_connect(...)`.
- Migration will only change file locations and include paths, not DB constants.
- Post-move validation will explicitly confirm that runtime still connects using port `3307`.

