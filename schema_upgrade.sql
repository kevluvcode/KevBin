-- KevBin schema upgrade: profile fields, IP bans
-- Run this ONCE in phpMyAdmin on your own database (the one from config.php).

-- Extra profile fields
ALTER TABLE users
    ADD COLUMN bio TEXT NULL AFTER profile_color,
    ADD COLUMN location VARCHAR(100) NULL AFTER bio,
    ADD COLUMN website VARCHAR(255) NULL AFTER location,
    ADD COLUMN discord VARCHAR(100) NULL AFTER website,
    ADD COLUMN telegram VARCHAR(100) NULL AFTER discord,
    ADD COLUMN twitter VARCHAR(100) NULL AFTER telegram,
    ADD COLUMN youtube VARCHAR(255) NULL AFTER twitter;

-- IP ban list (moderators can ban an IP from the admin panel)
CREATE TABLE IF NOT EXISTS banned_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    banned_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_banned_ip (ip),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paste color available to all users now (not just admins)
-- (no schema change needed, paste_color column already exists)'