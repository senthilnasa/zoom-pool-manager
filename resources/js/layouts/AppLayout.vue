<template>
  <div class="min-h-screen flex bg-slate-50/60 dark:bg-slate-950 transition-colors duration-200">
    <!-- Desktop Sidebar -->
    <Sidebar class="hidden lg:flex" />

    <!-- Mobile Sidebar Drawer -->
    <Teleport to="body">
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="mobileSidebarOpen"
          v-scroll-lock
          class="fixed inset-0 z-50 lg:hidden"
        >
          <!-- Backdrop overlay -->
          <div
            class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity"
            @click="mobileSidebarOpen = false"
          />

          <!-- Drawer panel -->
          <div class="fixed inset-y-0 left-0 flex max-w-full z-10">
            <Sidebar
              class="bg-white dark:bg-slate-900 shadow-2xl border-r border-slate-200 dark:border-slate-800"
              @close="mobileSidebarOpen = false"
            />
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Main Workspace -->
    <div class="flex-1 flex flex-col min-w-0">
      <Header @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />

      <!-- Page Content Area -->
      <main class="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto min-w-0">
        <router-view v-slot="{ Component }">
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
            mode="out-in"
          >
            <component :is="Component" />
          </transition>
        </router-view>
      </main>

      <Footer />
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '@/components/Sidebar.vue';
import Header from '@/components/Header.vue';
import Footer from '@/components/Footer.vue';

const route = useRoute();
const mobileSidebarOpen = ref(false);

// Automatically close mobile sidebar drawer on page navigation
watch(
  () => route.fullPath,
  () => {
    mobileSidebarOpen.value = false;
  }
);
</script>
