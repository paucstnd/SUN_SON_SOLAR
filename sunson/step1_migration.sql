USE sun_son_solar_db;

CREATE TABLE IF NOT EXISTS login_attempts (
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username, ip_address),
    INDEX idx_login_attempts_locked_until (locked_until)
);

UPDATE admins
SET middle_name = 'Sun', phone_number = '09291230983'
WHERE username = 'Admin';

UPDATE admins
SET phone_number = '09291230983'
WHERE username = 'KittyKat16';