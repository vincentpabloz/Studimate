# Administrator dashboard (separate admin area)

Administrators now get their own area with its own sidebar. They no longer see Subjects, AI Tools,
Microlearning, Schedule, Analytics, Profile or Rate StudiMate. Students never see any admin page.

## Admin sidebar
- Overview (modules/admin.php): usage across the platform, Gemini usage (last 30 days), reminder emails,
  subjects with the lowest quiz scores
- System health (modules/admin_health.php): server, database and integration checks
- User accounts (modules/admin_users.php): counts, search, role and status filters, deactivate/reactivate,
  password reset link, make/remove admin
- Profile (modules/admin_profile.php): your admin activity (actions taken, recent actions), account info,
  edit your name, change your password

## Install
1. Copy everything in this folder into your Studimate project (replace existing files).
   DELETE includes/admin_nav.php and modules/admin_evaluation.php if you installed an earlier version.
2. Create the new columns and tables (skip if already done):
       C:\xampp\php\php.exe database\migrations\migrate_admin.php
3. Make your account an administrator (skip if already done), then log out and back in:
       C:\xampp\php\php.exe database\migrations\make_admin.php yourname@example.com
4. Run the test:
       C:\xampp\php\php.exe tests\admin_helpers_test.php
5. Log in with the admin account. You land on the Overview page.

## What changed in this version
New:      includes/admin_sidebar.php, modules/admin_health.php, modules/admin_profile.php
Changed:  modules/admin_profile.php (named recent actions such as "Deactivated Maria Santos (maria@example.com)"), modules/admin.php (health checks moved out, shows a warning banner if a check fails),
          modules/admin_users.php (counts, role/status filters, admin sidebar),
          includes/sidebar.php (student sidebar: "Administrator" link removed),
          includes/admin_helpers.php (health checks are grouped, adminUserCounts added),
          config/db.php (see below), index.php (admins are sent to the Overview after login)
Removed:  includes/admin_nav.php, modules/admin_evaluation.php (the admin evaluation results page and CSV)

## How admins are kept out of student pages
config/db.php runs on every web request for a signed-in user. It now:
- signs out deactivated accounts (as before),
- re-reads the role from the database, so promote/demote works without logging in again,
- redirects an administrator to modules/admin.php if they open any student page.
To try the student side, use a separate student account (the admin account cannot open it).

## Notes
- Admins never see lessons, uploaded files or quiz answers. Aggregate rows need at least 3 different students.
- The student "Rate StudiMate" page (modules/evaluate.php) was not touched. Remove it from includes/sidebar.php if you only use the Google Form.
- Gemini and email counts start from the day the migration is installed.
