/**
 * VOD Server Web GUI - Main Application
 * SPA router, API client, shared utilities
 */
var App = {
    currentPage: null,
    refreshInterval: null,
    apiBase: '/api',

    /* API key: entered once on the login screen, kept for this browser tab
     * only (sessionStorage). The server never embeds it in the page. */
    KEY_STORAGE: 'vod_api_key',

    apiKey() {
        try { return sessionStorage.getItem(this.KEY_STORAGE) || ''; } catch (e) { return ''; }
    },

    setApiKey(key) {
        try {
            if (key) sessionStorage.setItem(this.KEY_STORAGE, key);
            else sessionStorage.removeItem(this.KEY_STORAGE);
        } catch (e) { /* storage unavailable: the key lives in memory only */ }
        this._memKey = key || '';
    },

    /* API Client */
    async api(method, path, body) {
        const headers = { 'Content-Type': 'application/json' };
        const key = this.apiKey() || this._memKey;
        if (key) headers['X-API-Key'] = key;
        const opts = { method, headers };
        if (body) opts.body = JSON.stringify(body);

        try {
            const res = await fetch(this.apiBase + path, opts);
            const data = await res.json();
            if (res.status === 401) {
                /* Key missing, wrong, or rotated on the server: ask again */
                this.showLogin('The API key was not accepted. Enter the current key from /etc/vod-server/vod-server.conf.');
                throw new Error(data.error || 'Unauthorized');
            }
            if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
            return data;
        } catch (err) {
            if (err.message.includes('Failed to fetch')) {
                throw new Error('Server unreachable');
            }
            throw err;
        }
    },

    /* --- Login screen (API key) --- */

    showLogin(message) {
        const el = document.getElementById('login-screen');
        if (!el) return;
        const msg = document.getElementById('login-message');
        if (msg) { msg.textContent = message || ''; msg.classList.toggle('hidden', !message); }
        el.classList.remove('hidden');
        document.body.classList.add('login-active');
        const input = document.getElementById('login-key');
        if (input) { input.value = ''; setTimeout(() => input.focus(), 50); }
    },

    hideLogin() {
        const el = document.getElementById('login-screen');
        if (el) el.classList.add('hidden');
        document.body.classList.remove('login-active');
    },

    /* Verify the key against an authenticated endpoint before accepting it. */
    async login(key) {
        key = (key || '').trim();
        if (!key) { this.showLogin('Enter the API key.'); return false; }
        const res = await fetch(this.apiBase + '/config', { headers: { 'X-API-Key': key } });
        if (res.status === 401) { this.showLogin('That key was not accepted.'); return false; }
        if (!res.ok) { this.showLogin(`Server error (HTTP ${res.status}).`); return false; }
        this.setApiKey(key);
        this.hideLogin();
        this.start();
        return true;
    },

    logout() {
        this.setApiKey('');
        if (this.refreshInterval) { clearInterval(this.refreshInterval); this.refreshInterval = null; }
        this.showLogin('Signed out.');
    },

    async get(path) { return this.api('GET', path); },
    async post(path, body) { return this.api('POST', path, body); },
    async del(path) { return this.api('DELETE', path); },

    /* SPA Router */
    init() {
        /* Login form */
        const form = document.getElementById('login-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.login(document.getElementById('login-key').value).catch(err => this.showLogin(err.message));
            });
        }
        const logoutLink = document.getElementById('logout-link');
        if (logoutLink) logoutLink.addEventListener('click', (e) => { e.preventDefault(); this.logout(); });

        /* No key yet: show the login screen and wait */
        if (!this.apiKey()) {
            this.showLogin('');
            return;
        }
        this.start();
    },

    /* Everything that needs an API key */
    start() {
        if (this._started) {
            this.loadPage(this.getPageFromPath());
            if (!this.refreshInterval) this.refreshInterval = setInterval(() => this.pollStatus(), 10000);
            this.pollStatus();
            return;
        }
        this._started = true;

        /* Handle navigation clicks */
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                this.navigate(page);
            });
        });

        /* Handle browser back/forward */
        window.addEventListener('popstate', () => {
            const page = this.getPageFromPath();
            this.loadPage(page);
        });

        /* Initial page load */
        const page = this.getPageFromPath();
        this.loadPage(page);

        /* Start status polling */
        this.pollStatus();
        this.refreshInterval = setInterval(() => this.pollStatus(), 10000);
    },

    getPageFromPath() {
        const path = window.location.pathname;
        if (path === '/' || path === '') return 'dashboard';
        return path.replace(/^\//, '').split('/')[0] || 'dashboard';
    },

    navigate(page) {
        const path = page === 'dashboard' ? '/' : `/${page}`;
        history.pushState({}, '', path);
        this.loadPage(page);
    },

    loadPage(page) {
        /* Stop any existing page refresh */
        if (this.currentPage && window[this.currentPage + 'Page'] && window[this.currentPage + 'Page'].stop) {
            window[this.currentPage + 'Page'].stop();
        }

        this.currentPage = page;

        /* Update nav active state */
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.toggle('active', link.dataset.page === page);
        });

        /* Render page */
        const container = document.getElementById('page-container');
        const pageModule = window[page + 'Page'];
        if (pageModule && pageModule.render) {
            container.innerHTML = pageModule.render();
            if (pageModule.init) pageModule.init();
        } else {
            container.innerHTML = `<div class="empty-state"><h3>Page not found</h3></div>`;
        }
    },

    /* Poll server status */
    async pollStatus() {
        try {
            const data = await this.get('/status');
            /* Update sidebar status */
            const dot = document.querySelector('#node-status .status-dot');
            const name = document.getElementById('node-name');
            dot.className = 'status-dot online';
            name.textContent = data.node_name || 'VOD Server';

            /* Update version */
            const ver = document.getElementById('server-version');
            if (data.version) ver.textContent = `v${data.version}`;

            /* Update jobs badge */
            const badge = document.getElementById('jobs-badge');
            if (data.active_jobs > 0) {
                badge.textContent = data.active_jobs;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }

            /* Store for pages to use */
            this.serverStatus = data;
        } catch (err) {
            const dot = document.querySelector('#node-status .status-dot');
            const name = document.getElementById('node-name');
            dot.className = 'status-dot offline';
            name.textContent = 'Disconnected';
        }
    },

    /* Toast notifications */
    toast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 300ms';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    /* Modal helpers */
    showModal(title, bodyHtml, footerHtml) {
        /* Remove existing */
        document.querySelectorAll('.modal-overlay').forEach(m => m.remove());

        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h2>${title}</h2>
                    <button class="modal-close" onclick="App.closeModal()">&times;</button>
                </div>
                <div class="modal-body">${bodyHtml}</div>
                ${footerHtml ? `<div class="modal-footer">${footerHtml}</div>` : ''}
            </div>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) App.closeModal();
        });
        requestAnimationFrame(() => overlay.classList.add('active'));
    },

    closeModal() {
        const overlay = document.querySelector('.modal-overlay.active');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 200);
        }
    },

    /* Utility: format bytes */
    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    /* Utility: format duration */
    formatDuration(seconds) {
        if (!seconds) return '0s';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = Math.floor(seconds % 60);
        if (h > 0) return `${h}h ${m}m`;
        if (m > 0) return `${m}m ${s}s`;
        return `${s}s`;
    },

    /* Utility: relative time */
    timeAgo(dateStr) {
        if (!dateStr) return 'Never';
        const now = new Date();
        const date = new Date(dateStr);
        const seconds = Math.floor((now - date) / 1000);
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        return Math.floor(seconds / 86400) + 'd ago';
    },

    /* Utility: escape HTML */
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

/* Start the app */
document.addEventListener('DOMContentLoaded', () => App.init());
