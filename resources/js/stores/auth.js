import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useThemeStore } from './theme';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(window.__ZPM__?.user || null);
    const loading = ref(false);
    const demoMode = ref(window.__ZPM__?.demoMode || false);
    const appVersion = ref(window.__ZPM__?.appVersion || '1.0.0');

    const isAuthenticated = computed(() => !!user.value);

    const hasRole = (role) => {
        if (!user.value || !user.value.roles) return false;
        const target = role.toLowerCase().replace(/[\s_-]+/g, '');
        return user.value.roles.some((r) => r.toLowerCase().replace(/[\s_-]+/g, '') === target);
    };

    const hasAnyRole = (roles) => {
        return roles.some((r) => hasRole(r));
    };

    const isAdmin = computed(() => {
        if (!user.value) return false;
        return (
            user.value.is_admin ||
            hasRole('super_admin') ||
            hasRole('it_admin') ||
            hasRole('Super Administrator') ||
            hasRole('Administrator')
        );
    });

    const isDeptAdmin = computed(() => {
        return isAdmin.value || hasRole('dept_admin') || hasRole('Department Administrator');
    });

    const hasPermission = (permission) => {
        if (!user.value) return false;
        if (isAdmin.value) return true;
        return user.value.permissions?.includes(permission);
    };

    const can = (permission) => hasPermission(permission);

    const fetchUser = async () => {
        try {
            loading.value = true;
            const response = await axios.get('/spa/auth/me');
            if (response.data.authenticated) {
                user.value = response.data.user;
                demoMode.value = response.data.demo_mode;
                appVersion.value = response.data.app_version;

                // Sync theme if provided
                if (user.value?.theme) {
                    const themeStore = useThemeStore();
                    if (!localStorage.getItem('zpm_theme')) {
                        themeStore.setTheme(user.value.theme);
                    }
                }
            }
        } catch (error) {
            user.value = null;
        } finally {
            loading.value = false;
        }
    };

    const logout = async () => {
        try {
            await axios.post('/logout');
        } catch (e) {
            // ignore
        } finally {
            window.location.href = '/login';
        }
    };

    return {
        user,
        loading,
        demoMode,
        appVersion,
        isAuthenticated,
        isAdmin,
        isDeptAdmin,
        hasRole,
        hasAnyRole,
        hasPermission,
        can,
        fetchUser,
        logout,
    };
});
