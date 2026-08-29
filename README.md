# Bathyal

Bathyal is a modern, full-stack project management system designed to seamlessly balance lightweight daily to-do tracking with enterprise-grade work operating system capabilities.

It leverages a Unified Data Core with Dynamic View Scoping to support both zero-friction personal task management and complex multi-workspace project orchestration.

---

## Architecture Overview

Bathyal operates on a four-tier containerized architecture orchestrated via Docker Compose:

1. **Layer A: Nginx Routing & Reverse Proxy (`web`)**
   - Serves frontend static assets directly from `/public`.
   - FastCGI proxy for PHP dynamic endpoints (`/api/v1/*`).
   - Reverse proxy for Python AI services (`/api/v1/ai/*` to `bathyal_ai:8000`).

2. **Layer B: PHP Core Application (`php`)**
   - Native PHP 8.3 FPM service with no heavy framework dependencies.
   - Handles authentication, RBAC, workspace hierarchies, and custom field logic.
   - Connects to MySQL via PDO with prepared statement caching.

3. **Layer C: Python AI Microservice (`ai`)**
   - Python 3.11 FastAPI service located in `ai_services/`.
   - Provides intelligent task effort estimation, subtask decomposition, and dependency-based schedule optimization.

4. **Layer D: Relational Data Core (`db`)**
   - MySQL 8.0 database with utf8mb4 collation.
   - Automatically initializes schema from `docker/mysql/init/01-schema.sql`.

---

## Directory Structure

```text
bathyal/
├── ai_services/                # Python FastAPI AI & scheduling microservice
│   ├── tests/                  # Automated pytest test suite
│   │   ├── __init__.py
│   │   ├── conftest.py
│   │   └── test_api.py
│   ├── main.py                 # FastAPI microservice application entrypoint
│   ├── pytest.ini              # Pytest configuration
│   └── requirements.txt        # Python package dependencies
├── app/                        # PHP application core (Framework-free)
│   ├── Controllers/            # API controllers (Workspace, Task, AI, etc.)
│   ├── Core/                   # Database PDO wrapper, Router, Session manager
│   └── Services/               # Scheduler, Custom Field Engine, Permissions
├── docker/                     # Container configuration files
│   ├── ai/                     # Python Dockerfile
│   ├── mysql/                  # MySQL initialization schema
│   ├── nginx/                  # Nginx configuration & Dockerfile
│   └── php/                    # PHP Dockerfile, php.ini, php-fpm configuration
├── public/                     # Web root & Single Page Application (SPA)
│   ├── css/                    # Stylesheets
│   ├── js/                     # Vanilla JavaScript SPA engine & state store
│   ├── index.html              # Frontend application template
│   └── index.php               # PHP API entrypoint
├── docker-compose.yml          # Multi-container orchestration specification
├── .env.example                # Environment variables template
├── LICENSE                     # GNU Affero General Public License v3
├── PLAN.md                     # Architecture specification & schema blueprint
└── README.md                   # Project documentation
```

---

## Quick Start Guide

### Prerequisites

- Docker (version 24.0 or later)
- Docker Compose (v2)

### 1. Environment Configuration

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
