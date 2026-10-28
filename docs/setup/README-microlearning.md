# Microlearning setup

1. Copy these files into your Studimate project (replace includes/sidebar.php and database/schema.sql):
   - includes/micro_helpers.php        (new)
   - includes/sidebar.php              (adds the Microlearning menu item)
   - modules/micro.php                 (new, the unit viewer page)
   - modules/save_micro.php            (new, checks quick-check answers on the server)
   - database/migrations/migrate_microlearning.php (new)
   - tests/micro_helpers_test.php      (new)
   - database/schema.sql               (fresh installs get the two new tables)
2. Create the tables once:
       C:\xampp\php\php.exe database\migrations\migrate_microlearning.php
3. Run the helper test:
       C:\xampp\php\php.exe tests\micro_helpers_test.php
4. Open the site, click Microlearning in the sidebar, pick a lesson, and press Generate.
