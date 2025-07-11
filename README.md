# AppointMed - Healthcare Appointment System

AppointMed is a simple PHP-MySQL web application that allows patients to book appointments and administrators to manage appointments and schedules. This system is ideal for small clinics or healthcare providers who want a basic and easy-to-use appointment management tool.

---

## 📌 Features

- Patient registration and login
- Admin and Doctor login and dashboard
- Book, update, and cancel appointments
- Manage patients and schedules
- Appointment status tracking
- Responsive user interface

---

## ⚙️ Technologies Used

- PHP
- MySQL (phpMyAdmin)
- HTML5 / CSS3 / Bootstrap
- JavaScript

---

## 🛠️ Installation & Setup Guide

### ✅ Prerequisites

Make sure you have the following installed on your machine:

- XAMPP
- A web browser (e.g., Chrome)
- Git (optional, for cloning the repo)

---

### 🔽 Step 1: Clone or Download the Repository

**Option 1: Clone using Git**

```bash
git clone https://github.com/YOUR_USERNAME/appointmed.git
````

**Option 2: Manual Download**

* Click the green **Code** button on the GitHub repo
* Select **Download ZIP**
* Extract it to your web server directory (e.g., `htdocs`)

---

### 📁 Step 2: Move Project to Server Directory

If you're using XAMPP:

* Copy the entire `appointmed` folder into:

```
C:\xampp\htdocs\
```

You should now have:
`C:\xampp\htdocs\appointmed`

---

### 🧠 Step 3: Create the MySQL Database

1. Open your browser and go to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **New** and create a database with this name:

```
appointmed_system
```

3. Select the database you just created
4. Click the **Import** tab
5. Choose the file:

```
config/appointmed_system.sql
```

6. Click **Go** to import all tables and data

---

### 🔧 Step 4: Configure Database Connection

Open this file in a text/code editor:

```
config/db.php
```

Edit the credentials to match your local server setup:

```php
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "appointmed_system";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
```

---

### ▶️ Step 5: Run the App

Start Apache and MySQL in XAMPP Control Panel.

In your browser, go to:

```
http://localhost/appointmed/main-page/index.php
```

You should now see the login page.

---

## 👤 Admin Login Credentials

Use this default account to log in as admin:

* **Email Address:** `admin@gmail.com`
* **Password:** `Admin12345`

---

## 🌐 Live Preview (Static Version)

If you want to **preview how the project looks** without installing XAMPP or importing the database, you can visit the **static version** hosted via GitHub Pages:

🔗 [Static Demo - AppointMed UI Only](https://shielaonsana.github.io/appointmed_static/)

> ⚠️ Note: This static version does **not** have any backend functionality — it only shows the layout and design of the system.

---

## 📜 License

This project is open-source and free to use for educational and non-commercial purposes.

---
