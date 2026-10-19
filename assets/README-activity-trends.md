# Admin activity log + usage trends

Two new admin pages, built on the Justin_Admin-Branch zip.

## Install
1. Copy this folder into your Studimate project. Two existing files are replaced:
   - includes/admin_sidebar.php  (adds "Usage trends" and "Activity log")
   - config/db.php               (adds the two new pages to the admin-only list; without this an
                                  admin would be redirected away from them)
2. New files: includes/admin_report_helpers.php, modules/admin_activity.php, modules/admin_trends.php,
   tests/admin_report_test.php. No database change is needed.
3. Run the test:
       C:\xampp\php\php.exe tests\admin_report_test.php
4. Log in as admin. Open "Activity log" and "Usage trends" in the admin sidebar.

## Activity log (modules/admin_activity.php)
Every admin action with the date, the admin's name, the action, and the affected account's name.
Filter by action type (deactivate, reactivate, make admin, remove admin, reset link). Paged, newest first.
The dashboard has no way to edit or delete entries.

## Usage trends (modules/admin_trends.php)
Two 30-day line charts, totals only:
- Quizzes taken and active students per day (active = took a quiz, checked a microlearning unit,
  or generated study tools that day).
- New AI generations, reused saved copies and failures per day, with the share of requests that
  avoided a Gemini call.

Gemini figures come from the activity log, so they start from the day the log was installed.
