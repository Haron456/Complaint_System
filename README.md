# KCA University Complaint and Feedback Management System

A web-based system where students lodge complaints, staff handle them, and the administrator assigns, monitors and reports on them. Built with PHP, MySQL, HTML, CSS and JavaScript.

## Features

- Student registration and one login for students, staff and administrators
- Complaints with department, category, optional attachment (PDF, JPG, PNG, up to 2 MB) and a reference number
- Seven-stage workflow with full status history
- Assignment and reassignment by the administrator
- Replies to the student and internal staff notes
- Priority levels, automatic escalation and manual escalation to senior staff
- In-app notifications and email notifications
- Service rating from 1 to 5 once a complaint is resolved
- Reports with charts, CSV export and print to PDF
- User management, escalation settings and an activity log for the administrator
- Database backup script

## Requirements

- PHP 7.4 or higher (PHP 8 recommended) with the `mysqli` extension
- MySQL 5.7+ or MariaDB
- Apache (XAMPP recommended)
- Composer, only to install PHPMailer for email

## Setup (XAMPP on Linux)

1. Copy this folder to `/opt/lampp/htdocs/complaint_system`.
2. Start Apache and MySQL: `sudo /opt/lampp/lampp start`.
3. In phpMyAdmin, choose **Import** and import `database/schema.sql`. If an old `complaint_system` database exists, export it first and drop it.
4. Copy `config/config.sample.php` to `config/config.php` and fill in the database and email settings.
5. Install the email library: `composer install`.
6. Let the web server write attachments: `sudo chgrp daemon uploads && sudo chmod 775 uploads`.
7. Open `http://localhost/complaint_system/install.php`, create the first administrator, then **delete `install.php`**.
8. Log in at `http://localhost/complaint_system/`.

On Windows, XAMPP is at `C:\xampp\htdocs\complaint_system`, and the upload folder needs no permission change.

## First steps

1. Log in as the administrator and open **Users**.
2. Add staff accounts. Staff need a department. Tick **Senior staff member** for people who receive escalated complaints, and make sure every department has one.
3. A new account's first password is the person's own ID in capital letters. They must change it at first login.
4. Students register themselves on the **Register** page.

## Configuration (`config/config.php`)

| Setting | Purpose |
| --- | --- |
| `db` | Database host, user, password and name |
| `session_timeout` | Automatic logout after this many seconds (1800 = 30 minutes) |
| `debug` | `true` shows PHP errors on screen. Keep `false` on a live server |
| `base_url` | Web address used for links inside emails |
| `mail` | Gmail SMTP settings. Use a Gmail **app password**, never the normal password |

## Folder layout

```
index.php  login.php  register.php  logout.php  change_password.php  install.php
student.php  staff.php  admin.php  complaint.php  notifications.php  download.php
admin_users.php  admin_settings.php  admin_reports.php  admin_activity.php
cron_escalate.php
includes/   bootstrap.php  helpers.php  complaints.php  mailer.php  uploads.php  header.php  footer.php
config/     config.sample.php  config.php (not committed)
database/   schema.sql  backup.sh
assets/     css/style.css  logo.png
uploads/    complaint attachments (not committed, blocked from direct web access)
```

## Scheduled jobs (optional)

Add these lines with `crontab -e`:

```
0 * * * *  /opt/lampp/bin/php /opt/lampp/htdocs/complaint_system/cron_escalate.php >> /tmp/escalate.log 2>&1
0 2 * * *  /opt/lampp/htdocs/complaint_system/database/backup.sh >> /tmp/backup.log 2>&1
```

The first escalates overdue complaints every hour. The second backs up the database every night.

## Backup and restore

`database/backup.sh` saves a compressed copy in `~/complaint_system_backups` and keeps 14 days.

To restore a copy into an empty database:

```
gunzip < BACKUPFILE.sql.gz | /opt/lampp/bin/mysql -u root complaint_system
```

## Security notes

- Every database query is prepared, all output is escaped, and every form carries a CSRF token.
- Passwords are stored hashed. People are logged out after 30 minutes without activity.
- Attachments are checked by their contents, saved under random names, and served only through `download.php` to people allowed to see the complaint.
- `config/config.php` holds the database login and the email password and is excluded from Git. Never commit it.
- On a live server: set `'debug' => false`, use HTTPS, use a database user with limited rights instead of `root`, and delete `install.php`.

## Documentation

The user manual covers students, staff and administrators.