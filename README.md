# Studimate

Studimate is a PHP study application. The PHP pages stay at the project root so existing URLs
continue to work.

## Project structure

- `assets/` contains static files only: CSS, JavaScript, images, and fonts.
- `classes/` contains application PHP classes and is included in Composer's classmap.
- `config/` contains application configuration.
- `cron/` contains scheduled command-line jobs.
- `database/schema.sql` creates the initial database; `database/migrations/` contains upgrades
  and database maintenance scripts.
- `docs/` contains setup and feature guides.
- `includes/` contains shared PHP helpers and page templates.
- `modules/` contains application pages and handlers.
- `tests/` contains PHP test scripts.
- `uploads/` stores uploaded documents and is blocked from direct web access.
- `vendor/` contains Composer dependencies.

See [docs/README.md](docs/README.md) for the documentation index.

## Setup with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Create the database by importing `database/schema.sql` in phpMyAdmin. For an existing
   database, run the needed scripts in `database/migrations/` from the project root.
3. Check the local database settings in `config/db.php`.
4. Copy `.env.example` to `.env` and set the API or email values needed for your installation.
   Keep `.env` private; do not commit it.
5. From `C:\xampp\htdocs\studimate-main`, run `composer install`.
6. Make sure PHP can write to `uploads/`. Uploaded files are served through authenticated
   application routes; do not remove its access-denial rules.
7. Open `http://localhost/studimate-main/` in your browser.

To run the email reminder job manually:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\studimate-main\cron\send_reminders.php --dry-run
```

Remove `--dry-run` when you intend to send messages. Schedule the command with Windows Task
Scheduler if reminders should run automatically.

## Tests

Run the PHP test scripts from the project root with `C:\xampp\php\php.exe tests\<test-file>.php`.
Feature-specific notes are available in [docs/README.md](docs/README.md).
