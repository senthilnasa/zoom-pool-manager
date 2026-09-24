import axios from 'axios';

/**
 * Zoom Pool Manager (ZPM) — CSRF Token & Session Keepalive Service
 * 
 * Provides:
 * 1. Dynamic token extraction and synchronization across DOM, Axios headers, and window.__ZPM__.
 * 2. Automatic CSRF token refresh via /spa/csrf-token.
 * 3. Proactive session keepalive (heartbeat ping) to prevent session timeouts while SPA is kept open.
 * 4. Visibility & focus re-synchronization when returning to an idle or backgrounded tab.
 * 5. Automatic interceptor for HTTP 419 (Page Expired / Token Mismatch) with in-flight retry.
 */

let refreshPromise = null;
let keepAliveTimer = null;
let lastPingTime = Date.now();
const PING_INTERVAL_MS = 10 * 60 * 1000; // 10 minutes (well within default 120m session lifetime)
const COOLDOWN_MS = 3 * 60 * 1000; // 3 minutes cooldown before re-pinging on visibility change

/**
 * Read the current CSRF token from DOM meta, window.__ZPM__, or XSRF-TOKEN cookie.
 */
export function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;

    if (window.__ZPM__?.csrfToken) {
        return window.__ZPM__.csrfToken;
    }

    // Fallback: parse document.cookie for XSRF-TOKEN
    const match = document.cookie.match(new RegExp('(^|;\\s*)(?:XSRF-TOKEN)=([^;]*)'));
    if (match && match[2]) {
        try {
            return decodeURIComponent(match[2]);
        } catch {
            return match[2];
        }
    }

    return '';
}

/**
 * Update the CSRF token in all runtime locations.
 */
export function setCsrfToken(newToken) {
    if (!newToken) return;

    if (!window.__ZPM__) {
        window.__ZPM__ = {};
    }
    window.__ZPM__.csrfToken = newToken;

    let metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        metaTag.setAttribute('content', newToken);
    } else {
        metaTag = document.createElement('meta');
        metaTag.name = 'csrf-token';
        metaTag.content = newToken;
        document.head.appendChild(metaTag);
    }

    axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
}

/**
 * Request a fresh CSRF token from the server.
 * Uses a single in-flight Promise to deduplicate concurrent requests.
 */
export async function refreshCsrfToken() {
    if (refreshPromise) {
        return refreshPromise;
    }

    refreshPromise = (async () => {
        try {
            // Use native fetch to bypass Axios 419 interceptor and avoid recursion
            const response = await fetch('/spa/csrf-token', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return { success: false, authenticated: false };
            }

            const data = await response.json();
            if (data.csrf_token) {
                setCsrfToken(data.csrf_token);
                lastPingTime = Date.now();
            }

            return {
                success: true,
                authenticated: !!data.authenticated,
                csrfToken: data.csrf_token,
                user: data.user || null,
            };
        } catch (err) {
            console.warn('[ZPM CSRF] Failed to refresh CSRF token:', err);
            return { success: false, authenticated: false };
        } finally {
            refreshPromise = null;
        }
    })();

    return refreshPromise;
}

/**
 * Handle a genuine session expiration.
 */
function handleSessionExpired() {
    console.warn('[ZPM Session] Session expired or invalid. Redirecting to login.');
    const currentPath = window.location.pathname + window.location.search;
    const loginUrl = `/login?redirect=${encodeURIComponent(currentPath)}`;
    window.location.href = loginUrl;
}

/**
 * Configure Axios request & response interceptors for CSRF and 419 handling.
 */
export function setupCsrfInterceptors(axiosInstance = axios) {
    // 1. Request Interceptor: Attach latest token and headers
    axiosInstance.interceptors.request.use((config) => {
        config.headers = config.headers || {};
        config.headers['X-Requested-With'] = 'XMLHttpRequest';

        const token = getCsrfToken();
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }

        return config;
    }, (error) => Promise.reject(error));

    // 2. Response Interceptor: Catch 419 Page Expired (CSRF token mismatch)
    axiosInstance.interceptors.response.use(
        (response) => response,
        async (error) => {
            const originalRequest = error.config;

            // Only retry once per request if error is 419
            if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
                originalRequest._retry = true;

                const refreshResult = await refreshCsrfToken();

                if (refreshResult.success && refreshResult.authenticated && refreshResult.csrfToken) {
                    originalRequest.headers['X-CSRF-TOKEN'] = refreshResult.csrfToken;
                    return axiosInstance(originalRequest);
                } else if (refreshResult.success && !refreshResult.authenticated) {
                    handleSessionExpired();
                }
            }

            return Promise.reject(error);
        }
    );
}

/**
 * Start proactive keepalive heartbeat and visibility/focus listeners.
 * Prevents session and CSRF expiration when the SPA is kept open for hours.
 */
export function initCsrfKeepAlive(axiosInstance = axios) {
    // Initial token registration
    const initialToken = getCsrfToken();
    if (initialToken) {
        setCsrfToken(initialToken);
    }

    // Configure Axios interceptors
    setupCsrfInterceptors(axiosInstance);

    // Periodic heartbeat to touch the session and refresh tokens
    if (keepAliveTimer) {
        clearInterval(keepAliveTimer);
    }

    keepAliveTimer = setInterval(async () => {
        await refreshCsrfToken();
    }, PING_INTERVAL_MS);

    // Visibility change listener: when returning to tab after sleep/inactive period
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const elapsed = Date.now() - lastPingTime;
            if (elapsed > COOLDOWN_MS) {
                refreshCsrfToken();
            }
        }
    });

    // Window focus listener: when switching back from another application
    window.addEventListener('focus', () => {
        const elapsed = Date.now() - lastPingTime;
        if (elapsed > COOLDOWN_MS) {
            refreshCsrfToken();
        }
    });
}
