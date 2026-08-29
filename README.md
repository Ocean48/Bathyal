# Bathyal

Bathyal is a modern, full-stack project management system designed to seamlessly balance lightweight daily to-do tracking with enterprise-grade work operating system capabilities.

It leverages a Unified Data Core with Dynamic View Scoping to support both zero-friction personal task management and complex multi-workspace project orchestration.

---

## Quickstart with Docker Compose

### Prerequisites
- Docker Engine & Docker Compose

### Start Stack
```bash
docker compose up -d --build
```

### Access Services
- **Web Application:** [http://localhost:8082](http://localhost:8082)
- **FastAPI AI Docs:** [http://localhost:8005/api/v1/ai/docs](http://localhost:8005/api/v1/ai/docs)
- **phpMyAdmin:** [http://localhost:8081](http://localhost:8081)

---

## Default User Accounts

| Account | Email | Password | Role | Default Mode |
|---|---|---|---|---|
| **System Admin** | `admin@bathyal.local` | `password123` | `owner` | Enterprise |
| **Demo User** | `demo@bathyal.local` | `password123` | `member` | Simple |

---

## AI Microservice (Gemini 3.1 Flash-Lite)

Bathyal integrates with Google's **Gemini 3.1 Flash-Lite** (`gemini-3.1-flash-lite`) for fast and structured project automation.

### Configuration
In `.env` or Docker environment variables:
```ini
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-3.1-flash-lite
```
*Note: If `GEMINI_API_KEY` is not provided, the microservice automatically falls back to deterministic local heuristic algorithms with zero disruption.*

---

## RESTful API Endpoints

### 1. Authentication
- `POST /api/v1/auth/login` — User login and Bearer token generation.
- `POST /api/v1/auth/register` — Account registration with auto-provisioned personal workspace.
- `GET /api/v1/auth/me` — Current authenticated user profile & workspaces.
- `POST /api/v1/auth/logout` — Revoke session.
- `PATCH /api/v1/auth/preferences` — Update default mode (`simple` / `enterprise`).

### 2. Workspaces & Hierarchy
- `GET /api/v1/workspaces` — List accessible workspaces.
- `POST /api/v1/workspaces` — Create workspace.
- `GET /api/v1/workspaces/{id}/tree` — Full recursive hierarchy of folders, projects, and task counts.
- `CRUD /api/v1/folders` — Manage folders and ordering.
- `CRUD /api/v1/projects` — Manage projects.
- `POST /api/v1/projects/{id}/duplicate` — Clone project with tasks and custom fields.

### 3. Tasks & Workflows
- `GET /api/v1/tasks` — List tasks with view filters (`inbox`, `today`, `upcoming`, `completed`).
- `POST /api/v1/tasks` — Create task with inline NLP parsing (`@today`, `!urgent`).
- `GET /api/v1/tasks/{id}` — Single task with assignees and custom fields.
- `PATCH /api/v1/tasks/{id}` — Update task attributes.
- `DELETE /api/v1/tasks/{id}` — Delete task.
- `POST /api/v1/tasks/batch` — Bulk status, priority, and deletion.
- `POST /api/v1/tasks/reorder` — Update task ordering.

### 4. Dependencies & Custom Fields
- `GET /api/v1/dependencies` — List task dependencies.
- `POST /api/v1/dependencies` — Link tasks with cycle detection and auto-schedule recalculation.
- `DELETE /api/v1/dependencies/{id}` — Remove dependency link.
- `GET /api/v1/custom-fields` — Custom field schemas.
- `POST /api/v1/custom-fields` — Define new custom field.
- `PUT /api/v1/tasks/{id}/custom-fields` — Set task custom field value with type validation.

### 5. Documents & Collaboration
- `CRUD /api/v1/documents` — Structured JSON block specifications and notes.
- `CRUD /api/v1/time-logs` — Active timers, stopwatch logging, and task timesheets.
- `GET /api/v1/activity-logs` — Audit log timeline.

### 6. AI Microservice
- `POST /api/v1/ai/parse-prompt` — Extract task entities from natural language text.
- `POST /api/v1/ai/decompose-task` — Break complex tasks into subtasks with hour estimates.
- `POST /api/v1/ai/recommend-dependencies` — Suggest predecessor/successor links.
- `POST /api/v1/ai/optimize-schedule` — Run Critical Path Method (CPM) leveling on project graphs.

---

## Keyboard Shortcuts

| Key | Action |
|---|---|
| `Ctrl + K` / `Cmd + K` / `/` | Open Universal Command Palette |
| `j` / `↓` | Select Next Task |
| `k` / `↑` | Select Previous Task |
| `c` | Focus Quick Add Bar |
| `x` / `Space` | Toggle Task Complete |
| `e` | Edit Task Title Inline |
| `1` - `4` | Quick Switch Focus View (Inbox, Today, Upcoming, Completed) |
| `?` | Show Keyboard Shortcuts Cheat Sheet |
| `Delete` / `Backspace` | Delete Selected Task |
| `Esc` | Close Drawer / Modal / Clear Selection |

---

## Running Automated Tests

### PHP Test Suite
```bash
docker compose exec php php tests/run.php
```

### Python AI Test Suite
```bash
docker compose exec ai pytest
```

Copy the example environment file:

```bash
cp .env.example .env
```

Review and adjust variables in `.env` as needed (ports, database credentials, environment mode).

### 2. Build and Launch Containers

Start the complete multi-container stack:

```bash
docker compose up -d --build
```

Verify that all containers are running healthy:

```bash
docker compose ps
```

### 3. Accessing the Application

- **Web Application:** [http://localhost](http://localhost) (or configured `HTTP_PORT`)
- **Database Management (phpMyAdmin):** [http://localhost:8080](http://localhost:8080) (or configured `PMA_PORT`)
- **AI Microservice Direct API:** [http://localhost:8000](http://localhost:8000) (or configured `AI_HOST_PORT`)
- **Interactive OpenAPI Documentation:** [http://localhost/api/v1/ai/docs](http://localhost/api/v1/ai/docs)
- **OpenAPI JSON Schema:** [http://localhost/api/v1/ai/openapi.json](http://localhost/api/v1/ai/openapi.json)

---

## AI Microservice (`ai_services`)

The AI microservice in `ai_services/` provides endpoints for task estimation and auto-scheduling:

### Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/` | Service root and status |
| `GET` | `/api/v1/ai/health` | Health check and active environment |
| `POST` | `/api/v1/ai/estimate-task` | Calculates task effort & generates subtasks |
| `POST` | `/api/v1/ai/optimize-schedule` | Computes schedule optimization from dependency graph |
| `GET` | `/api/v1/ai/docs` | Swagger UI documentation |

### Example Request: Task Estimation

```bash
curl -X POST http://localhost/api/v1/ai/estimate-task \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Implement OAuth2 Authentication",
    "description": "Add Google and GitHub social login",
    "priority": "urgent"
  }'
```

Response:

```json
{
  "estimated_hours": 8.0,
  "confidence": 0.85,
  "suggested_subtasks": [
    "Research and planning for: Implement OAuth2 Authentication",
    "Implementation phase for: Implement OAuth2 Authentication",
    "Testing and review for: Implement OAuth2 Authentication"
  ]
}
```

---

## Testing

### Running AI Service Tests in Docker

Execute the pytest suite within the running AI container:

```bash
docker compose exec ai pytest
```

### Running Tests Locally

If developing locally outside Docker:

```bash
cd ai_services
python -m venv .venv
# On Windows:
.venv\Scripts\activate
# On Linux/macOS:
source .venv/bin/activate

pip install -r requirements.txt
pytest
```

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Bathyal` | Application display name |
| `APP_ENV` | `development` | Environment mode (`development` / `production`) |
| `APP_DEBUG` | `true` | Debug flag |
| `HTTP_PORT` | `80` | Host port for HTTP Nginx server |
| `HTTPS_PORT` | `443` | Host port for HTTPS Nginx server |
| `AI_HOST` | `bathyal_ai` | Internal hostname for AI microservice |
| `AI_PORT` | `8000` | Internal port for AI microservice |
| `AI_HOST_PORT` | `8000` | Published host port for direct AI access |
| `DB_HOST` | `db` | Database hostname inside Docker network |
| `DB_PORT` | `3306` | Database internal port |
| `DB_HOST_PORT` | `3306` | Published host port for MySQL |
| `DB_DATABASE` | `bathyal_db` | MySQL database name |
| `DB_USERNAME` | `bathyal_user` | MySQL application user |
| `DB_PASSWORD` | `bathyal_secret` | MySQL application password |
| `DB_ROOT_PASSWORD` | `root_secret` | MySQL root password |

---

## License

This project is licensed under the terms of the GNU Affero General Public License v3 (AGPL-3.0). See the [LICENSE](LICENSE) file for full details.
