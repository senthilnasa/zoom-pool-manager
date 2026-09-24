import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useThemeStore = defineStore('theme', () => {
    // Strictly default to 'system'
    const currentTheme = ref(localStorage.getItem('zpm_theme') || 'system');
    const systemPrefersDark = ref(
        typeof window !== 'undefined' && window.matchMedia
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : false
    );

    const isDark = computed(() => {
        if (currentTheme.value === 'dark') return true;
        if (currentTheme.value === 'light') return false;
        return systemPrefersDark.value;
    });

    const applyThemeToDom = () => {
        if (typeof document === 'undefined') return;
        const root = document.documentElement;
        if (isDark.value) {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    };

    const setTheme = async (theme) => {
        if (!['system', 'light', 'dark'].includes(theme)) return;
        currentTheme.value = theme;
        localStorage.setItem('zpm_theme', theme);
        applyThemeToDom();

        try {
            await axios.post('/spa/auth/theme', { theme });
        } catch (e) {
            // Silently fail if unauthenticated or offline
        }
    };

    const init = () => {
        if (typeof window !== 'undefined' && window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', (e) => {
                systemPrefersDark.value = e.matches;
                if (currentTheme.value === 'system') {
                    applyThemeToDom();
                }
            });
        }
        applyThemeToDom();
    };

    return {
        currentTheme,
        isDark,
        setTheme,
        init,
    };
});
