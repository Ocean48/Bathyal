"""Critical Path Method (CPM) & Schedule Leveling Algorithms.

Computes topological order, forward pass (Early Start, Early Finish),
backward pass (Late Start, Late Finish), Total Slack, and Critical Path
for arbitrary directed acyclic task dependency graphs.
"""

from typing import Any, Dict, List, Set, Tuple


class CPMScheduler:
    @staticmethod
    def calculate_cpm(tasks: List[Dict[str, Any]], dependencies: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Run Critical Path Method forward and backward passes.

        Parameters:
            tasks: list of dicts with keys: 'id' (int), 'duration_days' (float or int), optional 'name'
            dependencies: list of dicts with keys: 'blocking_task_id' (int), 'dependent_task_id' (int)

        Returns:
            Dict containing:
                - 'is_cyclic': bool
                - 'project_duration_days': float
                - 'critical_path': list of task IDs
                - 'schedule': list of task schedule objects with ES, EF, LS, LF, slack, is_critical
        """
        if not tasks:
            return {
                "is_cyclic": False,
                "project_duration_days": 0.0,
                "critical_path": [],
                "schedule": [],
            }

        task_dict = {t["id"]: t for t in tasks}
        task_ids = list(task_dict.keys())

        # Build Adjacency and In-Degree maps
        adj: Dict[int, List[int]] = {tid: [] for tid in task_ids}
        rev_adj: Dict[int, List[int]] = {tid: [] for tid in task_ids}
        in_degree: Dict[int, int] = {tid: 0 for tid in task_ids}

        for dep in dependencies:
            u = dep.get("blocking_task_id")
            v = dep.get("dependent_task_id")
            if u in adj and v in adj:
                adj[u].append(v)
                rev_adj[v].append(u)
                in_degree[v] += 1

        # 1. Topological Sort (Kahn's algorithm)
        queue = [tid for tid in task_ids if in_degree[tid] == 0]
        topo_order: List[int] = []

        while queue:
            curr = queue.pop(0)
            topo_order.append(curr)
            for neighbor in adj[curr]:
                in_degree[neighbor] -= 1
                if in_degree[neighbor] == 0:
                    queue.append(neighbor)

        if len(topo_order) < len(task_ids):
            # Graph contains a cycle
            return {
                "is_cyclic": True,
                "project_duration_days": 0.0,
                "critical_path": [],
                "schedule": [],
                "error": "Circular dependency loop detected in task network",
            }

        # 2. Forward Pass: Early Start (ES) and Early Finish (EF)
        es: Dict[int, float] = {tid: 0.0 for tid in task_ids}
        ef: Dict[int, float] = {tid: 0.0 for tid in task_ids}

        for tid in topo_order:
            duration = float(task_dict[tid].get("duration_days", 1.0))
            if duration <= 0:
                duration = 1.0

            predecessors = rev_adj[tid]
            if predecessors:
                es[tid] = max(ef[p] for p in predecessors)
            else:
                es[tid] = 0.0

            ef[tid] = es[tid] + duration

        project_duration = max(ef.values()) if ef else 0.0

        # 3. Backward Pass: Late Finish (LF) and Late Start (LS)
        lf: Dict[int, float] = {tid: project_duration for tid in task_ids}
        ls: Dict[int, float] = {tid: project_duration for tid in task_ids}

        for tid in reversed(topo_order):
            duration = float(task_dict[tid].get("duration_days", 1.0))
            if duration <= 0:
                duration = 1.0

            successors = adj[tid]
            if successors:
                lf[tid] = min(ls[s] for s in successors)
            else:
                lf[tid] = project_duration

            ls[tid] = lf[tid] - duration

        # 4. Total Slack and Critical Path
        slack: Dict[int, float] = {}
        critical_path_set: Set[int] = set()

        for tid in task_ids:
            s = round(ls[tid] - es[tid], 3)
            slack[tid] = s
            if abs(s) < 0.001:
                critical_path_set.add(tid)

        schedule = []
        for tid in topo_order:
            t = task_dict[tid]
            schedule.append({
                "task_id": tid,
                "title": t.get("title") or t.get("name") or f"Task #{tid}",
                "duration_days": float(t.get("duration_days", 1.0)),
                "early_start_day": es[tid],
                "early_finish_day": ef[tid],
                "late_start_day": ls[tid],
                "late_finish_day": lf[tid],
                "total_slack_days": slack[tid],
                "is_critical": tid in critical_path_set,
            })

        critical_path = [tid for tid in topo_order if tid in critical_path_set]

        return {
            "is_cyclic": False,
            "project_duration_days": project_duration,
            "critical_path": critical_path,
            "schedule": schedule,
        }
