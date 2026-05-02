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

- **Backend:** PHP 8.3 (via PHP-FPM)
- **Frontend:** Vanilla JS (`app.js`), Tailwind CSS
- **Database:** MySQL 8.0
- **Web Server:** Nginx (via Docker)
- **Dependency Management:** Composer

## Getting Started (Docker)

The recommended way to run Bathyal locally or in production is using Docker.

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/bathyal.git
   cd bathyal
   ```

2. **Setup Environment Variables:**
   Copy the example environment file and fill in your database passwords.
   ```bash
   cp .env.example .env
   ```

3. **Start the containers:**
   ```bash
   docker-compose up -d --build
   ```

   *Note 1: On the very first run, MySQL will automatically execute `database.sql` to seed the initial database schema.*

   *Note 2: The PHP container will automatically run `composer install` upon starting to install the required dependencies (like PHPMailer).*

4. **Access the application:**
   - Web App: `http://localhost:8000`
   - phpMyAdmin: `http://localhost:8080` (Use credentials from your `.env` file)

### Common Docker Commands

- **Start the app:**
  ```bash
  docker-compose up -d
  ```
- **Stop the app:**
  ```bash
  docker-compose down
  ```
- **Rebuild the app** (e.g., after changing Docker configs. It is best to stop the app first before rebuild):
  ```bash
  docker-compose up -d --build
  ```

### Database Setup Best Practices

For a secure and stable installation, keep these best practices in mind when setting up your `.env` variables:
1. **Change Default Passwords:** Always change `DB_PASS` and `DB_ROOT_PASS` from their defaults before starting the containers for the first time.
2. **Fresh Installation Hook:** MySQL only runs the `database.sql` initialization script if the `.docker/mysql-data` directory is **completely empty**. If you start the container, make a mistake, and want to start over, you must delete that folder.
3. **Database Client:** You can manage the database visually by navigating to `http://localhost:8080`. Log in using `DB_USER` and `DB_PASS`.

### Email / SMTP Setup

Bathyal uses PHPMailer to send system emails (like notifications and password resets). Out of the box, if SMTP is not configured, it will attempt to use the local PHP `mail()` function as a fallback.

To properly configure SMTP for reliable email delivery:

1. Open your `.env` file.
2. Update the SMTP variables with your mail server credentials. For example, using Gmail:
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   SMTP_USER=your.email@gmail.com
   SMTP_PASS=your_app_password
   SMTP_FROM_EMAIL=your.email@gmail.com
   SMTP_FROM_NAME=Pat System
   ```
   *(Note: If using Gmail, you must generate an "App Password" from your Google Account settings, rather than using your main account password.)*
3. Restart your docker containers if they are currently running: `docker-compose restart app`

### Data Persistence (Important!)
By default, there are two areas where data is safely persisted to your local machine:
1. **Database:** Saved to `.docker/mysql-data/`.
2. **File Uploads:** Saved to `assets/uploads/`.

**This ensures your database and user uploads are safe and persist** even if you stop, rebuild, or completely remove the Docker containers.

If you ever need to completely wipe the database and start fresh (re-running `database.sql`), you must manually delete the database folder and the docker volumes:
```bash
docker-compose down -v
rm -rf .docker/mysql-data/
```

If you also want to completely wipe all user-uploaded files, you can delete the contents of the uploads folder:
```bash
rm -rf assets/uploads/*
```

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
