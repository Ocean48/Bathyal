/**
 * Dedicated Authentication Modal Component (Sign In & Create Account)
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class AuthModal {
    constructor() {
        this.backdrop = null;
        this.isOpen = false;
        this.activeTab = 'login'; // 'login' or 'register'
    }

    open(defaultTab = 'login') {
        this.activeTab = defaultTab;
        if (this.isOpen && this.backdrop) {
            this.updateTabUI();
            return;
        }
        this.isOpen = true;
        this.render();
    }

    close() {
        this.isOpen = false;
        if (this.backdrop) {
            this.backdrop.remove();
            this.backdrop = null;
        }
    }

    render() {
        if (this.backdrop) {
            this.backdrop.remove();
        }

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.id = 'auth-modal-backdrop';

        this.backdrop.innerHTML = `
            <div class="modal auth-modal-card" style="max-width: 440px;">
                <div class="auth-modal-header">
                    <div style="display: flex; align-items: center; gap: 0.6rem; justify-content: center; margin-bottom: 0.5rem;">
                        <div class="logo-icon" style="width: 32px; height: 32px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12c3-3 6-3 9 0s6 3 9 0 3-3 4-3"/></svg>
                        </div>
                        <span class="brand-title" style="font-size: 1.3rem;">Bathyal</span>
                    </div>
                    <div class="auth-tabs">
                        <button class="auth-tab ${this.activeTab === 'login' ? 'active' : ''}" id="tab-btn-login">Sign In</button>
                        <button class="auth-tab ${this.activeTab === 'register' ? 'active' : ''}" id="tab-btn-register">Create Account</button>
                    </div>
                </div>

                <div class="auth-modal-body" id="auth-form-container">
                    ${this.renderForm()}
                </div>

                <div class="auth-modal-footer">
                    <span style="font-size: 0.75rem; color: var(--text-subtle);">Quick Demo Logins:</span>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 0.35rem;">
                        <button class="btn btn-sm btn-secondary" id="btn-quick-admin">Admin Account</button>
                        <button class="btn btn-sm btn-secondary" id="btn-quick-demo">Demo User</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);
        this.bindEvents();
    }

    renderForm() {
        if (this.activeTab === 'register') {
            return `
                <form id="auth-register-form" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div>
                        <label class="auth-label">Full Name</label>
                        <input type="text" class="quick-add-input auth-input" id="reg-name" placeholder="Alex Morgan" required autocomplete="name" />
                    </div>
                    <div>
                        <label class="auth-label">Email Address</label>
                        <input type="email" class="quick-add-input auth-input" id="reg-email" placeholder="alex@company.com" required autocomplete="email" />
                    </div>
                    <div>
                        <label class="auth-label">Password (min 6 characters)</label>
                        <input type="password" class="quick-add-input auth-input" id="reg-password" placeholder="••••••••" required minlength="6" autocomplete="new-password" />
                    </div>
                    <div>
                        <label class="auth-label">Starting Workspace Mode</label>
                        <select class="field-inline-select" id="reg-mode" style="width: 100%; padding: 0.45rem;">
                            <option value="simple">Simple Mode (Zero-friction to-do list)</option>
                            <option value="enterprise">Enterprise Mode (Workspaces &amp; Gantt)</option>
                        </select>
                    </div>
                    <div id="auth-error-msg" class="auth-error-box" style="display: none;"></div>
                    <button type="submit" class="btn btn-primary" id="btn-submit-register" style="margin-top: 0.5rem; padding: 0.6rem;">
                        Create Account &amp; Sign In
                    </button>
                </form>
            `;
        }

        // Login Form
        return `
            <form id="auth-login-form" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div>
                    <label class="auth-label">Email Address</label>
                    <input type="email" class="quick-add-input auth-input" id="login-email" placeholder="demo@bathyal.local" value="demo@bathyal.local" required autocomplete="email" />
                </div>
                <div>
                    <label class="auth-label">Password</label>
                    <input type="password" class="quick-add-input auth-input" id="login-password" placeholder="••••••••" value="password123" required autocomplete="current-password" />
                </div>
                <div id="auth-error-msg" class="auth-error-box" style="display: none;"></div>
                <button type="submit" class="btn btn-primary" id="btn-submit-login" style="margin-top: 0.5rem; padding: 0.6rem;">
                    Sign In
                </button>
            </form>
        `;
    }

    updateTabUI() {
        const formContainer = this.backdrop.querySelector('#auth-form-container');
        const loginTab = this.backdrop.querySelector('#tab-btn-login');
        const regTab = this.backdrop.querySelector('#tab-btn-register');

        if (loginTab && regTab) {
            loginTab.classList.toggle('active', this.activeTab === 'login');
            regTab.classList.toggle('active', this.activeTab === 'register');
        }

        if (formContainer) {
            formContainer.innerHTML = this.renderForm();
            this.bindFormEvents();
        }
    }

    bindEvents() {
        const loginTab = this.backdrop.querySelector('#tab-btn-login');
        const regTab = this.backdrop.querySelector('#tab-btn-register');

        if (loginTab) {
            loginTab.onclick = () => {
                this.activeTab = 'login';
                this.updateTabUI();
            };
        }

        if (regTab) {
            regTab.onclick = () => {
                this.activeTab = 'register';
                this.updateTabUI();
            };
        }

        // Quick Demo Logins
        const quickAdmin = this.backdrop.querySelector('#btn-quick-admin');
        if (quickAdmin) {
            quickAdmin.onclick = () => this.performLogin('admin@bathyal.local', 'password123');
        }

        const quickDemo = this.backdrop.querySelector('#btn-quick-demo');
        if (quickDemo) {
            quickDemo.onclick = () => this.performLogin('demo@bathyal.local', 'password123');
        }

        this.bindFormEvents();
    }

    bindFormEvents() {
        const loginForm = this.backdrop.querySelector('#auth-login-form');
        if (loginForm) {
            loginForm.onsubmit = async (e) => {
                e.preventDefault();
                const email = this.backdrop.querySelector('#login-email').value.trim();
                const password = this.backdrop.querySelector('#login-password').value;
                await this.performLogin(email, password);
            };
        }

        const regForm = this.backdrop.querySelector('#auth-register-form');
        if (regForm) {
            regForm.onsubmit = async (e) => {
                e.preventDefault();
                const fullName = this.backdrop.querySelector('#reg-name').value.trim();
                const email = this.backdrop.querySelector('#reg-email').value.trim();
                const password = this.backdrop.querySelector('#reg-password').value;
                const defaultMode = this.backdrop.querySelector('#reg-mode').value;

                await this.performRegister(fullName, email, password, defaultMode);
            };
        }
    }

    showError(message) {
        const errBox = this.backdrop.querySelector('#auth-error-msg');
        if (errBox) {
            errBox.textContent = message;
            errBox.style.display = 'block';
        }
    }

    async performLogin(email, password) {
        const submitBtn = this.backdrop.querySelector('#btn-submit-login') || this.backdrop.querySelector('#btn-quick-demo');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await api.post('/api/v1/auth/login', { email, password });
            if (res && res.data && res.data.token) {
                api.setToken(res.data.token);
                store.setState({
                    currentUser: res.data.user,
                    workspaces: res.data.workspaces || [],
                    activeWorkspaceId: res.data.workspaces?.[0]?.id || 1,
                    activeMode: res.data.user.default_mode || 'simple',
                });
                toast.success(`Welcome back, ${res.data.user.full_name}!`);
                this.close();
                eventBus.emit('auth:success', res.data.user);
            }
        } catch (err) {
            this.showError(err.message || 'Invalid email or password credentials');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async performRegister(fullName, email, password, defaultMode) {
        const submitBtn = this.backdrop.querySelector('#btn-submit-register');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await api.post('/api/v1/auth/register', {
                full_name: fullName,
                email,
                password,
                default_mode: defaultMode,
            });

            if (res && res.data && res.data.token) {
                api.setToken(res.data.token);
                store.setState({
                    currentUser: res.data.user,
                    workspaces: [
                        { id: 1, name: 'My Personal Tasks', is_personal: 1, role: 'owner' }
                    ],
                    activeWorkspaceId: 1,
                    activeMode: defaultMode,
                });
                toast.success(`Account created! Welcome, ${res.data.user.full_name}.`);
                this.close();
                eventBus.emit('auth:success', res.data.user);
            }
        } catch (err) {
            this.showError(err.message || 'Registration failed');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }
}

export const authModal = new AuthModal();
