-- ============================================================================
-- DeedsPlan (ProjectLibre Online) — MySQL 8 Schema
-- Host: InfinityFree (InnoDB, utf8mb4). Import qua phpMyAdmin.
-- Engine tính toán nằm ở client; DB lưu KẾT QUẢ đã persist để minh bạch đa người dùng.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- users ----
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,               -- PHP password_hash()
  locale        VARCHAR(10)  NOT NULL DEFAULT 'vi',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ projects ----
CREATE TABLE IF NOT EXISTS projects (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id       INT UNSIGNED NOT NULL,
  name           VARCHAR(190) NOT NULL,
  description    TEXT NULL,
  start_date     DATE NOT NULL,                      -- project start (PSAP)
  finish_date    DATE NULL,                          -- computed target finish
  current_date   DATE NULL,                          -- status date for reporting
  timezone       VARCHAR(60) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  work_day_hours DECIMAL(5,2) NOT NULL DEFAULT 8.00, -- hours per day
  work_week_days JSON NOT NULL,                      -- [1,2,3,4,5] T2..T6
  calendar_json  JSON NULL,                          -- exception days (holidays) ["2026-02-17",...]
  autoschedule   TINYINT(1) NOT NULL DEFAULT 1,
  critical_path_visible TINYINT(1) NOT NULL DEFAULT 1,
  baseline_count INT UNSIGNED NOT NULL DEFAULT 0,
  invite_code    CHAR(6) NULL UNIQUE,                -- share by code
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_proj_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_proj_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- members (share) --
CREATE TABLE IF NOT EXISTS project_members (
  project_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  role       ENUM('owner','editor','viewer') NOT NULL DEFAULT 'editor',
  joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id),
  CONSTRAINT fk_pm_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- tasks ------
CREATE TABLE IF NOT EXISTS tasks (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id   INT UNSIGNED NOT NULL,
  id_uid       INT UNSIGNED NOT NULL,                 -- Task ID shown in UI (stable)
  name         VARCHAR(255) NOT NULL,
  notes        TEXT NULL,
  parent_uid   INT UNSIGNED NULL,                     -- NULL => top level
  level        TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- outline level
  is_summary   TINYINT(1) NOT NULL DEFAULT 0,
  is_milestone TINYINT(1) NOT NULL DEFAULT 0,
  is_manual    TINYINT(1) NOT NULL DEFAULT 0,
  type_task    ENUM('fixed_duration','fixed_units','fixed_work') NOT NULL DEFAULT 'fixed_units',
  duration_h   DECIMAL(12,2) NOT NULL DEFAULT 0,      -- planned duration (hours)
  work_h       DECIMAL(12,2) NOT NULL DEFAULT 0,      -- planned work (hours)
  start_h      DECIMAL(14,2) NULL,                    -- scheduled start (working-hours axis from PSAP)
  finish_h     DECIMAL(14,2) NULL,
  constraint_type ENUM('none','snet','snlt','mnso','mnsn','mfno','mfnl','msao','lsac') NOT NULL DEFAULT 'none',
  constraint_date DATE NULL,
  percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0,   -- 0..100
  actual_start_h   DECIMAL(14,2) NULL,
  actual_finish_h  DECIMAL(14,2) NULL,
  actual_work_h    DECIMAL(12,2) NOT NULL DEFAULT 0,
  remaining_work_h DECIMAL(12,2) NULL,
  priority     TINYINT UNSIGNED NOT NULL DEFAULT 128, -- leveling order 0..255
  fixed_cost   DECIMAL(14,2) NOT NULL DEFAULT 0,
  material_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_cost   DECIMAL(14,2) NOT NULL DEFAULT 0,      -- persisted roll-up
  splits_json  JSON NULL,                             -- [{startH,endH},...]
  wbs          VARCHAR(32) NULL,                      -- "1.2.3"
  sort_order   INT NOT NULL DEFAULT 0,
  updated_by   INT UNSIGNED NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_task (project_id, id_uid),
  CONSTRAINT fk_task_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  INDEX idx_task_parent (project_id, parent_uid),
  INDEX idx_task_sort (project_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- links -------
CREATE TABLE IF NOT EXISTS task_links (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  pred_uid   INT UNSIGNED NOT NULL,
  succ_uid   INT UNSIGNED NOT NULL,
  link_type  ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
  lag_h      DECIMAL(10,2) NOT NULL DEFAULT 0,        -- lag (+) / lead (-) hours
  enabled    TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_link (project_id, pred_uid, succ_uid),
  CONSTRAINT fk_link_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- resources ------
CREATE TABLE IF NOT EXISTS resources (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id   INT UNSIGNED NOT NULL,
  id_uid       INT UNSIGNED NOT NULL,
  name         VARCHAR(190) NOT NULL,
  resource_type ENUM('work','material') NOT NULL DEFAULT 'work',
  initials     VARCHAR(8)  NULL,
  max_units_pct DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  std_rate_hr  DECIMAL(12,2) NOT NULL DEFAULT 0,      -- currency/hour
  ovt_rate_hr  DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_per_use DECIMAL(12,2) NOT NULL DEFAULT 0,
  material_label VARCHAR(60) NULL,                    -- "kg","cái"
  calendar_json JSON NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  notes        TEXT NULL,
  UNIQUE KEY uq_res (project_id, id_uid),
  CONSTRAINT fk_res_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- assignments -----
CREATE TABLE IF NOT EXISTS assignments (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  task_uid    INT UNSIGNED NOT NULL,
  res_uid     INT UNSIGNED NOT NULL,
  units_pct   DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  work_h      DECIMAL(12,2) NOT NULL DEFAULT 0,
  actual_work_h DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_type   ENUM('proportional','fixed','prorated') NOT NULL DEFAULT 'proportional',
  overtime_h  DECIMAL(10,2) NOT NULL DEFAULT 0,
  delay_h     DECIMAL(10,2) NOT NULL DEFAULT 0,       -- leveling delay (persisted!)
  remaining_work_h DECIMAL(12,2) NULL,
  notes       TEXT NULL,
  UNIQUE KEY uq_assign (project_id, task_uid, res_uid),
  CONSTRAINT fk_as_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- baselines -----
CREATE TABLE IF NOT EXISTS baselines (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  bl_number  INT UNSIGNED NOT NULL,                   -- 1..n
  label      VARCHAR(60) NOT NULL DEFAULT 'Baseline',
  saved_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  saved_by   INT UNSIGNED NULL,
  snapshot   LONGTEXT NOT NULL,                       -- JSON per-task {uid:{s,f,d,w,c}}
  UNIQUE KEY uq_bl (project_id, bl_number),
  CONSTRAINT fk_bl_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- custom fields ----
CREATE TABLE IF NOT EXISTS custom_fields (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  entity     ENUM('task','resource','assignment') NOT NULL DEFAULT 'task',
  alias      VARCHAR(60) NOT NULL,
  data_type  ENUM('text','number','date','percent','boolean','dropdown') NOT NULL DEFAULT 'text',
  values_map JSON NULL,
  UNIQUE KEY uq_cf (project_id, entity, alias),
  CONSTRAINT fk_cf_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_field_values (
  field_id   BIGINT UNSIGNED NOT NULL,
  entity_uid BIGINT UNSIGNED NOT NULL,
  value      VARCHAR(255) NULL,
  PRIMARY KEY (field_id, entity_uid),
  CONSTRAINT fk_cfv FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- audit trail ----
CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,                    -- 'graph.put','baseline.save'...
  entity     VARCHAR(30) NULL,
  entity_uid BIGINT UNSIGNED NULL,
  diff_json  JSON NULL,                               -- before/after cho tường minh
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  INDEX idx_audit (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
