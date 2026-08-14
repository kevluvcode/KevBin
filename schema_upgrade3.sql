-- KevBin schema upgrade #3: Wiki (site docs, community wiki, personal wikis)
-- NOTE: this is now OPTIONAL. With 'auto_migrate' => true in config.php, the app
-- applies the exact same DDL by itself on the first page load after deploy.
-- Run this ONCE manually in phpMyAdmin on the IF0_XXXXX_YOURDB database only
-- if you have disabled auto_migrate (or to apply it ahead of deploying the files).
-- The app also seeds a "Welcome" home page for the community wiki on first run.

-- Wiki pages: scope = 'site' (admin docs), 'community' (shared), 'personal' (user-owned)
CREATE TABLE IF NOT EXISTS wiki_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('site','community','personal') NOT NULL DEFAULT 'community',
    owner_id INT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wiki_slug (scope, owner_id, slug),
    KEY idx_wiki_scope (scope, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full edit history for every wiki page
CREATE TABLE IF NOT EXISTS wiki_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    user_id INT NULL,
    content MEDIUMTEXT NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wiki_rev (page_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;