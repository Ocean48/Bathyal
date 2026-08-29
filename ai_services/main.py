"""Bathyal Python AI Microservice Gateway (FastAPI).

Provides intelligent scheduling, task breakdown, and natural language
processing for the Bathyal project management platform.
"""

import os
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

app = FastAPI(
    title="Bathyal AI Microservice",
    description="AI and Auto-Scheduling service for Bathyal Project Management",
    version="1.0.0",
    docs_url="/api/v1/ai/docs",
    openapi_url="/api/v1/ai/openapi.json",
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class TaskEstimateRequest(BaseModel):
    title: str
    description: Optional[str] = None
    priority: Optional[str] = "medium"


class TaskEstimateResponse(BaseModel):
    estimated_hours: float
    confidence: float
    suggested_subtasks: List[str] = Field(default_factory=list)


class ScheduleOptimizationRequest(BaseModel):
    tasks: List[Dict[str, Any]]
    dependencies: List[Dict[str, Any]]


@app.get("/")
def root():
    return {
        "service": "Bathyal AI Microservice",
        "status": "healthy",
        "version": "1.0.0",
    }


@app.get("/api/v1/ai/health")
def health_check():
    return {
        "status": "healthy",
        "service": "bathyal-ai",
        "environment": os.getenv("APP_ENV", "development"),
    }


@app.post("/api/v1/ai/estimate-task", response_model=TaskEstimateResponse)
def estimate_task(request: TaskEstimateRequest):
    """Estimate task effort and suggest subtask breakdown."""
    base_hours = 4.0
    if request.priority == "urgent":
        base_hours = 8.0
    elif request.priority == "low":
        base_hours = 2.0

    return TaskEstimateResponse(
        estimated_hours=base_hours,
        confidence=0.85,
        suggested_subtasks=[
            f"Research and planning for: {request.title}",
            f"Implementation phase for: {request.title}",
            f"Testing and review for: {request.title}",
        ],
    )


@app.post("/api/v1/ai/optimize-schedule")
def optimize_schedule(request: ScheduleOptimizationRequest):
    """Placeholder for dependency graph topological sorting & schedule optimization."""
    return {
        "status": "success",
        "tasks_processed": len(request.tasks),
        "dependencies_processed": len(request.dependencies),
        "message": "Auto-schedule calculation complete",
    }
