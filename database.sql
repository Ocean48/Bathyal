-- TEAMS AND USERS
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member', 'data_analyst') DEFAULT 'member',
    dashboard_preferences TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE team_members (
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PROJECTS AND SECTIONS
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status ENUM('active', 'planning', 'completed', 'archived') DEFAULT 'active',
    cycle VARCHAR(50), -- e.g., "Q3 2026", "Sprint 4"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE project_members (
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('manager', 'member', 'viewer') DEFAULT 'member',
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE project_teams (
    project_id INT NOT NULL,
    team_id INT NOT NULL,
    PRIMARY KEY (project_id, team_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

CREATE TABLE project_default_notify (
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    position INT DEFAULT 0, -- For drag & drop ordering
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- TASKS (Hierarchy and Core Data)
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_task_id INT NULL, -- For Subtasks
    title VARCHAR(255) NOT NULL,
    description TEXT, -- Supports URL hyperlinks parsing
    status ENUM('todo', 'in_progress', 'paused', 'completed') DEFAULT 'todo',
    start_date DATETIME NULL,
    expected_start_date DATETIME NULL,
    expected_due_date DATETIME NULL,
    completed_date DATETIME NULL,
    estimated_minutes INT DEFAULT 0,
    position INT DEFAULT 0, -- For ordering native subtasks
    storage_location ENUM('active', 'backlog', 'archive', 'unattached') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task_order (parent_task_id, position) -- indexing order to ensure sub-subtask sorting is maintained
);

-- MULTIPLE ASSIGNEES PER TASK
CREATE TABLE task_assignees (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- MULTIPLE COLLABORATORS PER TASK
CREATE TABLE task_collaborators (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TASK TO PROJECT MAPPING (Supports attaching a single task to many projects)
CREATE TABLE task_projects (
    task_id INT NOT NULL,
    project_id INT NOT NULL,
    section_id INT NULL,
    position INT DEFAULT 0, -- For drag & drop ordering within a section
    PRIMARY KEY (task_id, project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
);

-- TIME TRACKING (Start, Pause, End)
CREATE TABLE task_time_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    status ENUM('running', 'paused', 'completed') DEFAULT 'running',
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- LABELS & CATEGORIES
CREATE TABLE labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#000000'
);

CREATE TABLE task_labels (
    task_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (task_id, label_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
);

-- COMMENTS (With Hyperlink/Mention support handled in frontend/PHP)
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- AUTOMATIONS & TRIGGERS
CREATE TABLE task_triggers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_task_id INT NOT NULL,     -- The task that triggers the event (e.g., when this finishes)
    target_task_id INT NULL,         -- The task to start/create (if applicable)
    trigger_event ENUM('on_complete', 'on_status_change', 'on_expected_due_date') NOT NULL,
    action ENUM('start_next_task', 'notify_assignee', 'notify_admin', 'create_task') NOT NULL,
    FOREIGN KEY (source_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (target_task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- NOTIFICATIONS (For automations, assignments, and trigger alerts)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NULL,
    category ENUM('general', 'task_update', 'system', 'mention') DEFAULT 'general',
    message VARCHAR(255) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- INTEGRATIONS (Slack/Email/AI)
CREATE TABLE project_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    integration_type ENUM('slack', 'email', 'ai_assistant') NOT NULL,
    webhook_url VARCHAR(255) NULL,
    config_json TEXT NULL, -- Store specific AI prompts or email preferences
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- LINKED SUBTASKS (Shared across projects)
CREATE TABLE task_links (
    parent_id INT NOT NULL,
    subtask_id INT NOT NULL,
    position INT DEFAULT 0, -- For ordering linked subtasks
    PRIMARY KEY (parent_id, subtask_id),
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (subtask_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- TASK ATTACHMENTS
CREATE TABLE task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- RECENT PROJECTS HISTORY
CREATE TABLE user_recent_projects (
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, project_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
-- ==========================================
-- FAKE TEST DATA
-- ==========================================

-- Insert a Team
INSERT INTO teams (name, created_by) VALUES ('PharmAchieve Engineering', 1);

INSERT INTO `team_members` (`team_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 'owner', '2026-04-19 21:55:10'),
(1, 2, 'member', '2026-04-19 21:55:10'),
(1, 3, 'member', '2026-04-19 21:55:10');


-- Insert Users (Passwords are set to 'user123')
INSERT INTO users (name, email, password_hash, role) VALUES 
('Si', 'si@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'admin'),
('John Doe', 'john1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Jane Data', 'jane1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'data_analyst'),
('Mark Smith', 'mark1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Emily Davis', 'emily1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Michael Brown', 'michael1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Sarah Wilson', 'sarah1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('David Lee', 'david1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Laura Martinez', 'laura1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('James Anderson', 'james1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
('Olivia Thomas', 'olivia1@pharmachieve.org', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member');


-- Insert Projects
INSERT INTO projects (name, description, status, cycle) VALUES 
('Project 1', 'Overhaul the main site with the new Ocean theme', 'active', 'Sprint 4'),
('Project 2', 'Campaign planning and execution for Q3', 'planning', 'Q3 2026'),
('Project 3', 'Build the MVP for the new app tracking cleanups', 'active', 'Sprint 5');

-- Insert Sections
INSERT INTO sections (project_id, name, position) VALUES 
-- Project 1 Sections
(1, 'P1 - Sec 1', 1), (1, 'P1 - Sec 2', 2), (1, 'P1 - Sec 3', 3), (1, 'P1 - Sec 4', 4), (1, 'P1 - Sec 5', 5),
-- Project 2 Sections
(2, 'P2 - Sec 1', 1), (2, 'P2 - Sec 2', 2), (2, 'P2 - Sec 3', 3), (2, 'P2 - Sec 4', 4), (2, 'P2 - Sec 5', 5),
-- Project 3 Sections
(3, 'P3 - Sec 1', 1), (3, 'P3 - Sec 2', 2), (3, 'P3 - Sec 3', 3), (3, 'P3 - Sec 4', 4), (3, 'P3 - Sec 5', 5);

-- Insert Project Members
INSERT INTO project_members (project_id, user_id, role) VALUES 
(1, 1, 'manager'), (1, 2, 'member'), (1, 3, 'viewer'),
(2, 1, 'manager'), (2, 3, 'member'),
(3, 1, 'manager'), (3, 2, 'manager'), (3, 3, 'member');

-- Insert Tasks
INSERT INTO tasks (id, parent_task_id, title, description, status, position, storage_location) VALUES
-- P1S1 Tasks
(1, NULL, 'Task P1-S1-T1', 'Desc', 'todo', 1, 'active'),
(2, NULL, 'Task P1-S1-T2', 'Desc', 'todo', 2, 'active'),
(3, NULL, 'Task P1-S1-T3', 'Desc', 'todo', 3, 'active'),
-- P1S2 Tasks
(4, NULL, 'Task P1-S2-T1', 'Desc', 'todo', 1, 'active'),
(5, NULL, 'Task P1-S2-T2', 'Desc', 'todo', 2, 'active'),
(6, NULL, 'Task P1-S2-T3', 'Desc', 'todo', 3, 'active'),
-- P1S3 Tasks
(7, NULL, 'Task P1-S3-T1', 'Desc', 'todo', 1, 'active'),
(8, NULL, 'Task P1-S3-T2', 'Desc', 'todo', 2, 'active'),
(9, NULL, 'Task P1-S3-T3', 'Desc', 'todo', 3, 'active'),
-- P1S4 Tasks
(10, NULL, 'Task P1-S4-T1', 'Desc', 'todo', 1, 'active'),
(11, NULL, 'Task P1-S4-T2', 'Desc', 'todo', 2, 'active'),
(12, NULL, 'Task P1-S4-T3', 'Desc', 'todo', 3, 'active'),
-- P1S5 Tasks
(13, NULL, 'Task P1-S5-T1', 'Desc', 'todo', 1, 'active'),
(14, NULL, 'Task P1-S5-T2', 'Desc', 'todo', 2, 'active'),
(15, NULL, 'Task P1-S5-T3', 'Desc', 'todo', 3, 'active'),

-- P2S1 Tasks
(16, NULL, 'Task P2-S1-T1', 'Desc', 'todo', 1, 'active'),
(17, NULL, 'Task P2-S1-T2', 'Desc', 'todo', 2, 'active'),
(18, NULL, 'Task P2-S1-T3', 'Desc', 'todo', 3, 'active'),
-- P2S2 Tasks
(19, NULL, 'Task P2-S2-T1', 'Desc', 'todo', 1, 'active'),
(20, NULL, 'Task P2-S2-T2', 'Desc', 'todo', 2, 'active'),
(21, NULL, 'Task P2-S2-T3', 'Desc', 'todo', 3, 'active'),
-- P2S3 Tasks
(22, NULL, 'Task P2-S3-T1', 'Desc', 'todo', 1, 'active'),
(23, NULL, 'Task P2-S3-T2', 'Desc', 'todo', 2, 'active'),
(24, NULL, 'Task P2-S3-T3', 'Desc', 'todo', 3, 'active'),
-- P2S4 Tasks
(25, NULL, 'Task P2-S4-T1', 'Desc', 'todo', 1, 'active'),
(26, NULL, 'Task P2-S4-T2', 'Desc', 'todo', 2, 'active'),
(27, NULL, 'Task P2-S4-T3', 'Desc', 'todo', 3, 'active'),
-- P2S5 Tasks
(28, NULL, 'Task P2-S5-T1', 'Desc', 'todo', 1, 'active'),
(29, NULL, 'Task P2-S5-T2', 'Desc', 'todo', 2, 'active'),
(30, NULL, 'Task P2-S5-T3', 'Desc', 'todo', 3, 'active'),

-- P3S1 Tasks
(31, NULL, 'Task P3-S1-T1', 'Desc', 'todo', 1, 'active'),
(32, NULL, 'Task P3-S1-T2', 'Desc', 'todo', 2, 'active'),
(33, NULL, 'Task P3-S1-T3', 'Desc', 'todo', 3, 'active'),
-- P3S2 Tasks
(34, NULL, 'Task P3-S2-T1', 'Desc', 'todo', 1, 'active'),
(35, NULL, 'Task P3-S2-T2', 'Desc', 'todo', 2, 'active'),
(36, NULL, 'Task P3-S2-T3', 'Desc', 'todo', 3, 'active'),
-- P3S3 Tasks
(37, NULL, 'Task P3-S3-T1', 'Desc', 'todo', 1, 'active'),
(38, NULL, 'Task P3-S3-T2', 'Desc', 'todo', 2, 'active'),
(39, NULL, 'Task P3-S3-T3', 'Desc', 'todo', 3, 'active'),
-- P3S4 Tasks
(40, NULL, 'Task P3-S4-T1', 'Desc', 'todo', 1, 'active'),
(41, NULL, 'Task P3-S4-T2', 'Desc', 'todo', 2, 'active'),
(42, NULL, 'Task P3-S4-T3', 'Desc', 'todo', 3, 'active'),
-- P3S5 Tasks
(43, NULL, 'Task P3-S5-T1', 'Desc', 'todo', 1, 'active'),
(44, NULL, 'Task P3-S5-T2', 'Desc', 'todo', 2, 'active'),
(45, NULL, 'Task P3-S5-T3', 'Desc', 'todo', 3, 'active');

-- Map Tasks to Projects/Sections
INSERT INTO task_projects (task_id, project_id, section_id, position) VALUES 
(1, 1, 1, 1), (2, 1, 1, 2), (3, 1, 1, 3),
(4, 1, 2, 1), (5, 1, 2, 2), (6, 1, 2, 3),
(7, 1, 3, 1), (8, 1, 3, 2), (9, 1, 3, 3),
(10, 1, 4, 1), (11, 1, 4, 2), (12, 1, 4, 3),
(13, 1, 5, 1), (14, 1, 5, 2), (15, 1, 5, 3),

(16, 2, 6, 1), (17, 2, 6, 2), (18, 2, 6, 3),
(19, 2, 7, 1), (20, 2, 7, 2), (21, 2, 7, 3),
(22, 2, 8, 1), (23, 2, 8, 2), (24, 2, 8, 3),
(25, 2, 9, 1), (26, 2, 9, 2), (27, 2, 9, 3),
(28, 2, 10, 1), (29, 2, 10, 2), (30, 2, 10, 3),

(31, 3, 11, 1), (32, 3, 11, 2), (33, 3, 11, 3),
(34, 3, 12, 1), (35, 3, 12, 2), (36, 3, 12, 3),
(37, 3, 13, 1), (38, 3, 13, 2), (39, 3, 13, 3),
(40, 3, 14, 1), (41, 3, 14, 2), (42, 3, 14, 3),
(43, 3, 15, 1), (44, 3, 15, 2), (45, 3, 15, 3);

