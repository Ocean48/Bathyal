/**
 * Theme & Accent Palette Manager
 */
import { store } from '../core/store.js';
import { storage } from '../core/storage.js';

export class ThemeManager {
    constructor() {
        this.palettes = {
            indigo: { primary: '#6366f1', hover: '#4f46e5', light: 'rgba(99, 102, 241, 0.15)' },
            ocean:  { primary: '#0ea5e9', hover: '#0284c7', light: 'rgba(14, 165, 233, 0.15)' },
            emerald:{ primary: '#10b981', hover: '#059669', light: 'rgba(16, 185, 129, 0.15)' },
            rose:   { primary: '#f43f5e', hover: '#e11d48', light: 'rgba(244, 63, 94, 0.15)' },
            amber:  { primary: '#f59e0b', hover: '#d97706', light: 'rgba(245, 158, 11, 0.15)' },
            violet: { primary: '#8b5cf6', hover: '#7c3aed', light: 'rgba(139, 92, 246, 0.15)' },
        };

        this.currentPalette = storage.get('accent_palette', 'indigo');
        this.currentMode = storage.get('theme_mode', 'dark');
        this.applyTheme(this.currentMode);
        this.applyPalette(this.currentPalette);
    }

    setMode(mode) {
        this.currentMode = mode;
        storage.set('theme_mode', mode);
        this.applyTheme(mode);
        store.setTheme(mode);
    }

    setPalette(paletteName) {
        if (!this.palettes[paletteName]) return;
        this.currentPalette = paletteName;
        storage.set('accent_palette', paletteName);
        this.applyPalette(paletteName);
    }

    toggleMode() {
        const next = this.currentMode === 'dark' ? 'light' : 'dark';
        this.setMode(next);
        return next;
    }

    applyTheme(mode) {
        document.documentElement.setAttribute('data-theme', mode);
    }

    applyPalette(paletteName) {
        const pal = this.palettes[paletteName] || this.palettes.indigo;
        document.documentElement.style.setProperty('--primary', pal.primary);
        document.documentElement.style.setProperty('--primary-hover', pal.hover);
        document.documentElement.style.setProperty('--primary-light', pal.light);
    }
}

export const themeManager = new ThemeManager();
