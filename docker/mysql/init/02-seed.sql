-- Bathyal Database Seed Data
-- Default Users, Personal Workspace (Simple Mode), Enterprise Workspace, Folders, Projects, and Sample Tasks

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Default Users
-- Passwords are 'password123'
-- Generated via password_hash('password123', PASSWORD_BCRYPT)
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `full_name`, `default_mode`, `created_at`) VALUES
(1, 'admin@bathyal.local', '$2y$10$hVhQf.18VvfHmVzdw6Pw5OfliO/IZ.3Lz8sxPlxA4dAM9axE2X7he', 'System Administrator', 'enterprise', NOW()),
(2, 'demo@bathyal.local', '$2y$10$hVhQf.18VvfHmVzdw6Pw5OfliO/IZ.3Lz8sxPlxA4dAM9axE2X7he', 'Demo User', 'simple', NOW());

-- 2. Workspaces
INSERT IGNORE INTO `workspaces` (`id`, `name`, `slug`, `is_personal`, `created_at`) VALUES
(1, 'My Personal Tasks', 'personal-tasks', 1, NOW()),
(2, 'Acme Corp Work OS', 'acme-corp', 0, NOW());

-- 3. Workspace Members
INSERT IGNORE INTO `workspace_members` (`workspace_id`, `user_id`, `role`) VALUES
(1, 2, 'owner'),
(1, 1, 'admin'),
(2, 1, 'owner'),
(2, 2, 'member');

-- 4. Enterprise Folders
INSERT IGNORE INTO `folders` (`id`, `workspace_id`, `name`, `position`) VALUES
(1, 2, 'Product & Design', 1),
(2, 2, 'Engineering', 2);

-- 5. Projects
INSERT IGNORE INTO `projects` (`id`, `workspace_id`, `folder_id`, `name`, `description`, `icon`, `color_hex`, `is_template`, `created_at`) VALUES
(1, 2, 1, 'Mobile App Redesign', 'Complete redesign of iOS and Android applications', 'smartphone', '#6366F1', 0, NOW()),
(2, 2, 2, 'Core API Platform', 'High-throughput microservices and database clustering', 'server', '#0EA5E9', 0, NOW()),
(3, 2, 2, 'Infrastructure & Cloud', 'Kubernetes clusters and automated CI/CD pipelines', 'cloud', '#10B981', 0, NOW());

-- 6. Custom Fields
INSERT IGNORE INTO `custom_fields` (`id`, `workspace_id`, `project_id`, `field_name`, `field_type`, `options`) VALUES
(1, 2, NULL, 'Department', 'select', '["Engineering", "Product", "Design", "Marketing", "Operations"]'),
(2, 2, 1, 'Client Approval', 'checkbox', NULL),
(3, 2, 2, 'Complexity Score', 'number', NULL),
(4, 2, NULL, 'Target Release', 'date', NULL);

-- 7. Tasks (Personal / Simple Mode)
INSERT IGNORE INTO `tasks` (`id`, `workspace_id`, `project_id`, `parent_id`, `status_id`, `title`, `description`, `priority`, `start_date`, `due_date`, `estimated_hours`, `position`, `created_by`, `created_at`) VALUES
(1, 1, NULL, NULL, 1, 'Review weekly project goals', 'Check current roadmap and clear immediate blockers', 'high', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 1.0, 1, 2, NOW()),
(2, 1, NULL, NULL, 2, 'Design new landing page wireframes', 'Prepare low-fidelity Figma mockups for review', 'medium', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 3.5, 2, 2, NOW()),
(3, 1, NULL, NULL, 3, 'Install workspace dependencies', 'Ensure local Docker and PHP environments are running', 'low', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), 0.5, 3, 2, NOW()),
(4, 1, NULL, NULL, 1, 'Schedule 1-on-1 with tech lead', 'Discuss architectural roadmap and Q3 priorities', 'none', NOW(), DATE_ADD(NOW(), INTERVAL 4 DAY), 0.5, 4, 2, NOW());

-- 8. Tasks (Enterprise Mode)
INSERT IGNORE INTO `tasks` (`id`, `workspace_id`, `project_id`, `parent_id`, `status_id`, `title`, `description`, `priority`, `start_date`, `due_date`, `estimated_hours`, `position`, `created_by`, `created_at`) VALUES
(5, 2, 1, NULL, 2, 'User Research & Personas', 'Synthesize 15 user interview sessions and extract pain points', 'high', NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 8.0, 1, 1, NOW()),
(6, 2, 1, NULL, 1, 'Figma Design System Components', 'Build typography, buttons, inputs, and dark theme tokens in Figma', 'urgent', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 12 DAY), 16.0, 2, 1, NOW()),
(7, 2, 2, NULL, 2, 'Implement JWT Authentication & RBAC', 'Build permission engine and auth token middleware in PHP', 'urgent', NOW(), DATE_ADD(NOW(), INTERVAL 4 DAY), 12.0, 1, 1, NOW()),
(8, 2, 2, NULL, 1, 'Build Task Batch API Endpoints', 'Provide bulk updates for statuses, dates, and assignees', 'high', DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY), 10.0, 2, 1, NOW()),
(9, 2, 3, NULL, 3, 'Containerize Services with Docker Compose', 'Configure Nginx, PHP-FPM, MySQL, and FastAPI microservices', 'medium', DATE_SUB(NOW(), INTERVAL 3 DAY), NOW(), 6.0, 1, 1, NOW());

-- 9. Task Assignees
INSERT IGNORE INTO `task_assignees` (`task_id`, `user_id`) VALUES
(1, 2),
(2, 2),
(3, 2),
(4, 2),
(5, 1),
(5, 2),
(6, 2),
(7, 1),
(8, 1),
(9, 1);

-- 10. Task Dependencies
INSERT IGNORE INTO `task_dependencies` (`id`, `blocking_task_id`, `dependent_task_id`, `dependency_type`) VALUES
(1, 5, 6, 'finish_to_start'),
(2, 7, 8, 'finish_to_start');

-- 11. Custom Field Values
INSERT IGNORE INTO `custom_field_values` (`task_id`, `custom_field_id`, `value_text`, `value_number`, `value_date`, `value_json`) VALUES
(5, 1, 'Design', NULL, NULL, NULL),
(6, 1, 'Design', NULL, NULL, NULL),
(6, 2, '1', NULL, NULL, NULL),
(7, 1, 'Engineering', NULL, NULL, NULL),
(7, 3, NULL, 8.5, NULL, NULL),
(8, 1, 'Engineering', NULL, NULL, NULL),
(8, 3, NULL, 5.0, NULL, NULL);

-- 12. Documents & Task Attachments
INSERT IGNORE INTO `documents` (`id`, `workspace_id`, `project_id`, `task_id`, `title`, `content`, `doc_type`, `file_path`, `file_name`, `original_name`, `file_size`, `mime_type`, `file_extension`, `created_by`, `created_at`) VALUES
(1, 2, 1, 5, 'Mobile App Architecture Spec', '{"blocks":[{"type":"heading","level":1,"text":"Mobile App Architecture"},{"type":"paragraph","text":"Defines component structure, state management, and offline cache protocols."}]}', 'rich_text', NULL, NULL, NULL, NULL, NULL, NULL, 1, NOW()),
(2, 2, 2, 7, 'API Security & Rate Limiting Guidelines', '{"blocks":[{"type":"heading","level":1,"text":"API Security Guidelines"},{"type":"paragraph","text":"Mandatory token inspection, CSRF validation, and strict prepared SQL statements."}]}', 'rich_text', NULL, NULL, NULL, NULL, NULL, NULL, 1, NOW()),
(3, 2, 1, 5, 'Design System UI Wireframes.png', NULL, 'file', '/uploads/tasks/5/seed_design_wireframes.png', 'seed_design_wireframes.png', 'Design System UI Wireframes.png', 2458120, 'image/png', 'png', 1, NOW()),
(4, 2, 1, 6, 'Sprint Backlog & User Flow.pdf', NULL, 'file', '/uploads/tasks/6/seed_sprint_backlog.pdf', 'seed_sprint_backlog.pdf', 'Sprint Backlog & User Flow.pdf', 1048576, 'application/pdf', 'pdf', 1, NOW()),
(5, 2, 2, 7, 'Database Migration Architecture.pptx', NULL, 'file', '/uploads/tasks/7/seed_db_migration.pptx', 'seed_db_migration.pptx', 'Database Migration Architecture.pptx', 4194304, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx', 1, NOW());

-- 13. Activity Logs
INSERT IGNORE INTO `activity_logs` (`id`, `workspace_id`, `task_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 3, 2, 'task_completed', '{"title":"Install workspace dependencies"}', NOW()),
(2, 2, 7, 1, 'status_changed', '{"from":"To Do","to":"In Progress"}', NOW());

SET FOREIGN_KEY_CHECKS = 1;
