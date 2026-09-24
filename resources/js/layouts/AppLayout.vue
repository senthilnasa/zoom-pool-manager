<template>
  <div class="min-h-screen flex bg-slate-50/60 dark:bg-slate-950 transition-colors duration-200">
    <!-- Desktop Sidebar -->
    <Sidebar class="hidden lg:flex" />

    <!-- Mobile Sidebar Drawer -->
    <div
      v-if="mobileSidebarOpen"
      class="fixed inset-0 z-40 lg:hidden"
    >
      <div
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
        @click="mobileSidebarOpen = false"
      />
      <div class="fixed inset-y-0 left-0 flex">
        <Sidebar @click="mobileSidebarOpen = false" />
      </div>
    </div>

    <!-- Main Workspace -->
    <div class="flex-1 flex flex-col min-w-0">
      <Header @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />

      <!-- Page Content Area -->
      <main class="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto">
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
import { ref } from 'vue';
import Sidebar from '@/components/Sidebar.vue';
import Header from '@/components/Header.vue';
import Footer from '@/components/Footer.vue';

const mobileSidebarOpen = ref(false);
</script>
