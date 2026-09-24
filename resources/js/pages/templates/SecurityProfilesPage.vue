<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Security & Compliance Profiles
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Standardized Zoom security postures (waiting rooms, passcodes, AI Companion, encryption, and host controls)
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchProfiles"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.can('security_profile.manage') || authStore.isAdmin"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Profile</span>
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search profiles by name or code..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Profiles Grid -->
    <div v-if="loading && !profiles.length" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm">Loading security profiles...</p>
    </div>

    <div v-else-if="!filteredProfiles.length" class="p-12 text-center text-slate-400 glass-card rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
      <Shield class="w-10 h-10 mx-auto mb-3 opacity-40" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
        {{ searchQuery ? 'No Matching Security Profiles' : 'No Security Profiles Found' }}
      </p>
      <p class="text-xs text-slate-400 mt-1">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Create security profiles to govern waiting rooms, passcodes, and compliance.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="profile in filteredProfiles"
        :key="profile.id"
        class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4 hover:border-brand-500/40 transition flex flex-col justify-between"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ profile.name }}</h3>
              <code class="text-[10px] font-mono text-slate-400">{{ profile.code }}</code>
            </div>
            <span
              v-if="profile.is_default"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400"
            >
              Default
            </span>
          </div>

          <!-- Feature Matrix -->
          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Waiting Room:</span>
              <span class="font-semibold" :class="profile.settings?.waiting_room ? 'text-emerald-600' : 'text-slate-400'">
                {{ profile.settings?.waiting_room ? 'Enforced' : 'Off' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Passcode Protection:</span>
              <span class="font-semibold" :class="profile.settings?.passcode ? 'text-emerald-600' : 'text-slate-400'">
                {{ profile.settings?.passcode ? 'Required' : 'Optional' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Auth Users Only:</span>
              <span class="font-semibold" :class="profile.settings?.authenticated_users_only ? 'text-amber-600' : 'text-slate-400'">
                {{ profile.settings?.authenticated_users_only ? 'Strict' : 'Open' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Join Before Host:</span>
              <span class="font-semibold" :class="profile.settings?.join_before_host ? 'text-blue-600' : 'text-slate-400'">
                {{ profile.settings?.join_before_host ? 'Allowed' : 'Blocked' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500">AI Companion:</span>
              <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                {{ profile.settings?.ai_companion || 'ALLOWED' }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button
            v-if="authStore.can('security_profile.manage') || authStore.isAdmin"
            @click="editProfile(profile)"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Edit Profile
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Profile Modal -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ editingItem ? 'Edit Security Profile' : 'New Security Profile' }}</h2>
          <button @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveProfile" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Profile Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Profile Code *</label>
            <input
              v-model="form.code"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div class="space-y-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
            <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Enforcement Rules</h3>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="form.settings.waiting_room" class="rounded text-brand-600 focus:ring-brand-500" />
              <span>Enable Waiting Room</span>
            </label>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="form.settings.passcode" class="rounded text-brand-600 focus:ring-brand-500" />
              <span>Require Meeting Passcode</span>
            </label>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="form.settings.authenticated_users_only" class="rounded text-brand-600 focus:ring-brand-500" />
              <span>Institutional Sign-In Only (Restricted Domain)</span>
            </label>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="form.settings.join_before_host" class="rounded text-brand-600 focus:ring-brand-500" />
              <span>Allow Participants to Join Before Host</span>
            </label>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="form.settings.mute_upon_entry" class="rounded text-brand-600 focus:ring-brand-500" />
              <span>Mute Participants Upon Entry</span>
            </label>
          </div>

          <div class="pt-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Zoom AI Companion Policy</label>
            <select
              v-model="form.settings.ai_companion"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option value="ALLOWED">ALLOWED (Host can activate)</option>
              <option value="DISABLED">DISABLED (Strict Prohibited)</option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="modalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Profile' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  Shield,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const profiles = ref([]);
const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredProfiles = computed(() => {
  if (!searchQuery.value.trim()) return profiles.value;
  const q = searchQuery.value.toLowerCase().trim();
  return profiles.value.filter((p) => {
    return (
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.code && p.code.toLowerCase().includes(q))
    );
  });
});

const modalOpen = ref(false);
const editingItem = ref(null);
const form = ref({
  id: null,
  name: '',
  code: '',
  is_default: false,
  settings: {
    waiting_room: true,
    passcode: true,
    authenticated_users_only: false,
    join_before_host: false,
    mute_upon_entry: true,
    ai_companion: 'ALLOWED',
  },
});

const fetchProfiles = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/security-profiles');
    profiles.value = res.data || [];
  } catch (err) {
    console.error('Failed to load security profiles', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  form.value = {
    id: null,
    name: '',
    code: '',
    is_default: false,
    settings: {
      waiting_room: true,
      passcode: true,
      authenticated_users_only: false,
      join_before_host: false,
      mute_upon_entry: true,
      ai_companion: 'ALLOWED',
    },
  };
  modalOpen = true;
};

const editProfile = (p) => {
  editingItem.value = p;
  form.value = {
    id: p.id,
    name: p.name,
    code: p.code,
    is_default: !!p.is_default,
    settings: {
      waiting_room: !!p.settings?.waiting_room,
      passcode: !!p.settings?.passcode,
      authenticated_users_only: !!p.settings?.authenticated_users_only,
      join_before_host: !!p.settings?.join_before_host,
      mute_upon_entry: !!p.settings?.mute_upon_entry,
      ai_companion: p.settings?.ai_companion || 'ALLOWED',
    },
  };
  modalOpen = true;
};

const saveProfile = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/security-profiles', form.value);
    feedback.value = `Profile "${form.value.name}" saved successfully.`;
    modalOpen = false;
    await fetchProfiles();
  } catch (err) {
    console.error('Failed to save security profile', err);
  } finally {
    saving.value = false;
  }
};

onMounted(fetchProfiles);
</script>
