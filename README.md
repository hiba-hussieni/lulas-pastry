# Lula’s Pastry

**Author:** Hiba Hussieni  
**Context:** VioletPro — Web and Mobile Development Challenge 2026

A pastry ordering and shop management application built with PHP, MySQL/MariaDB, HTML, CSS, and JavaScript.

Customers can browse products, manage their cart, and place orders. A separate Chef/Admin interface supports product, category, stock, and order management.

## Features

### Customer

- Register an account and log in.
- Browse products and categories.
- View product descriptions, prices, images, and availability.
- Add products to the cart, update quantities, and remove items.
- Complete checkout with delivery information and special instructions.
- View order confirmation and order information.

### Chef/Admin

- Log in to the management dashboard.
- Add, edit, and delete products.
- Manage categories and their display order.
- Update stock quantities and product availability.
- Review orders and customer delivery details.
- Update order and payment statuses.

Payment status tracking is part of the application; it does not by itself represent an integrated online payment gateway.

## Technologies

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP with PDO
- **Database:** MySQL / MariaDB
- **Local environment:** XAMPP, Apache, phpMyAdmin
- **UI resources:** Font Awesome and Google Fonts

## Main Project Files

| Path | Purpose |
| --- | --- |
| `index.php` | Home page |
| `customer-login.php` | Customer login and registration |
| `products.php` | Product browsing |
| `cart.php` | Shopping cart |
| `checkout.php` | Checkout |
| `delivery.php` | Delivery-related interface |
| `order-success.php` | Order confirmation |
| `chef-login.php` | Chef/Admin login |
| `chef-dashboard.php` | Management dashboard |
| `chef-products.php` | Product management interface |
| `chef-categories.php` | Category management |
| `chef-stock.php` | Stock management |
| `chef-order-details.php` | Order details and management |
| `db.php` | Shared database connection |
| `css/` | Stylesheets |
| `js/` | JavaScript files |
| `uploads/` | Product upload assets and product management handlers |
| `database/lulas_pastry_demo.sql` | Database structure and demonstration data |

Keep the supplied asset folders in their original locations so image and stylesheet paths continue to work.

## Local Installation

### 1. Prepare the server

Install XAMPP with PHP, Apache, and MySQL/MariaDB. PHP must have the PDO MySQL extension enabled.

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 2. Copy the project

Copy the project into your XAMPP `htdocs` directory.

For example:

```text
C:\xampp\htdocs\lulas_pastry_challenge
```

The folder should contain `index.php` and `db.php` directly.

### 3. Import the database

Open:

```text
http://localhost/phpmyadmin
```

Create a new, empty database named:

```text
lulas_pastry_challenge
```

Select that database, open **Import**, and choose:

```text
database/lulas_pastry_demo.sql
```

The file contains the table structure and fictional demonstration data, including test accounts, products, and orders. Import it into a separate demo database rather than an existing database containing important data.

### 4. Configure the connection

The connection is defined in the root-level `db.php`.

Its local development defaults are:

| Setting | Default |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `lulas_pastry_challenge` |
| Username | `root` |
| Password | Empty |

These defaults can be overridden with environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

If your MySQL service uses port `3307`, update the port setting accordingly.

Environment variables must be available to the PHP process. This project does not automatically load a `.env` file.

Keep private database credentials out of Git.

### 5. Open the application

With Apache and MySQL running, visit:

```text
http://localhost/lulas_pastry_challenge/
```

If you use a different folder name or Apache port, adjust the URL.

PHP requires a running PHP server. Opening the files directly or using a static preview server will not execute the backend.

## Demo Accounts

After importing the supplied demonstration database:

| Role | Login | Password |
| --- | --- | --- |
| Chef/Admin | `ahmed@lulas.com` | `12345` |
| Customer | `jana` | `jana123` |

These credentials are intentionally provided for local testing only. The included accounts and saved records are fictional demonstration data.

## Suggested Demo Flow

### Customer workflow

1. Open the home page and choose customer login.
2. Log in with the customer demo account.
3. Browse the available products.
4. Add a product to the cart.
5. Change quantities or remove an item.
6. Continue to checkout.
7. Enter fictional delivery details.
8. Submit the order and review its confirmation.

### Chef/Admin workflow

1. Open `chef-login.php`.
2. Log in with the Chef/Admin demo account.
3. Review the dashboard and orders.
4. Add or edit a product.
5. Update a category, stock quantity, or availability.
6. Open an order and update its status.
7. Log out.

Changes made during the demo are saved in the local database.

## Database

The database contains seven main tables:

- `users`
- `categories`
- `dishes`
- `cart`
- `orders`
- `order_items`
- `delivery`

Foreign keys connect users, products, categories, orders, and delivery records.

## Authentication

The application uses PHP sessions and separate customer and Chef/Admin login flows.

The login handlers support password hashes and also accept legacy plaintext demo passwords, upgrading them to hashes after a successful login. New customer registrations use password hashing.

This is a local challenge and portfolio project. Authentication and other security controls require further review before production deployment.

## Verification Status

The application was used and tested locally during development.

The portfolio copy includes database connection configuration changes. A fresh end-to-end check of this copy after importing the demonstration database is still pending.

No automated test suite or production security audit is claimed.

## Troubleshooting

### Database connection error

- Confirm that MySQL is running.
- Check the database name and connection settings in `db.php`.
- Check whether MySQL uses port `3306` or `3307`.
- Confirm that the SQL import completed successfully.

### Page not found

- Confirm that the project is inside the active Apache `htdocs` directory.
- Match the URL to the project folder name.
- Check the Apache port.

### Missing images or styles

- Keep the original asset folders and filenames.
- Confirm that the project was fully extracted before copying it.

## Scope

This project demonstrates customer ordering and shop administration in a local PHP application.

Publishing the source on GitHub does not host the running application. Running it requires PHP and a compatible database server.