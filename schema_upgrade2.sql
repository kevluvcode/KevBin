-- KevBin schema upgrade #2: votes, comments, profile metrics, paste metadata, aliases, password lock
-- NOTE: this is now OPTIONAL. With 'auto_migrate' => true in config.php, the app
-- applies the exact same DDL by itself on the first page load after deploy.
-- Run this ONCE manually in phpMyAdmin on the IF0_XXXXX_YOURDB database only
-- if you have disabled auto_migrate (or to apply it ahead of deploying the files).

-- Votes (likes/dislikes) â€” works for both pastes and user profiles
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(10) NOT NULL COMMENT 'paste or profile',
    entity_id VARCHAR(40) NOT NULL,
    user_id INT NOT NULL,
    vote TINYINT NOT NULL DEFAULT 1 COMMENT '1 = like, -1 = dislike',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (entity_type, entity_id, user_id),
    KEY idx_vote_entity (entity_type, entity_id),
    KEY idx_vote_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments â€” one table for both pastes and profiles
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(10) NOT NULL COMMENT 'paste or profile',
    entity_id VARCHAR(40) NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_comment_entity (entity_type, entity_id, created_at),
    KEY idx_comment_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profile alias (display nickname) + profile view counter
ALTER TABLE users
    ADD COLUMN alias VARCHAR(50) NULL AFTER profile_color,
    ADD COLUMN profile_views INT UNSIGNED NOT NULL DEFAULT 0 AFTER alias;

-- Paste metadata: description + tags (comma separated) for SEO & rich embeds
ALTER TABLE pastes
    ADD COLUMN description VARCHAR(255) NULL AFTER title,
    ADD COLUMN tags VARCHAR(255) NULL AFTER description;

-- Paste password lock (optional; content hidden until the password is entered)
ALTER TABLE pastes
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER tags;