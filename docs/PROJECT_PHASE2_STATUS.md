# Phase 2 Checklist (Progress + What’s Missing)

This file tracks what we have **already implemented** vs what still needs work for Phase 2.

## Key decisions (from our discussion)

- **Draft saving**:
  - Per-step form POST (no AJAX). Each step submits to `app/handlers/save-step.php`, saves to DB, redirects.
  - Uses **PHP + MySQL** when logged in.
- **After submission**:
  - JavaScript `confirm()` dialog before form POST: “Are you sure you want to submit? This cannot be undone.”
  - On submit: mark application as **Application Submitted** in DB, generate server-side reference number, redirect to dashboard.
  - Submitted applications become **read-only**: no Prev/Next/Save Draft/Submit, and inputs are disabled.
  - Dashboard shows **View Application** for submitted apps.

## Tasks list status

### 1) Convert `application-form.html` to `app/student/application-form.php`
- **Done**: `app/student/application-form.php` exists and is now a real PHP page (session + DB + context injection).

### 2) Check if user is logged in (session check)
- **Done**:
  - `app/student/dashboard.php` already enforced session.
  - `app/student/application-form.php` now enforces session (redirects to `index.php` if not logged in).

### 3) Load draft data from database (if exists)
- **Done**:
  - `app/student/application-form.php` loads full row from DB and pre-fills each step’s fields based on `?step=X`.

### 4) Wrap each step in `<form method="POST">`
- **Done**:
  - Each step is a separate `<form method="POST" action="app/handlers/save-step.php">` (steps 1–6) or `action="app/handlers/submit-application.php"` (step 7).
  - Step controlled by PHP via `?step=X`.

### 5) Create `app/handlers/save-step.php` handler for each step
- **Done**:
  - `app/handlers/save-step.php` receives POST from steps 1–6, validates, saves to DB, updates `current_step`, redirects to `app/student/application-form.php?step=N`.

### 6) Save data to database on "Next" button
- **Done**:
  - "Next" is a submit button. Form POSTs to `app/handlers/save-step.php` → DB → redirect to next step.
  - "Save Draft" POSTs to `app/handlers/save-step.php` → DB → redirect to same step.

### 7) Update `current_step` in database
- **Done**:
  - `app/handlers/save-step.php` updates `applications.current_step` on each successful POST.

### 8) Final submit: Generate reference number, set status to "Submitted"
- **Done**:
  - `app/handlers/submit-application.php` generates reference number server-side and updates DB:
    - `status = 'Application Submitted'`
    - `submitted = 1`
    - `reference_number = ...`
    - `current_step = 7`

### 9) Redirect to dashboard after submission
- **Done**:
  - `app/handlers/submit-application.php` redirects to `app/student/dashboard.php` after successful submit (no AJAX).

### 10) Step 7 review shows all entered information
- **Done**:
  - Review summary built in PHP from DB row. Includes key fields from **Steps 1–6**, including transferee-only fields.
  - Each section has an **Edit** link that jumps back to its step.

## Notes / Dependencies

- **File uploads**:
  - Handled in `app/handlers/save-step.php` (step 6) using `$_FILES` and `move_uploaded_file`.
  - Saved under `uploads/` directory; paths stored in `applications` columns (`id_photo_path`, `grades_path`, etc.).
  - Step 7 form POSTs only certify and dataPrivacy; all other data read from DB.

