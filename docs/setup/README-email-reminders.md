# Email reminders setup

1. Install the mailer (project root):
       composer require phpmailer/phpmailer
2. Copy the new .env keys from `.env.example` into your real `.env` (APP_URL + SMTP_*).
3. Run the migration once:
       php database/migrations/migrate_email_reminders.php
   (Fresh installs can use `database/schema.sql` instead.)
4. Test without sending:
       php cron/send_reminders.php --dry-run
   Then for real:
       php cron/send_reminders.php
5. Schedule it daily.
   - XAMPP/Windows Task Scheduler: Program `C:\xampp\php\php.exe`,
     Arguments `C:\xampp\htdocs\Studimate\cron\send_reminders.php`, trigger daily 8:00 AM.
   - Linux cron: `0 8 * * * php /var/www/studimate/cron/send_reminders.php`

Files: database/migrations/migrate_email_reminders.php, includes/mail_helpers.php,
cron/send_reminders.php, unsubscribe.php, config/env.php, database/schema.sql.
