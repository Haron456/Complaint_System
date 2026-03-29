 KCA Complaint Management System

Overview

This is a **web-based complaint management system** built using **PHP and MySQL**.
It allows students to submit complaints, track their status using ticket numbers, and receive feedback from the admin.

---

 Features

 Student (User)

* Register and login securely
* Submit complaints
* Automatically generate ticket numbers (e.g. `TICK-0001`)
* Track complaint status
* View feedback from admin

Admin

* View all complaints
* Update complaint status (Pending / Resolved)
* Submit feedback with rating and comments

---

 System Requirements

Make sure you have the following installed:

* **PHP Version:** 7.4 or higher
* **MySQL Version:** 5.7 or higher
* **Web Server:** Apache (XAMPP recommended)

---

Database Setup

1. Open **phpMyAdmin**

2. Create a new database:

   ```
   complaint_system
   ```

3. Import the SQL file included in this project:

   * File: `complaint_system.sql` (or the one in your repo)
   * Steps:

     * Click your database
     * Go to **Import**
     * Choose the `.sql` file
     * Click **Go**

This will create the required tables:

* `users`
* `complaints`
* `feedback`

---

How to Run the System

1. Start **Apache** and **MySQL** in XAMPP

2. Copy the project folder into:

   ```
   C:\xampp\htdocs\
   ```

3. Open your browser and go to:

   ```
   http://localhost/your-folder-name/
   ```

---

Configuration

Open `db.php` and make sure it matches your database:

```php
$conn = new mysqli($host, $user, $password, $dbname);
```

---

Default Access

 You can register a new account from the system
 Admin access depends on how you set it up (can be extended)

---

 Project Structure

```
/project-folder
│── login.php
│── register.php
│── student.php
│── admin.php
│── db.php
│── complaint_system.sql
│── logo.png
```


NB:  To access other portal like staff and admin you have to search it as (admin.php)
      and you must  login first or register to acccess the student portal 
      For now the admin and the staff have no login or register page 


 Notes

* Ensure XAMPP is running before accessing the system
* Import the database before use
* Update database credentials if needed

