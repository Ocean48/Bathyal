"""Gemini 3.1 Flash-Lite AI Client Wrapper.

Provides structured asynchronous calls to the Gemini 3.1 Flash-Lite model (gemini-3.1-flash-lite)
using the Google GenAI SDK and HTTP fallback, with automated local heuristic fallback when
an API key is not supplied or network connectivity is unavailable.
"""

import json
import os
import re
from typing import Any, Dict, List, Optional
import httpx


class GeminiClient:
    def __init__(self):
        self.api_key = os.getenv("GEMINI_API_KEY", "").strip()
        self.model = os.getenv("GEMINI_MODEL", "gemini-3.1-flash-lite")
        self.base_url = "https://generativelanguage.googleapis.com/v1beta"

    @property
    def is_configured(self) -> bool:
        return bool(self.api_key)

    async def generate_json(self, prompt: str, system_instruction: Optional[str] = None) -> Optional[Dict[str, Any]]:
        """Call Gemini 3.1 Flash-Lite with structured JSON response."""
        if not self.is_configured:
            return None

        url = f"{self.base_url}/models/{self.model}:generateContent?key={self.api_key}"

        contents = [{"role": "user", "parts": [{"text": prompt}]}]
        body: Dict[str, Any] = {
            "contents": contents,
            "generationConfig": {
                "responseMimeType": "application/json",
                "temperature": 0.2,
            },
        }

        if system_instruction:
            body["systemInstruction"] = {
                "parts": [{"text": system_instruction}]
            }

        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                response = await client.post(url, json=body)
                if response.status_code == 200:
                    data = response.json()
                    candidates = data.get("candidates", [])
                    if candidates:
                        content_parts = candidates[0].get("content", {}).get("parts", [])
                        if content_parts:
                            raw_text = content_parts[0].get("text", "")
                            # Clean markdown if enclosed
                            clean_json = re.sub(r"^```json\s*", "", raw_text.strip(), flags=re.MULTILINE)
                            clean_json = re.sub(r"```$", "", clean_json.strip(), flags=re.MULTILINE)
                            return json.loads(clean_json)
        except Exception as e:
            # Fallback to local heuristics on any network / key failure
            print(f"[GeminiClient] Fallback to heuristics due to error: {e}")

        return None

    # -------------------------------------------------------------
    # 1. Natural Language Prompt Parsing
    # -------------------------------------------------------------
    async def parse_prompt(self, text: str) -> Dict[str, Any]:
        """Extract structured task metadata from natural language prompt."""
        system_prompt = (
            "You are an expert project management AI. Parse natural language task requests into structured JSON. "
            "Extract: title (clean title without metadata tags), due_date (YYYY-MM-DD or relative description), "
            "priority ('urgent', 'high', 'medium', 'low', 'none'), estimated_hours (float or null), "
            "project_name (string or null), and tags (list of string hashtags/labels)."
        )
        prompt = f"Parse this task prompt: \"{text}\""

        gemini_result = await self.generate_json(prompt, system_prompt)
        if gemini_result and isinstance(gemini_result, dict):
            return {
                "title": gemini_result.get("title") or text,
                "due_date": gemini_result.get("due_date"),
                "priority": gemini_result.get("priority", "none"),
                "estimated_hours": gemini_result.get("estimated_hours"),
                "project_name": gemini_result.get("project_name"),
                "tags": gemini_result.get("tags", []),
                "source": "gemini-3.1-flash-lite",
            }

        # Local heuristic fallback
        return self._heuristic_parse_prompt(text)

    def _heuristic_parse_prompt(self, text: str) -> Dict[str, Any]:
        input_str = text
        priority = "none"
        estimated_hours = None
        due_date = None
        tags = []

        # Priority !urgent, !high, etc.
        prio_match = re.search(r"!(urgent|high|medium|low)", input_str, re.IGNORECASE)
        if prio_match:
            priority = prio_match.group(1).lower()
            input_str = re.sub(r"!(urgent|high|medium|low)", "", input_str, flags=re.IGNORECASE)

        # Hours ~4h, ~2.5h, 4hrs
        hours_match = re.search(r"~?(\d+(?:\.\d+)?)\s*(?:h|hrs|hours)", input_str, re.IGNORECASE)
        if hours_match:
            estimated_hours = float(hours_match.group(1))
            input_str = re.sub(r"~?(\d+(?:\.\d+)?)\s*(?:h|hrs|hours)", "", input_str, flags=re.IGNORECASE)

        # Tags #dev, #frontend
        tags = re.findall(r"#([a-zA-Z0-9_-]+)", input_str)
        input_str = re.sub(r"#[a-zA-Z0-9_-]+", "", input_str)

        # Dates @today, @tomorrow, @friday
        date_match = re.search(r"@(today|tomorrow|mon|tue|wed|thu|fri|sat|sun)", input_str, re.IGNORECASE)
        if date_match:
            due_date = date_match.group(1).lower()
            input_str = re.sub(r"@(today|tomorrow|mon|tue|wed|thu|fri|sat|sun)", "", input_str, flags=re.IGNORECASE)

        clean_title = " ".join(input_str.split()).strip()

        return {
            "title": clean_title or text,
            "due_date": due_date,
            "priority": priority,
            "estimated_hours": estimated_hours,
            "project_name": None,
            "tags": tags,
            "source": "heuristic",
        }

    # -------------------------------------------------------------
    # 2. Contextual Task Decomposition
    # -------------------------------------------------------------
    async def decompose_task(self, title: str, description: Optional[str] = None, complexity: str = "medium") -> Dict[str, Any]:
        """Decompose a high-level task into actionable subtasks with estimates and priorities."""
        system_prompt = (
            "You are an agile technical project lead. Break down a complex project task into 3 to 6 logical, "
            "actionable subtasks. Return JSON with 'subtasks' array where each item has 'title', 'estimated_hours' (number), "
            "and 'priority' ('low', 'medium', 'high', 'urgent'). Also provide 'total_estimated_hours'."
        )
        prompt = f"Task Title: {title}\nTask Description: {description or 'N/A'}\nComplexity Level: {complexity}"

        gemini_result = await self.generate_json(prompt, system_prompt)
        if gemini_result and "subtasks" in gemini_result:
            subtasks = gemini_result.get("subtasks", [])
            total_hours = sum(s.get("estimated_hours", 0) for s in subtasks)
            return {
                "parent_title": title,
                "complexity": complexity,
                "subtasks": subtasks,
                "total_estimated_hours": round(total_hours, 1),
                "source": "gemini-3.1-flash-lite",
            }

        # Local heuristic fallback
        return self._heuristic_decompose_task(title, complexity)

    def _heuristic_decompose_task(self, title: str, complexity: str) -> Dict[str, Any]:
        multiplier = 1.0
        if complexity == "high" or complexity == "urgent":
            multiplier = 2.0
        elif complexity == "low":
            multiplier = 0.5

        subtasks = [
            {"title": f"Requirements & architectural planning for {title}", "estimated_hours": 2.0 * multiplier, "priority": "medium"},
            {"title": f"Core implementation of {title}", "estimated_hours": 4.5 * multiplier, "priority": "high"},
            {"title": f"Unit & integration tests for {title}", "estimated_hours": 2.0 * multiplier, "priority": "medium"},
            {"title": f"Code review & deployment of {title}", "estimated_hours": 1.0 * multiplier, "priority": "low"},
        ]

        total_hours = sum(s["estimated_hours"] for s in subtasks)

        return {
            "parent_title": title,
            "complexity": complexity,
            "subtasks": subtasks,
            "total_estimated_hours": round(total_hours, 1),
            "source": "heuristic",
        }

    # -------------------------------------------------------------
    # 3. Dependency Recommendation Engine
    # -------------------------------------------------------------
    async def recommend_dependencies(self, tasks: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Analyze a list of tasks and recommend logical predecessor -> successor dependency links."""
        if len(tasks) < 2:
            return []

        system_prompt = (
            "Analyze project tasks and suggest logical dependencies (which task blocks which). "
            "Return JSON with 'recommendations' array: [{'blocking_task_id': int, 'dependent_task_id': int, 'reason': str, 'confidence': float}]."
        )
        task_summary = [{"id": t.get("id"), "title": t.get("title"), "description": t.get("description")} for t in tasks]
        prompt = f"Tasks to evaluate for dependencies: {json.dumps(task_summary)}"

        gemini_result = await self.generate_json(prompt, system_prompt)
        if gemini_result and "recommendations" in gemini_result:
            return gemini_result.get("recommendations", [])

        # Local heuristic keyword match
        return self._heuristic_recommend_dependencies(tasks)

    def _heuristic_recommend_dependencies(self, tasks: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        recommendations = []
        # Patterns: Design -> Implement -> Test -> Deploy
        stages = ["research", "design", "wireframe", "api", "database", "schema", "implement", "build", "frontend", "test", "qa", "deploy", "release"]

        for i, t1 in enumerate(tasks):
            t1_title = (t1.get("title") or "").lower()
            for j, t2 in enumerate(tasks):
                if i == j:
                    continue
                t2_title = (t2.get("title") or "").lower()

                # Check stage sequencing
                t1_stage = next((idx for idx, s in enumerate(stages) if s in t1_title), None)
                t2_stage = next((idx for idx, s in enumerate(stages) if s in t2_title), None)

                if t1_stage is not None and t2_stage is not None and t1_stage < t2_stage:
                    recommendations.append({
                        "blocking_task_id": t1.get("id"),
                        "dependent_task_id": t2.get("id"),
                        "reason": f"'{t1.get('title')}' is a prerequisite for '{t2.get('title')}'",
                        "confidence": 0.80,
                    })

        return recommendations[:5]  # top 5 recommendations


gemini_client = GeminiClient()
