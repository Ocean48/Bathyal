/**
 * Pluggable Block Registry for Slash-Command Document Editor
 */
class BlockRegistry {
    constructor() {
        this.blockTypes = new Map();
        this.registerBuiltIns();
    }

    register(type, definition) {
        // definition: { label, icon, render(blockData), createDefault(), parseHtml(el) }
        this.blockTypes.set(type, definition);
    }

    get(type) {
        return this.blockTypes.get(type) || this.blockTypes.get('paragraph');
    }

    getAll() {
        const list = [];
        this.blockTypes.forEach((def, type) => {
            list.push({ type, ...def });
        });
        return list;
    }

    registerBuiltIns() {
        this.register('paragraph', {
            label: 'Text',
            description: 'Plain text paragraph',
            icon: '¶',
            render: (data) => `<p class="doc-block doc-p" contenteditable="true" data-type="paragraph">${data.text || ''}</p>`,
            createDefault: () => ({ type: 'paragraph', text: '' }),
        });

        this.register('heading1', {
            label: 'Heading 1',
            description: 'Large page title heading',
            icon: 'H1',
            render: (data) => `<h1 class="doc-block doc-h1" contenteditable="true" data-type="heading1">${data.text || ''}</h1>`,
            createDefault: () => ({ type: 'heading1', text: '' }),
        });

        this.register('heading2', {
            label: 'Heading 2',
            description: 'Medium section heading',
            icon: 'H2',
            render: (data) => `<h2 class="doc-block doc-h2" contenteditable="true" data-type="heading2">${data.text || ''}</h2>`,
            createDefault: () => ({ type: 'heading2', text: '' }),
        });

        this.register('heading3', {
            label: 'Heading 3',
            description: 'Small subsection heading',
            icon: 'H3',
            render: (data) => `<h3 class="doc-block doc-h3" contenteditable="true" data-type="heading3">${data.text || ''}</h3>`,
            createDefault: () => ({ type: 'heading3', text: '' }),
        });

        this.register('bullet', {
            label: 'Bullet List',
            description: 'Simple bullet list item',
            icon: '•',
            render: (data) => `<div class="doc-block doc-bullet" data-type="bullet"><span class="bullet-dot">•</span><div class="doc-block-content" contenteditable="true">${data.text || ''}</div></div>`,
            createDefault: () => ({ type: 'bullet', text: '' }),
        });

        this.register('checklist', {
            label: 'To-do List',
            description: 'Task item with a checkbox',
            icon: '☑',
            render: (data) => `
                <div class="doc-block doc-checklist ${data.checked ? 'checked' : ''}" data-type="checklist">
                    <input type="checkbox" class="doc-chk" ${data.checked ? 'checked' : ''} />
                    <div class="doc-block-content" contenteditable="true">${data.text || ''}</div>
                </div>
            `,
            createDefault: () => ({ type: 'checklist', text: '', checked: false }),
        });

        this.register('code', {
            label: 'Code Block',
            description: 'Code snippet with monospace font',
            icon: '<>',
            render: (data) => `<pre class="doc-block doc-code" data-type="code"><code contenteditable="true">${data.text || ''}</code></pre>`,
            createDefault: () => ({ type: 'code', text: '' }),
        });

        this.register('callout', {
            label: 'Callout Note',
            description: 'Highlighted callout box',
            icon: '💡',
            render: (data) => `
                <div class="doc-block doc-callout" data-type="callout">
                    <span class="callout-icon">💡</span>
                    <div class="doc-block-content" contenteditable="true">${data.text || ''}</div>
                </div>
            `,
            createDefault: () => ({ type: 'callout', text: '' }),
        });

        this.register('quote', {
            label: 'Quote',
            description: 'Blockquote styling',
            icon: '“',
            render: (data) => `<blockquote class="doc-block doc-quote" contenteditable="true" data-type="quote">${data.text || ''}</blockquote>`,
            createDefault: () => ({ type: 'quote', text: '' }),
        });

        this.register('divider', {
            label: 'Divider',
            description: 'Visual horizontal line',
            icon: '―',
            render: () => `<div class="doc-block doc-divider" data-type="divider"><hr /></div>`,
            createDefault: () => ({ type: 'divider' }),
        });
    }
}

export const blockRegistry = new BlockRegistry();
