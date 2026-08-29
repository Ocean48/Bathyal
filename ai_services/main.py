"""Bathyal Python AI Microservice Gateway (FastAPI).

Provides intelligent scheduling, Gemini 3.1 Flash-Lite autonomous assistance,
task breakdown, entity extraction, and Critical Path Method (CPM) leveling.
"""

import os
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from gemini_client import gemini_client
from scheduler import CPMScheduler

app = FastAPI(
    title="Bathyal AI Microservice",
    description="Autonomous AI Assistant & Scheduling Engine powered by Gemini 3.1 Flash-Lite",
    version="1.1.0",
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


# --- Request & Response Models ---

class TaskEstimateRequest(BaseModel):
    title: str
    description: Optional[str] = None
    priority: Optional[str] = "medium"


class TaskEstimateResponse(BaseModel):
    estimated_hours: float
    confidence: float
    suggested_subtasks: List[str] = Field(default_factory=list)
    source: Optional[str] = "heuristic"


class ParsePromptRequest(BaseModel):
    prompt: str


class ParsePromptResponse(BaseModel):
    title: str
    due_date: Optional[str] = None
    priority: str = "none"
    estimated_hours: Optional[float] = None
    project_name: Optional[str] = None
    tags: List[str] = Field(default_factory=list)
    source: str = "heuristic"


class DecomposeTaskRequest(BaseModel):
    title: str
    description: Optional[str] = None
    complexity: Optional[str] = "medium"


class DecomposeTaskResponse(BaseModel):
    parent_title: str
    complexity: str
    subtasks: List[Dict[str, Any]]
    total_estimated_hours: float
    source: str = "heuristic"


class RecommendDependenciesRequest(BaseModel):
    tasks: List[Dict[str, Any]]


class RecommendDependenciesResponse(BaseModel):
    recommendations: List[Dict[str, Any]]


class ScheduleOptimizationRequest(BaseModel):
    tasks: List[Dict[str, Any]]
    dependencies: Optional[List[Dict[str, Any]]] = None


class ScheduleOptimizationResponse(BaseModel):
    status: str
    is_cyclic: bool
    project_duration_days: float
    critical_path: List[int]
    schedule: List[Dict[str, Any]]
    message: str


# --- Endpoints ---

@app.get("/")
def root():
    return {
        "service": "Bathyal AI Microservice",
        "status": "healthy",
        "version": "1.1.0",
        "ai_engine": "Gemini 3.1 Flash-Lite",
        "model": os.getenv("GEMINI_MODEL", "gemini-3.1-flash-lite"),
        "gemini_connected": gemini_client.is_configured,
    }


@app.get("/api/v1/ai/health")
def health_check():
    return {
        "status": "healthy",
        "service": "bathyal-ai",
        "gemini_ready": gemini_client.is_configured,
        "model": os.getenv("GEMINI_MODEL", "gemini-3.1-flash-lite"),
        "environment": os.getenv("APP_ENV", "development"),
    }


@app.post("/api/v1/ai/estimate-task", response_model=TaskEstimateResponse)
async def estimate_task(request: TaskEstimateRequest):
    """Estimate task effort and suggest subtask breakdown via Gemini 3.1 Flash-Lite."""
    decomp = await gemini_client.decompose_task(request.title, request.description, request.priority or "medium")
    subtask_titles = [s.get("title", "") for s in decomp.get("subtasks", [])]
    total_hours = decomp.get("total_estimated_hours", 4.0)

    return TaskEstimateResponse(
        estimated_hours=total_hours,
        confidence=0.90 if decomp.get("source") == "gemini-3.1-flash-lite" else 0.85,
        suggested_subtasks=subtask_titles,
        source=decomp.get("source", "heuristic"),
    )


@app.post("/api/v1/ai/parse-prompt", response_model=ParsePromptResponse)
async def parse_prompt(request: ParsePromptRequest):
    """Parse unstructured task text into structured entities using Gemini 3.1 Flash-Lite."""
    if not request.prompt or not request.prompt.strip():
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Prompt text cannot be empty")

    result = await gemini_client.parse_prompt(request.prompt)
    return ParsePromptResponse(**result)


@app.post("/api/v1/ai/decompose-task", response_model=DecomposeTaskResponse)
async def decompose_task(request: DecomposeTaskRequest):
    """Break down a complex task into actionable subtasks with time estimates and priorities."""
    if not request.title or not request.title.strip():
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Task title cannot be empty")

    result = await gemini_client.decompose_task(request.title, request.description, request.complexity or "medium")
    return DecomposeTaskResponse(**result)


@app.post("/api/v1/ai/recommend-dependencies", response_model=RecommendDependenciesResponse)
async def recommend_dependencies(request: RecommendDependenciesRequest):
    """Suggest logical predecessor/successor task links based on project descriptions."""
    recommendations = await gemini_client.recommend_dependencies(request.tasks)
    return RecommendDependenciesResponse(recommendations=recommendations)


@app.post("/api/v1/ai/optimize-schedule", response_model=ScheduleOptimizationResponse)
def optimize_schedule(request: ScheduleOptimizationRequest):
    """Run Critical Path Method (CPM) and schedule leveling on task dependency networks."""
    deps = request.dependencies or []

    # If tasks contain inline dependencies, extract them
    extracted_deps = list(deps)
    for t in request.tasks:
        t_id = t.get("id")
        inline_deps = t.get("dependencies", [])
        for b_id in inline_deps:
            extracted_deps.append({"blocking_task_id": b_id, "dependent_task_id": t_id})

    cpm_result = CPMScheduler.calculate_cpm(request.tasks, extracted_deps)

    if cpm_result.get("is_cyclic"):
        return ScheduleOptimizationResponse(
            status="error",
            is_cyclic=True,
            project_duration_days=0.0,
            critical_path=[],
            schedule=[],
            message="Circular dependency detected in project graph. Schedule cannot be leveled.",
        )

    return ScheduleOptimizationResponse(
        status="success",
        is_cyclic=False,
        project_duration_days=cpm_result.get("project_duration_days", 0.0),
        critical_path=cpm_result.get("critical_path", []),
        schedule=cpm_result.get("schedule", []),
        message=f"Critical path computed: {len(cpm_result.get('critical_path', []))} tasks on critical path.",
    )

