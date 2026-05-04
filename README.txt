============================================================
  CortexPOS — Smart Cafe Ecosystem
  Installation Guide
============================================================

STEP 1 — Copy project
  Copy the cortexpos_v2/ folder to:
  C:/xampp/htdocs/cortexpos_v2/

STEP 2 — Start XAMPP
  Open XAMPP Control Panel
  Start Apache + MySQL

STEP 3 — Import database
  Open: http://localhost/phpmyadmin
  Click: Import tab
  Choose file: cortexpos_v2/config/schema.sql
  Click: Go

STEP 4 — Run setup
  Open: http://localhost/cortexpos_v2/setup.php
  This creates your admin account + default data
  DELETE setup.php after it runs!

STEP 5 — Login
  Open: http://localhost/cortexpos_v2/auth/login.php
  Role:     Admin
  Email:    admin@cortexpos.com
  Password: Admin@1234

============================================================
  FILE STRUCTURE
============================================================
admin/          12 pages (dashboard, sales, inventory...)
cashier/         5 pages (dashboard, orders, tables...)
client/          8 pages (home, menu, loyalty...)
auth/            5 pages (login, register, logout...)
config/          db.php + schema.sql
includes/        functions.php + layout.php
assets/          CSS + JS + logos
setup.php        Run once then delete

============================================================
  HOW TO CREATE CASHIER ACCOUNTS
============================================================
1. Login as Admin
2. Go to Settings page
3. Click "Generate Authorization Code"
4. Select role: Cashier, add a note
5. Copy the code (shown ONCE only)
6. Give it to the cashier
7. Cashier goes to: auth/register.php
8. Enters the code + creates their account
9. Code expires in 30 minutes, single use

============================================================
  DEFAULT CONFIG (config/db.php)
============================================================
DB_HOST = localhost
DB_USER = root
DB_PASS = (empty)
DB_NAME = cortexpos

Edit config/db.php if your MySQL has a password.
============================================================
