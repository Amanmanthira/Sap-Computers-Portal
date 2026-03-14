# SAP Computers Inventory Management System

**SAP Computers** is a simple PHP/MySQL inventory management system designed for managing products, suppliers, branches, stock movements, and purchase receipts (GRN).

## ✅ Key Features

- **User Authentication** (login/logout)
- **Branch Management** (add/edit/view branches)
- **Product Management** (add/edit/view products, categories)
- **Supplier Management** (add/edit/view suppliers)
- **Purchase Order / GRN (Goods Received Note)**
  - Track inventory received from suppliers
  - Maintain stock levels per branch
- **Stock Movements**
  - Record stock transfers between branches
  - Track stock in / stock out history
- **Point of Sale (POS) History**
  - View sales history (POS transactions)
- **Reports**
  - Inventory reports, stock levels, GRN details, and more

## 📁 Project Structure

- `index.php` — Main home/dashboard entry point
- `login.php`, `logout.php` — Authentication
- `config/` — Configuration files (database connection, app settings)
- `models/` — Core data access layer
- `controllers/` — Controllers for handling form submissions and business logic
- `views/` — UI templates (header/footer and page fragments)
- `assets/` — CSS and JavaScript resources
- `ajax/` — AJAX endpoints for dynamic UI data

## 🛠️ Requirements

- PHP 7.2+ (or compatible)
- MySQL / MariaDB
- Apache (XAMPP, WAMP, etc.)

## 🚀 Installation

1. Clone or copy the project into your web server root (e.g., `htdocs` for XAMPP)
2. Create a MySQL database and import `database.sql` (or `database - Copy.sql`) using phpMyAdmin or `mysql` CLI
3. Update database connection settings in `config/database.php`
4. Point your browser to the project root (e.g., `http://localhost/sap-computers`)

## 🧩 Main Pages / Modules

- `branches.php` — Manage branches (locations)
- `products.php` — Manage products and categories
- `suppliers.php` — Manage suppliers
- `grn.php` — Add/view GRN (Goods Received Note) entries
- `stock.php` — Track current stock per branch/product
- `movements.php` — Record and review stock movements
- `pos.php` / `pos_history.php` — POS entry and history
- `reports.php` — Generate various reports
- `users.php` — Manage application users

## 🔍 Troubleshooting

- If pages are blank, enable PHP error display in `php.ini` or add:
  ```php
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  ```
- Ensure database connection settings are correct in `config/database.php`

## 🛡️ Security Notes

This system is meant as a basic demo / internal tool. For production, consider:

- Using prepared statements / parameterized queries to prevent SQL injection
- Implementing CSRF protection on form submissions
- Hashing passwords (e.g., `password_hash`) and enforcing strong passwords

## 📦 License

This project is provided as-is. Modify and use it as needed.
