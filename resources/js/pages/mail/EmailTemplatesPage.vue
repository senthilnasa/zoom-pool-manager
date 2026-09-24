<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Email Notification Templates
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Customizable layouts, dynamic merge tags, and HTML styling for automated communications
        </p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative w-48 sm:w-60">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search email templates..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>

        <button
          @click="fetchTemplates"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition self-start sm:self-auto"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Templates Grid -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !templates.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading email templates...</p>
      </div>

      <div v-else-if="!filteredTemplates.length" class="p-12 text-center text-slate-400">
        <FileText class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Templates' : 'No Templates Found' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : '' }}
        </p>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="tpl in filteredTemplates"
          :key="tpl.id"
          class="p-5 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="font-bold text-sm text-slate-900 dark:text-white">{{ tpl.name }}</span>
              <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-300">{{ tpl.key }}</code>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="tpl.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
              >
                {{ tpl.is_active ? 'Active' : 'Disabled' }}
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
              <span class="font-semibold text-slate-700 dark:text-slate-300">Subject:</span>
              <span class="font-mono text-xs">{{ tpl.subject_template }}</span>
            </div>
            <p v-if="tpl.description" class="text-xs text-slate-400">{{ tpl.description }}</p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <button
              @click="editTemplate(tpl)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-800 hover:bg-brand-100 transition shadow-sm"
            >
              <Edit3 class="w-3.5 h-3.5" />
              <span>Edit Template</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Template Modal -->
    <div
      v-if="editingModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <FileText class="w-4 h-4 text-brand-500" />
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Edit {{ selectedTemplate?.name }}</h2>
          </div>
          <button @click="editingModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4">
          <!-- Tabs: Editor vs Live Preview -->
          <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
            <button
              @click="activeEditorTab = 'editor'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
              :class="activeEditorTab === 'editor' ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'"
            >
              Code & Layout
            </button>
            <button
              @click="loadPreview"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
              :class="activeEditorTab === 'preview' ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'"
            >
              <Eye class="w-3.5 h-3.5" />
              <span>Live Preview</span>
            </button>
          </div>

          <div v-if="activeEditorTab === 'editor'" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Display Name *</label>
                <input
                  v-model="editForm.name"
                  type="text"
                  required
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Subject Template *</label>
                <input
                  v-model="editForm.subject_template"
                  type="text"
                  required
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
                />
              </div>
            </div>

            <!-- Merge tags helper banner -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs">
              <span class="font-bold text-slate-700 dark:text-slate-300 block mb-1">Available Merge Tags:</span>
              <div class="flex flex-wrap gap-1.5">
                <code
                  v-for="tag in commonTags"
                  :key="tag"
                  @click="editForm.body_html_template += ` {{ ${tag} }}`"
                  class="px-2 py-0.5 rounded bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 font-mono text-[10px] text-brand-600 dark:text-brand-400 cursor-pointer hover:border-brand-500 transition"
                  title="Click to insert"
                >
                  &#123;&#123; {{ tag }} &#125;&#125;
                </code>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">HTML Body Template *</label>
              <textarea
                v-model="editForm.body_html_template"
                rows="10"
                required
                class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 leading-relaxed"
              ></textarea>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Plain Text Fallback Body *</label>
              <textarea
                v-model="editForm.body_text_template"
                rows="4"
                required
                class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              ></textarea>
            </div>
          </div>

          <!-- Preview Sandbox -->
          <div v-else class="space-y-4">
            <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono">
              <span class="text-slate-500">Subject Preview: </span>
              <strong class="text-slate-900 dark:text-white">{{ renderedPreview?.subject || 'Rendering...' }}</strong>
            </div>

            <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-white">
              <div class="px-4 py-2 bg-slate-100 border-b border-slate-200 text-[11px] text-slate-500 font-semibold flex items-center justify-between">
                <span>Rendered HTML Output</span>
                <span class="text-emerald-600 font-bold">Sample Context Applied</span>
              </div>
              <div class="p-6 text-slate-900" v-html="renderedPreview?.html"></div>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
            <input
              type="checkbox"
              v-model="editForm.is_active"
              class="rounded text-brand-600 focus:ring-brand-500"
            />
            <span>Template Active</span>
          </label>

          <div class="flex items-center gap-2">
            <button
              @click="editingModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              @click="saveTemplate"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Template' }}
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
  FileText,
  Edit3,
  Eye,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const templates = ref([]);
const loading = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredTemplates = computed(() => {
  if (!searchQuery.value.trim()) return templates.value;
  const q = searchQuery.value.toLowerCase().trim();
  return templates.value.filter((t) => {
    return (
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.key && t.key.toLowerCase().includes(q)) ||
      (t.subject_template && t.subject_template.toLowerCase().includes(q))
    );
  });
});

const editingModal = ref(false);
const selectedTemplate = ref(null);
const activeEditorTab = ref('editor');
const saving = ref(false);
const renderedPreview = ref(null);

const commonTags = [
  'recipient_name',
  'recipient_email',
  'meeting.title',
  'meeting.starts_at',
  'meeting.duration_minutes',
  'meeting.join_url',
  'meeting.passcode',
  'review_url',
  'reason',
];

const editForm = ref({
  name: '',
  subject_template: '',
  body_html_template: '',
  body_text_template: '',
  is_active: true,
});

const fetchTemplates = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/email-templates', {
      headers: { Accept: 'application/json' },
    });
    templates.value = res.data || [];
  } catch (err) {
    console.error('Failed to load email templates', err);
  } finally {
    loading.value = false;
  }
};

const editTemplate = (tpl) => {
  selectedTemplate.value = tpl;
  editForm.value = {
    name: tpl.name,
    subject_template: tpl.subject_template,
    body_html_template: tpl.body_html_template,
    body_text_template: tpl.body_text_template,
    is_active: !!tpl.is_active,
  };
  activeEditorTab.value = 'editor';
  renderedPreview.value = null;
  editingModal.value = true;
};

const loadPreview = async () => {
  activeEditorTab.value = 'preview';
  try {
    const res = await axios.post(`/admin/email-templates/${selectedTemplate.value.id}/preview`, editForm.value, {
      headers: { Accept: 'application/json' },
    });
    renderedPreview.value = res.data;
  } catch (err) {
    console.error('Failed to render preview', err);
  }
};

const saveTemplate = async () => {
  saving.value = true;
  try {
    await axios.put(`/admin/email-templates/${selectedTemplate.value.id}`, editForm.value, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = `Template "${editForm.value.name}" saved successfully.`;
    editingModal.value = false;
    await fetchTemplates();
  } catch (err) {
    console.error('Failed to save template', err);
  } finally {
    saving.value = false;
  }
};

onMounted(fetchTemplates);
</script>
