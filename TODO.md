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

- [x] **1.1 Application Bootstrap, Environment, and Error Handling**
  - [x] Implement `app/Core/Config.php`: Centralized environment loader (`.env` and system env), application mode (`APP_ENV`), debug settings, database credentials, and microservice URLs.
  - [x] Create `app/bootstrap.php`: PSR-4 autoloader initialization, global error/exception handler returning structured JSON (`status`, `error`, `code`, `trace` if debug mode enabled), and timezone/locale configuration.
  - [x] Update `public/index.php` to bootstrap the application and cleanly dispatch requests through the router.

- [x] **1.2 Database Infrastructure, Seeding, and Query Helpers**
  - [x] Create seed script (`docker/mysql/init/02-seed.sql`) containing:
    - [x] Default system accounts (`admin@bathyal.local` and `demo@bathyal.local` pre-hashed).
    - [x] Simple Mode dataset: Default Personal Workspace and unorganized flat tasks.
    - [x] Enterprise Mode dataset: Multi-tenant Workspace, 2 Folders ("Product", "Engineering"), 3 Projects, Custom Fields, Dependencies, and Time Logs.
  - [x] Validate relational integrity, cascade constraints, composite primary keys, and foreign keys across all 11 tables.
  - [x] Enhance `app/Core/Database.php`:
    - [x] Connection retry logic with exponential backoff on startup.
    - [x] Atomic transaction helper: `Database::transaction(callable $callback)`.
    - [x] Convenience query helpers: `fetchOne()`, `fetchAll()`, `insertGetId()`, and `execute()`.

- [x] **1.3 Framework-Free PHP Core Primitives & Base Architecture**
  - [x] Implement `app/Core/Request.php`: Typed parameter access (`getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()`, `getJson()`), sanitization, headers parsing, and bearer token extraction.
  - [x] Implement `app/Core/Response.php`: Standardized JSON envelopes (`json($data, $statusCode, $meta)`, `error($message, $code, $details)`), HTTP status constants, and security headers.
  - [ ] Implement `app/Core/View.php`: Simple templating engine to render PHP/HTML views with layout support and data extraction.
  - [x] Implement `app/Controllers/BaseController.php`: Common controller functionality (payload validation, current user context extraction, permission verification, standard response dispatching).
  - [ ] Implement `app/Controllers/PageController.php`: Handles rendering main HTML pages (Dashboard, Project, Task).
  - [x] Implement `app/Core/SessionManager.php`: Native session handling, timing-safe signature verification, token revocation, and Argon2id/Bcrypt password hashing.
  - [ ] Implement `app/Core/EventDispatcher.php`: In-app hook and event bus (`addListener`, `dispatch`, `addAction`, `applyFilters`) as specified in PLAN.md Layer B.
  - [ ] Implement `app/Core/PluginManager.php`: Modular feature registry, plugin lifecycle manager, and workspace feature-module activation toggles.
  - [ ] Setup `app/Views/` structure: Create `layout/header.php`, `layout/footer.php`, `layout/sidebar.php`, and initial `pages/dashboard.php`.

- [x] **1.4 Router and Middleware Pipeline Engine**
  - [x] Enhance `app/Core/Router.php`:
    - [x] Support web routes (returning HTML views) and API routes (returning JSON).
    - [x] Support middleware stacks for individual routes and route groups (`$router->group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class]], ...)`).
    - [x] Regex parameter matching with typed parameter extraction (`{id:\d+}`).
  - [x] Implement `app/Middleware/MiddlewareInterface.php`.
  - [x] Implement `app/Middleware/CorsMiddleware.php`: Handle preflight `OPTIONS` requests and CORS headers.
  - [x] Implement `app/Middleware/JsonBodyParserMiddleware.php`: Parse incoming JSON request bodies.
  - [x] Implement `app/Middleware/AuthMiddleware.php`: Guard protected endpoints and inject authenticated user into Request context (handles both session cookies for web and API tokens if needed).

- [x] **1.5 Frontend Foundations, Reactive Store, and Base Design System**
  - [x] Implement `public/js/core/store.js`: Lightweight PubSub state store with subscription channels for `currentUser`, `activeMode` (`simple` vs `enterprise`), `activeWorkspace`, `activeProject`, and `tasks`.
  - [x] Implement `public/js/core/eventBus.js`: Global decoupled event bus and UI hook system (`on`, `off`, `emit`, `filter`).
  - [x] Implement `public/js/core/api.js`: Unified Fetch client with automatic authorization headers, base URL configuration, request deduplication, and error formatting.
  - [ ] Implement `public/js/core/storage.js`: LocalStorage / IndexedDB persistence layer for active mode, user tokens, and offline caching.
  - [x] Refactor `public/css/style.css` with a complete Design System:
    - [x] CSS custom property tokens (color palettes, spacing, typography, dark/light or other costume theme variables, make it hot swappable like a theme plugin).
    - [x] Base UI utility classes: Buttons (`.btn-primary`, `.btn-secondary`, `.btn-ghost`), Badges, Inputs, Modals, Dropdowns, Skeleton Loaders, and Empty States.
  - [x] Implement `public/js/components/Toast.js`: Global non-intrusive toast notifications with severity types and undo action support.

- [x] **1.6 Gateway Proxy, Health Check, and Diagnostics Verification**
  - [x] Update `/api/v1/health` to verify end-to-end stack health: PHP-FPM, MySQL query execution, and Python AI gateway ping (`http://ai:8000/api/v1/ai/health`).
  - [x] Verify Nginx static asset caching and reverse proxy routing (`/api/v1/*` -> PHP-FPM, `/api/v1/ai/*` -> FastAPI).

---

## Phase 2: Domain Services, Modular Business Logic, and Full RESTful API

Goal: Construct modular domain engines, permission checks, and RESTful API controllers with zero third-party framework overhead.

- [x] **2.1 Core Domain Engines and Interfaces**
  - [x] Implement `app/Services/PermEngine.php`:
    - [x] Granular RBAC evaluation (`owner`, `admin`, `member`, `guest`).
    - [x] Scope-aware checks across Workspace, Folder, Project, Task, and Document levels.
    - [x] Public share token verification for guest/client readonly access.
  - [x] Implement `app/Services/CustomFieldEngine.php`:
    - [x] Pluggable `FieldTypeHandlerInterface` allowing new custom field types to register formatting, validation, and serialization logic.
    - [x] Built-in handlers for: `text`, `number`, `select`, `multi_select`, `date`, `checkbox`, `user`, `url`, and calculated `formula`.
    - [x] EAV data pivoting for single-query bulk task retrieval.
  - [x] Implement `app/Services/AutoScheduler.php`:
    - [x] Dependency graph construction with cycle detection (Tarjan/Kahn algorithm).
    - [x] Critical path calculation and slack time identification.
    - [x] Forward/backward date recalculations for all 4 dependency types (`finish_to_start`, `start_to_start`, `finish_to_finish`, `start_to_finish`).
  - [x] Implement `app/Services/RecurrenceEngine.php`:
    - [x] Recurring task pattern parser (daily, weekdays, weekly on specific days, monthly, yearly, custom interval).
    - [x] Automatic next-occurrence generation hook upon task completion.

- [x] **2.2 Modular API Controllers**
  - [x] **Auth Module (`app/Controllers/AuthController.php`)**:
    - [x] `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`.
    - [x] `GET /api/v1/auth/me`, `PATCH /api/v1/auth/preferences` (mode preference: `simple` vs `enterprise`).
  - [x] **Workspace & Hierarchy Module (`app/Controllers/WorkspaceController.php`, `FolderController.php`, `ProjectController.php`)**:
    - [x] `GET /api/v1/workspaces`, `POST /api/v1/workspaces`.
    - [x] `GET /api/v1/workspaces/{id}/tree`: Single-roundtrip hierarchical tree of folders, projects, and task counts.
    - [x] `POST /api/v1/workspaces/{id}/members`, `POST /api/v1/workspaces/{id}/modules` (enable/disable feature modules).
    - [x] `CRUD /api/v1/folders`, `CRUD /api/v1/projects`, `POST /api/v1/projects/{id}/duplicate`.
  - [x] **Task & Workflow Module (`app/Controllers/TaskController.php`, `StatusController.php`, `DependencyController.php`)**:
    - [x] `GET /api/v1/tasks`, `POST /api/v1/tasks` (with quick-add NLP extraction).
    - [x] `GET /api/v1/tasks/{id}`, `PATCH /api/v1/tasks/{id}`, `DELETE /api/v1/tasks/{id}`.
    - [x] `POST /api/v1/tasks/batch` (bulk status, date shifts, assignees, delete).
    - [x] `POST /api/v1/tasks/reorder` (Kanban and list positioning).
    - [x] `CRUD /api/v1/statuses`, `CRUD /api/v1/dependencies` (triggers AutoScheduler hooks).
  - [x] **Custom Fields Module (`app/Controllers/CustomFieldController.php`)**:
    - [x] `CRUD /api/v1/custom-fields`, `PUT /api/v1/tasks/{id}/custom-fields`.
  - [x] **Documents Module (`app/Controllers/DocumentController.php`)**:
    - [x] `CRUD /api/v1/documents` (block JSON content, workspace notes).
  - [x] **Time Tracking & Audit Module (`app/Controllers/TimeLogController.php`, `ActivityLogController.php`)**:
    - [x] `CRUD /api/v1/time-logs` (start, stop, manual entry, timesheets).
    - [x] `GET /api/v1/activity-logs` (paginated audit trails).
  - [x] **AI Gateway Proxy Module (`app/Controllers/AIController.php`)**:
    - [x] Proxy `/api/v1/ai/decompose-task`, `/api/v1/ai/estimate-task`, `/api/v1/ai/optimize-schedule`, `/api/v1/ai/parse-prompt` to FastAPI with error fallbacks.

---

## Phase 3: Frontend Hybrid Architecture, Performance, and QoL Engine

Goal: Build a high-performance Hybrid architecture powered by a plugin/registry system for rich client-side components initialized on server-rendered pages.

- [x] **3.1 Frontend Core Plugin and Component Engine**
  - [x] Implement `public/js/core/store.js`: Lightweight PubSub state store with subscription channels for tasks, active filters, workspace tree, and user preferences (synced with server state where appropriate).
  - [x] Implement `public/js/core/eventBus.js`: Global decoupled event bus and UI hook system (`on`, `off`, `emit`, `filter`).
  - [x] Implement `public/js/core/componentRegistry.js`: Extensible registry managing active widgets (Table, Kanban, Gantt, SimpleList, SimpleKanban, DocEditor, Calendar) bound to DOM elements.
  - [x] Implement `public/js/core/fieldRegistry.js`: Extensible custom field widget registry for rendering and inline editing across views.
  - [x] Implement `public/js/core/api.js`: Unified Fetch client for AJAX requests, with CSRF token handling, request deduplication, and retry logic.
  - [x] Implement `public/js/core/storage.js`: IndexedDB / LocalStorage caching layer.
  - [x] Implement `public/js/core/optimistic.js`: Optimistic UI mutation manager with automatic rollback on API failure for widget interactions.
  - [x] Implement `public/js/core/virtualizer.js`: DOM windowing/virtual scroll engine for fluid 60fps rendering of 10,000+ tasks in table/kanban widgets.

- [x] **3.2 Global Ergonomics and Quality of Life (QoL)**
  - [x] Implement `public/js/components/CommandPalette.js` & `commandRegistry.js`:
    - [x] Universal launcher triggered via `Cmd/Ctrl + K` or `/`.
    - [x] Pluggable command providers registering actions dynamically.
    - [x] Fuzzy search across tasks, projects, documents, workspaces, and navigation routes.
    - [x] Quick action executions: "Create new task", "Switch to Enterprise mode", "Go to Today", "Toggle Dark Mode".
  - [x] Implement `public/js/components/KeyboardShortcuts.js`:
    - [x] Navigation keys: `j` / `k` (move up/down), `Enter` (open detail), `Escape` (close/blur).
    - [x] Action keys: `c` (new task), `e` (edit inline), `x` or `Space` (toggle complete), `d` (set due date), `p` (set priority), `m` (assign), `Backspace`/`Delete` (delete task).
    - [x] Visual shortcut cheat-sheet modal (`?` trigger).
  - [x] Implement `public/js/components/Toast.js`:
    - [x] Non-intrusive feedback toasts with severity levels (info, success, warning, error).
    - [x] Interactive "Undo" button on destructive actions (task deletion, bulk edits, status changes) using `Ctrl + Z`.
  - [x] Implement `public/js/components/ThemeManager.js`:
    - [x] Instant theme switching (Dark, Light, System Sync).
    - [x] Accent color palette selector with CSS custom property variables.

---

## Phase 4: Simple Mode (Micro-Tier Focus Experience)

Goal: Deliver a clean, zero-friction task management experience tailored for daily personal focus, inbox zero workflows, and rapid capture.

- [x] **4.1 Simple Mode Layout and Views**
  - [x] Minimalist sidebar with focus views:
    - [x] "Inbox": Unsorted, newly captured thoughts and micro-tasks.
    - [x] "Today": Tasks scheduled for current day with progress completion ring.
    - [x] "Upcoming": Next 7 days view grouped by date.
    - [x] "Completed": History of completed items with completion timestamps.
  - [x] Distraction-free clean header hiding enterprise multi-project dropdowns and complex toolbar controls.

- [x] **4.2 Rapid Capture QuickAddBar**
  - [x] Implement `public/js/components/QuickAddBar.js`:
    - [x] Single-line input with auto-expanding capability.
    - [x] In-line NLP parsing: `@today`, `@tomorrow`, `@friday 3pm`, `!urgent`, `!low`, `#work`, `#personal`.
    - [x] One-click AI auto-breakdown button for generating subtasks on enter.
    - [x] Sticky bottom/top bar accessible anywhere in Simple Mode.

- [x] **4.3 Simple Task List and Simple Kanban**
  - [x] Implement `public/js/components/SimpleTaskList.js` (registered in `componentRegistry.js`):
    - [x] One-click checkbox completion with micro-animation and celebratory completion sound toggle.
    - [x] Direct inline text editing on click without modal popups.
    - [x] Lightweight drag handle for custom prioritization and ordering.
    - [x] Inline subtask checklist expansion.
  - [x] Implement `public/js/components/SimpleKanban.js` (registered in `componentRegistry.js`):
    - [x] Streamlined 3-column personal board (To Do, In Progress, Done).
    - [x] Fluid HTML5 drag-and-drop card movement.

- [x] **4.4 Dynamic Upgrade Path to Enterprise**
  - [x] Implement `public/js/components/UpgradePrompt.js`:
    - [x] Contextual prompt: "Convert this list into an Enterprise Project".
    - [x] Seamless one-click migration: binds personal tasks to a new workspace/folder hierarchy, enables Gantt and custom fields without data loss.

---

## Phase 5: Enterprise Mode (Macro-Tier Work OS)

Goal: Build an enterprise-grade work management platform supporting nested hierarchies, pluggable multi-views, custom fields, Gantt timelines, block documentation, and team collaboration.

- [x] **5.1 Enterprise Workspace Hierarchy and Navigation**
  - [x] Implement `public/js/components/SidebarHierarchy.js`:
    - [x] Multi-tenant workspace switcher with avatar icons and role tags.
    - [x] Nested Tree View: Workspaces -> Folders -> Projects -> Custom Views.
    - [x] Context menu on tree nodes: Rename, Duplicate, Change Color, Create Sub-folder, Delete.
    - [x] Drag-and-drop reorganization of folders and projects.
  - [x] Implement `public/js/components/WorkspaceModal.js`:
    - [x] Member management table with role selection (`owner`, `admin`, `member`, `guest`).
    - [x] Feature module toggle panel (enable/disable Gantt, Docs, Time Tracking, Custom Fields per workspace).
    - [x] Invitation link generation and guest sharing permissions.

- [x] **5.2 Pluggable Data Views Engine**
  - [x] Implement `public/js/components/ViewSwitcher.js`: Tab bar dynamically rendered from registered widgets in `componentRegistry.js` or via server navigation.
  - [x] Implement `public/js/components/FilterBar.js`:
    - [x] Multi-condition filtering (Status is/is not, Assignee contains, Due Date before/after/within, Priority, Custom Field values).
    - [x] Multi-level sorting (by Priority, Due Date, Title, Created Date, Custom Field).
    - [x] Group By engine (group tasks by Status, Assignee, Priority, or Project).
    - [x] Saved View presets (e.g., "My High Priority Tasks", "Overdue Milestones").

- [x] **5.3 Enterprise Table / Spreadsheet Grid Plugin**
  - [x] Implement `public/js/components/TaskTable.js` (registered in `componentRegistry.js`):
    - [x] Virtualized table supporting 5,000+ rows with pinned column headers.
    - [x] Inline cell editors consuming `fieldRegistry.js` widget plugins.
    - [x] Column resizing, reordering, and visibility toggling dropdown.
    - [x] Multi-row selection with shift-click and box-selection.
    - [x] Bottom summary bar showing task counts, sum/average of estimated hours, and custom field totals.

- [x] **5.4 Enterprise Kanban Board Plugin**
  - [x] Implement `public/js/components/KanbanBoard.js` (registered in `componentRegistry.js`):
    - [x] Dynamic status columns with customizable WIP (Work In Progress) limits.
    - [x] Horizontal Swimlanes (group by Assignee, Priority, or Folder).
    - [x] Card customization settings (show/hide assignees, subtask progress bar, tags, custom fields, cover colors).
    - [x] Drag-and-drop cards between columns and swimlanes with drop placeholder indicator.

- [x] **5.5 Interactive Gantt Chart and Timeline Plugin**
  - [x] Implement `public/js/components/GanttChart.js` (registered in `componentRegistry.js`):
    - [x] High-performance SVG + HTML canvas timeline rendering.
    - [x] Drag-to-resize duration handles and drag-to-shift date bars.
    - [x] Interactive dependency drawing: drag link line from task connector circle to another task.
    - [x] Visual dependency curves with arrow markers for all 4 dependency types.
    - [x] Critical Path highlighting toggle and milestone diamonds.
    - [x] Zoom scale selector (Day, Week, Month, Quarter, Year).
    - [x] One-click "AI Auto-Schedule" button resolving timeline conflicts.

- [x] **5.6 Bulk Operations Toolbar**
  - [x] Implement `public/js/components/BulkActionToolbar.js`:
    - [x] Floating bottom action bar appearing when multiple tasks are selected.
    - [x] Actions: Change Status, Reassign, Shift Due Dates (+1 day, +1 week), Set Priority, Move to Project, Batch Delete.

- [x] **5.7 Custom Fields Engine UI**
  - [x] Implement `public/js/components/CustomFieldManager.js` and `fieldRegistry.js`:
    - [x] Modular widget system for 9 field types: Text, Number, Dropdown Select, Multi-Select, Date, Checkbox, User Assignee, URL, Formula.
    - [x] Custom color badge assignment for Select options.
    - [x] Dynamic field rendering inside Task Details, Table Grid, and Kanban Cards.

- [x] **5.8 Task Detail Drawer and Modal**
  - [x] Implement `public/js/components/TaskDetailDrawer.js`:
    - [x] Slide-out drawer or full-screen modal view.
    - [x] Markdown rich-text task description with auto-save.
    - [x] Subtask hierarchy tree with add/remove/check operations.
    - [x] Dependency manager tab showing "Blocked By" and "Blocking" task lists.
    - [x] Time tracking stopwatch widget and manual log list.
    - [x] Activity log timeline showing exact timestamps, changes, and authors.
    - [x] Comments thread with rich markdown and @mention support.

- [x] **5.9 Slash-Command Block Document Editor Plugin**
  - [x] Implement `public/js/components/DocEditor.js` & `blockRegistry.js` (registered in `componentRegistry.js`):
    - [x] Block-based contenteditable document workspace for project specs and wikis.
    - [x] Pluggable slash menu (`/`) blocks: Heading 1/2/3, Bullet List, Numbered List, Checklist, Code Block, Callout Note, Quote, Table, Divider.
    - [x] Embeddable live task card block directly linking to project tasks.

- [x] **5.10 Import and Export Engine**
  - [x] Implement `public/js/components/DataImportExport.js`:
    - [x] CSV/JSON import wizard with column mapping to task fields and custom fields.
    - [x] One-click export of project data to CSV, JSON, and printable summary report.

---

## Phase 6: Python AI Microservice Expansion and Autonomous Helpers

Goal: Deliver intelligent task breakdown, effort estimation, natural language parsing, and automated Gantt schedule optimization.

- [x] **6.1 AI Microservice Algorithms (`ai_services/`)**
  - [x] Implement critical path method (CPM) and PERT schedule leveling in Python.
  - [x] Implement natural language processing task parser in `ai_services/main.py` extracting entities (dates, priority, tags, estimates) via Gemini 3.1 Flash-Lite.
  - [x] Implement task breakdown subtask generator using Gemini 3.1 Flash-Lite and contextual prompt templates.
  - [x] Implement smart dependency recommendation engine comparing task descriptions and historical project flows.

- [x] **6.2 Microservice Testing and Pytest Suite**
  - [x] Add unit tests in `ai_services/tests/test_api.py` covering:
    - [x] Health check and CORS validation.
    - [x] Task effort estimation boundary tests.
    - [x] Schedule optimization cycle detection and date shift validation.
    - [x] NLP prompt parser edge cases.
  - [x] Add unit tests in `ai_services/tests/test_scheduler.py` covering CPM forward/backward passes, early/late start & finish, slack times, and cycle detection.

---

## Phase 7: Testing, Security Hardening, and Production Polish

Goal: Validate complete system reliability, prevent security vulnerabilities, optimize rendering pipelines, and ensure smooth multi-container deployment.

- [x] **7.1 Comprehensive Automated and Manual Testing**
  - [x] Unit tests for PHP Core (Router, Database, SessionManager, PermEngine, AutoScheduler, CustomFieldEngine, PluginManager, EventDispatcher).
  - [x] Integration tests for REST API endpoints verifying authentication, authorization, and error responses.
  - [x] Frontend browser verification across Simple Mode and Enterprise Mode workflows.

- [x] **7.2 Security Hardening**
  - [x] Audit all SQL queries across PHP controllers ensuring 100% prepared statement parameter binding.
  - [x] Implement CSRF token verification on state-modifying requests.
  - [x] Enforce output HTML escaping (`escapeHtml`) to prevent Cross-Site Scripting (XSS).
  - [x] Add API rate-limiting middleware (`RateLimitMiddleware.php`) to protect against brute-force attacks.

- [x] **7.3 Performance Optimization**
  - [x] Optimize database indexes and verify query execution plans (`EXPLAIN`).
  - [x] Minify frontend JavaScript modules and CSS stylesheets.
  - [x] Ensure sub-50ms frontend render latency for core view switches and sub-100ms API response times.

- [x] **7.4 Documentation and Deployment**
  - [x] Update `README.md` with complete architecture diagrams, environment setup guide, API reference, and Docker deployment commands.
  - [x] Verify clean startup on Docker Compose (`docker-compose up -d --build`).
