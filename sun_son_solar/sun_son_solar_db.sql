-- ============================================================
-- Sun Son Solar - FULL database (fresh install)
-- Import this file once in phpMyAdmin (or: mysql -u root < sun_son_solar_db.sql)
-- This file includes the complete schema and seed data for the current version.
-- ============================================================
CREATE DATABASE IF NOT EXISTS sun_son_solar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE sun_son_solar_db;

-- ---------- Accounts ----------
CREATE TABLE users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_type         ENUM('Customer','Employee') NOT NULL,
    first_name           VARCHAR(50)  NOT NULL,
    middle_name          VARCHAR(50)  NOT NULL DEFAULT '',
    last_name            VARCHAR(50)  NOT NULL,
    birthdate            DATE         NOT NULL,
    gender               ENUM('Male','Female','Other') NOT NULL,
    email                VARCHAR(100) NOT NULL UNIQUE,
    phone                VARCHAR(11)  NOT NULL,
    department           ENUM('Administration','IT','Technician','Dispatch',
                              'Accounting','HR','Marketing','Sales','Customer Service') NULL DEFAULT NULL,
    address              VARCHAR(255) NOT NULL,
    username             VARCHAR(50)  NOT NULL UNIQUE,
    password             VARCHAR(255) NOT NULL,
    status               ENUM('pending','approved','rejected','inactive') NOT NULL DEFAULT 'pending',
    pref_reminders       TINYINT(1)   NOT NULL DEFAULT 1,
    pref_promotions      TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  middle_name VARCHAR(50),
  last_name VARCHAR(50) NOT NULL,
  birthdate DATE,
  gender VARCHAR(20),
  email VARCHAR(100) UNIQUE,
  phone_number VARCHAR(20),
  address VARCHAR(255),
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE login_attempts (
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username, ip_address),
    INDEX idx_login_attempts_locked_until (locked_until)
);

-- ---------- Catalog & settings ----------
CREATE TABLE services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT '',
    base_price  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0
);

CREATE TABLE products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(50)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    price       DECIMAL(10,2) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE availability (
    day_of_week  TINYINT NOT NULL PRIMARY KEY,   -- 0 = Sunday ... 6 = Saturday
    is_open      TINYINT(1) NOT NULL DEFAULT 1,
    open_time    TIME NOT NULL DEFAULT '08:00:00',
    close_time   TIME NOT NULL DEFAULT '17:00:00',
    max_bookings INT  NOT NULL DEFAULT 5
);

CREATE TABLE blocked_dates (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL UNIQUE,
    reason       VARCHAR(120) NOT NULL DEFAULT ''
);

CREATE TABLE notification_settings (
    type       ENUM('schedule','reminder','promotion','system') NOT NULL PRIMARY KEY,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1
);

-- ---------- Scheduling & payments ----------
CREATE TABLE bookings (
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

CREATE TABLE payments (
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

CREATE TABLE product_orders (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    status           ENUM('pending','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    total_amount     DECIMAL(14,2) NULL,
    delivery_address VARCHAR(255) NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_orders_status (status),
    INDEX idx_product_orders_created (created_at),
    CONSTRAINT fk_product_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE product_order_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id       INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    product_name   VARCHAR(120) NOT NULL,
    category       VARCHAR(50) NOT NULL,
    quantity       SMALLINT UNSIGNED NOT NULL,
    unit_price     DECIMAL(10,2) NULL,
    CONSTRAINT fk_product_order_items_order FOREIGN KEY (order_id) REFERENCES product_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_product_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- ---------- Notifications / announcements ----------
CREATE TABLE notifications (
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

CREATE TABLE notification_reads (
    notification_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (notification_id, user_id),
    CONSTRAINT fk_nr_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(120) NOT NULL,
    body       TEXT NOT NULL,
    priority   ENUM('normal','safety','urgent') NOT NULL DEFAULT 'normal',
    department VARCHAR(50) NULL,                 -- NULL = all employees
    posted_by  VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Support ----------
CREATE TABLE tickets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    category   VARCHAR(40)  NOT NULL DEFAULT 'General',
    status     ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ticket_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    sender_type ENUM('customer','admin') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

-- ---------- Technician field work ----------
CREATE TABLE attendance (
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

CREATE TABLE incident_reports (
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

-- ---------- Public website content ----------
CREATE TABLE quote_requests (
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

CREATE TABLE testimonials (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    location      VARCHAR(100) NOT NULL DEFAULT '',
    message       VARCHAR(600) NOT NULL,
    is_published  TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE news_posts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    body         TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Seed data
-- ============================================================
-- Admin passwords are bcrypt hashes (password_hash) so password_verify() works.
-- Kat: KittyKat16 / Sol: Admin  (passwords as given in the interview; Sol must change his after handover)
INSERT INTO admins (first_name, middle_name, last_name, birthdate, gender, email, phone_number, address, username, password_hash, status, must_change_password) VALUES
('Katherine', 'Olap', 'Sinagaraw', '1990-07-01', 'Female', 'katherine.sinagaraw@sunsonsolar.com', '09291230983', NULL, 'KittyKat16', '$2y$10$YK91cJ9XWP0v/heOxm6fj.z8QuHXWxIy3f3C9EOkzpcLw3VkJA9B6', 'active', 0),
('Sol', 'Sun', 'Solis', '1967-01-08', 'Male', 'sol.solis@sunsonsolar.com', '09291230983', NULL, 'Admin', '$2y$10$.t5Q7aj3uJ0BpnNS.m1bxe5KFnSvGvAf8vCTR/H3dysVUXhwKcr2W', 'active', 1);

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

-- ---------- Step 3 additions ----------

ALTER TABLE attendance
  ADD COLUMN checked_out_at DATETIME NULL,
  ADD COLUMN out_latitude   DECIMAL(10,7) NULL,
  ADD COLUMN out_longitude  DECIMAL(10,7) NULL;

CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_role VARCHAR(20)  NOT NULL,
    actor_id   INT UNSIGNED NOT NULL,
    actor_name VARCHAR(120) NOT NULL,
    action     VARCHAR(255) NOT NULL,
    page       VARCHAR(60)  NOT NULL DEFAULT '',
    ip_address VARCHAR(45)  NOT NULL DEFAULT '',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_created (created_at),
    INDEX idx_activity_actor (actor_role, actor_id)
);

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    status     ENUM('new','done','dismissed') NOT NULL DEFAULT 'new',
    handled_by VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prr_status (status),
    CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
