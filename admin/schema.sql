-- Smashbox dashboard schema.
-- Run once against the database created in the Cloud4Host panel.

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  name          VARCHAR(120) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  must_change   TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per edited field. Fields with no row here fall back to the
-- value baked into template.html.
CREATE TABLE IF NOT EXISTS content (
  field_id   VARCHAR(24) NOT NULL,
  type       ENUM('text','image') NOT NULL,
  value      MEDIUMTEXT NOT NULL,
  alt        VARCHAR(500) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded images, so the picker can offer previously used files.
CREATE TABLE IF NOT EXISTS media (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename    VARCHAR(255) NOT NULL,
  original    VARCHAR(255) NOT NULL DEFAULT '',
  mime        VARCHAR(60) NOT NULL DEFAULT '',
  bytes       INT UNSIGNED NOT NULL DEFAULT 0,
  width       INT UNSIGNED NOT NULL DEFAULT 0,
  height      INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(190) NOT NULL,
  name       VARCHAR(190) NOT NULL DEFAULT '',
  phone      VARCHAR(60) NOT NULL DEFAULT '',
  message    TEXT NULL,
  source     VARCHAR(60) NOT NULL DEFAULT 'hero',
  status     ENUM('new','replied','archived') NOT NULL DEFAULT 'new',
  seen_at    DATETIME NULL,
  replied_at DATETIME NULL,
  note       TEXT NULL,
  ip         VARBINARY(16) NULL,
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leads_status (status),
  KEY idx_leads_created (created_at),
  KEY idx_leads_seen (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reply templates offered on the lead panel.
CREATE TABLE IF NOT EXISTS templates (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name     VARCHAR(120) NOT NULL,
  subject  VARCHAR(255) NOT NULL,
  body     TEXT NOT NULL,
  position INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name  VARCHAR(80) NOT NULL,
  value TEXT NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
