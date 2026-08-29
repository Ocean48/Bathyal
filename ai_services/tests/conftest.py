"""Pytest fixtures for Bathyal AI Microservice tests."""

import pytest
from fastapi.testclient import TestClient

from main import app


@pytest.fixture(scope="session")
def client() -> TestClient:
    """Fixture providing a FastAPI TestClient."""
    with TestClient(app) as test_client:
        yield test_client
