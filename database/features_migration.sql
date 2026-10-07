-- =============================================================
-- StudentFlow — Features (Batch 2) migration
-- Learning Resource Finder: per-user result cache for the Google Books API.
--
-- Additive only. Run against the 'studentflow' database:
--   mysql -u root studentflow < database/features_migration.sql
-- =============================================================

CREATE TABLE IF NOT EXISTS learning_resource_cache (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED    NOT NULL,
  query      VARCHAR(150)    NOT NULL,
  payload    MEDIUMTEXT      NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lrc_user_query (user_id, query),
  CONSTRAINT fk_lrc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;