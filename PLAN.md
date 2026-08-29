Bathyal (Project Management System)

To accommodate simple daily task tracking without cluttering the UI, while still supporting large-scale enterprise project management, the platform uses a **Unified Data Engine with Dynamic View Scoping**.

```
                    +---------------------------------------+
                    |            USER INTERFACE             |
                    +-------------------+-------------------+
                                        |
                +-----------------------+-----------------------+
                |                                               |
                v                                               v
    +-----------------------+                       +-----------------------+
    |  SIMPLE MODE (To-Do)  |                       | ENTERPRISE WORK OS    |
    | - Flat Task Lists     |                       | - Nested Workspaces   |
    | - Quick Add Input     |                       | - Custom Fields       |
    | - Kanban & Table Only |                       | - Gantt & Dependencies|
    | - Personal Scope      |                       | - Time Logs & Audits  |
    +-----------+-----------+                       +-----------+-----------+
                |                                               |
                +-----------------------+-----------------------+
                                        |
                                        v
                    +---------------------------------------+
                    |       UNIFIED MYSQL DATA CORE         |
                    | (Tasks, Dynamic Fields, Dependencies) |
                    +---------------------------------------+

```

### How the Dual-Mode System Works:
1. **Simple Mode (Micro-Tier):**
   * Uses a hidden default "Personal Workspace" and single "Default List."
   * Hides complex controls (Gantt, Custom Fields, Automation, Time Tracking) from the UI.
   * Acts as a zero-friction personal task manager (e.g., Todoist / Apple Reminders style).
2. **Enterprise Mode (Macro-Tier):**
   * Unlocks full workspace-folder-project hierarchies, multi-assignees, Gantt chart dependencies, custom field management, and granular permission controls.
3. **Seamless Upgrade Path:** A simple task list can be upgraded into a full Enterprise Project with one click by attaching a workspace context, status workflows, and custom field templates.

---

## 2. Rich Component Structure (Hybrid Architecture)

### Layer A: Nginx Routing & Reverse Proxy Layer
* **Static File Dispatcher:** Directly serves JavaScript modules, CSS stylesheets, and images.
* **PHP-FPM Router:** Routes all main application requests (both HTML page requests and AJAX endpoints) to PHP.
* **Python AI Microservice Gateway:** Proxies requests directed to `/api/v1/ai/*` directly to the FastAPI Python container.

### Layer B: PHP Core Application Architecture (Server-Side Rendering + API)


```

/app
├── Core/
│   ├── Router.php               # Handles web routes (HTML) and API routes (JSON)
│   ├── Database.php             # PDO Wrapper with Prepared Statement Caching
│   ├── SessionManager.php       # Native PHP Sessions & Auth
│   └── View.php                 # Simple templating engine to render HTML views
├── Controllers/
│   ├── PageController.php       # Renders main HTML pages (Dashboard, Project, Task)
│   ├── WorkspaceController.php  # Hierarchy & RBAC management
│   ├── TaskController.php       # Task CRUD operations & Batch operations
│   ├── CustomFieldController.php# Dynamic field definitions & value updates
│   └── AIController.php         # Proxy to Python LLM microservice
├── Views/                       # HTML templates (e.g., dashboard.php, kanban.php)
│   ├── layout/                  # Headers, footers, sidebars
│   └── pages/                   # Specific page content
├── Services/
│   ├── AutoScheduler.php        # Recalculates Gantt dates on dependency shift
│   └── PermEngine.php           # Checks user privileges across scopes

```

### Layer C: Rich JavaScript UI (Progressive Enhancement)

Instead of a strict SPA, the initial page load and layout are handled by PHP (the traditional way). Rich JavaScript components then take over specific UI regions to provide a seamless, app-like experience without sacrificing ease of use.

* **AJAX & DOM Management:** Vanilla JavaScript fetching data from PHP endpoints and updating the DOM dynamically where needed.
* **Interactive Components (`/public/js/components`):**
  * `TaskTable.js`: High-performance inline-editable HTML table grid.
  * `KanbanBoard.js`: Drag-and-drop board powered by HTML5 Drag & Drop API.
  * `GanttChart.js`: Custom SVG/Canvas timeline view with interactive dependency lines.
  * `QuickAddBar.js`: Minimalist modal/bar for fast simple-task creation.
  * `DocEditor.js`: Slash-command (`/`) block editor built on contenteditable API.

---

## 3. Updated Relational Database Schema (MySQL)

This schema handles simple micro-tasks, multi-tenant enterprise projects, rich document management, automations, and dynamic custom field attributes.

```sql
-- --------------------------------------------------------
-- 1. WORKSPACE & HIERARCHY LAYER
-- --------------------------------------------------------

CREATE TABLE workspaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    is_personal BOOLEAN DEFAULT FALSE, -- Supports personal simplified mode
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    default_mode ENUM('simple', 'enterprise') DEFAULT 'simple',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workspace_members (
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member', 'guest') DEFAULT 'member',
    PRIMARY KEY (workspace_id, user_id),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    position INT DEFAULT 0,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NULL, -- NULL indicates top-level workspace project
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) DEFAULT 'list',
    color_hex VARCHAR(7) DEFAULT '#4A90E2',
    is_template BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. WORKFLOW & STATUS ENGINE
-- --------------------------------------------------------

CREATE TABLE statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NULL, -- NULL means global default system status
    name VARCHAR(100) NOT NULL,
    color_hex VARCHAR(7) DEFAULT '#808080',
    position INT DEFAULT 0,
    type ENUM('todo', 'in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. CORE TASK ENGINE (SIMPLE TO ENTERPRISE)
-- --------------------------------------------------------

CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL, -- NULL for inbox/simple personal tasks
    parent_id BIGINT UNSIGNED NULL,  -- Infinite nested subtasks
    status_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NULL,
    priority ENUM('none', 'low', 'medium', 'high', 'urgent') DEFAULT 'none',
    start_date DATETIME NULL,
    due_date DATETIME NULL,
    estimated_hours DECIMAL(6,2) NULL,
    position INT DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_dependencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blocking_task_id BIGINT UNSIGNED NOT NULL,
    dependent_task_id BIGINT UNSIGNED NOT NULL,
    dependency_type ENUM('finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish') DEFAULT 'finish_to_start',
    FOREIGN KEY (blocking_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (dependent_task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. DYNAMIC CUSTOM FIELDS ENGINE (ENTERPRISE)
-- --------------------------------------------------------

CREATE TABLE custom_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL, -- If null, available across entire workspace
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'number', 'select', 'multi_select', 'date', 'checkbox', 'user', 'url', 'formula') NOT NULL,
    options JSON NULL, -- Choices for select/multi_select
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE custom_field_values (
    task_id BIGINT UNSIGNED NOT NULL,
    custom_field_id BIGINT UNSIGNED NOT NULL,
    value_text TEXT NULL,
    value_number DECIMAL(12,4) NULL,
    value_date DATETIME NULL,
    value_json JSON NULL,
    PRIMARY KEY (task_id, custom_field_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (custom_field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. DOCUMENTS, WORKSPACE NOTES & WHITEBOARDS
-- --------------------------------------------------------

CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NULL, -- HTML / JSON block content
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. TIME TRACKING & AUDIT LOGS
-- --------------------------------------------------------

CREATE TABLE time_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration_seconds INT NULL,
    billable BOOLEAN DEFAULT FALSE,
    notes TEXT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL, -- e.g., 'status_changed', 'assignee_added'
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
