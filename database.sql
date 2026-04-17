-- TEAMS AND USERS
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

-- PROJECTS AND SECTIONS
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    admin_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status ENUM('active', 'planning', 'completed', 'archived') DEFAULT 'active',
    cycle VARCHAR(50), -- e.g., "Q3 2026", "Sprint 4"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
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
    assignee_id INT NULL,
    due_date DATETIME NULL,
    estimated_minutes INT DEFAULT 0,
    storage_location ENUM('active', 'backlog', 'archive', 'unattached') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
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

-- ==========================================
-- FAKE TEST DATA
-- ==========================================

-- Insert a Team
INSERT INTO teams (name) VALUES ('Ocean48 Engineering');

-- Insert Users (Passwords are set to 'user123')
INSERT INTO users (team_id, name, email, password_hash, role) VALUES 
(1, 'Admin User', 'admin@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'admin'),
(1, 'John Doe', 'john@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'member'),
(1, 'Jane Data', 'jane@ocean48.com', '$2y$10$Bzvnfr5Xu1xOOQpp.M0.fuANSp9G530af.GTooEo9zsRGuE4.az8G', 'data_analyst');

-- Insert Projects
INSERT INTO projects (team_id, admin_id, name, description, status, cycle) VALUES 
(1, 1, 'Website Redesign', 'Overhaul the main site with the new Ocean theme', 'active', 'Sprint 4'),
(1, 1, 'Marketing Q3', 'Campaign planning and execution for Q3', 'planning', 'Q3 2026'),
(1, 1, 'Ocean Conservation App', 'Build the MVP for the new app tracking cleanups', 'active', 'Sprint 5');

-- Insert Sections
INSERT INTO sections (project_id, name, position) VALUES 
-- Website Redesign Sections
(1, 'To Do', 1),
(1, 'In Progress', 2),
(1, 'Done', 3),
-- Marketing Sections
(2, 'Ideation', 1),
(2, 'Drafting', 2),
(2, 'Published', 3);

-- Insert Tasks
INSERT INTO tasks (title, description, status, assignee_id, due_date, estimated_minutes) VALUES 
('Draft AI trigger automations', 'Create the project triggers for the AI and Slack.', 'todo', 2, '2026-04-20 12:00:00', 120),
('Review Kanban board drag-and-drop', 'Review the UI implementation for the boards.', 'in_progress', 1, '2026-04-21 16:30:00', 90),
('Prepare Q3 Budget', 'Draft the expected budget for new marketing campaigns.', 'todo', 3, '2026-05-01 10:00:00', 240);

-- Map Tasks to Projects/Sections
INSERT INTO task_projects (task_id, project_id, section_id, position) VALUES 
(1, 1, 1, 1), -- Draft AI attached to 'To Do' in Website Redesign
(2, 1, 2, 1), -- Kanban Review attached to 'In Progress' in Website Redesign
(3, 2, 4, 1); -- Q3 budget attached to 'Ideation' in Marketing Q3
