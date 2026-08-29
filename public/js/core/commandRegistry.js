/**
 * Command Registry for the Universal Command Palette
 */
class CommandRegistry {
    constructor() {
        this.commands = [];
        this.dynamicProviders = [];
    }

    register(command) {
        // command: { id, title, category, shortcut, handler, keywords }
        this.commands.push(command);
        return () => {
            this.commands = this.commands.filter(c => c.id !== command.id);
        };
    }

    registerProvider(providerFn) {
        // providerFn: async (query) => array of commands
        this.dynamicProviders.push(providerFn);
        return () => {
            this.dynamicProviders = this.dynamicProviders.filter(p => p !== providerFn);
        };
    }

    async search(query = '') {
        const q = query.trim().toLowerCase();
        let results = [];

        // 1. Filter static commands
        for (const cmd of this.commands) {
            const score = this.calculateScore(cmd, q);
            if (score > 0) {
                results.push({ ...cmd, score, isDynamic: false });
            }
        }

        // 2. Fetch from dynamic providers
        if (q.length > 0) {
            for (const provider of this.dynamicProviders) {
                try {
                    const dynResults = await provider(q);
                    if (Array.isArray(dynResults)) {
                        dynResults.forEach(cmd => {
                            const score = this.calculateScore(cmd, q);
                            if (score > 0) {
                                results.push({ ...cmd, score, isDynamic: true });
                            }
                        });
                    }
                } catch (e) {
                    console.warn('Error in dynamic command provider:', e);
                }
            }
        }

        // 3. Sort by score descending, then title ascending
        results.sort((a, b) => b.score - a.score || a.title.localeCompare(b.title));

        return results;
    }

    calculateScore(cmd, query) {
        if (!query) return 1;

        const title = (cmd.title || '').toLowerCase();
        const category = (cmd.category || '').toLowerCase();
        const keywords = (cmd.keywords || []).map(k => k.toLowerCase()).join(' ');
        const fullSearchTarget = `${title} ${category} ${keywords}`;

        // Exact match in title
        if (title === query) return 100;

        // Prefix match in title
        if (title.startsWith(query)) return 80;

        // Substring in title
        if (title.includes(query)) return 60;

        // Substring in keywords or category
        if (fullSearchTarget.includes(query)) return 40;

        // Fuzzy sequential matching
        let tIdx = 0;
        let matched = 0;
        for (let i = 0; i < query.length; i++) {
            const char = query[i];
            const foundIdx = title.indexOf(char, tIdx);
            if (foundIdx !== -1) {
                matched++;
                tIdx = foundIdx + 1;
            }
        }

        if (matched === query.length) {
            return 20;
        }

        return 0;
    }
}

export const commandRegistry = new CommandRegistry();
