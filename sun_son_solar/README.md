## Project Title
SUN_SON_SOLAR

## Team Name
PAW PATWROL

## Description
When light becomes power. Manage your solar account, installations, and service in one place.

## Setup (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\sun_son_solar`.
2. For a new database, import **sun_son_solar_db.sql** in phpMyAdmin. It includes the complete schema and seed data.
   If you already imported it, do not import it again; your database is already set up.
   This project does not include upgrade scripts for older databases. Back up existing data before replacing an old database.
3. Open `http://localhost/sun_son_solar/` (homepage) or `.../admin.php` (administrator login).

## Default accounts (from the interviews)
| Who | Username | Password |
|---|---|---|
| Katherine Sinagaraw (admin) | KittyKat16 | K@tSunShine16 |
| Sol Solis (admin, IT head) | Admin | admin123 (change it in My account after handover) |

Customers register themselves on the homepage. Employees are created by an administrator or HR
(User management > Add employee) and must change their temporary password on first login.

## Roles and pages
- **Public**: index.php (hero, products, services, benefits, about, testimonials, quote request, login/sign-up, FAQ, news, footer), quote_request.php
- **Customer**: customer_dashboard.php (book/reschedule/cancel), customer_notifications.php, customer_history.php (+ receipt.php), customer_support.php (tickets), customer_profile.php
- **Administrator**: admin_dashboard.php, admin_schedule.php, admin_users.php, admin_reports.php (+ admin_report_export.php), admin_notifications.php, admin_settings.php, admin_payments.php, admin_attendance.php, admin_incidents.php, admin_announcements.php, admin_support.php, admin_quotes.php, admin_content.php, admin_profile.php
- **Also**: forgot_password.php (public request), admin_resets.php (admin/HR set a temporary password), admin_activity.php (who did what), technician TIME OUT button with hours in admin_attendance.php
- **Dispatcher**: dispatcher_dashboard.php (approve/decline, assign technician, adjust schedule)
- **Technician**: technician_dashboard.php (one-button TIME IN with name, time and coordinates; assigned jobs), technician_report.php, employee_announcements.php, employee_profile.php
- **HR**: admin_users.php, admin_announcements.php, employee pages
- **Other departments**: employee_announcements.php, employee_profile.php

Shared files: config.php, security.php, auth.php, functions.php, layout.php, schedule_view.php, booking_update.php, login_register.php, admin_login.php, logout.php, assets/ (app.css, app.js, home.css), transL.png.

Note: the browser only shares location on https:// or localhost. When the site is hosted online, use https so the technician TIME IN can capture coordinates.
