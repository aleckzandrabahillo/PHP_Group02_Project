# Avela

**A Hair Care E-Commerce Application with Hair Profile Assessment and Routine Building**

Avela is a PHP and MySQL web application for browsing hair-care products, managing customer accounts, and building a more personalized shopping experience through a hair profile and routine feature.

This repository is the Group 02 project for **Open-Source Programming**.

## Group Information

- **Group:** Group 02
- **Project:** Avela
- **Course:** Open-Source Programming

### Group Members

1. Aleckzandra Bahillo - @aleckzandrabahillo
2. Jasmine Iris Eva - @EvaIris5892
3. Alexzis Mae Tutor - @AlexzisMae
4. Angela Nicole Zurbano - @zurbanoangelanicole

## Technology Stack

- **PHP 8.2 with `pdo_mysql`** - server-side logic and routing
- **MySQL / MariaDB** - database
- **HTML5** - page structure
- **CSS3** - layout and responsive design
- **Vanilla JavaScript** - client-side interactions

No PHP or front-end framework is used in the project.

A small Python component for NLP-based analysis of free-text hair concerns is planned for a later development phase. It is not included in the current build yet.

## Current Features

- Public home, shop, search, about, and product pages
- Customer registration and login
- CAPTCHA validation
- Email OTP account activation
- Staff OTP authentication
- Password hashing and password rules
- Failed-login tracking and temporary account lockout
- Session timeout and session ID regeneration
- CSRF protection for state-changing forms
- Role-based access for Customer, Catalog Manager, and Administrator
- Product filtering and sorting
- Favorites
- Shopping cart and checkout interface
- Product reviews and catalog-based recommendations
- Customer, Catalog Manager, and Administrator dashboards
- Authentication and audit-related database tables

Some modules are still under development, including full Catalog Manager CRUD, order completion/payment processing, and the NLP component.

## Project Structure

```text
PHP_Group02_Project/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   ├── Support/
│   └── Views/
├── database/
│   └── schema.sql
├── public/
│   ├── assets/
│   ├── uploads/
│   └── index.php
├── scripts/
│   ├── create_staff.php
│   ├── healthcheck.php
│   └── install.php
├── storage/
│   └── logs/
├── .env.example
├── .gitignore
├── bootstrap.php
├── router.php
└── README.md
```

## Database

The default database name is:

```text
avela
```

The complete database structure and initial local testing records are in:

```text
database/schema.sql
```

## Local Setup

### Requirements

- PHP 8.2 with `pdo_mysql` 
- MySQL or MariaDB
- VS Code or another code editor
- Web browser

### Steps

1. Clone or copy the project to your local machine.
2. Copy `.env.example` and rename the copy to `.env`.
3. Update the database settings in `.env` if needed.
4. Start MySQL or MariaDB.
5. From the project folder, run:

```bash
php scripts/install.php
```

6. Start the local PHP server:

```bash
php -S 127.0.0.1:8000 -t public router.php
```

7. Open the site in a browser:

```text
http://127.0.0.1:8000
```

For XAMPP on Windows, `C:\xampp\php\php.exe` can be used instead of `php` if PHP is not added to the system PATH.

## Staff Accounts

Administrator and Catalog Manager accounts can be created from the command line using:

```bash
php scripts/create_staff.php
```

The script will ask for the needed account information.

## Security Notes

- Passwords are stored using PHP password hashing.
- Sensitive local settings are kept in `.env` and should not be committed.
- `.env.example` contains only sample configuration values.
- Uploaded files and local log files are excluded from Git except for their empty placeholder folders.

## Development Status

Avela is still being developed. The repository will be updated as the group completes the remaining e-commerce, catalog management, NLP, and deployment requirements.
