# BPC iEnroll - Deep Audit Report
**Date:** February 2026  
**Reference:** MASTER_PROJECT_STATE.md vs actual codebase

---

## 1. VERIFICATION: Completed Features vs Actual Code

### ✅ MATCHES

| MASTER Claim | Actual Code | Notes |
|--------------|-------------|-------|
| Port 3306 | `config/db.php` line 9: `define('DB_PORT', 3306)` | ✓ Correct |
| Pure PHP, no AJAX | All forms use POST + redirect | ✓ Correct |
| Session-based auth | Student: `$_SESSION['user_id']`, Admin: `$_SESSION['admin_id']` | ✓ Correct |
| Prepared statements | All queries use `mysqli_prepare` + `bind_param` | ✓ Correct |
| status_history logging | Used in admin-update-status, admin-set-exam, admin-set-interview, admin-encode-results, admin-encode-interview-results, admin-set-final-decision, resubmit-documents | ✓ Correct |
| CHED/TESDA separation | Enforced in admin-exam-schedule (CHED only), admin-exam-results (CHED only), admin-interview-schedule (both), admin-set-interview (both) | ✓ Correct |
| program_category in Step 5 | app/handlers/save-step.php saves `program_category` from programTrack | ✓ Correct |
| 7-step application form | app/student/application-form.php with steps 1–7 | ✓ Correct |
| Document verification | Accept/Reject in app/admin/admin-application-detail.php | ✓ Correct |
| app/shared/admin-sidebar.php | Used by all 7 admin HTML pages | ✓ Correct |
| assets/admin-styles.css | Linked by all 7 admin HTML pages | ✓ Correct |
| config/admin-auth-check.php | Included by all admin pages | ✓ Correct |

### ❌ DISCREPANCIES

| MASTER Claim | Actual Code | Issue |
|--------------|-------------|-------|
| **Project: Bestlink College of the Philippines** | **Bulacan Polytechnic College (BPC)** throughout codebase | **College name mismatch.** MASTER says Bestlink; index.php, app/student/application-form.php, admin-login, footer all say Bulacan Polytechnic College. Only app/student/dashboard.php line 399 says "Bestlink College of the Philippines" in the Admitted banner. |
| **admin-verify-app/student/documents.php** | Does not exist | Document verification is in **app/admin/admin-application-detail.php**, not a separate page. MASTER file inventory is outdated. |
| **Step 4: elementary, junior high, senior high** | Step 4: Freshmen (SHS) vs Transferee | Form structure differs. Actual has Applicant Type → SHS info OR transferee info. No elementary/junior high fields. |
| **Step 2: region/province/city dropdowns** | Step 2: Free-text address fields | Address fields are text inputs, not dropdowns. |
| **Step 6: Form 137, Good Moral, etc.** | Step 6: 2x2 ID Photo, Report Card, PSA Birth Cert, Transfer Credential, TOR | Document names differ from MASTER. |
| **Phase 2B: PSA Registry Number, LRN, School Type, Third Choice** | ✓ Implemented | app/student/application-form.php + app/handlers/save-step.php have psa_registry_no, lrn, school_type, third_choice |

---

## 2. LOGIC CHECK: status_history & CHED/TESDA

### status_history

- **Logging:** All status changes that go through the following scripts insert into `status_history`:
  - app/admin/admin-update-status.php
  - app/admin/admin-set-exam.php
  - app/admin/admin-set-interview.php
  - app/admin/admin-encode-results.php
  - app/admin/admin-encode-interview-results.php
  - app/admin/admin-set-final-decision.php
  - resubmit-app/student/documents.php
- **Column usage:** `created_at` is used; no `changed_at`. Matches MASTER.
- **Potential gap:** `app/admin/admin-update-status.php` allowed_transitions are incomplete for later phases (e.g. Exam Scheduled → Exam Completed). Those transitions are handled by other scripts, so this is acceptable.

### CHED/TESDA branching

- **app/admin/admin-exam-schedule.php:** `program_category = 'CHED' OR program_category IS NULL` → CHED only. ✓  
- **app/admin/admin-exam-results.php:** Same filter. ✓  
- **app/admin/admin-set-interview.php:**  
  - CHED: `status = 'Exam Completed'`  
  - TESDA: `status = 'Documents Verified' AND program_category = 'TESDA'` ✓  
- **app/admin/admin-interview-schedule.php:** Separate CHED (Exam Completed) and TESDA (Documents Verified) sections. ✓  
- **app/handlers/save-step.php (Step 5):** Saves `program_category` from `programTrack`; TESDA gets `third_choice = ''`. ✓  
- **app/student/dashboard.php:** Uses `$is_tesda` for timeline (5 steps TESDA, 7 steps CHED). ✓  

Conclusion: CHED/TESDA logic is correctly enforced in the codebase.

---

## 3. CONSISTENCY: Admin Pages

### admin-auth-check.php

All admin pages include it:

- admin-app/student/dashboard.php ✓
- app/admin/admin-application-detail.php ✓
- app/admin/admin-exam-schedule.php ✓
- app/admin/admin-exam-results.php ✓
- app/admin/admin-interview-schedule.php ✓
- app/admin/admin-interview-results.php ✓
- app/admin/admin-final-decision.php ✓
- app/admin/admin-update-status.php ✓ (POST handler)
- app/admin/admin-set-exam.php ✓ (POST handler)
- app/admin/admin-set-interview.php ✓ (POST handler)
- app/admin/admin-encode-results.php ✓ (POST handler)
- app/admin/admin-encode-interview-results.php ✓ (POST handler)
- app/admin/admin-set-final-decision.php ✓ (POST handler)

### app/shared/admin-sidebar.php

Used by all admin pages that render HTML:

- admin-app/student/dashboard.php ✓
- app/admin/admin-application-detail.php ✓
- app/admin/admin-exam-schedule.php ✓
- app/admin/admin-exam-results.php ✓
- app/admin/admin-interview-schedule.php ✓
- app/admin/admin-interview-results.php ✓
- app/admin/admin-final-decision.php ✓

POST-only handlers (admin-update-status, admin-set-exam, etc.) do not need the sidebar. ✓

### assets/admin-styles.css

Linked by the same 7 admin HTML pages. ✓

### Port 3306

- `config/db.php`: `DB_PORT` = 3306 ✓  
- No other DB config files found. ✓  

---

## 4. MISSING / MISMATCHED ITEMS

### 1. College name inconsistency
- **Issue:** MASTER says Bestlink College; most of the app uses Bulacan Polytechnic College.
- **Location:** app/student/dashboard.php line 399 (Admitted banner) says "Bestlink College of the Philippines".
- **Action:** Decide which college name is correct and align MASTER and code.

### 2. admin-verify-app/student/documents.php
- **Issue:** MASTER lists it; it does not exist.
- **Reality:** Verification is in app/admin/admin-application-detail.php.
- **Action:** Update MASTER to remove admin-verify-app/student/documents.php and state that verification is in app/admin/admin-application-detail.php.

### 3. Outdated placeholder in app/admin/admin-application-detail.php
- **Location:** Lines 1015–1016.
- **Text:** "Documents verified. Exam scheduling will be available in Phase 4."
- **Issue:** Phase 4 (exam scheduling) is implemented.
- **Action:** Replace with a link or note pointing to app/admin/admin-exam-schedule.php (e.g. for CHED applicants).

### 4. Phase 2B fields (PSA, LRN, school_type, third_choice)
- **Status:** ✓ Implemented in app/student/application-form.php and app/handlers/save-step.php.

### 5. app/admin/admin-applications.php
- **Status:** Not in current project. MASTER does not list it.
- **Conclusion:** No mismatch; likely removed in favor of admin-dashboard.

---

## 5. SUMMARY

| Category | Status |
|----------|--------|
| Port 3306 | ✓ Correct in config |
| admin-auth-check | ✓ All admin pages |
| admin-sidebar | ✓ All admin HTML pages |
| admin-styles.css | ✓ All admin HTML pages |
| status_history | ✓ Used and uses created_at |
| CHED/TESDA logic | ✓ Correct in all relevant scripts |
| College name | ⚠ Inconsistent (Bestlink vs Bulacan) |
| MASTER file inventory | ⚠ admin-verify-app/student/documents.php does not exist |
| admin-application-detail placeholder | ⚠ Obsolete Phase 4 text |

---

**END OF AUDIT REPORT**
