"""Unit tests for Critical Path Method (CPM) and Schedule Leveling algorithm."""

import pytest
from scheduler import CPMScheduler


def test_empty_tasks():
    result = CPMScheduler.calculate_cpm([], [])
    assert result["is_cyclic"] is False
    assert result["project_duration_days"] == 0.0
    assert result["critical_path"] == []
    assert result["schedule"] == []


def test_single_task():
    tasks = [{"id": 1, "title": "Single Task", "duration_days": 3.0}]
    result = CPMScheduler.calculate_cpm(tasks, [])
    assert result["is_cyclic"] is False
    assert result["project_duration_days"] == 3.0
    assert result["critical_path"] == [1]
    assert result["schedule"][0]["early_start_day"] == 0.0
    assert result["schedule"][0]["early_finish_day"] == 3.0
    assert result["schedule"][0]["total_slack_days"] == 0.0
    assert result["schedule"][0]["is_critical"] is True


def test_serial_dependency_chain():
    # 1 (2d) -> 2 (3d) -> 3 (1d) => Total = 6d
    tasks = [
        {"id": 1, "title": "Design", "duration_days": 2.0},
        {"id": 2, "title": "Develop", "duration_days": 3.0},
        {"id": 3, "title": "Deploy", "duration_days": 1.0},
    ]
    deps = [
        {"blocking_task_id": 1, "dependent_task_id": 2},
        {"blocking_task_id": 2, "dependent_task_id": 3},
    ]
    result = CPMScheduler.calculate_cpm(tasks, deps)
    assert result["is_cyclic"] is False
    assert result["project_duration_days"] == 6.0
    assert result["critical_path"] == [1, 2, 3]

    schedule_map = {s["task_id"]: s for s in result["schedule"]}
    assert schedule_map[1]["early_start_day"] == 0.0
    assert schedule_map[1]["early_finish_day"] == 2.0
    assert schedule_map[2]["early_start_day"] == 2.0
    assert schedule_map[2]["early_finish_day"] == 5.0
    assert schedule_map[3]["early_start_day"] == 5.0
    assert schedule_map[3]["early_finish_day"] == 6.0


def test_parallel_branches_critical_path():
    # Task 1 (1d)
    # Branches:
    # 1 -> 2 (4d) -> 4 (1d) => 1 + 4 + 1 = 6d (Critical)
    # 1 -> 3 (2d) -> 4 (1d) => 1 + 2 + 1 = 4d (Slack = 2d on task 3)
    tasks = [
        {"id": 1, "title": "Start", "duration_days": 1.0},
        {"id": 2, "title": "Heavy Work", "duration_days": 4.0},
        {"id": 3, "title": "Light Work", "duration_days": 2.0},
        {"id": 4, "title": "Finish", "duration_days": 1.0},
    ]
    deps = [
        {"blocking_task_id": 1, "dependent_task_id": 2},
        {"blocking_task_id": 1, "dependent_task_id": 3},
        {"blocking_task_id": 2, "dependent_task_id": 4},
        {"blocking_task_id": 3, "dependent_task_id": 4},
    ]
    result = CPMScheduler.calculate_cpm(tasks, deps)
    assert result["is_cyclic"] is False
    assert result["project_duration_days"] == 6.0
    assert result["critical_path"] == [1, 2, 4]

    schedule_map = {s["task_id"]: s for s in result["schedule"]}
    assert schedule_map[3]["is_critical"] is False
    assert schedule_map[3]["total_slack_days"] == 2.0
    assert schedule_map[2]["is_critical"] is True
    assert schedule_map[2]["total_slack_days"] == 0.0


def test_cycle_detection():
    # 1 -> 2 -> 3 -> 1 (Cycle)
    tasks = [
        {"id": 1, "title": "A", "duration_days": 1.0},
        {"id": 2, "title": "B", "duration_days": 1.0},
        {"id": 3, "title": "C", "duration_days": 1.0},
    ]
    deps = [
        {"blocking_task_id": 1, "dependent_task_id": 2},
        {"blocking_task_id": 2, "dependent_task_id": 3},
        {"blocking_task_id": 3, "dependent_task_id": 1},
    ]
    result = CPMScheduler.calculate_cpm(tasks, deps)
    assert result["is_cyclic"] is True
    assert "error" in result
    assert result["critical_path"] == []
