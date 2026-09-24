<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Departmental Hierarchy
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Academic faculties, administrative units, and departmental quota scopes
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchDepartments"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="openCreateDeptModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Department</span>
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
          placeholder="Search departments by name, code, or description..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Departments Grid -->
    <div v-if="loading && !departments.length" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm">Loading department directory...</p>
    </div>

    <div v-else-if="!filteredDepartments.length" class="glass-card rounded-2xl p-12 text-center text-slate-400 border border-slate-200/60 dark:border-slate-800/60">
      <Building2 class="w-10 h-10 mx-auto mb-3 opacity-40" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
        {{ searchQuery ? 'No Matching Departments' : 'No Departments Configured' }}
      </p>
      <p class="text-xs text-slate-400 mt-1">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Create departments to organize faculty members and apply quotas.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="dept in filteredDepartments"
        :key="dept.id"
        class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4 hover:border-brand-500/40 transition flex flex-col justify-between"
      >
        <div class="space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ dept.name }}</h3>
              <code class="text-[10px] font-mono text-slate-400">{{ dept.code }}</code>
            </div>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
              :class="dept.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
            >
              {{ dept.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
            {{ dept.description || 'No description provided.' }}
          </p>

          <div class="pt-2 flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 border-t border-slate-100 dark:border-slate-800">
            <span class="text-slate-400">Total Members:</span>
            <span class="font-bold text-brand-600 dark:text-brand-400">{{ dept.users_count || 0 }} user(s)</span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <button
            v-if="authStore.isAdmin"
            @click="editDept(dept)"
            class="px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Edit
          </button>
          <button
            v-if="authStore.isAdmin"
            @click="deleteDept(dept)"
            class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Department Modal -->
    <div
      v-if="deptModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ editingDept ? 'Edit Department' : 'New Department' }}</h2>
          <button @click="deptModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveDept" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department Name *</label>
            <input
              v-model="deptForm.name"
              type="text"
              required
              placeholder="e.g. Computer Science & Engineering"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department Code *</label>
            <input
              v-model="deptForm.code"
              type="text"
              required
              placeholder="e.g. CSE"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <textarea
              v-model="deptForm.description"
              rows="2"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="deptModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Department' }}
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
  Building2,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const departments = ref([]);
const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredDepartments = computed(() => {
  if (!searchQuery.value.trim()) return departments.value;
  const q = searchQuery.value.toLowerCase().trim();
  return departments.value.filter((d) => {
    return (
      (d.name && d.name.toLowerCase().includes(q)) ||
      (d.code && d.code.toLowerCase().includes(q)) ||
      (d.description && d.description.toLowerCase().includes(q))
    );
  });
});

const deptModal = ref(false);
const editingDept = ref(null);
const deptForm = ref({
  id: null,
  name: '',
  code: '',
  description: '',
  is_active: true,
});

const fetchDepartments = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/departments');
    departments.value = res.data || [];
  } catch (err) {
    console.error('Failed to load departments', err);
  } finally {
    loading.value = false;
  }
};

const openCreateDeptModal = () => {
  editingDept.value = null;
  deptForm.value = {
    id: null,
    name: '',
    code: '',
    description: '',
    is_active: true,
  };
  deptModal.value = true;
};

const editDept = (dept) => {
  editingDept.value = dept;
  deptForm.value = {
    id: dept.id,
    name: dept.name,
    code: dept.code,
    description: dept.description || '',
    is_active: !!dept.is_active,
  };
  deptModal.value = true;
};

const saveDept = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/departments', deptForm.value);
    feedback.value = `Department "${deptForm.value.name}" saved successfully.`;
    deptModal.value = false;
    await fetchDepartments();
  } catch (err) {
    console.error('Failed to save department', err);
  } finally {
    saving.value = false;
  }
};

const deleteDept = async (dept) => {
  if (!confirm(`Are you sure you want to delete department "${dept.name}"?`)) return;
  try {
    await axios.delete(`/spa/departments/${dept.id}`);
    feedback.value = `Department "${dept.name}" deleted.`;
    await fetchDepartments();
  } catch (err) {
    console.error('Failed to delete department', err);
  }
};

onMounted(fetchDepartments);
</script>
