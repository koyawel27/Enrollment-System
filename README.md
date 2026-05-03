# BPC iEnroll

Web-based admissions workflow for Bulacan Polytechnic College: student registration and multi-step applications, admin document review, exams, interviews, and final admission decisions — **pure PHP**, **MySQL**, **vanilla HTML/CSS/JS** (see `docs/MASTER_PROJECT_STATE.md` for full detail).

---

## Requirements

- **Windows** with [XAMPP](https://www.apachefriends.org/) (or equivalent: Apache + PHP + MariaDB/MySQL)
- **PHP** 8.x (matches typical XAMPP bundles)
- **MariaDB/MySQL** — this project expects MySQL on port **3307** by default (adjust if yours uses `3306`)

---

## Quick setup

### 1. Put the project on your web server

Copy the repo folder under your Apache document root, for example:

`C:\xampp\htdocs\Enrollment System\`

In the browser you will open URLs like:

`http://localhost/Enrollment%20System/index.php`

(Spaces in the folder name become `%20` in the URL.)

### 2. Create the database

1. Start **Apache** and **MySQL** in XAMPP.
2. Open **phpMyAdmin** (often `http://localhost/phpmyadmin`).
3. Create a database named **`bpc_ienroll`**, collation **`utf8mb4_unicode_ci`** (or utf8mb4_general).

Import a schema that matches this app:

- Use a **compatible SQL export** from your team or your own backup, **or**
- Run the incremental scripts under **`database/`** if you already have a base schema (migration files are additive; see filenames for purpose).

Optional for password reset emails: columns on `users`:

- `reset_token` (VARCHAR(64), nullable)  
- `reset_token_expires` (DATETIME, nullable)  

See `database/add_password_reset_tokens.sql`.

### 3. Configure PHP

**Database**

1. Copy `config/db.example.php` to **`config/db.php`**.
2. Edit `config/db.php` and set **`DB_HOST`**, **`DB_PORT`**, **`DB_USER`**, **`DB_PASS`**, and **`DB_NAME`** to match your MySQL user and the **`bpc_ienroll`** database.

**Mail (optional — OTP, notifications, password reset)**

1. Copy `config/mail_config.example.php` to **`config/mail_config.php`**.
2. Fill in SMTP settings (e.g. Gmail with an [App Password](https://support.google.com/accounts/answer/185833)).

`mail_config.php` and `db.php` are listed in `.gitignore` so local secrets are not committed.

### 4. Uploads folder

Ensure the **`uploads/`** directory exists and is **writable** by Apache (student documents are saved here). On Windows/XAMPP this is usually fine by default.

### 5. PHPMailer

The repo includes **`PHPMailer/`** vendored copy; no Composer step is required for basic use.

---

## How to run

| Who | Entry point |
|-----|--------------|
| **Public / students** | `index.php` (landing; login/register links into `app/auth/`) |
| **Admins** | `public/admin-login.php` → after login, `app/admin/admin-dashboard.php` |

Use the same Apache vhost/host you used for setup (see **BASE_URL**: `config/paths.php` derives it from the folder under `DOCUMENT_ROOT`, so the app must live under the web root correctly).

---

## Documentation

- **`docs/SYSTEM_BEHAVIOR.txt`** — behavior-focused reference (workflow, handlers, edge cases).
- **`docs/MASTER_PROJECT_STATE.md`** — project state, inventory, pending items.

---

## Security reminder for public repos

Do **not** commit **`config/db.php`**, **`config/mail_config.php`**, **`uploads/`**, or **full phpMyAdmin dumps** with real accounts. Examples in the repo: `db.example.php`, `mail_config.example.php`; see `.gitignore`.

---

## License / academic use

Align with your institution’s policy; added here for README completeness only.
