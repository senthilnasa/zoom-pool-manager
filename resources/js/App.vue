<template>
  <div class="min-h-screen text-slate-900 dark:text-slate-100 bg-slate-50/60 dark:bg-slate-950 transition-colors duration-200">
    <router-view />
    <ToastContainer />
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import ToastContainer from '@/components/ToastContainer.vue';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';

const themeStore = useThemeStore();
const authStore = useAuthStore();

onMounted(() => {
  themeStore.init();
  authStore.fetchUser();
});
</script>
