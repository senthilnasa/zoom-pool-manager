/**
 * Zoom Pool Manager (ZPM) — Modern SPA Micro-Interactions & AJAX Layer
 * Made with ❤️ by Senthil Nasa (https://github.com/senthilnasa)
 */

(function () {
    'use strict';

    window.ZPM = window.ZPM || {};

    // 1. Top Loading Progress Bar
    const progressEl = document.createElement('div');
    progressEl.id = 'zpm-progress-bar';
    progressEl.className = 'fixed top-0 left-0 h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500 z-50 transition-all duration-300 pointer-events-none opacity-0';
    progressEl.style.width = '0%';
    document.addEventListener('DOMContentLoaded', () => {
        document.body.appendChild(progressEl);
    });

    ZPM.progress = {
        timer: null,
        start() {
            if (!progressEl) return;
            clearTimeout(this.timer);
            progressEl.style.opacity = '1';
            progressEl.style.width = '25%';
            this.timer = setTimeout(() => {
                progressEl.style.width = '65%';
            }, 200);
        },
        done() {
            if (!progressEl) return;
            clearTimeout(this.timer);
            progressEl.style.width = '100%';
            setTimeout(() => {
                progressEl.style.opacity = '0';
                setTimeout(() => {
                    progressEl.style.width = '0%';
                }, 300);
            }, 250);
        }
    };

    // 2. Modern Glassmorphic Toast Notification Engine
    let toastContainer = null;
    function getToastContainer() {
        if (!toastContainer) {
            toastContainer = document.getElementById('zpm-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'zpm-toast-container';
                toastContainer.className = 'fixed bottom-5 right-5 z-50 flex flex-col space-y-3 max-w-sm w-full pointer-events-none px-4 sm:px-0';
                document.body.appendChild(toastContainer);
            }
        }
        return toastContainer;
    }

    ZPM.toast = function (type, message, duration = 4500) {
        const container = getToastContainer();
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto transform translate-y-3 opacity-0 transition-all duration-300 flex items-start p-4 rounded-2xl border shadow-xl backdrop-blur-xl';

        let iconSvg = '';
        let typeClasses = '';

        switch (type) {
            case 'success':
                typeClasses = 'bg-emerald-500/10 dark:bg-emerald-950/40 border-emerald-500/30 text-emerald-900 dark:text-emerald-200';
                iconSvg = `<svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
                break;
            case 'error':
                typeClasses = 'bg-rose-500/10 dark:bg-rose-950/40 border-rose-500/30 text-rose-900 dark:text-rose-200';
                iconSvg = `<svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
                break;
            case 'warning':
                typeClasses = 'bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-900 dark:text-amber-200';
                iconSvg = `<svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`;
                break;
            default:
                typeClasses = 'bg-sky-500/10 dark:bg-sky-950/40 border-sky-500/30 text-sky-900 dark:text-sky-200';
                iconSvg = `<svg class="w-5 h-5 text-sky-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
        }

        toast.className += ` ${typeClasses}`;
        toast.innerHTML = `
            ${iconSvg}
            <div class="ml-3 text-sm font-medium flex-1 pr-2 leading-relaxed">${message}</div>
            <button type="button" class="ml-auto text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        `;

        const closeBtn = toast.querySelector('button');
        const dismiss = () => {
            toast.classList.add('translate-y-3', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        };

        if (closeBtn) closeBtn.addEventListener('click', dismiss);
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-3', 'opacity-0');
        });

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }
    };

    // 3. Centralized Fetch / AJAX Client with CSRF & Error Normalization
    ZPM.api = async function (url, options = {}) {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
            ...(options.headers || {})
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        ZPM.progress.start();

        try {
            const response = await fetch(url, { ...options, headers });
            ZPM.progress.done();

            if (!response.ok) {
                let errorData = {};
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { message: `Request failed with status ${response.status}` };
                }

                if (response.status === 419) {
                    ZPM.toast('error', 'Session expired. Please refresh the page.');
                } else if (response.status === 403) {
                    ZPM.toast('error', errorData.message || 'You are not authorized to perform this action.');
                } else if (response.status === 422) {
                    const firstError = errorData.errors ? Object.values(errorData.errors)[0][0] : errorData.message;
                    ZPM.toast('error', firstError || 'Validation failed. Please check your inputs.');
                } else if (response.status >= 500) {
                    ZPM.toast('error', errorData.message || 'Internal server error occurred.');
                }

                throw { status: response.status, data: errorData };
            }

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return await response.json();
            }
            return await response.text();
        } catch (err) {
            ZPM.progress.done();
            throw err;
        }
    };

    // 4. Form Submission Interceptor
    ZPM.submitForm = async function (form, options = {}) {
        if (!form) return;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline-block text-current" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            `;
        }

        try {
            const formData = new FormData(form);
            const method = (form.getAttribute('method') || 'POST').toUpperCase();
            const action = form.getAttribute('action') || window.location.href;

            const res = await ZPM.api(action, {
                method: method,
                body: formData
            });

            if (options.onSuccess) {
                options.onSuccess(res);
            } else if (res && res.message) {
                ZPM.toast('success', res.message);
                if (res.redirect) {
                    setTimeout(() => window.location.href = res.redirect, 800);
                }
            }
            return res;
        } catch (error) {
            if (options.onError) {
                options.onError(error);
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    };

    // 5. Modal Controller
    ZPM.modal = function (modalId) {
        const modal = document.getElementById(modalId);
        return {
            open() {
                if (modal) {
                    modal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                }
            },
            close() {
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        };
    };

    // 6. Live Table Search / Filter Helper
    ZPM.filterTable = function (inputSelector, tableSelector) {
        const input = document.querySelector(inputSelector);
        const table = document.querySelector(tableSelector);
        if (!input || !table) return;

        let debounceTimer;
        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = e.target.value.toLowerCase().trim();
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            }, 150);
        });
    };

    // Automatically display session flash messages as toasts
    document.addEventListener('DOMContentLoaded', () => {
        const flashSuccess = document.querySelector('meta[name="flash-success"]');
        const flashError = document.querySelector('meta[name="flash-error"]');
        const flashWarning = document.querySelector('meta[name="flash-warning"]');

        if (flashSuccess && flashSuccess.content) ZPM.toast('success', flashSuccess.content);
        if (flashError && flashError.content) ZPM.toast('error', flashError.content);
        if (flashWarning && flashWarning.content) ZPM.toast('warning', flashWarning.content);
    });

})();
