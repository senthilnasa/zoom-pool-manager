import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useToastStore = defineStore('toast', () => {
    const toasts = ref([]);

    const show = (message, type = 'info', duration = 4000) => {
        const id = Date.now() + Math.random().toString(36).substr(2, 9);
        toasts.value.push({ id, message, type });

        if (duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }
    };

    const success = (message, duration = 4000) => show(message, 'success', duration);
    const error = (message, duration = 5000) => show(message, 'error', duration);
    const info = (message, duration = 4000) => show(message, 'info', duration);
    const warning = (message, duration = 4500) => show(message, 'warning', duration);

    const remove = (id) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    return {
        toasts,
        show,
        success,
        error,
        info,
        warning,
        remove,
    };
});
