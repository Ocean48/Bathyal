/**
 * Reactive State Store with Subscriptions
 */
import { eventBus } from './eventBus.js';

class Store {
    constructor() {
        this.state = {
            currentUser: null,
            workspaces: [],
            activeWorkspaceId: null,
            activeMode: localStorage.getItem('bathyal_mode') || 'simple',
            activeView: 'inbox', // inbox, today, upcoming, completed
            tasks: [],
            statuses: [],
            isLoading: false,
            theme: localStorage.getItem('bathyal_theme') || 'dark',
        };
        this.listeners = new Map();
    }

    getState() {
        return this.state;
    }

    setState(updates) {
        const prevState = { ...this.state };
        this.state = { ...this.state, ...updates };

        Object.keys(updates).forEach(key => {
            if (prevState[key] !== this.state[key]) {
                this.notify(key, this.state[key], prevState[key]);
            }
        });

        eventBus.emit('state:changed', { state: this.state, updates, prevState });
    }

    subscribe(key, callback) {
        if (!this.listeners.has(key)) {
            this.listeners.set(key, new Set());
        }
        this.listeners.get(key).add(callback);
        return () => this.listeners.get(key).delete(callback);
    }

    notify(key, newValue, oldValue) {
        if (this.listeners.has(key)) {
            this.listeners.get(key).forEach(cb => cb(newValue, oldValue));
        }
    }

    setMode(mode) {
        localStorage.setItem('bathyal_mode', mode);
        this.setState({ activeMode: mode });
    }

    setTheme(theme) {
        localStorage.setItem('bathyal_theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        this.setState({ theme });
    }
}

export const store = new Store();
