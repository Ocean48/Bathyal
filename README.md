# Bathyal

A lightweight, self-hosted project management web application built with PHP and MySQL. Bathyal helps teams organise work into projects, sections, and tasks — with time tracking, notifications, automations, and reporting built in.

## Features

- **Projects & Sections** — Organise work into projects with drag-and-drop sections and task ordering.
- **Tasks** — Full task lifecycle with subtasks, multiple assignees, labels, start/due dates, and status tracking (`todo`, `in_progress`, `paused`, `completed`).
- **Time Tracking** — Log time against tasks with start, pause, and end support.
- **Teams** — Create teams, invite members, and assign roles (`owner`, `admin`, `member`).
- **Inbox / Notifications** — In-app notification centre with category filtering and mark-as-read.
- **Automations** — Trigger-based actions (e.g. start next task on completion, notify assignees on due date).
- **Reports** — Task status breakdowns, project workload summaries, and overdue task views.
- **File Uploads** — Attach files to tasks via the upload API.
- **Role-Based Access** — User roles: `admin`, `member`, `data_analyst`.
- **REST JSON API** — All data operations exposed under `/api/`.

## Tech Stack

| Layer      | Technology          |
|------------|---------------------|
| Backend    | PHP (front-controller pattern) |
| Database   | MySQL               |
| Frontend   | HTML, CSS, Vanilla JS |
| Server     | Apache (tested with WAMP) |

## Project Structure

```
bathyal/
├── index.php            # Front controller / router
├── database.sql         # Full schema
├── api/                 # JSON API endpoints
│   ├── info.php
│   ├── projects.php
│   ├── sections.php
│   ├── tasks.php
│   ├── teams.php
│   ├── upload.php
│   └── users.php
├── core/                # Shared utilities
│   ├── auth_check.php
│   ├── database.php
│   └── db_query.php
├── views/
│   ├── auth/            # Login, register, logout
│   ├── layouts/         # Shared header & footer
│   └── pages/           # Dashboard, projects, tasks, reports, inbox, teams, settings
└── assets/
    ├── css/style.css
    ├── js/app.js
    └── uploads/
```

## Getting Started

### Prerequisites

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache with `mod_rewrite` enabled (WAMP, XAMPP, or native)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Ocean48/bathyal.git
   ```

2. **Place in your web root**  
   e.g. `C:\wamp64\www\bathyal` or `/var/www/html/bathyal`

3. **Import the database schema**
   ```bash
   mysql -u root -p < database.sql
   ```

4. **Configure the database connection**  
   Edit `core/database.php` with your host, database name, username, and password.

5. **Set the base path** (if not hosted at the domain root)  
   In `index.php`, update `$base_path`:
   ```php
   $base_path = '/bathyal'; // or '' if hosted at root
   ```

6. **Visit the app**  
   `http://localhost/bathyal`

## API Endpoints

All endpoints return JSON and expect authenticated sessions.

| Method | Path                  | Description                  |
|--------|-----------------------|------------------------------|
| GET    | `/api/projects.php`   | List / retrieve projects     |
| POST   | `/api/projects.php`   | Create / update projects     |
| GET    | `/api/tasks.php`      | List / retrieve tasks        |
| POST   | `/api/tasks.php`      | Create / update tasks        |
| GET    | `/api/sections.php`   | Retrieve sections            |
| POST   | `/api/sections.php`   | Create / reorder sections    |
| GET    | `/api/teams.php`      | List teams and members       |
| POST   | `/api/teams.php`      | Create teams / manage members|
| GET    | `/api/users.php`      | Retrieve users               |
| POST   | `/api/upload.php`     | Upload file attachments      |
| GET    | `/api/info.php`       | Server / session info        |

## License

This project is licensed under the terms of the [LICENSE](LICENSE) file.
