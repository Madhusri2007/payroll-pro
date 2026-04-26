# 💼 PayRoll Pro — Employee Payroll Management System
### PHP + MongoDB | Mini Project

---

## 📁 Project Structure

```
payroll/
├── config/
│   └── db.php              ← MongoDB connection
├── login.php               ← Login page (entry point)
├── auth.php                ← Session guard (included on all pages)
├── layout.php              ← Shared sidebar + topbar layout
├── dashboard.php           ← Stats overview
├── employees.php           ← Add / Edit / Delete employees
├── payroll.php             ← Salary calculator
├── payslips.php            ← All payslips list
├── print_payslip.php       ← Printable payslip view
├── add_user.php            ← User access management
├── logout.php              ← Session destroy
├── setup.php               ← One-time admin user setup
└── composer.json           ← MongoDB PHP library
```

---

## ⚙️ Requirements

| Tool | Version |
|------|---------|
| PHP | 8.0+ |
| MongoDB | 6.0+ |
| MongoDB PHP Extension | 1.9+ |
| Composer | Latest |
| Web Server | Apache / Nginx / PHP Built-in |

---

## 🚀 Setup Instructions

### Step 1 — Install MongoDB PHP Extension

**Windows (XAMPP):**
1. Download `php_mongodb.dll` from https://pecl.php.net/package/mongodb
2. Place in `C:\xampp\php\ext\`
3. Add `extension=mongodb` to `php.ini`
4. Restart Apache

**Ubuntu/Debian:**
```bash
sudo pecl install mongodb
echo "extension=mongodb.so" >> php.ini
sudo systemctl restart apache2
```

**macOS (Homebrew):**
```bash
brew install php
pecl install mongodb
```

### Step 2 — Install MongoDB

**Windows:** Download from https://www.mongodb.com/try/download/community
**Ubuntu:** `sudo apt install mongodb`
**macOS:** `brew install mongodb-community`

Start MongoDB:
```bash
# Linux/macOS
sudo systemctl start mongod
# or
mongod --dbpath /data/db

# Windows
net start MongoDB
```

### Step 3 — Install PHP Dependencies

```bash
cd /path/to/payroll/
composer install
```

### Step 4 — Deploy Project

**Option A — XAMPP/WAMP:**
- Copy `payroll/` folder to `C:\xampp\htdocs\` (Windows) or `/var/www/html/` (Linux)
- Start Apache & ensure MongoDB is running

**Option B — PHP Built-in Server:**
```bash
cd /path/to/payroll/
php -S localhost:8000
```

### Step 5 — First Time Setup

1. Open browser → `http://localhost/payroll/setup.php`
2. This creates the default admin user
3. **Delete setup.php** immediately after!

### Step 6 — Login

Go to: `http://localhost/payroll/login.php`

```
Username: admin
Password: admin123
```

---

## 🔑 Features

### ✅ Login Security
- PHP `password_hash()` / `password_verify()` (bcrypt)
- Session-based authentication
- All pages protected by `auth.php` guard
- Forced redirect to login if not authenticated

### 👥 Employee Management
- Add, Edit, Delete employees
- Fields: Name, Email, Phone, Department, Designation
- Salary components: Basic, HRA %, DA %, PF %, Tax %
- Join Date, Status (Active/Inactive)
- Search by name

### 💰 Salary Calculation
- Select employee → auto-fill salary config
- Real-time breakdown:
  - **Earnings:** Basic + HRA + DA + Bonus
  - **Deductions:** PF + Income Tax
  - **Net Pay** = Gross − Deductions
- Save as payslip with one click
- Prevents duplicate payslips for same month

### 🧾 Payslip Generation
- Professional printable payslip
- Filter by month or employee name
- Print button (browser print / PDF)
- Delete payslips

### 🔐 User Access
- Create multiple system users
- Roles: Admin, HR Manager, Accountant, Staff
- Delete users (admin account protected)

---

## 🧮 Salary Formula

```
HRA         = Basic × HRA%
DA          = Basic × DA%
Gross       = Basic + HRA + DA + Bonus
PF          = Basic × PF%
Income Tax  = Gross × Tax%
Deductions  = PF + Income Tax
Net Pay     = Gross − Deductions
```

---

## 🎨 Tech Stack

| Layer | Tech |
|-------|------|
| Backend | PHP 8 |
| Database | MongoDB (via official PHP library) |
| Frontend | HTML5 + CSS3 (no frameworks!) |
| Fonts | Google Fonts (Syne + DM Sans) |
| Auth | PHP Sessions + bcrypt |

---

## 📝 MongoDB Collections

| Collection | Purpose |
|-----------|---------|
| `users` | System login accounts |
| `employees` | Employee records |
| `payslips` | Generated payslips |

Database name: `payroll_db`

---

## 🛠️ Troubleshooting

**"Class MongoDB\Client not found"**
→ Run `composer install` in the project folder

**"Connection refused"**
→ Make sure MongoDB is running: `sudo systemctl start mongod`

**Blank page / 500 error**
→ Enable PHP error display: add `ini_set('display_errors', 1);` to top of page

**Login not working**
→ Re-run `setup.php` or check MongoDB connection in `config/db.php`
