CREATE DATABASE sun_son_solar_db;
USE sun_son_solar_db;

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_type  ENUM('Customer','Employee') NOT NULL,
    first_name    VARCHAR(50)  NOT NULL,
    middle_name   VARCHAR(50)  NOT NULL DEFAULT '',
    last_name     VARCHAR(50)  NOT NULL,
    birthdate     DATE         NOT NULL,
    gender        ENUM('Male','Female','Other') NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    phone         VARCHAR(11)  NOT NULL,
    department    ENUM('Administration','IT','Technician','Dispatcher',
                       'Accounting','HR','Marketing','Sales','Customer Service') NULL DEFAULT NULL,
    address       VARCHAR(255) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);


INSERT INTO admins (first_name, middle_name, last_name, birthdate, gender, email, phone_number, address, username, password_hash, status) 
VALUES ('Katherine', 'Olap', 'Sinagaraw', '1990-07-01', 'Female', 'katherine.sinagaraw@sunsonsolar.com', '09291230983', NULL, 'KittyKat16', '6feeeb76780f3f486befec26e8516979b3626fad360f780d3c3e82c6e423994b', 'active'), 
('Sol', NULL, 'Solis', '1967-01-08', 'Male', 'sol.solis@sunsonsolar.com', '09291230984', NULL, 'Admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'active');
