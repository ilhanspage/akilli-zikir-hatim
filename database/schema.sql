CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT 'Yönetici',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zikirs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  arabic_text VARCHAR(255) NULL,
  meaning TEXT NULL,
  default_target INT UNSIGNED NOT NULL DEFAULT 100,
  is_favorite TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_contents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  reference_text VARCHAR(180) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zikir_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  zikir_id INT UNSIGNED NULL,
  subtitle VARCHAR(255) NULL,
  target_count INT UNSIGNED NOT NULL DEFAULT 100000,
  current_count INT UNSIGNED NOT NULL DEFAULT 0,
  participant_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_live TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_live (is_live),
  CONSTRAINT fk_zikir_session_zikir FOREIGN KEY (zikir_id) REFERENCES zikirs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zikir_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  nickname VARCHAR(120) NOT NULL DEFAULT 'Misafir',
  amount INT UNSIGNED NOT NULL DEFAULT 0,
  client_id VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_session_created (session_id, created_at),
  CONSTRAINT fk_contribution_session FOREIGN KEY (session_id) REFERENCES zikir_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dua_circles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  subtitle VARCHAR(255) NULL,
  participant_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_live TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_dua_live (is_live)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dua_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  circle_id INT UNSIGNED NULL,
  nickname VARCHAR(120) NOT NULL DEFAULT 'Misafir',
  client_id VARCHAR(120) NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Genel',
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  amin_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_dua_approved (is_approved, created_at),
  INDEX idx_dua_client (client_id),
  INDEX idx_dua_category (category),
  CONSTRAINT fk_dua_circle FOREIGN KEY (circle_id) REFERENCES dua_circles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dua_joins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  nickname VARCHAR(120) NOT NULL DEFAULT 'Misafir',
  client_id VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_dua_client (request_id, client_id),
  CONSTRAINT fk_dua_join_request FOREIGN KEY (request_id) REFERENCES dua_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hatims (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
  participant_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_hatim_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hatim_juz (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hatim_id INT UNSIGNED NOT NULL,
  juz_number TINYINT UNSIGNED NOT NULL,
  status ENUM('empty','reserved','completed') NOT NULL DEFAULT 'empty',
  nickname VARCHAR(120) NULL,
  client_id VARCHAR(120) NULL,
  reserved_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_hatim_juz (hatim_id, juz_number),
  INDEX idx_hatim_status (hatim_id, status),
  CONSTRAINT fk_hatim_juz_hatim FOREIGN KEY (hatim_id) REFERENCES hatims(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(40) NOT NULL UNIQUE,
  description TEXT NULL,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
