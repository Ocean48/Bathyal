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
| Server     | Apache/Nginx |


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

- **PHP 8.x** (with `pdo_mysql`, `curl`, and `mbstring` extensions enabled)
- **MySQL 8.0+** or **MariaDB**
- **Composer** (for managing dependencies like PHPMailer)
- **Web Server:** WAMP (Windows/Apache) for local development, or Linux/Nginx for production.

### Installation

#### 1. Local Development (WAMP)

1. **Clone the repository** into your WAMP `www` directory:
   ```bash
   cd c:\wamp64\www
   git clone <repository-url> bathyal
   cd bathyal
   ```

2. **Install dependencies** using Composer:
   ```bash
   composer install
   ```
   *Note: This will read `composer.json` and `composer.lock` to download the exact package versions into a `vendor/` folder. Do not commit the `vendor/` folder.*

3. **Database Setup:**
   - Open phpMyAdmin (usually `http://localhost/phpmyadmin`).
   - Create a new database named `bathyal`.
   - Import the `database.sql` file located in the root of the project to create the necessary tables.

4. **Configuration:**
   - Update `core/database.php` with your local database credentials (usually `root` for username and an empty password in WAMP).

5. **Run the App:**
   - Open your browser and navigate to `http://localhost/bathyal`.

#### 2. Production Deployment (Linux / Nginx)

1. **Clone the repository** to your web root (e.g., `/var/www/bathyal`):
   ```bash
   git clone <repository-url> /var/www/bathyal
   cd /var/www/bathyal
   ```

2. **Install production dependencies:**
   This command installs exact versions from `composer.lock`, skips development tools, and optimizes classes for faster loading:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Database Setup:**
   - Create a production database and dedicated database user.
   - Import the database schema via the command line:
     ```bash
     mysql -u your_user -p bathyal < database.sql
     ```
   - Update `core/database.php` with your secure production database credentials.

4. **Permissions:**
   - Ensure your web server has write permissions to any required directories (like an uploads folder, if applicable):
     ```bash
     chown -R www-data:www-data /var/www/bathyal
     ```

5. **Nginx Configuration:**
   - Point your Nginx virtual host `root` to `/var/www/bathyal`.
   - Ensure it is configured to pass `.php` files to PHP-FPM.

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
