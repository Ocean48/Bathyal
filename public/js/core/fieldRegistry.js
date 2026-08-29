/**
 * Custom Field Widget Registry
 * Provides standardized rendering and inline editor generation for custom fields.
 */
class FieldWidgetRegistry {
    constructor() {
        this.widgets = new Map();
        this.registerBuiltIns();
    }

    register(type, widgetDefinition) {
        this.widgets.set(type, widgetDefinition);
    }

    get(type) {
        return this.widgets.get(type) || this.widgets.get('text');
    }

    render(type, value, options = null) {
        const widget = this.get(type);
        return widget.render(value, options);
    }

    createEditor(type, value, options = null, onSave = () => {}) {
        const widget = this.get(type);
        return widget.createEditor(value, options, onSave);
    }

    registerBuiltIns() {
        // 1. Text
        this.register('text', {
            render: (val) => {
                if (val === null || val === undefined || val === '') {
                    return '<span class="field-empty">-</span>';
                }
                return `<span class="field-val-text">${this.escapeHtml(String(val))}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'field-inline-input';
                input.value = val !== null && val !== undefined ? String(val) : '';
                input.onblur = () => onSave(input.value.trim());
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') input.blur();
                    if (e.key === 'Escape') onSave(val);
                };
                return input;
            }
        });

        // 2. Number
        this.register('number', {
            render: (val) => {
                if (val === null || val === undefined || val === '') {
                    return '<span class="field-empty">-</span>';
                }
                return `<span class="field-val-number">${Number(val).toLocaleString()}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const input = document.createElement('input');
                input.type = 'number';
                input.step = 'any';
                input.className = 'field-inline-input';
                input.value = val !== null && val !== undefined ? String(val) : '';
                input.onblur = () => {
                    const num = input.value !== '' ? parseFloat(input.value) : null;
                    onSave(num);
                };
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') input.blur();
                    if (e.key === 'Escape') onSave(val);
                };
                return input;
            }
        });

        // 3. Select
        this.register('select', {
            render: (val) => {
                if (!val) return '<span class="field-empty">-</span>';
                return `<span class="badge-tag field-tag-select">${this.escapeHtml(String(val))}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const select = document.createElement('select');
                select.className = 'field-inline-select';
                
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = 'None';
                select.appendChild(defaultOpt);

                const choices = Array.isArray(opts) ? opts : [];
                choices.forEach(opt => {
                    const optionEl = document.createElement('option');
                    optionEl.value = opt;
                    optionEl.textContent = opt;
                    if (opt === val) optionEl.selected = true;
                    select.appendChild(optionEl);
                });

                select.onchange = () => onSave(select.value || null);
                select.onblur = () => onSave(select.value || null);
                return select;
            }
        });

        // 4. Multi-Select
        this.register('multi_select', {
            render: (val) => {
                const items = Array.isArray(val) ? val : [];
                if (items.length === 0) return '<span class="field-empty">-</span>';
                return items.map(item => `<span class="badge-tag field-tag-multiselect">${this.escapeHtml(String(item))}</span>`).join(' ');
            },
            createEditor: (val, opts, onSave) => {
                const container = document.createElement('div');
                container.className = 'field-multiselect-editor';
                const selected = new Set(Array.isArray(val) ? val : []);
                const choices = Array.isArray(opts) ? opts : [];

                choices.forEach(choice => {
                    const label = document.createElement('label');
                    label.className = 'field-multiselect-item';
                    const chk = document.createElement('input');
                    chk.type = 'checkbox';
                    chk.checked = selected.has(choice);
                    chk.onchange = () => {
                        if (chk.checked) selected.add(choice);
                        else selected.delete(choice);
                        onSave(Array.from(selected));
                    };
                    label.appendChild(chk);
                    label.appendChild(document.createTextNode(` ${choice}`));
                    container.appendChild(label);
                });
                return container;
            }
        });

        // 5. Date
        this.register('date', {
            render: (val) => {
                if (!val) return '<span class="field-empty">-</span>';
                const d = new Date(val);
                return `<span class="field-val-date">${isNaN(d.getTime()) ? this.escapeHtml(String(val)) : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const input = document.createElement('input');
                input.type = 'date';
                input.className = 'field-inline-input';
                if (val) {
                    const d = new Date(val);
                    if (!isNaN(d.getTime())) {
                        input.value = d.toISOString().split('T')[0];
                    }
                }
                input.onblur = () => onSave(input.value || null);
                input.onchange = () => onSave(input.value || null);
                return input;
            }
        });

        // 6. Checkbox
        this.register('checkbox', {
            render: (val) => {
                const checked = Boolean(val);
                return `<span class="field-val-checkbox ${checked ? 'checked' : ''}">${checked ? '[x]' : '[ ]'}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const chk = document.createElement('input');
                chk.type = 'checkbox';
                chk.checked = Boolean(val);
                chk.onchange = () => onSave(chk.checked ? 1 : 0);
                return chk;
            }
        });

        // 7. User
        this.register('user', {
            render: (val) => {
                if (!val) return '<span class="field-empty">-</span>';
                return `<span class="badge-tag field-tag-user">User #${val}</span>`;
            },
            createEditor: (val, opts, onSave) => {
                const input = document.createElement('input');
                input.type = 'number';
                input.placeholder = 'User ID';
                input.className = 'field-inline-input';
                input.value = val || '';
                input.onblur = () => onSave(input.value ? parseInt(input.value, 10) : null);
                return input;
            }
        });

        // 8. URL
        this.register('url', {
            render: (val) => {
                if (!val) return '<span class="field-empty">-</span>';
                const safeUrl = this.escapeHtml(String(val));
                return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="field-val-url">${safeUrl}</a>`;
            },
            createEditor: (val, opts, onSave) => {
                const input = document.createElement('input');
                input.type = 'url';
                input.placeholder = 'https://...';
                input.className = 'field-inline-input';
                input.value = val || '';
                input.onblur = () => onSave(input.value.trim() || null);
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') input.blur();
                    if (e.key === 'Escape') onSave(val);
                };
                return input;
            }
        });

        // 9. Formula
        this.register('formula', {
            render: (val) => {
                if (val === null || val === undefined || val === '') {
                    return '<span class="field-empty">-</span>';
                }
                return `<span class="field-val-formula">${this.escapeHtml(String(val))}</span>`;
            },
            createEditor: (val) => {
                const span = document.createElement('span');
                span.className = 'field-empty';
                span.textContent = 'Formula fields are computed automatically';
                return span;
            }
        });
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export const fieldRegistry = new FieldWidgetRegistry();
