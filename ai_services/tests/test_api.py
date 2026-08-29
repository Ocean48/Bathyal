"""Unit and integration tests for the Bathyal AI Microservice endpoints."""

import pytest
from fastapi.testclient import TestClient


class TestRootAndHealthEndpoints:
    """Tests for base service discovery and health check endpoints."""

    def test_root_endpoint(self, client: TestClient):
        response = client.get("/")
        assert response.status_code == 200
        data = response.json()
        assert data["service"] == "Bathyal AI Microservice"
        assert data["status"] == "healthy"
        assert data["version"] == "1.0.0"

    def test_health_check_default(self, client: TestClient):
        response = client.get("/api/v1/ai/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert data["service"] == "bathyal-ai"
        assert "environment" in data

    def test_health_check_custom_environment(self, client: TestClient, monkeypatch: pytest.MonkeyPatch):
        monkeypatch.setenv("APP_ENV", "production")
        response = client.get("/api/v1/ai/health")
        assert response.status_code == 200
        data = response.json()
        assert data["environment"] == "production"


class TestTaskEstimationEndpoint:
    """Tests for the task estimation and subtask generation endpoint."""

    def test_estimate_task_default_priority(self, client: TestClient):
        payload = {"title": "Implement User Authentication"}
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["estimated_hours"] == 4.0
        assert data["confidence"] == 0.85
        assert len(data["suggested_subtasks"]) == 3
        assert "Research and planning for: Implement User Authentication" in data["suggested_subtasks"]
        assert "Implementation phase for: Implement User Authentication" in data["suggested_subtasks"]
        assert "Testing and review for: Implement User Authentication" in data["suggested_subtasks"]

    def test_estimate_task_urgent_priority(self, client: TestClient):
        payload = {
            "title": "Critical Security Patch",
            "description": "Patch SQL injection vulnerability",
            "priority": "urgent",
        }
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["estimated_hours"] == 8.0
        assert data["confidence"] == 0.85
        assert len(data["suggested_subtasks"]) == 3

    def test_estimate_task_low_priority(self, client: TestClient):
        payload = {
            "title": "Update Footer Copyright Year",
            "priority": "low",
        }
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["estimated_hours"] == 2.0
        assert data["confidence"] == 0.85

    def test_estimate_task_custom_description(self, client: TestClient):
        payload = {
            "title": "Database Migration Script",
            "description": "Migrate MySQL 5.7 schema to MySQL 8.0 utf8mb4",
            "priority": "medium",
        }
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["estimated_hours"] == 4.0
        assert len(data["suggested_subtasks"]) == 3

    def test_estimate_task_missing_title(self, client: TestClient):
        payload = {"description": "Task without title"}
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 422
        data = response.json()
        assert "detail" in data

    def test_estimate_task_invalid_json(self, client: TestClient):
        response = client.post(
            "/api/v1/ai/estimate-task",
            content="invalid-json-body",
            headers={"Content-Type": "application/json"},
        )
        assert response.status_code == 422


class TestScheduleOptimizationEndpoint:
    """Tests for the auto-scheduler dependency optimization endpoint."""

    def test_optimize_schedule_success(self, client: TestClient):
        payload = {
            "tasks": [
                {"id": 1, "title": "Design Database Schema", "estimated_hours": 8},
                {"id": 2, "title": "Implement API Endpoints", "estimated_hours": 16},
                {"id": 3, "title": "Deploy to Staging", "estimated_hours": 4},
            ],
            "dependencies": [
                {"blocking_task_id": 1, "dependent_task_id": 2, "type": "finish_to_start"},
                {"blocking_task_id": 2, "dependent_task_id": 3, "type": "finish_to_start"},
            ],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["tasks_processed"] == 3
        assert data["dependencies_processed"] == 2
        assert "Auto-schedule calculation complete" in data["message"]

    def test_optimize_schedule_empty_payload(self, client: TestClient):
        payload = {
            "tasks": [],
            "dependencies": [],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["tasks_processed"] == 0
        assert data["dependencies_processed"] == 0

    def test_optimize_schedule_missing_dependencies_field(self, client: TestClient):
        payload = {
            "tasks": [{"id": 1, "title": "Single Task"}],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 422

    def test_optimize_schedule_invalid_types(self, client: TestClient):
        payload = {
            "tasks": "not-a-list",
            "dependencies": 123,
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 422


class TestOpenAPIAndDocs:
    """Tests for API documentation and schema validity."""

    def test_openapi_schema(self, client: TestClient):
        response = client.get("/api/v1/ai/openapi.json")
        assert response.status_code == 200
        schema = response.json()
        assert schema["info"]["title"] == "Bathyal AI Microservice"
        assert "/api/v1/ai/health" in schema["paths"]
        assert "/api/v1/ai/estimate-task" in schema["paths"]
        assert "/api/v1/ai/optimize-schedule" in schema["paths"]

    def test_docs_page(self, client: TestClient):
        response = client.get("/api/v1/ai/docs")
        assert response.status_code == 200
        assert "text/html" in response.headers.get("content-type", "")

    def test_cors_preflight(self, client: TestClient):
        response = client.options(
            "/api/v1/ai/health",
            headers={
                "Origin": "http://localhost",
                "Access-Control-Request-Method": "GET",
            },
        )
        assert response.status_code == 200
        assert response.headers.get("access-control-allow-origin") == "*"
