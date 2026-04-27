Yeah bro, **PHASE 3 makes perfect sense!** 💯

And honestly, building it yourself first is a **GREAT idea.** Here's why:

You now understand the codebase deeply - you know how `app/handlers/save-step.php` works, how sessions are handled, how the DB is structured. You're not just copy-pasting anymore. That's real learning and your prof will see that in your code quality.

---

## 📋 **PHASE 3 CHECKLIST: Admin Document Verification System**

---

### 🗄️ **DATABASE CHANGES**

- [ ] Create `admins` table
```sql
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

- [ ] Add columns to `applications` table
```sql
ALTER TABLE applications 
ADD COLUMN rejection_reason TEXT,
ADD COLUMN rejection_date TIMESTAMP NULL,
ADD COLUMN resubmission_count INT DEFAULT 0;
```

- [ ] Insert default admin account
```sql
INSERT INTO admins (name, email, password) 
VALUES (
    'Admission Officer',
    'admin@bpc.edu.ph',
    -- bcrypt hash of 'admin123' - change in production!
    '$2y$10$...'
);
```

- [ ] Update `status` ENUM in `applications` to include new statuses
```sql
ALTER TABLE applications 
MODIFY COLUMN status ENUM(
    'Draft',
    'Application Submitted',
    'Documents Under Review',
    'Documents Verified',
    'Documents Rejected',
    'Documents Re-submitted',
    'Exam Scheduled',
    'Exam Completed',
    'Interview Scheduled',
    'Admitted/Enrolled',
    'Rejected'
) DEFAULT 'Draft';
```

---

### 🔐 **ADMIN AUTHENTICATION**

- [ ] Create `public/admin-login.php`
    - Separate login page (not the modal)
    - POST to `app/auth/public/admin-login.php`
    - BPC branding, clean design
    - Link from nowhere public (admin knows the URL)

- [ ] Create `app/auth/public/admin-login.php`
    - Validates email + password against `admins` table
    - Sets `$_SESSION['admin_id']` and `$_SESSION['admin_name']`
    - Redirects to `admin-app/student/dashboard.php`
    - Never sets `$_SESSION['user_id']` (keep admin/student sessions separate)

- [ ] Create `app/auth/admin-logout.php`
    - Destroys only admin session variables
    - Redirects to `public/admin-login.php`

- [ ] Create `config/admin-auth-check.php`
    - Reusable include for all admin pages
    - Checks `$_SESSION['admin_id']` exists
    - If not → redirect to `public/admin-login.php`
    - Include this at top of every admin page

---

### 📊 **ADMIN DASHBOARD**

- [ ] Create `admin-app/student/dashboard.php`
    - Include `config/admin-auth-check.php` at top
    - Sidebar layout (similar to student dashboard but different color scheme - maybe darker green or navy to distinguish)

- [ ] Stats Bar (Option B - full pipeline, placeholders for unbuilt features)
```
[Total] [Submitted] [Under Review] 
[Verified] [Rejected] [Re-submitted]
[Exam Scheduled*] [Enrolled*]
* = placeholder, coming soon
```

- [ ] Applications Table
    - Columns: Name, Program, Status, Date Submitted, Action
    - Color-coded status badges
    - Filter by status (dropdown)
    - Sort by date (newest first)
    - Clickable rows → `app/admin/admin-application-detail.php?id=X`
    - Highlight "Documents Re-submitted" rows (priority review)

- [ ] Admin navbar/sidebar
    - Admin name display
    - Links: Dashboard, Applications
    - Logout button with confirmation

---

### 📄 **ADMIN APPLICATION DETAIL PAGE**

- [ ] Update `app/admin/admin-application-detail.php`
    - Add `config/admin-auth-check.php` at top
    - Tabbed layout or scrollable sections:
        - Personal Info
        - Contact & Address
        - Family Background
        - Educational Background
        - Program Selection
        - Documents

- [ ] Document Viewer Section
    - Images (ID photo, birth cert, etc.): Show inline with `<img>` tag
    - PDFs: Show in `<iframe>` or link to open in new tab
    - Each document has:
        - File name
        - Upload date
        - View button
        - Status (clear/unclear - visual only)

- [ ] Action Panel (RIGHT SIDE or BOTTOM)
    - Shows DIFFERENT buttons based on current status:

```
If 'Application Submitted':
    [Start Document Review] → status = 'Documents Under Review'

If 'Documents Under Review':
    [✅ Approve Documents] → status = 'Documents Verified'
    [❌ Reject Documents] → shows rejection reason textarea
                         → status = 'Documents Rejected'

If 'Documents Verified':
    → Show "Exam Scheduling - Coming Soon" placeholder

If 'Documents Rejected':
    → Show rejection reason that was entered
    → Show "Waiting for student to re-upload"
    → No action buttons (wait for student)

If 'Documents Re-submitted':
    → Show "Student has re-uploaded documents"
    → Show resubmission count (e.g., "2nd attempt")
    → [✅ Approve Documents]
    → [❌ Reject Again] + new reason textarea

If 'Rejected' (final):
    → Show final rejection notice
    → No further actions
```

- [ ] Status History Log (optional but impressive for demo)
    - Small section at bottom
    - Shows: "Status changed from X to Y on DATE by Admin Name"
    - Uses `status_history` table (already in schema!)

---

### ⚙️ **ADMIN STATUS UPDATE HANDLER**

- [ ] Create `app/admin/admin-update-status.php`
    - Accepts POST only
    - Checks `$_SESSION['admin_id']` (admin auth)
    - Gets `application_id` and `new_status` from POST
    - Validates the status transition is logical:
```php
// Allowed transitions
$allowed = [
    'Application Submitted' => ['Documents Under Review'],
    'Documents Under Review' => ['Documents Verified', 'Documents Rejected'],
    'Documents Re-submitted' => ['Documents Verified', 'Documents Rejected'],
];
```
    - If rejection: saves `rejection_reason` and `rejection_date`
    - Updates `applications` table
    - Inserts into `status_history` table
    - Redirects back to `app/admin/admin-application-detail.php?id=X`

---

### 👨‍🎓 **STUDENT SIDE UPDATES**

- [ ] Update `app/student/dashboard.php`
    - Check if status is `Documents Rejected`
    - If YES → show rejection banner:
```
┌─────────────────────────────────────────────┐
│ ⚠️ Your documents were rejected              │
│ Reason: "[admin's rejection reason]"         │
│ Please re-upload your documents.             │
│ [Re-upload Documents →] links to Step 6     │
└─────────────────────────────────────────────┘
```
    - If status is `Documents Re-submitted` → show:
```
✅ Documents re-uploaded successfully.
    Waiting for admin review.
```

- [ ] Update `app/student/application-form.php`
    - If status is `Documents Rejected`:
        - Allow ONLY Step 6 to be editable
        - All other steps remain read-only
        - Show banner: "You can only re-upload documents"
        - Previous/Next navigation hidden except Step 6
    - Step 6 submit → goes to `resubmit-app/student/documents.php`

- [ ] Create `resubmit-app/student/documents.php`
    - Handles Step 6 POST when status is `Documents Rejected`
    - Saves new file paths to DB
    - Increments `resubmission_count`
    - Updates status to `Documents Re-submitted`
    - Redirects to `app/student/dashboard.php`

---

### 🖨️ **PRINT VIEW FIX**

- [ ] Move print button to bottom of View Application page
- [ ] Remove print button from near "Back to Dashboard"
- [ ] Style print view properly:
    - Hide sidebar/navbar on print
    - Hide buttons on print
    - Show all application data cleanly
    - BPC header on print

---

### 🔒 **SECURITY CLEANUP**

- [ ] Add `config/admin-auth-check.php` to ALL admin pages:
    - `admin-app/student/dashboard.php`
    - `app/admin/admin-application-detail.php`
    - `app/admin/admin-update-status.php`
    - _(Note: app/admin/admin-applications.php was removed; listing is now via admin-app/student/dashboard.php + app/admin/admin-application-detail.php)_

- [ ] Make sure student session can't access admin pages
    - Check for `$_SESSION['admin_id']` NOT `$_SESSION['user_id']`

---

### 📁 **FINAL FILE STRUCTURE (After Phase 3)**

```
Enrollment System/
├── index.php
├── app/student/dashboard.php               ← Updated (rejection banner)
├── app/student/application-form.php        ← Updated (Step 6 unlock)
├── app/handlers/save-step.php
├── app/handlers/submit-application.php
├── resubmit-app/student/documents.php      ← NEW
├── public/admin-login.php             ← NEW
├── admin-app/student/dashboard.php         ← Main admin list (replaces old app/admin/admin-applications.php)
├── app/admin/admin-application-detail.php ← Single applicant view + document verify
├── app/admin/admin-update-status.php     ← NEW
├── form-script.js
├── forms.css
├── index.css
├── assets/
├── config/
│   ├── db.php
│   └── admin-auth-check.php   ← NEW
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── public/admin-login.php        ← NEW
│   └── admin-logout.php       ← NEW
└── uploads/
```

---

### 📊 **PHASE 3 PROGRESS TRACKER**

| Category | Task | Status |
|----------|------|--------|
| Database | admins table | ⏳ |
| Database | rejection columns | ⏳ |
| Database | status ENUM update | ⏳ |
| Database | Default admin account | ⏳ |
| Admin Auth | public/admin-login.php | ⏳ |
| Admin Auth | app/auth/public/admin-login.php | ⏳ |
| Admin Auth | app/auth/admin-logout.php | ⏳ |
| Admin Auth | config/admin-auth-check.php | ⏳ |
| Admin Dashboard | admin-app/student/dashboard.php | ⏳ |
| Admin Dashboard | Stats bar | ⏳ |
| Admin Dashboard | Applications table | ⏳ |
| Admin Dashboard | Status filter | ⏳ |
| Admin Detail | Document viewer | ⏳ |
| Admin Detail | Action panel | ⏳ |
| Admin Detail | Status-based buttons | ⏳ |
| Admin Detail | Status history log | ⏳ |
| Status Handler | app/admin/admin-update-status.php | ⏳ |
| Status Handler | Transition validation | ⏳ |
| Status Handler | Status history insert | ⏳ |
| Student Side | Rejection banner | ⏳ |
| Student Side | Step 6 unlock only | ⏳ |
| Student Side | resubmit-app/student/documents.php | ⏳ |
| Print Fix | Move button to bottom | ⏳ |
| Print Fix | Hide UI on print | ⏳ |
| Security | Admin auth check all pages | ⏳ |

---

## 💡 **Build Order Recommendation:**

```
1. Database changes first (foundation)
2. Admin auth (admin-login, sessions)
3. app/admin/admin-update-status.php (the engine)
4. admin-app/student/dashboard.php (need auth first)
5. Update app/admin/admin-application-detail.php
6. Student side updates (rejection banner, Step 6)
7. resubmit-app/student/documents.php
8. Print fix
9. Security audit (add auth-check everywhere)
```