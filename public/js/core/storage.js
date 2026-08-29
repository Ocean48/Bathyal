/**
 * Client Storage & Cache Helper
 * Supports LocalStorage with TTL expiry and basic offline fallback caching.
 */
class StorageManager {
    constructor(prefix = 'bathyal_') {
        this.prefix = prefix;
    }

    getKey(key) {
        return `${this.prefix}${key}`;
    }

    set(key, value, ttlSeconds = null) {
        try {
            const item = {
                value,
                timestamp: Date.now(),
                expiry: ttlSeconds ? Date.now() + (ttlSeconds * 1000) : null,
            };
            localStorage.setItem(this.getKey(key), JSON.stringify(item));
            return true;
        } catch (e) {
            console.warn('Storage set failed:', e);
            return false;
        }
    }

    get(key, defaultValue = null) {
        try {
            const raw = localStorage.getItem(this.getKey(key));
            if (!raw) return defaultValue;

            const item = JSON.parse(raw);
            if (!item || typeof item !== 'object') return defaultValue;

            if (item.expiry && Date.now() > item.expiry) {
                this.remove(key);
                return defaultValue;
            }

            return item.value !== undefined ? item.value : defaultValue;
        } catch (e) {
            console.warn('Storage get failed:', e);
            return defaultValue;
        }
    }

    remove(key) {
        try {
            localStorage.removeItem(this.getKey(key));
        } catch (e) {
            console.warn('Storage remove failed:', e);
        }
    }

    clear() {
        try {
            Object.keys(localStorage).forEach(k => {
                if (k.startsWith(this.prefix)) {
                    localStorage.removeItem(k);
                }
            });
        } catch (e) {
            console.warn('Storage clear failed:', e);
        }
    }
}

export const storage = new StorageManager();
