/**
 * Global Toast Notifications
 */
class ToastManager {
    constructor() {
        this.container = null;
    }

    ensureContainer() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }

    show(message, type = 'info', duration = 3500) {
        this.ensureContainer();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content" style="flex: 1;">${this.escapeHtml(message)}</div>
            <button class="btn btn-ghost btn-sm" style="padding: 2px 6px; font-size: 11px;">✕</button>
        `;

        const closeBtn = toast.querySelector('button');
        closeBtn.onclick = () => this.dismiss(toast);

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => this.dismiss(toast), duration);
        }
    }

    dismiss(toast) {
        if (toast && toast.parentNode) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.2s ease';
            setTimeout(() => toast.remove(), 200);
        }
    }

    success(message) {
        this.show(message, 'success');
    }

    error(message) {
        this.show(message, 'error', 5000);
    }

    info(message) {
        this.show(message, 'info');
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export const toast = new ToastManager();
