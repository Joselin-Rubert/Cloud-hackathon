-- StudentFlow - GitHub Explorer migration
-- Additive only: existing tables are NOT touched, trimmed or rebuilt.
-- Run once: mysql -u root < database/github_migration.sql

CREATE TABLE IF NOT EXISTS github_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    github_username VARCHAR(39) NOT NULL,
    github_profile_url VARCHAR(255) NOT NULL DEFAULT '',
    avatar_url VARCHAR(255) DEFAULT NULL,
    name VARCHAR(150) DEFAULT NULL,
    bio VARCHAR(600) DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    company VARCHAR(150) DEFAULT NULL,
    public_repos INT UNSIGNED NOT NULL DEFAULT 0,
    followers INT UNSIGNED NOT NULL DEFAULT 0,
    following INT UNSIGNED NOT NULL DEFAULT 0,
    top_language VARCHAR(80) DEFAULT NULL,
    recent_activity VARCHAR(120) DEFAULT NULL,
    recent_events TEXT DEFAULT NULL,
    recent_activity_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_synced DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_github_profile_user (user_id),
    CONSTRAINT fk_github_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS github_repositories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    github_repo_id INT UNSIGNED NOT NULL,
    repo_name VARCHAR(200) NOT NULL,
    description VARCHAR(600) DEFAULT NULL,
    language VARCHAR(80) DEFAULT NULL,
    stars INT UNSIGNED NOT NULL DEFAULT 0,
    forks INT UNSIGNED NOT NULL DEFAULT 0,
    open_issues INT UNSIGNED NOT NULL DEFAULT 0,
    html_url VARCHAR(255) NOT NULL DEFAULT '',
    repo_updated_at DATETIME DEFAULT NULL,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_github_repo (user_id, github_repo_id),
    KEY idx_github_repo_user (user_id),
    CONSTRAINT fk_github_repo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional per-goal GitHub repository link (kept additive + optional).
ALTER TABLE goals ADD COLUMN IF NOT EXISTS github_repo_url VARCHAR(255) DEFAULT NULL AFTER description;