CREATE DATABASE sun_son_solar_db;
USE sun_son_solar_db;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  account_type  ENUM('Customer','Employee') NOT NULL,
  first_name    VARCHAR(100) NOT NULL,
  middle_name   VARCHAR(100) DEFAULT NULL,
  last_name     VARCHAR(100) NOT NULL,
  birthdate     DATE NOT NULL,
  gender        ENUM('Male','Female','Other') NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  phone         VARCHAR(20) NOT NULL,
  department    ENUM('IT','Technician','Dispatcher') DEFAULT NULL,
  address       VARCHAR(255) NOT NULL,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  status        ENUM('pending','approved') NOT NULL DEFAULT 'approved',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(50) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (username, password_hash, status) VALUES
('KittyKat16', SHA2('K@tSunshine16Shh... don\'ttellanyone!', 256), 'active'),
('Admin',      SHA2('admin 123', 256), 'active');
