

# KCA Complaint Management System

## Overview

The KCA Complaint Management System is a web-based application developed using PHP and MySQL.
It is designed to improve how complaints are submitted, managed, and resolved within an institution.

The system provides a structured workflow where students can submit complaints, staff can manage them, and administrators can respond with feedback and notifications.

---

## Features

### Student (User)

* Secure registration and login
* Submit complaints
* Automatic ticket generation (e.g., TICK-0001)
* Track complaint status
* View admin feedback
* Submit ratings and comments

---

### Staff

* View all complaints
* Update complaint status (Pending / Resolved)

---

### Admin

* View all complaints
* Update complaint status
* Provide feedback (comments)
* Send email notifications to users
* View student ratings and comments
* Dashboard with analytics:

  * Total complaints
  * Complaints by status
  * Feedback count
  * Average response time

---

## System Requirements

* PHP 7.4 or higher
* MySQL 5.7 or higher
* Apache Web Server (XAMPP recommended)
* Composer (for PHPMailer)

---

## Database Setup

1. Open phpMyAdmin
2. Create a database named:

```
complaint_system
```

3. Import the SQL file provided in the project:

* Select the database
* Click "Import"
* Choose the `.sql` file
* Click "Go"

---

## Database Tables

The system includes the following tables:

* users
* complaints
* feedback
* super_admin
* super_staff

Note: If your code references additional tables (e.g., staff_codes), ensure they exist or update the code accordingly.

---

## Installation

1. Start Apache and MySQL in XAMPP

2. Copy the project folder into:

```
C:\xampp\htdocs\
```

3. Open your browser and navigate to:

```
http://localhost/your-folder-name/
```

---

## Configuration

Open `db.php` and configure your database connection:

```php
$conn = new mysqli("localhost", "root", "", "complaint_system");
```

---

## Email Configuration (PHPMailer)

To enable email notifications:

1. Install PHPMailer using Composer:

```
composer require phpmailer/phpmailer
```

2. Update email credentials in `admin.php`:

```php
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
```

Important:

* Use a Gmail App Password, not your actual password
* Ensure SMTP settings are correct

---

## Project Structure

```
/project-folder
│── login.php
│── register.php
│── student.php
│── staff.php
│── admin.php
│── logout.php
│── db.php
│── complaint_system.sql
│── /vendor
│── /assets
```

---

## Common Issues

### Too Many Redirects

* Ensure session roles are correctly set
* Clear browser cookies
* Verify login redirection logic

---

### Missing Table Error

Example:

```
Table 'complaint_system.staff_codes' doesn't exist
```

Solution:

* Re-import the SQL file
* Or create the missing table
* Or update the code to match existing tables

---

### Email Not Sending

* Check internet connection
* Verify SMTP credentials
* Ensure App Password is used
* Confirm Composer dependencies are installed

---

## Notes

* Always start Apache and MySQL before running the system
* Import the database before accessing the application
* Keep credentials secure

---

## Author

MUNGA BRANDON BILLY-- Student at  KCA University

---
