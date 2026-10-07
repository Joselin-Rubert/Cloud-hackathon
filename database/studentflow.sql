-- ============================================================
-- StudentFlow - Smart Student Life Assistant
-- MySQL Database Schema
-- Import this file in phpMyAdmin (http://localhost/phpmyadmin)
-- ============================================================

CREATE DATABASE IF NOT EXISTS studentflow CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE studentflow;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  college VARCHAR(150) DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  year VARCHAR(30) DEFAULT NULL,
  profile_image VARCHAR(255) DEFAULT NULL,
  remember_token VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- tasks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  category ENUM('Assignment','Exam','Project','Study','Personal','Other') NOT NULL DEFAULT 'Other',
  priority ENUM('High','Medium','Low') NOT NULL DEFAULT 'Medium',
  due_date DATE DEFAULT NULL,
  due_time TIME DEFAULT NULL,
  estimated_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  status ENUM('Not Started','In Progress','Completed') NOT NULL DEFAULT 'Not Started',
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_user (user_id),
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- events
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  event_date DATE NOT NULL,
  start_time TIME DEFAULT NULL,
  end_time TIME DEFAULT NULL,
  category ENUM('Assignment','Exam','Project','Personal','Study','Other') NOT NULL DEFAULT 'Personal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_user (user_id),
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- subjects
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
  exam_date DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subjects_user (user_id),
  CONSTRAINT fk_subjects_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- study_sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS study_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED DEFAULT NULL,
  topic VARCHAR(200) NOT NULL,
  duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  session_date DATE NOT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_study_user (user_id),
  KEY idx_study_date (session_date),
  CONSTRAINT fk_study_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- expenses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  category ENUM('Food','Travel','Education','Shopping','Entertainment','Bills','Other') NOT NULL DEFAULT 'Other',
  description VARCHAR(255) DEFAULT NULL,
  expense_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_expenses_user (user_id),
  KEY idx_expenses_date (expense_date),
  CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- habits
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS habits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  frequency ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
  target INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_habits_user (user_id),
  CONSTRAINT fk_habits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- habit_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS habit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  habit_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_habit_log (habit_id, log_date),
  KEY idx_habit_logs_user (user_id),
  CONSTRAINT fk_habitlogs_habit FOREIGN KEY (habit_id) REFERENCES habits (id) ON DELETE CASCADE,
  CONSTRAINT fk_habitlogs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- goals
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS goals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Personal',
  target_date DATE DEFAULT NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('On Track','At Risk','Completed') NOT NULL DEFAULT 'On Track',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_goals_user (user_id),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- focus_sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS focus_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED DEFAULT NULL,
  duration_minutes INT UNSIGNED NOT NULL DEFAULT 25,
  session_date DATE NOT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_focus_user (user_id),
  KEY idx_focus_date (session_date),
  CONSTRAINT fk_focus_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_focus_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  message VARCHAR(500) NOT NULL,
  type ENUM('deadline','overdue','exam','habit','goal','budget','focus','system') NOT NULL DEFAULT 'system',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- user_settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  dark_mode TINYINT(1) NOT NULL DEFAULT 0,
  daily_study_target INT UNSIGNED NOT NULL DEFAULT 120,
  monthly_budget DECIMAL(10,2) NOT NULL DEFAULT 0,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  task_reminders TINYINT(1) NOT NULL DEFAULT 1,
  habit_reminders TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_user (user_id),
  CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
