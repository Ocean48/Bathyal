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
    team_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member', 'data_analyst') DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
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
    due_date DATETIME NULL,
    completed_date DATETIME NULL,
    estimated_minutes INT DEFAULT 0,
    position INT DEFAULT 0, -- For ordering native subtasks
    storage_location ENUM('active', 'backlog', 'archive', 'unattached') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- MULTIPLE ASSIGNEES PER TASK
CREATE TABLE task_assignees (
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
    trigger_event ENUM('on_complete', 'on_status_change', 'on_due_date') NOT NULL,
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
INSERT INTO teams (name, created_by) VALUES ('Ocean48 Engineering', 1);

INSERT INTO `team_members` (`team_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 'owner', '2026-04-19 21:55:10');

-- Insert Users (Passwords are set to 'user123')
INSERT INTO users (team_id, name, email, password_hash, role) VALUES 
(1, 'Admin User', 'admin@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'admin'),
(1, 'John Doe', 'john@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
(1, 'Jane Data', 'jane@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'data_analyst');

-- Insert Projects
INSERT INTO projects (name, description, status, cycle) VALUES 
('Website Redesign', 'Overhaul the main site with the new Ocean theme', 'active', 'Sprint 4'),
('Marketing Q3', 'Campaign planning and execution for Q3', 'planning', 'Q3 2026'),
('Ocean Conservation App', 'Build the MVP for the new app tracking cleanups', 'active', 'Sprint 5');

-- Insert Sections
INSERT INTO sections (project_id, name, position) VALUES 
-- Website Redesign Sections
(1, 'To Do', 1),
(1, 'In Progress', 2),
(1, 'Done', 3),
(1, 'QA Review', 4),
(1, 'Backlog', 5),
-- Marketing Sections
(2, 'Ideation', 1),
(2, 'Drafting', 2),
(2, 'Published', 3),
(2, 'Review', 4),
(2, 'Archived', 5),
-- Ocean Conservation App Sections
(3, 'Backlog', 1),
(3, 'Sprints', 2);

-- Insert Project Members
INSERT INTO project_members (project_id, user_id, role) VALUES 
(1, 1, 'manager'),
(1, 2, 'member'),
(1, 3, 'viewer'),
(2, 1, 'manager'),
(2, 3, 'member'),
(3, 1, 'manager'),
(3, 2, 'manager'),
(3, 3, 'member');

-- Insert Tasks
-- First batch of Top-Level Tasks
INSERT INTO tasks (id, parent_task_id, title, description, status, due_date, estimated_minutes, position, storage_location, created_at, updated_at) VALUES
(1, NULL, 'Draft AI trigger automations', 'Create the project triggers for the AI and Slack.', 'todo', '2026-04-20 12:00:00', 120, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(2, NULL, 'Review Kanban board drag-and-drop', 'Review the UI implementation for the boards.', 'in_progress', '2026-04-21 16:30:00', 90, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(3, NULL, 'Prepare Q3 Budget', 'Draft the expected budget for new marketing campaigns.', 'todo', '2026-05-01 10:00:00', 240, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(4, NULL, 'Update Typography Globals', 'Update the global css file and Tailwind config for the new font stack.', 'todo', '2026-04-22 17:00:00', 60, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(5, NULL, 'Fix Sidebar Navigation', 'Mobile menu is broken on iOS Safari when scrolled.', 'todo', '2026-04-25 12:00:00', 120, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(6, NULL, 'Social Media Assets', 'Design banners for Twitter, Facebook, and LinkedIn.', 'in_progress', '2026-04-30 09:00:00', 200, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(7, NULL, 'App Store Descriptions', 'Write localization text for the Apple App Store and Google Play.', 'todo', '2026-05-15 15:00:00', 90, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(8, 1, 'Define webhooks', 'Create JSON schema for Slack webhook payloads.', 'completed', '2026-04-18 10:00:00', 30, 3, 'active', '2026-04-17 23:49:20', '2026-04-17 23:54:44'),
(9, 1, 'Setup OpenAI integration logic', 'Connect via cURL the prompt handlers.', 'todo', '2026-04-19 14:00:00', 90, 4, 'active', '2026-04-17 23:49:20', '2026-04-17 23:54:46'),
(10, 4, 'Download Google Fonts', 'Download Poppins and Inter locally to serve them properly.', 'completed', '2026-04-20 17:00:00', 15, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(11, 4, 'Update tailwind.config.js', 'Add font families to tailwind overrides.', 'todo', '2026-04-21 17:00:00', 30, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(12, 5, 'Check z-index issues', 'Overlay is going behind the canvas on iOS Safari specifically.', 'todo', '2026-04-25 10:00:00', 30, 1, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:54'),
(13, 2, 'Fix drag ghost image transparency', 'Currently the SortableJS ghost image looks bad when dragging.', 'todo', '2026-04-21 15:00:00', 45, 0, 'active', '2026-04-17 23:49:20', '2026-04-17 23:49:20'),
(14, 1, 'Sub1', NULL, 'todo', NULL, 0, 2, 'active', '2026-04-17 23:49:33', '2026-04-17 23:54:44'),
(15, 5, 'Sub2', NULL, 'todo', NULL, 0, 2, 'active', '2026-04-17 23:49:37', '2026-04-17 23:49:54');

INSERT INTO task_assignees (task_id, user_id) VALUES
(1, 2),
(2, 1),
(2, 3),
(3, 3),
(4, 1),
(5, 2),
(6, 3),
(7, 1),
(8, 2),
(9, 2),
(10, 1),
(11, 1),
(12, 2),
(13, 1);

-- Map Tasks to Projects/Sections
INSERT INTO task_projects (task_id, project_id, section_id, position) VALUES 
(1, 1, 1, 1), -- Draft AI attached to 'To Do' in Website Redesign (Section 1)
(2, 1, 2, 1), -- Kanban Review attached to 'In Progress' in Website Redesign (Section 2)
(3, 2, 6, 1), -- Q3 budget attached to 'Ideation' in Marketing Q3 (Section 6)
(4, 1, 5, 1), -- Update Typography attached to 'Backlog' in Website Redesign (Section 5)
(5, 1, 1, 2), -- Fix Sidebar attached to 'To Do' in Website Redesign (Section 1)
(6, 2, 7, 1), -- Social Media attached to 'Drafting' in Marketing Q3 (Section 7)
(7, 3, 11, 1); -- App Store attached to 'Backlog' in Ocean Conservation (Section 11)

INSERT INTO task_links (parent_id, subtask_id, position) VALUES
(1, 3, 5),
(1, 6, 1);

