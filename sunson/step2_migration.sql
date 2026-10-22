-- ============================================================
-- Sun Son Solar - STEP 2 MIGRATION
-- For an EXISTING sun_son_solar_db (the one you already imported, with step1_migration.sql applied).
-- Run ONCE in phpMyAdmin > sun_son_solar_db > SQL.
-- Fresh install? Use sun_son_solar_db.sql instead.
-- ============================================================
USE sun_son_solar_db;

-- 1) users: 'Dispatch' department (per interview), 'inactive' status (deactivate accounts),
--    notification preferences, temp-password flag for employee accounts created by HR/Admin
ALTER TABLE users
  MODIFY department ENUM('Administration','IT','Technician','Dispatcher','Dispatch',
                         'Accounting','HR','Marketing','Sales','Customer Service') NULL DEFAULT NULL,
  MODIFY status ENUM('pending','approved','rejected','inactive') NOT NULL DEFAULT 'pending',
  ADD COLUMN pref_reminders TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN pref_promotions TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
UPDATE users SET department = 'Dispatch' WHERE department = 'Dispatcher';
ALTER TABLE users
  MODIFY department ENUM('Administration','IT','Technician','Dispatch',
                         'Accounting','HR','Marketing','Sales','Customer Service') NULL DEFAULT NULL;

-- 2) admins: FIX - passwords were stored as SHA-256 but the login uses password_verify() (bcrypt),
--    so nobody could log in. Replace with proper bcrypt hashes.
ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
UPDATE admins SET password_hash = '$2y$10$YK91cJ9XWP0v/heOxm6fj.z8QuHXWxIy3f3C9EOkzpcLw3VkJA9B6', must_change_password = 0 WHERE username = 'KittyKat16';
UPDATE admins SET password_hash = '$2y$10$.t5Q7aj3uJ0BpnNS.m1bxe5KFnSvGvAf8vCTR/H3dysVUXhwKcr2W', must_change_password = 1 WHERE username = 'Admin';

-- 3) New tables
CREATE TABLE IF NOT EXISTS services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT '',
    base_price  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(50)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    price       DECIMAL(10,2) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS availability (
    day_of_week  TINYINT NOT NULL PRIMARY KEY,   -- 0 = Sunday ... 6 = Saturday
    is_open      TINYINT(1) NOT NULL DEFAULT 1,
    open_time    TIME NOT NULL DEFAULT '08:00:00',
    close_time   TIME NOT NULL DEFAULT '17:00:00',
    max_bookings INT  NOT NULL DEFAULT 5
);

CREATE TABLE IF NOT EXISTS blocked_dates (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL UNIQUE,
    reason       VARCHAR(120) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS notification_settings (
    type       ENUM('schedule','reminder','promotion','system') NOT NULL PRIMARY KEY,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS bookings (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    service_id     INT UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    address        VARCHAR(255) NOT NULL,
    notes          VARCHAR(500) NOT NULL DEFAULT '',
    status         ENUM('pending','approved','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
    technician_id  INT UNSIGNED NULL,
    admin_note     VARCHAR(255) NOT NULL DEFAULT '',
    price          DECIMAL(10,2) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bookings_date (scheduled_date),
    INDEX idx_bookings_status (status),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_bookings_tech FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    method      ENUM('Cash','GCash','Bank Transfer','Card','Other') NOT NULL DEFAULT 'Cash',
    reference_no VARCHAR(60) NOT NULL DEFAULT '',
    receipt_no  VARCHAR(30) NOT NULL UNIQUE,
    recorded_by VARCHAR(100) NOT NULL DEFAULT '',
    paid_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    audience   ENUM('customers','user') NOT NULL DEFAULT 'customers',
    user_id    INT UNSIGNED NULL,
    type       ENUM('schedule','reminder','promotion','system') NOT NULL DEFAULT 'system',
    title      VARCHAR(120) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    created_by VARCHAR(100) NOT NULL DEFAULT 'System',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notification_reads (
    notification_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (notification_id, user_id),
    CONSTRAINT fk_nr_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(120) NOT NULL,
    body       TEXT NOT NULL,
    priority   ENUM('normal','safety','urgent') NOT NULL DEFAULT 'normal',
    department VARCHAR(50) NULL,                 -- NULL = all employees
    posted_by  VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tickets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    category   VARCHAR(40)  NOT NULL DEFAULT 'General',
    status     ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ticket_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    sender_type ENUM('customer','admin') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    checked_in_at DATETIME NOT NULL,
    latitude      DECIMAL(10,7) NULL,
    longitude     DECIMAL(10,7) NULL,
    accuracy_m    INT NULL,
    booking_id    INT UNSIGNED NULL,
    INDEX idx_attendance_date (checked_in_at),
    CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS incident_reports (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    booking_id    INT UNSIGNED NULL,
    type          ENUM('Equipment issue','Customer concern','Safety hazard','Other') NOT NULL DEFAULT 'Other',
    description   TEXT NOT NULL,
    status        ENUM('new','reviewed','resolved') NOT NULL DEFAULT 'new',
    admin_note    VARCHAR(255) NOT NULL DEFAULT '',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_inc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS quote_requests (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    phone      VARCHAR(20)  NOT NULL,
    service_id INT UNSIGNED NULL,
    message    VARCHAR(500) NOT NULL DEFAULT '',
    status     ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quote_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS testimonials (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    location      VARCHAR(100) NOT NULL DEFAULT '',
    message       VARCHAR(600) NOT NULL,
    is_published  TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS news_posts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    body         TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4) Seed data
INSERT INTO services (name, description, base_price, sort_order) VALUES
('Consultation', 'Free assessment of your energy needs and roof suitability.', 0.00, 1),
('Designing',    'Custom solar system layout and sizing for your property.', 0.00, 2),
('Permitting',   'We prepare and file the permits and utility paperwork for you.', 0.00, 3),
('Installation', 'Professional installation by our certified technicians.', 0.00, 4),
('Maintenance',  'Scheduled cleaning, inspection and performance checks.', 0.00, 5),
('Repair',       'Fast troubleshooting and repair of panels, inverters and wiring.', 0.00, 6),
('Monitoring',   'Remote monitoring of your system output and alerts.', 0.00, 7);

INSERT INTO products (category, name, description, price) VALUES
('Solar Panels', 'Monocrystalline Solar Panel', 'High-efficiency panels for homes and small businesses.', NULL),
('Inverters', 'Hybrid Solar Inverter', 'Converts solar DC power to AC and works with batteries.', NULL),
('Batteries', 'Lithium Solar Battery', 'Store your extra solar energy for night time and outages.', NULL),
('Racking & Mounting', 'Roof Mounting Rail Kit', 'Corrosion-resistant rails, clamps and hooks for secure installation.', NULL),
('Wires & Cables', 'PV Solar Cable', 'UV-resistant DC cable made for solar installations.', NULL);

INSERT INTO availability (day_of_week, is_open, open_time, close_time, max_bookings) VALUES
(0,0,'08:00:00','17:00:00',5),(1,1,'08:00:00','17:00:00',5),(2,1,'08:00:00','17:00:00',5),
(3,1,'08:00:00','17:00:00',5),(4,1,'08:00:00','17:00:00',5),(5,1,'08:00:00','17:00:00',5),
(6,1,'08:00:00','12:00:00',3);

INSERT INTO notification_settings (type, is_enabled) VALUES
('schedule',1),('reminder',1),('promotion',1),('system',1);
INSERT INTO news_posts (title, body) VALUES
('How a solar power system works', 'Solar panels turn sunlight into direct current (DC) electricity. An inverter converts it to the alternating current (AC) your home uses. Any extra energy can be stored in a battery or, where your utility allows it, exported to the grid.'),
('On-grid, off-grid or hybrid: which one fits you?', 'On-grid systems connect to the utility and are the most affordable. Off-grid systems rely on batteries and suit remote sites. Hybrid systems combine both so you keep power during an outage. Book a free consultation and we will help you choose.');
