# Bathyal Project Implementation Roadmap

This document outlines the comprehensive, modular implementation roadmap for the Bathyal project management system based on the architectural specifications defined in `PLAN.md`.

To ensure the platform scales effortlessly from a lightweight daily task tracker (Simple Mode) to a massive, extensible enterprise work operating system (Enterprise Mode), the entire architecture follows a **Modular Control and Plugin-Driven Design** across both the backend PHP engine and the frontend hybrid JavaScript component registry.

---

## Architectural Principles: Modular Control and Plugin Foundations

1. **Modular Feature Providers (Backend):**
   - Every major domain (Workspaces, Tasks, Custom Fields, Dependencies/Gantt, Documents, Time Tracking, AI Gateway, Automations) operates as an isolated module registering its own routes, permissions, event listeners, and hook handlers.
   - Workspace-level feature flags allow dynamically enabling or disabling modules per workspace or mode.

2. **Hook and Plugin Lifecycle Engine (`EventDispatcher.php` & `PluginManager.php`):**
   - Action hooks (`doAction('task.created', $task)`) for trigger-based side effects.
   - Filter hooks (`applyFilter('task.data', $data)`) for mutating and enriching data payloads.
   - Standardized module interface (`ModuleInterface`) allowing internal components and third-party extensions to hook into core request/response and database lifecycles.

3. **Frontend Component & Widget Registry (`public/js/core/`):**
   - **Component Registry (`ComponentRegistry.js`)**: Standardized lifecycle interface (`mount`, `render`, `update`, `unmount`) for all rich interactive widgets initialized on server-rendered pages (SimpleList, SimpleKanban, TaskTable, KanbanBoard, GanttChart, DocEditor, Calendar).
   - **Field Widget Registry (`FieldRegistry.js`)**: Pluggable rendering and inline editing controls for each custom field type.
   - **Slash Block Registry (`BlockRegistry.js`)**: Pluggable block types for the document editor.
   - **Command Registry (`CommandRegistry.js`)**: Extensible command items and shortcuts for the Command Palette.

---

## Phase 1: Foundation, Infrastructure, and Modular Core Engine

Goal: Establish bootstrap configuration, database seeding, container health, core PHP primitives, plugin/hook infrastructure, base controllers/middleware, and frontend core store/styling.

- [ ] **1.1 Application Bootstrap, Environment, and Error Handling**
  - [ ] Implement `app/Core/Config.php`: Centralized environment loader (`.env` and system env), application mode (`APP_ENV`), debug settings, database credentials, and microservice URLs.
  - [ ] Create `app/bootstrap.php`: PSR-4 autoloader initialization, global error/exception handler returning structured JSON (`status`, `error`, `code`, `trace` if debug mode enabled), and timezone/locale configuration.
  - [ ] Update `public/index.php` to bootstrap the application and cleanly dispatch requests through the router.

- [ ] **1.2 Database Infrastructure, Seeding, and Query Helpers**
  - [ ] Create seed script (`docker/mysql/init/02-seed.sql`) containing:
    - [ ] Default system accounts (`admin@bathyal.local` and `demo@bathyal.local` pre-hashed).
    - [ ] Simple Mode dataset: Default Personal Workspace and unorganized flat tasks.
    - [ ] Enterprise Mode dataset: Multi-tenant Workspace, 2 Folders ("Product", "Engineering"), 3 Projects, Custom Fields, Dependencies, and Time Logs.
  - [ ] Validate relational integrity, cascade constraints, composite primary keys, and foreign keys across all 11 tables.
  - [ ] Enhance `app/Core/Database.php`:
    - [ ] Connection retry logic with exponential backoff on startup.
    - [ ] Atomic transaction helper: `Database::transaction(callable $callback)`.
    - [ ] Convenience query helpers: `fetchOne()`, `fetchAll()`, `insertGetId()`, and `execute()`.

- [ ] **1.3 Framework-Free PHP Core Primitives & Base Architecture**
  - [ ] Implement `app/Core/Request.php`: Typed parameter access (`getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()`, `getJson()`), sanitization, headers parsing, and bearer token extraction.
  - [ ] Implement `app/Core/Response.php`: Standardized JSON envelopes (`json($data, $statusCode, $meta)`, `error($message, $code, $details)`), HTTP status constants, and security headers.
  - [ ] Implement `app/Core/View.php`: Simple templating engine to render PHP/HTML views with layout support and data extraction.
  - [ ] Implement `app/Controllers/BaseController.php`: Common controller functionality (payload validation, current user context extraction, permission verification, standard response dispatching).
  - [ ] Implement `app/Controllers/PageController.php`: Handles rendering main HTML pages (Dashboard, Project, Task).
  - [ ] Implement `app/Core/SessionManager.php`: Native session handling, timing-safe signature verification, token revocation, and Argon2id/Bcrypt password hashing.
  - [ ] Implement `app/Core/EventDispatcher.php`: In-app hook and event bus (`addListener`, `dispatch`, `addAction`, `applyFilters`) as specified in PLAN.md Layer B.
  - [ ] Implement `app/Core/PluginManager.php`: Modular feature registry, plugin lifecycle manager, and workspace feature-module activation toggles.
  - [ ] Setup `app/Views/` structure: Create `layout/header.php`, `layout/footer.php`, `layout/sidebar.php`, and initial `pages/dashboard.php`.

- [ ] **1.4 Router and Middleware Pipeline Engine**
  - [ ] Enhance `app/Core/Router.php`:
    - [ ] Support web routes (returning HTML views) and API routes (returning JSON).
    - [ ] Support middleware stacks for individual routes and route groups (`$router->group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class]], ...)`).
    - [ ] Regex parameter matching with typed parameter extraction (`{id:\d+}`).
  - [ ] Implement `app/Middleware/MiddlewareInterface.php`.
  - [ ] Implement `app/Middleware/CorsMiddleware.php`: Handle preflight `OPTIONS` requests and CORS headers.
  - [ ] Implement `app/Middleware/JsonBodyParserMiddleware.php`: Parse incoming JSON request bodies.
  - [ ] Implement `app/Middleware/AuthMiddleware.php`: Guard protected endpoints and inject authenticated user into Request context (handles both session cookies for web and API tokens if needed).

- [ ] **1.5 Frontend Foundations, Reactive Store, and Base Design System**
  - [ ] Implement `public/js/core/store.js`: Lightweight PubSub state store with subscription channels for `currentUser`, `activeMode` (`simple` vs `enterprise`), `activeWorkspace`, `activeProject`, and `tasks`.
  - [ ] Implement `public/js/core/eventBus.js`: Global decoupled event bus and UI hook system (`on`, `off`, `emit`, `filter`).
  - [ ] Implement `public/js/core/api.js`: Unified Fetch client with automatic authorization headers, base URL configuration, request deduplication, and error formatting.
  - [ ] Implement `public/js/core/storage.js`: LocalStorage / IndexedDB persistence layer for active mode, user tokens, and offline caching.
  - [ ] Refactor `public/css/style.css` with a complete Design System:
    - [ ] CSS custom property tokens (color palettes, spacing, typography, dark/light theme variables).
    - [ ] Base UI utility classes: Buttons (`.btn-primary`, `.btn-secondary`, `.btn-ghost`), Badges, Inputs, Modals, Dropdowns, Skeleton Loaders, and Empty States.
  - [ ] Implement `public/js/components/Toast.js`: Global non-intrusive toast notifications with severity types and undo action support.

- [ ] **1.6 Gateway Proxy, Health Check, and Diagnostics Verification**
  - [ ] Update `/api/v1/health` to verify end-to-end stack health: PHP-FPM, MySQL query execution, and Python AI gateway ping (`http://ai:8000/api/v1/ai/health`).
  - [ ] Verify Nginx static asset caching and reverse proxy routing (`/api/v1/*` -> PHP-FPM, `/api/v1/ai/*` -> FastAPI).

---

## Phase 2: Domain Services, Modular Business Logic, and Full RESTful API

Goal: Construct modular domain engines, permission checks, and RESTful API controllers with zero third-party framework overhead.

- [ ] **2.1 Core Domain Engines and Interfaces**
  - [ ] Implement `app/Services/PermEngine.php`:
    - [ ] Granular RBAC evaluation (`owner`, `admin`, `member`, `guest`).
    - [ ] Scope-aware checks across Workspace, Folder, Project, Task, and Document levels.
    - [ ] Public share token verification for guest/client readonly access.
  - [ ] Implement `app/Services/CustomFieldEngine.php`:
    - [ ] Pluggable `FieldTypeHandlerInterface` allowing new custom field types to register formatting, validation, and serialization logic.
    - [ ] Built-in handlers for: `text`, `number`, `select`, `multi_select`, `date`, `checkbox`, `user`, `url`, and calculated `formula`.
    - [ ] EAV data pivoting for single-query bulk task retrieval.
  - [ ] Implement `app/Services/AutoScheduler.php`:
    - [ ] Dependency graph construction with cycle detection (Tarjan/Kahn algorithm).
    - [ ] Critical path calculation and slack time identification.
    - [ ] Forward/backward date recalculations for all 4 dependency types (`finish_to_start`, `start_to_start`, `finish_to_finish`, `start_to_finish`).
  - [ ] Implement `app/Services/RecurrenceEngine.php`:
    - [ ] Recurring task pattern parser (daily, weekdays, weekly on specific days, monthly, yearly, custom interval).
    - [ ] Automatic next-occurrence generation hook upon task completion.

- [ ] **2.2 Modular API Controllers**
  - [ ] **Auth Module (`app/Controllers/AuthController.php`)**:
    - [ ] `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`.
    - [ ] `GET /api/v1/auth/me`, `PATCH /api/v1/auth/preferences` (mode preference: `simple` vs `enterprise`).
  - [ ] **Workspace & Hierarchy Module (`app/Controllers/WorkspaceController.php`, `FolderController.php`, `ProjectController.php`)**:
    - [ ] `GET /api/v1/workspaces`, `POST /api/v1/workspaces`.
    - [ ] `GET /api/v1/workspaces/{id}/tree`: Single-roundtrip hierarchical tree of folders, projects, and task counts.
    - [ ] `POST /api/v1/workspaces/{id}/members`, `POST /api/v1/workspaces/{id}/modules` (enable/disable feature modules).
    - [ ] `CRUD /api/v1/folders`, `CRUD /api/v1/projects`, `POST /api/v1/projects/{id}/duplicate`.
  - [ ] **Task & Workflow Module (`app/Controllers/TaskController.php`, `StatusController.php`, `DependencyController.php`)**:
    - [ ] `GET /api/v1/tasks`, `POST /api/v1/tasks` (with quick-add NLP extraction).
    - [ ] `GET /api/v1/tasks/{id}`, `PATCH /api/v1/tasks/{id}`, `DELETE /api/v1/tasks/{id}`.
    - [ ] `POST /api/v1/tasks/batch` (bulk status, date shifts, assignees, delete).
    - [ ] `POST /api/v1/tasks/reorder` (Kanban and list positioning).
    - [ ] `CRUD /api/v1/statuses`, `CRUD /api/v1/dependencies` (triggers AutoScheduler hooks).
  - [ ] **Custom Fields Module (`app/Controllers/CustomFieldController.php`)**:
    - [ ] `CRUD /api/v1/custom-fields`, `PUT /api/v1/tasks/{id}/custom-fields`.
  - [ ] **Documents Module (`app/Controllers/DocumentController.php`)**:
    - [ ] `CRUD /api/v1/documents` (block JSON content, workspace notes).
  - [ ] **Time Tracking & Audit Module (`app/Controllers/TimeLogController.php`, `ActivityLogController.php`)**:
    - [ ] `CRUD /api/v1/time-logs` (start, stop, manual entry, timesheets).
    - [ ] `GET /api/v1/activity-logs` (paginated audit trails).
  - [ ] **AI Gateway Proxy Module (`app/Controllers/AIController.php`)**:
    - [ ] Proxy `/api/v1/ai/decompose-task`, `/api/v1/ai/estimate-task`, `/api/v1/ai/optimize-schedule`, `/api/v1/ai/parse-prompt` to FastAPI with error fallbacks.

---

## Phase 3: Frontend Hybrid Architecture, Performance, and QoL Engine

Goal: Build a high-performance Hybrid architecture powered by a plugin/registry system for rich client-side components initialized on server-rendered pages.

- [ ] **3.1 Frontend Core Plugin and Component Engine**
  - [ ] Implement `public/js/core/store.js`: Lightweight PubSub state store with subscription channels for tasks, active filters, workspace tree, and user preferences (synced with server state where appropriate).
  - [ ] Implement `public/js/core/eventBus.js`: Global decoupled event bus and UI hook system (`on`, `off`, `emit`, `filter`).
  - [ ] Implement `public/js/core/componentRegistry.js`: Extensible registry managing active widgets (Table, Kanban, Gantt, SimpleList, SimpleKanban, DocEditor, Calendar) bound to DOM elements.
  - [ ] Implement `public/js/core/fieldRegistry.js`: Extensible custom field widget registry for rendering and inline editing across views.
  - [ ] Implement `public/js/core/api.js`: Unified Fetch client for AJAX requests, with CSRF token handling, request deduplication, and retry logic.
  - [ ] Implement `public/js/core/storage.js`: IndexedDB / LocalStorage caching layer.
  - [ ] Implement `public/js/core/optimistic.js`: Optimistic UI mutation manager with automatic rollback on API failure for widget interactions.
  - [ ] Implement `public/js/core/virtualizer.js`: DOM windowing/virtual scroll engine for fluid 60fps rendering of 10,000+ tasks in table/kanban widgets.

- [ ] **3.2 Global Ergonomics and Quality of Life (QoL)**
  - [ ] Implement `public/js/components/CommandPalette.js` & `commandRegistry.js`:
    - [ ] Universal launcher triggered via `Cmd/Ctrl + K` or `/`.
    - [ ] Pluggable command providers registering actions dynamically.
    - [ ] Fuzzy search across tasks, projects, documents, workspaces, and navigation routes.
    - [ ] Quick action executions: "Create new task", "Switch to Enterprise mode", "Go to Today", "Toggle Dark Mode".
  - [ ] Implement `public/js/components/KeyboardShortcuts.js`:
    - [ ] Navigation keys: `j` / `k` (move up/down), `Enter` (open detail), `Escape` (close/blur).
    - [ ] Action keys: `c` (new task), `e` (edit inline), `x` or `Space` (toggle complete), `d` (set due date), `p` (set priority), `m` (assign), `Backspace`/`Delete` (delete task).
    - [ ] Visual shortcut cheat-sheet modal (`?` trigger).
  - [ ] Implement `public/js/components/ToastManager.js`:
    - [ ] Non-intrusive feedback toasts with severity levels (info, success, warning, error).
    - [ ] Interactive "Undo" button on destructive actions (task deletion, bulk edits, status changes) using `Ctrl + Z`.
  - [ ] Implement `public/js/components/ThemeManager.js`:
    - [ ] Instant theme switching (Dark, Light, System Sync).
    - [ ] Accent color palette selector with CSS custom property variables.

---

## Phase 4: Simple Mode (Micro-Tier Focus Experience)

Goal: Deliver a clean, zero-friction task management experience tailored for daily personal focus, inbox zero workflows, and rapid capture.

- [ ] **4.1 Simple Mode Layout and Views**
  - [ ] Minimalist sidebar with focus views:
    - [ ] "Inbox": Unsorted, newly captured thoughts and micro-tasks.
    - [ ] "Today": Tasks scheduled for current day with progress completion ring.
    - [ ] "Upcoming": Next 7 days view grouped by date.
    - [ ] "Completed": History of completed items with completion timestamps.
  - [ ] Distraction-free clean header hiding enterprise multi-project dropdowns and complex toolbar controls.

- [ ] **4.2 Rapid Capture QuickAddBar**
  - [ ] Implement `public/js/components/QuickAddBar.js`:
    - [ ] Single-line input with auto-expanding capability.
    - [ ] In-line NLP parsing: `@today`, `@tomorrow`, `@friday 3pm`, `!urgent`, `!low`, `#work`, `#personal`.
    - [ ] One-click AI auto-breakdown button for generating subtasks on enter.
    - [ ] Sticky bottom/top bar accessible anywhere in Simple Mode.

- [ ] **4.3 Simple Task List and Simple Kanban**
  - [ ] Implement `public/js/components/SimpleTaskList.js` (registered in `componentRegistry.js`):
    - [ ] One-click checkbox completion with micro-animation and celebratory completion sound toggle.
    - [ ] Direct inline text editing on click without modal popups.
    - [ ] Lightweight drag handle for custom prioritization and ordering.
    - [ ] Inline subtask checklist expansion.
  - [ ] Implement `public/js/components/SimpleKanban.js` (registered in `componentRegistry.js`):
    - [ ] Streamlined 3-column personal board (To Do, In Progress, Done).
    - [ ] Fluid HTML5 drag-and-drop card movement.

- [ ] **4.4 Dynamic Upgrade Path to Enterprise**
  - [ ] Implement `public/js/components/UpgradePrompt.js`:
    - [ ] Contextual prompt: "Convert this list into an Enterprise Project".
    - [ ] Seamless one-click migration: binds personal tasks to a new workspace/folder hierarchy, enables Gantt and custom fields without data loss.

---

## Phase 5: Enterprise Mode (Macro-Tier Work OS)

Goal: Build an enterprise-grade work management platform supporting nested hierarchies, pluggable multi-views, custom fields, Gantt timelines, block documentation, and team collaboration.

- [ ] **5.1 Enterprise Workspace Hierarchy and Navigation**
  - [ ] Implement `public/js/components/SidebarHierarchy.js`:
    - [ ] Multi-tenant workspace switcher with avatar icons and role tags.
    - [ ] Nested Tree View: Workspaces -> Folders -> Projects -> Custom Views.
    - [ ] Context menu on tree nodes: Rename, Duplicate, Change Color, Create Sub-folder, Delete.
    - [ ] Drag-and-drop reorganization of folders and projects.
  - [ ] Implement `public/js/components/WorkspaceModal.js`:
    - [ ] Member management table with role selection (`owner`, `admin`, `member`, `guest`).
    - [ ] Feature module toggle panel (enable/disable Gantt, Docs, Time Tracking, Custom Fields per workspace).
    - [ ] Invitation link generation and guest sharing permissions.

- [ ] **5.2 Pluggable Data Views Engine**
  - [ ] Implement `public/js/components/ViewSwitcher.js`: Tab bar dynamically rendered from registered widgets in `componentRegistry.js` or via server navigation.
  - [ ] Implement `public/js/components/FilterBar.js`:
    - [ ] Multi-condition filtering (Status is/is not, Assignee contains, Due Date before/after/within, Priority, Custom Field values).
    - [ ] Multi-level sorting (by Priority, Due Date, Title, Created Date, Custom Field).
    - [ ] Group By engine (group tasks by Status, Assignee, Priority, or Project).
    - [ ] Saved View presets (e.g., "My High Priority Tasks", "Overdue Milestones").

- [ ] **5.3 Enterprise Table / Spreadsheet Grid Plugin**
  - [ ] Implement `public/js/components/TaskTable.js` (registered in `componentRegistry.js`):
    - [ ] Virtualized table supporting 5,000+ rows with pinned column headers.
    - [ ] Inline cell editors consuming `fieldRegistry.js` widget plugins.
    - [ ] Column resizing, reordering, and visibility toggling dropdown.
    - [ ] Multi-row selection with shift-click and box-selection.
    - [ ] Bottom summary bar showing task counts, sum/average of estimated hours, and custom field totals.

- [ ] **5.4 Enterprise Kanban Board Plugin**
  - [ ] Implement `public/js/components/KanbanBoard.js` (registered in `componentRegistry.js`):
    - [ ] Dynamic status columns with customizable WIP (Work In Progress) limits.
    - [ ] Horizontal Swimlanes (group by Assignee, Priority, or Folder).
    - [ ] Card customization settings (show/hide assignees, subtask progress bar, tags, custom fields, cover colors).
    - [ ] Drag-and-drop cards between columns and swimlanes with drop placeholder indicator.

- [ ] **5.5 Interactive Gantt Chart and Timeline Plugin**
  - [ ] Implement `public/js/components/GanttChart.js` (registered in `componentRegistry.js`):
    - [ ] High-performance SVG + HTML canvas timeline rendering.
    - [ ] Drag-to-resize duration handles and drag-to-shift date bars.
    - [ ] Interactive dependency drawing: drag link line from task connector circle to another task.
    - [ ] Visual dependency curves with arrow markers for all 4 dependency types.
    - [ ] Critical Path highlighting toggle and milestone diamonds.
    - [ ] Zoom scale selector (Day, Week, Month, Quarter, Year).
    - [ ] One-click "AI Auto-Schedule" button resolving timeline conflicts.

- [ ] **5.6 Bulk Operations Toolbar**
  - [ ] Implement `public/js/components/BulkActionToolbar.js`:
    - [ ] Floating bottom action bar appearing when multiple tasks are selected.
    - [ ] Actions: Change Status, Reassign, Shift Due Dates (+1 day, +1 week), Set Priority, Move to Project, Batch Delete.

- [ ] **5.7 Custom Fields Engine UI**
  - [ ] Implement `public/js/components/CustomFieldManager.js` and `fieldRegistry.js`:
    - [ ] Modular widget system for 9 field types: Text, Number, Dropdown Select, Multi-Select, Date, Checkbox, User Assignee, URL, Formula.
    - [ ] Custom color badge assignment for Select options.
    - [ ] Dynamic field rendering inside Task Details, Table Grid, and Kanban Cards.

- [ ] **5.8 Task Detail Drawer and Modal**
  - [ ] Implement `public/js/components/TaskDetailDrawer.js`:
    - [ ] Slide-out drawer or full-screen modal view.
    - [ ] Markdown rich-text task description with auto-save.
    - [ ] Subtask hierarchy tree with add/remove/check operations.
    - [ ] Dependency manager tab showing "Blocked By" and "Blocking" task lists.
    - [ ] Time tracking stopwatch widget and manual log list.
    - [ ] Activity log timeline showing exact timestamps, changes, and authors.
    - [ ] Comments thread with rich markdown and @mention support.

- [ ] **5.9 Slash-Command Block Document Editor Plugin**
  - [ ] Implement `public/js/components/DocEditor.js` & `blockRegistry.js` (registered in `componentRegistry.js`):
    - [ ] Block-based contenteditable document workspace for project specs and wikis.
    - [ ] Pluggable slash menu (`/`) blocks: Heading 1/2/3, Bullet List, Numbered List, Checklist, Code Block, Callout Note, Quote, Table, Divider.
    - [ ] Embeddable live task card block directly linking to project tasks.

- [ ] **5.10 Import and Export Engine**
  - [ ] Implement `public/js/components/DataImportExport.js`:
    - [ ] CSV/JSON import wizard with column mapping to task fields and custom fields.
    - [ ] One-click export of project data to CSV, JSON, and printable summary report.

---

## Phase 6: Python AI Microservice Expansion and Autonomous Helpers

Goal: Deliver intelligent task breakdown, effort estimation, natural language parsing, and automated Gantt schedule optimization.

- [ ] **6.1 AI Microservice Algorithms (`ai_services/`)**
  - [ ] Implement critical path method (CPM) and PERT schedule leveling in Python.
  - [ ] Implement natural language processing task parser in `ai_services/main.py` extracting entities (dates, priority, tags, estimates).
  - [ ] Implement task breakdown subtask generator using contextual prompt templates.
  - [ ] Implement smart dependency recommendation engine comparing task descriptions and historical project flows.

- [ ] **6.2 Microservice Testing and Pytest Suite**
  - [ ] Add unit tests in `ai_services/tests/test_api.py` covering:
    - [ ] Health check and CORS validation.
    - [ ] Task effort estimation boundary tests.
    - [ ] Schedule optimization cycle detection and date shift validation.
    - [ ] NLP prompt parser edge cases.

---

## Phase 7: Testing, Security Hardening, and Production Polish

Goal: Validate complete system reliability, prevent security vulnerabilities, optimize rendering pipelines, and ensure smooth multi-container deployment.

- [ ] **7.1 Comprehensive Automated and Manual Testing**
  - [ ] Unit tests for PHP Core (Router, Database, SessionManager, PermEngine, AutoScheduler, CustomFieldEngine, PluginManager, EventDispatcher).
  - [ ] Integration tests for REST API endpoints verifying authentication, authorization, and error responses.
  - [ ] Frontend browser verification across Simple Mode and Enterprise Mode workflows.

- [ ] **7.2 Security Hardening**
  - [ ] Audit all SQL queries across PHP controllers ensuring 100% prepared statement parameter binding.
  - [ ] Implement CSRF token verification on state-modifying requests.
  - [ ] Enforce output HTML escaping (`escapeHtml`) to prevent Cross-Site Scripting (XSS).
  - [ ] Add API rate-limiting middleware to protect against brute-force attacks.

- [ ] **7.3 Performance Optimization**
  - [ ] Optimize database indexes and verify query execution plans (`EXPLAIN`).
  - [ ] Minify frontend JavaScript modules and CSS stylesheets.
  - [ ] Ensure sub-50ms frontend render latency for core view switches and sub-100ms API response times.

- [ ] **7.4 Documentation and Deployment**
  - [ ] Update `README.md` with complete architecture diagrams, environment setup guide, API reference, and Docker deployment commands.
  - [ ] Verify clean startup on Docker Compose (`docker-compose up -d --build`).
    - [ ] Time tracking stopwatch widget and manual log list.
    - [ ] Activity log timeline showing exact timestamps, changes, and authors.
    - [ ] Comments thread with rich markdown and @mention support.

- [ ] **5.9 Slash-Command Block Document Editor**
  - [ ] Implement `public/js/components/DocEditor.js`:
    - [ ] Block-based contenteditable document workspace for project specs and wikis.
    - [ ] Slash menu (`/`) triggers: Heading 1/2/3, Bullet List, Numbered List, Checklist, Code Block, Callout Note, Quote, Table, Divider.
    - [ ] Embeddable live task card block directly linking to project tasks.

- [ ] **5.10 Import and Export Engine**
  - [ ] Implement `public/js/components/DataImportExport.js`:
    - [ ] CSV/JSON import wizard with column mapping to task fields and custom fields.
    - [ ] One-click export of project data to CSV, JSON, and printable summary report.

---

## Phase 6: Python AI Microservice Expansion and Autonomous Helpers

Goal: Deliver intelligent task breakdown, effort estimation, natural language parsing, and automated Gantt schedule optimization.

- [ ] **6.1 AI Microservice Algorithms (`ai_services/`)**
  - [ ] Implement critical path method (CPM) and PERT schedule leveling in Python.
  - [ ] Implement natural language processing task parser in `ai_services/main.py` extracting entities (dates, priority, tags, estimates).
  - [ ] Implement task breakdown subtask generator using contextual prompt templates.
  - [ ] Implement smart dependency recommendation engine comparing task descriptions and historical project flows.

- [ ] **6.2 Microservice Testing and Pytest Suite**
  - [ ] Add unit tests in `ai_services/tests/test_api.py` covering:
    - [ ] Health check and CORS validation.
    - [ ] Task effort estimation boundary tests.
    - [ ] Schedule optimization cycle detection and date shift validation.
    - [ ] NLP prompt parser edge cases.

---

## Phase 7: Testing, Security Hardening, and Production Polish

Goal: Validate complete system reliability, prevent security vulnerabilities, optimize rendering pipelines, and ensure smooth multi-container deployment.

- [ ] **7.1 Comprehensive Automated and Manual Testing**
  - [ ] Unit tests for PHP Core (Router, Database, SessionManager, PermEngine, AutoScheduler, CustomFieldEngine).
  - [ ] Integration tests for REST API endpoints verifying authentication, authorization, and error responses.
  - [ ] Frontend browser verification across Simple Mode and Enterprise Mode workflows.

- [ ] **7.2 Security Hardening**
  - [ ] Audit all SQL queries across PHP controllers ensuring 100% prepared statement parameter binding.
  - [ ] Implement CSRF token verification on state-modifying requests.
  - [ ] Enforce output HTML escaping (`escapeHtml`) to prevent Cross-Site Scripting (XSS).
  - [ ] Add API rate-limiting middleware to protect against brute-force attacks.

- [ ] **7.3 Performance Optimization**
  - [ ] Optimize database indexes and verify query execution plans (`EXPLAIN`).
  - [ ] Minify frontend JavaScript modules and CSS stylesheets.
  - [ ] Ensure sub-50ms frontend render latency for core view switches and sub-100ms API response times.

- [ ] **7.4 Documentation and Deployment**
  - [ ] Update `README.md` with complete architecture diagrams, environment setup guide, API reference, and Docker deployment commands.
  - [ ] Verify clean startup on Docker Compose (`docker-compose up -d --build`).

