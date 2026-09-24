<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            Notifications Center
          </h1>
          <span
            v-if="unreadCount > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400"
          >
            {{ unreadCount }} Unread
          </span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          In-app alerts, booking approvals, quota warnings, and meeting reminders
        </p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative w-48 sm:w-60">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search notifications..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>

        <button
          @click="fetchNotifications"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition shadow-sm shrink-0"
        >
          <CheckCheck class="w-3.5 h-3.5 text-brand-500" />
          <span>Mark All Read</span>
        </button>
      </div>
    </div>

    <!-- Notification Feed -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !notifications.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading notifications...</p>
      </div>

      <div v-else-if="!filteredNotifications.length" class="p-12 text-center text-slate-400">
        <Bell class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Notifications' : "You're All Caught Up!" }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'No pending notifications in your inbox.' }}
        </p>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="notif in filteredNotifications"
          :key="notif.id"
          class="p-5 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition flex items-start justify-between gap-4"
          :class="{ 'bg-brand-50/20 dark:bg-brand-950/10': !notif.read_at }"
        >
          <div class="flex items-start gap-3.5 flex-1">
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
              :class="notif.read_at ? 'bg-slate-100 dark:bg-slate-800 text-slate-500' : 'bg-brand-500/10 text-brand-600 dark:text-brand-400'"
            >
              <Bell class="w-4 h-4" />
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-slate-900 dark:text-white">
                  {{ notif.data?.title || 'Notification' }}
                </span>
                <span
                  v-if="!notif.read_at"
                  class="w-2 h-2 rounded-full bg-brand-500 shrink-0"
                ></span>
              </div>

              <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ notif.data?.message || notif.data?.body || 'No message content.' }}
              </p>

              <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                <span>{{ formatDate(notif.created_at) }}</span>
                <router-link
                  v-if="notif.data?.action_url"
                  :to="notif.data.action_url"
                  class="text-brand-600 dark:text-brand-400 font-semibold hover:underline"
                >
                  View Details &rarr;
                </router-link>
              </div>
            </div>
          </div>

          <div v-if="!notif.read_at" class="shrink-0">
            <button
              @click="markAsRead(notif)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
              title="Mark Read"
            >
              <Check class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  Bell,
  CheckCheck,
  Check,
  RefreshCw,
  Search,
} from 'lucide-vue-next';

const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(false);
const searchQuery = ref('');

const filteredNotifications = computed(() => {
  if (!searchQuery.value.trim()) return notifications.value;
  const q = searchQuery.value.toLowerCase().trim();
  return notifications.value.filter((n) => {
    const dataStr = JSON.stringify(n.data || {}).toLowerCase();
    return (
      (n.type && n.type.toLowerCase().includes(q)) ||
      dataStr.includes(q)
    );
  });
});

const fetchNotifications = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/notifications', {
      headers: { Accept: 'application/json' },
    });
    notifications.value = res.data?.notifications?.data || res.data?.notifications || [];
    unreadCount.value = res.data?.unread_count || 0;
  } catch (err) {
    console.error('Failed to load notifications', err);
  } finally {
    loading.value = false;
  }
};

const markAsRead = async (notif) => {
  try {
    await axios.post(`/notifications/${notif.id}/read`, {}, {
      headers: { Accept: 'application/json' },
    });
    notif.read_at = new Date().toISOString();
    if (unreadCount.value > 0) unreadCount.value--;
  } catch (err) {
    console.error('Failed to mark notification as read', err);
  }
};

const markAllAsRead = async () => {
  try {
    await axios.post('/notifications/mark-all-read', {}, {
      headers: { Accept: 'application/json' },
    });
    notifications.value.forEach((n) => (n.read_at = new Date().toISOString()));
    unreadCount.value = 0;
  } catch (err) {
    console.error('Failed to mark all as read', err);
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};

onMounted(fetchNotifications);
</script>
