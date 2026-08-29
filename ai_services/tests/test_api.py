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
        assert data["version"] == "1.1.0"
        assert "Gemini" in data["ai_engine"]

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
        assert data["estimated_hours"] > 0
        assert data["confidence"] >= 0.8
        assert len(data["suggested_subtasks"]) >= 3

    def test_estimate_task_urgent_priority(self, client: TestClient):
        payload = {
            "title": "Critical Security Patch",
            "description": "Patch SQL injection vulnerability",
            "priority": "urgent",
        }
        response = client.post("/api/v1/ai/estimate-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["estimated_hours"] > 0
        assert len(data["suggested_subtasks"]) >= 3


class TestPhase6AIFeatures:
    """Tests for Gemini 3.1 Flash-Lite NLP Prompt Parsing, Decomposition, Recommendations, and CPM."""

    def test_parse_prompt_endpoint(self, client: TestClient):
        payload = {"prompt": "Design responsive navigation bar next Friday !urgent #frontend ~3.5h"}
        response = client.post("/api/v1/ai/parse-prompt", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert "Design responsive navigation bar" in data["title"]
        assert data["priority"] == "urgent"
        assert data["estimated_hours"] == 3.5
        assert "frontend" in data["tags"]
        assert data["source"] in ["gemini-3.1-flash-lite", "heuristic"]

    def test_parse_prompt_empty(self, client: TestClient):
        response = client.post("/api/v1/ai/parse-prompt", json={"prompt": "   "})
        assert response.status_code == 422

    def test_decompose_task_endpoint(self, client: TestClient):
        payload = {
            "title": "Build Distributed Event Bus",
            "description": "Kafka integration with retry queue",
            "complexity": "high"
        }
        response = client.post("/api/v1/ai/decompose-task", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["parent_title"] == "Build Distributed Event Bus"
        assert len(data["subtasks"]) >= 3
        assert data["total_estimated_hours"] > 0
        for s in data["subtasks"]:
            assert "title" in s
            assert "estimated_hours" in s
            assert "priority" in s

    def test_recommend_dependencies_endpoint(self, client: TestClient):
        payload = {
            "tasks": [
                {"id": 1, "title": "Design Database Schema", "description": "Create SQL tables"},
                {"id": 2, "title": "Implement REST API Endpoints", "description": "Build backend routes"},
                {"id": 3, "title": "QA & Load Testing", "description": "Run performance tests"},
            ]
        }
        response = client.post("/api/v1/ai/recommend-dependencies", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert "recommendations" in data
        assert len(data["recommendations"]) > 0
        rec = data["recommendations"][0]
        assert "blocking_task_id" in rec
        assert "dependent_task_id" in rec

    def test_optimize_schedule_endpoint(self, client: TestClient):
        payload = {
            "tasks": [
                {"id": 1, "title": "Requirements", "duration_days": 2.0},
                {"id": 2, "title": "Core Build", "duration_days": 5.0},
                {"id": 3, "title": "Release", "duration_days": 1.0},
            ],
            "dependencies": [
                {"blocking_task_id": 1, "dependent_task_id": 2},
                {"blocking_task_id": 2, "dependent_task_id": 3},
            ]
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["is_cyclic"] is False
        assert data["project_duration_days"] == 8.0
        assert data["critical_path"] == [1, 2, 3]
        assert len(data["schedule"]) == 3

    def test_optimize_schedule_cycle_error(self, client: TestClient):
        payload = {
            "tasks": [
                {"id": 1, "duration_days": 1.0},
                {"id": 2, "duration_days": 1.0},
            ],
            "dependencies": [
                {"blocking_task_id": 1, "dependent_task_id": 2},
                {"blocking_task_id": 2, "dependent_task_id": 1},
            ]
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "error"
        assert data["is_cyclic"] is True
        assert data["critical_path"] == []



class TestScheduleOptimizationEndpoint:
    """Tests for the auto-scheduler dependency optimization endpoint."""

    def test_optimize_schedule_success(self, client: TestClient):
        payload = {
            "tasks": [
                {"id": 1, "title": "Design Database Schema", "duration_days": 1},
                {"id": 2, "title": "Implement API Endpoints", "duration_days": 2},
                {"id": 3, "title": "Deploy to Staging", "duration_days": 1},
            ],
            "dependencies": [
                {"blocking_task_id": 1, "dependent_task_id": 2},
                {"blocking_task_id": 2, "dependent_task_id": 3},
            ],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["is_cyclic"] is False
        assert data["project_duration_days"] == 4.0
        assert data["critical_path"] == [1, 2, 3]

    def test_optimize_schedule_empty_payload(self, client: TestClient):
        payload = {
            "tasks": [],
            "dependencies": [],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["project_duration_days"] == 0.0

    def test_optimize_schedule_missing_dependencies_field(self, client: TestClient):
        payload = {
            "tasks": [{"id": 1, "title": "Single Task", "duration_days": 2.0}],
        }
        response = client.post("/api/v1/ai/optimize-schedule", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert data["project_duration_days"] == 2.0

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
        assert response.headers.get("access-control-allow-origin") in ["*", "http://localhost"]
