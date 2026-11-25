# FNSTM Staff Excellence Portal

Full PHP web application for:
- Public homepage (first display)
- Admin dashboard
- Department dashboard
- Image management with resizing
- Role-based authentication
- Analytics charts
- Excel/PDF/print reporting

## Stack
- PHP 8+ with `pdo_sqlite`, `mbstring`, and `fileinfo`
- SQLite (default via PDO)
- GD is recommended for image resizing; uploads still work without it
- Offline local UI assets (Bootstrap-like, chart-lite, lightbox-lite)

## Standalone Local Storage
The app runs locally without an external hosting service. By default, SQLite data is stored in `database/app.db`, uploaded files are stored under `uploads/`, and PHP sessions are stored in `database/sessions/`. Start it with `start-offline.bat` or `start-offline.ps1`; the app does not need a remote database for local use.

## Implemented Features

### Homepage (`index.php`)
- Navbar
- Current winner (image + name + reasons)
- Submission window display (open and close time set by admin)
- Past winners carousel
- Event gallery with lightbox
- Event video highlights (admin-uploaded short clips)
- Committee members cards
- Department info (vision, mission, objectives)
- Analytics charts
- All images pulled dynamically from database file names
- Mobile-responsive layout

### Admin Dashboard (`admin/dashboard.php`)
- View all department submissions
- Search/filter/pagination
- Select monthly winner with reasons
- Set submission open/close time window for departments
- Manage committee members
- Upload/manage gallery images
- Upload/manage event video clips
- Analytics charts
- Export reports (Excel-compatible CSV + PDF)
- Print report
- Send notifications/reminders (+ email logging)
- Update department vision/mission/objectives
- Read and correct submitted monthly report narratives and KPI measures
- Create and download full database/upload backups and review the application activity log
- Change the signed-in admin account username and password

### Department Dashboard (`department/dashboard.php`)
- Staff submission form (name, rank, photo)
- Selection submission (choose staff + 2 reasons)
- Selection submission is allowed only during the admin-defined open window
- Submission history
- Notifications from admin

### Department Annual Event Reports and Monthly KPI Reports
- Each department can submit an annual event workflow with event dates, purpose, planned activities, responsible officer, expected outcomes, evidence, and an optional supporting attachment.
- Each department can save or submit an annual KPI target plan with KRA, SMART objectives, KPI, target, and evidence/verification source. Monthly KPI reports use this plan as their reference.
- Monthly KPI updates are linked to the submitted annual plan and capture achievement, percentage, and evidence for each KPI.
- Monthly reports support formatted narrative text or an attached PDF, Word, Excel, PowerPoint, or image file (up to 50 MB).
- Departments can reopen and update a month’s report; admins can review submissions and print annual plans and monthly reports.
- Admins can print staff selections by department and reporting period with each selected staff member’s photo.

### Image Pipeline
- Upload and save images to:
  - `uploads/staff`
  - `uploads/gallery`
  - `uploads/committee`
  - `uploads/winners`
- Store file names in database
- Resize images for consistency (GD-enabled PHP)
- Displayed on homepage and dashboards

### Auth & Security
- Login page for admin and departments
- Department registration (HOD, password confirmation)
- Forgot password for department login using HOD name as reset key
- Password hashing (`password_hash`)
- Session management
- Role-based access control

### Database
SQLite database in `database/app.db` by default; all application tables live in this one file. Schema in `database/schema.sql`:
- `users`
- `department`
- `staff`
- `submission`
- `winner`
- `gallery`
- `event_video`
- `committee`
- `department_info`
- `notifications`
- `email_logs`
- `submission_schedule`

Includes relationships and indexes for search/filter/pagination performance.

SQLite uses a bounded 10-second lock wait and full synchronous writes. It keeps the rollback-journal mode so the live database remains a single file. `database/migrate.php` checks database integrity after applying schema updates, and admin-created backups verify both the database snapshot and compressed archive before keeping it.


## Quick Start
1. Ensure PHP 8+ with extensions:
   - `pdo_sqlite`
   - `mbstring` (required)
   - `fileinfo` (required for uploads)
   - `gd` (recommended for image resizing)
2. From the project root, set the initial admin password and start PHP.

   PowerShell:
   ```powershell
   $env:ADMIN_DEFAULT_PASSWORD = 'choose-a-strong-password'
   php database/migrate.php
   Remove-Item Env:ADMIN_DEFAULT_PASSWORD
   php -S 127.0.0.1:8000
   ```

   macOS, Linux, or another POSIX shell:
   ```bash
   read -r -s -p 'Initial admin password: ' ADMIN_DEFAULT_PASSWORD; echo
   export ADMIN_DEFAULT_PASSWORD
   php database/migrate.php
   unset ADMIN_DEFAULT_PASSWORD
   php -S 127.0.0.1:8000
   ```
3. Open:
   - `http://localhost:8000/`

## Offline Start (Windows)
Use the included launcher to run fully offline without needing internet access.

1. Double-click `start-offline.bat`. On first run, it prompts for the initial admin password unless `config/local.php` or `ADMIN_DEFAULT_PASSWORD` is already configured.
2. Or run:
   ```powershell
   .\start-offline.ps1
   ```
3. Open:
   - `http://127.0.0.1:8000/`

Notes:
- The script finds a working `php.exe` (or uses `PHP_EXE` if set).
- It checks required extensions (`pdo_sqlite`, `mbstring`, and `fileinfo`), runs migration, and starts the server.
- If port `8000` is busy, run:
  ```powershell
  .\start-offline.ps1 -Port 8001
  ```

## Online Update (Windows)
Use this before starting online mode to apply DB/schema updates and verify required PHP extensions.

1. Double-click `online-update.bat`
2. Or run:
   ```powershell
   .\online-update.ps1
   ```

Then start online server:
```powershell
.\start-online.ps1
```
## Online Start (Windows)
Use this to expose the app on your local network (LAN). This uses PHP's built-in development server and is not intended to publish the app directly to the public internet. For internet access, deploy to a PHP web host and use its production web server and HTTPS.

1. Double-click `start-online.bat`. On a fresh database, it securely prompts for and confirms the initial admin password if none is configured.
2. Or run:
   ```powershell
   .\start-online.ps1
   ```
3. Open:
   - `http://localhost:8000/`
   - `http://<your-lan-ip>:8000/` (from another device on same network)

Notes:
- This mode binds to `0.0.0.0` so other devices on your network can access it.
- If port `8000` is busy, run:
  ```powershell
  .\start-online.ps1 -Port 8001
  ```

## Free PHP Hosting (School Demo)
This app can run on a PHP host that supports PHP 8+, PDO SQLite, mbstring, fileinfo, and writable files. GitHub Pages cannot run the PHP backend. For hosts that keep the project inside a public `htdocs` folder:

1. Create a free PHP hosting account and a site/subdomain.
2. Copy `config/local.php.example` to `config/local.php`, set a unique admin password, and upload the app files to the site's web root. Do not upload `database/app.db`, session files, local logs, or real staff data.
3. Keep the included `.htaccess` files in `config`, `database`, and private upload folders. On Nginx or another server that ignores `.htaccess`, add equivalent rules to deny direct web access to `config`, `database`, and private attachments.
4. Make `database`, `database/sessions`, and `uploads` writable by PHP. If the host stores sessions elsewhere, set `SESSION_PATH` in `config/local.php` to a private writable folder. The app will initialize a fresh database and create the initial `admin` account.
5. Enable HTTPS. For a subdirectory or reverse proxy, set `APP_BASE_URL` to the full public app URL.
6. For report attachments up to 50 MB, configure PHP with `upload_max_filesize` of at least `50M` and `post_max_size` of at least `55M` (the exact setting method depends on the host).

Free hosts can change limits and may not provide backups. Back up the database and uploads separately; this is suitable for a school demonstration, not sensitive production records. Run `database/migrate.php` from the host's CLI when applying schema updates; it is intentionally unavailable over HTTP.
## Admin Setup
The database seed creates the initial admin account as `admin` unless `ADMIN_DEFAULT_USERNAME` is set. Set `ADMIN_DEFAULT_PASSWORD` before the first database initialization; the application does not use a shared default password. Choose a strong password and keep it private.

You can also configure these environment variables:
- `ADMIN_DEFAULT_USERNAME`
- `ADMIN_DEFAULT_PASSWORD`
- `DB_DSN` (for custom database)
- `DB_USER`
- `DB_PASS`
- `APP_BASE_URL`
- `SESSION_PATH` (private writable folder; defaults to `database/sessions`)
- `UPLOAD_PATH` (writable upload root; defaults to `uploads`)
- `APP_TIMEZONE`


