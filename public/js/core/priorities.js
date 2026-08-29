/**
 * Centralized Priority Definitions & Color Mapping for Bathyal
 */

export const PRIORITIES = {
    urgent: {
        id: 'urgent',
        label: 'Urgent',
        color: '#ef4444',
        barColor: '#ef4444',
        badgeClass: 'prio-urgent',
        rank: 4,
    },
    high: {
        id: 'high',
        label: 'High',
        color: '#f59e0b',
        barColor: '#f59e0b',
        badgeClass: 'prio-high',
        rank: 3,
    },
    medium: {
        id: 'medium',
        label: 'Medium',
        color: '#0ea5e9',
        barColor: '#0ea5e9',
        badgeClass: 'prio-medium',
        rank: 2,
    },
    low: {
        id: 'low',
        label: 'Low',
        color: '#64748b',
        barColor: '#64748b',
        badgeClass: 'prio-low',
        rank: 1,
    },
    none: {
        id: 'none',
        label: 'None',
        color: 'transparent',
        barColor: null,
        badgeClass: 'prio-none',
        rank: 0,
    },
};

export function getPriority(prioKey) {
    const key = (prioKey || 'none').toLowerCase();
    return PRIORITIES[key] || PRIORITIES.none;
}

export function getPriorityColor(prioKey) {
    return getPriority(prioKey).color;
}

export function getPriorityBarColor(prioKey) {
    const p = getPriority(prioKey);
    return p.barColor || null;
}

export function renderPriorityBadge(prioKey) {
    const p = getPriority(prioKey);
    if (p.id === 'none') return '';
    return `<span class="badge-prio ${p.badgeClass}">${p.label}</span>`;
}
