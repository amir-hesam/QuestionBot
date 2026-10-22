-- ============================================================
--  Telegram exam bot - database schema (MySQL / MariaDB)
--  Charset: utf8mb4 (for Persian and emoji)
-- ============================================================

CREATE TABLE IF NOT EXISTS bot_users (
  telegram_id BIGINT NOT NULL PRIMARY KEY,
  username VARCHAR(100) NULL,
  full_name VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  last_seen DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  class_name VARCHAR(100) NULL,
  locked_telegram_id BIGINT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_code (code),
  KEY idx_name (last_name, first_name),
  KEY idx_locked (locked_telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quizzes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  access_code VARCHAR(32) NULL,
  time_limit INT NOT NULL DEFAULT 0,
  shuffle TINYINT(1) NOT NULL DEFAULT 1,
  show_answer TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 1,
  text TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  correct CHAR(1) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_quiz_pos (quiz_id, position),
  CONSTRAINT fk_q_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  telegram_id BIGINT NOT NULL,
  status ENUM('in_progress','finished') NOT NULL DEFAULT 'in_progress',
  finish_reason ENUM('student','timeout','admin') NULL,
  current_index INT NOT NULL DEFAULT 0,
  score INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  question_order LONGTEXT NULL,
  option_order LONGTEXT NULL,
  started_at DATETIME NOT NULL,
  deadline DATETIME NULL,
  finished_at DATETIME NULL,
  UNIQUE KEY uq_student_quiz (student_id, quiz_id),
  KEY idx_status (status),
  KEY idx_deadline (deadline),
  KEY idx_tg (telegram_id),
  CONSTRAINT fk_a_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
  CONSTRAINT fk_a_student FOREIGN KEY (student_id) REFERENCES student_codes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected CHAR(1) NULL,
  is_correct TINYINT(1) NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ans (attempt_id, question_id),
  KEY idx_attempt (attempt_id),
  CONSTRAINT fk_ans_attempt FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_q FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_sessions (
  telegram_id BIGINT NOT NULL PRIMARY KEY,
  state VARCHAR(64) NOT NULL,
  payload LONGTEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kv (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processed_updates (
  update_id BIGINT NOT NULL PRIMARY KEY,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
