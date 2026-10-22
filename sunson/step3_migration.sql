-- ============================================================
-- Sun Son Solar - STEP 3 MIGRATION (time-out, activity log, forgot-password requests)
-- Run ONCE on an existing sun_son_solar_db that already has step2 applied.
-- Fresh install? These are already included at the end of sun_son_solar_db.sql.
-- ============================================================
USE sun_son_solar_db;

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
