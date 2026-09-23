# Online Quartermaster

Online Quartermaster is a web-based equipment inventory and booking system for Scout groups.

It allows users to manage equipment, track individual stock units, create bookings, approve requests, record returns, and maintain equipment history.

## Features

- Equipment and category management
- Individual unit tracking with unique component codes
- Storage location management
- Equipment condition and maintenance history
- End-of-life and retirement tracking
- Booking requests and approval workflow
- Equipment collection and return tracking
- Damage notes and maintenance records
- Reports and data exports
- Role-based access control
- Password reset emails through SMTP

## Technology

- PHP 8+
- MySQL
- Apache
- HTML and CSS
- Vanilla JavaScript
- PHPMailer
- MAMP for local development

## Requirements

- MAMP or another Apache/PHP/MySQL environment
- PHP 8 or newer
- MySQL
- A web browser
- SMTP credentials for password reset emails

## User Roles

Administrator
- Manager users and accounts
- Manage equipment and stock
- Approve bookings
- View reports
- Manage Maintenance records

Quartermaster
- Manager equipment and individual units
- Manage storage locations
- Approve bookings
- Record maintenance and retirement

Section Volunteer
- Create equipment bookings
- View booking status
- Record booking and event details

## Workflows
#### Booking workflow
  -> Fill out booking details<br>
  -> Select equipment to be booked<br>
  -> Booking approval<br>
  -> Equipment collection<br>
  -> Equipment return<br>
  -> Damage or maintenance review<br>
  -> Booking completed

#### Stock Management
Equipment is stored as individual physical units. Each unit has:
- A unique component code
- An item type and category
- A storage location
- A condition
- A replacement cost
- A purchase date
- An option end-of-life date
- Maintenance and retirement history

#### Account Creation/Management
Accounts can be created by an administrator or Quartermaster via the [`manage_accounts.php`](manage_accounts.php) page
1. To create new accounts, the user is prompted to fill in:
   - First and last name
   - Email
   - Section name and role
   - A pre-filled, pre-generated, and unchangeable password is already in that input
2. An email is then sent to the given email address, prompting the new user to click on a link to reset their password to one of their choosing, the link resets after 1 hour
- The user can go the [`account.php`](account.php) page and edit their details at any time
- An administrator or Quartermaster can edit and delete accounts, as well as prompting another password reset email if needed.

## Important files
- [`index.php`](index.php) - Main application router
- [`login.php`](login.php) - User authentication
- [`manage_stock.php`](manage_stock.php) - Stock and unit management
- [`booking_creation.php`](booking_creation.php) - Booking creation
- [`reports.php`](reports.php) - Reports and searches
- [`db_conn.php`](db_conn.php) - Database connection
- [`mail_config.php`](mail_config.php) - SMTP email configuration
- [`static/css/stylings.css`](static/css/stylings.css) - Main styles
- [`scout_bookings/`](scout_bookings/) - File containing the SQL script to create the database and tables
- [`lib/PHPMailer/`](lib/PHPMailer/) - PHPMailer library

## Current limitations
- There is no default account within the database, so you would need to manually add in the details within a database editor
- Password reset and account creation relies on a SMTP configuration

