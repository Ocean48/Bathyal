-- Bathyal Database Schema Initialization
-- MySQL 8.0+

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. WORKSPACE & HIERARCHY LAYER
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `workspaces` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `is_personal` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `default_mode` ENUM('simple', 'enterprise') DEFAULT 'simple',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `workspace_members` (
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('owner', 'admin', 'member', 'guest') DEFAULT 'member',
    PRIMARY KEY (`workspace_id`, `user_id`),
    CONSTRAINT `fk_wm_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `user_groups` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `color_hex` VARCHAR(7) DEFAULT '#6366F1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_group_slug` (`workspace_id`, `slug`),
    CONSTRAINT `fk_ug_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `user_group_members` (
    `group_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`, `user_id`),
    CONSTRAINT `fk_ugm_group` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ugm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `folders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `position` INT DEFAULT 0,
    CONSTRAINT `fk_folders_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `projects` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `folder_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `icon` VARCHAR(50) DEFAULT 'list',
    `color_hex` VARCHAR(7) DEFAULT '#4A90E2',
    `is_template` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_projects_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_projects_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 2. WORKFLOW & STATUS ENGINE
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `statuses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL,
    `color_hex` VARCHAR(7) DEFAULT '#808080',
    `position` INT DEFAULT 0,
    `type` ENUM('todo', 'in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    CONSTRAINT `fk_statuses_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 3. CORE TASK ENGINE (SIMPLE TO ENTERPRISE)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tasks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `project_id` BIGINT UNSIGNED NULL,
    `parent_id` BIGINT UNSIGNED NULL,
    `status_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT NULL,
    `priority` ENUM('none', 'low', 'medium', 'high', 'urgent') DEFAULT 'none',
    `start_date` DATETIME NULL,
    `due_date` DATETIME NULL,
    `estimated_hours` DECIMAL(6,2) NULL,
    `position` INT DEFAULT 0,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tasks_ws_pos` (`workspace_id`, `position`),
    INDEX `idx_tasks_proj_status` (`project_id`, `status_id`),
    INDEX `idx_tasks_due_date` (`due_date`),
    CONSTRAINT `fk_tasks_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`),
    CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `task_assignees` (
    `task_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`task_id`, `user_id`),
    CONSTRAINT `fk_ta_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `task_group_assignees` (
    `task_id` BIGINT UNSIGNED NOT NULL,
    `group_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`task_id`, `group_id`),
    CONSTRAINT `fk_tga_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tga_group` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `project_group_permissions` (
    `project_id` BIGINT UNSIGNED NOT NULL,
    `group_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('admin', 'member', 'guest') DEFAULT 'member',
    PRIMARY KEY (`project_id`, `group_id`),
    CONSTRAINT `fk_pgp_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pgp_group` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `task_dependencies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `blocking_task_id` BIGINT UNSIGNED NOT NULL,
    `dependent_task_id` BIGINT UNSIGNED NOT NULL,
    `dependency_type` ENUM('finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish') DEFAULT 'finish_to_start',
    CONSTRAINT `fk_td_blocking` FOREIGN KEY (`blocking_task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_td_dependent` FOREIGN KEY (`dependent_task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 4. DYNAMIC CUSTOM FIELDS ENGINE (ENTERPRISE)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `custom_fields` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `project_id` BIGINT UNSIGNED NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `field_type` ENUM('text', 'number', 'select', 'multi_select', 'date', 'checkbox', 'user', 'url', 'formula') NOT NULL,
    `options` JSON NULL,
    CONSTRAINT `fk_cf_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cf_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `custom_field_values` (
    `task_id` BIGINT UNSIGNED NOT NULL,
    `custom_field_id` BIGINT UNSIGNED NOT NULL,
    `value_text` TEXT NULL,
    `value_number` DECIMAL(12,4) NULL,
    `value_date` DATETIME NULL,
    `value_json` JSON NULL,
    PRIMARY KEY (`task_id`, `custom_field_id`),
    CONSTRAINT `fk_cfv_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cfv_custom_field` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 5. DOCUMENTS, WORKSPACE NOTES & WHITEBOARDS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `documents` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `project_id` BIGINT UNSIGNED NULL,
    `task_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL,
    `doc_type` ENUM('rich_text', 'file') DEFAULT 'rich_text',
    `file_path` VARCHAR(500) NULL,
    `file_name` VARCHAR(255) NULL,
    `original_name` VARCHAR(255) NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `mime_type` VARCHAR(100) NULL,
    `file_extension` VARCHAR(20) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_docs_workspace` (`workspace_id`),
    INDEX `idx_docs_task` (`task_id`),
    INDEX `idx_docs_type` (`workspace_id`, `doc_type`),
    CONSTRAINT `fk_docs_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_docs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_docs_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_docs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 6. TIME TRACKING & AUDIT LOGS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `time_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NULL,
    `duration_seconds` INT NULL,
    `billable` BOOLEAN DEFAULT FALSE,
    `notes` TEXT NULL,
    CONSTRAINT `fk_tl_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `task_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_al_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_al_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- 7. INITIAL SEED DATA (Default Global Statuses)
-- --------------------------------------------------------

INSERT IGNORE INTO `statuses` (`id`, `project_id`, `name`, `color_hex`, `position`, `type`) VALUES
(1, NULL, 'To Do', '#94A3B8', 1, 'todo'),
(2, NULL, 'In Progress', '#3B82F6', 2, 'in_progress'),
(3, NULL, 'Completed', '#10B981', 3, 'completed'),
(4, NULL, 'Cancelled', '#EF4444', 4, 'cancelled');

SET FOREIGN_KEY_CHECKS = 1;
