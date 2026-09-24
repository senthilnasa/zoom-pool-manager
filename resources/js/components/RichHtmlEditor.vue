<template>
  <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-slate-900 flex flex-col shadow-xs">
    <!-- Toolbar -->
    <div class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700/80 px-3 py-2 flex flex-wrap items-center justify-between gap-2">
      <!-- Left: Formatting Actions -->
      <div v-if="activeTab === 'editor'" class="flex flex-wrap items-center gap-1">
        <!-- Headings / Block format -->
        <select
          @change="formatBlock($event.target.value); $event.target.value = ''"
          class="text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500"
          title="Text Style"
        >
          <option value="" disabled selected>Heading</option>
          <option value="p">Paragraph</option>
          <option value="h1">Heading 1</option>
          <option value="h2">Heading 2</option>
          <option value="h3">Heading 3</option>
        </select>

        <div class="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>

        <!-- Inline Formats -->
        <button
          type="button"
          @click="execCmd('bold')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Bold (Ctrl+B)"
        >
          <Bold class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="execCmd('italic')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Italic (Ctrl+I)"
        >
          <Italic class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="execCmd('underline')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Underline (Ctrl+U)"
        >
          <Underline class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="execCmd('strikeThrough')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Strikethrough"
        >
          <Strikethrough class="w-3.5 h-3.5" />
        </button>

        <div class="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>

        <!-- Lists -->
        <button
          type="button"
          @click="execCmd('insertUnorderedList')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Bullet List"
        >
          <List class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="execCmd('insertOrderedList')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Numbered List"
        >
          <ListOrdered class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="formatBlock('blockquote')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Quote"
        >
          <Quote class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="insertLink"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Insert Link"
        >
          <LinkIcon class="w-3.5 h-3.5" />
        </button>

        <button
          type="button"
          @click="execCmd('insertHorizontalRule')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Horizontal Line"
        >
          <Minus class="w-3.5 h-3.5" />
        </button>

        <div class="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>

        <button
          type="button"
          @click="execCmd('removeFormat')"
          class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
          title="Clear Formatting"
        >
          <RemoveFormatting class="w-3.5 h-3.5" />
        </button>
      </div>

      <div v-else-if="activeTab === 'source'" class="text-xs text-slate-500 font-mono flex items-center gap-1.5">
        <Code2 class="w-3.5 h-3.5 text-brand-500" />
        <span>Direct HTML Source Editor (Supports CKEditor tags & custom markup)</span>
      </div>

      <div v-else class="text-xs text-slate-500 flex items-center gap-1.5">
        <Eye class="w-3.5 h-3.5 text-emerald-500" />
        <span>Live Customer Document Preview</span>
      </div>

      <!-- Right: Tab Switcher & Template Helper -->
      <div class="flex items-center gap-1.5">
        <button
          v-if="templateType && (!modelValue || modelValue.trim() === '')"
          type="button"
          @click="insertTemplate"
          class="px-2 py-1 text-[11px] font-semibold text-brand-700 dark:text-brand-300 bg-brand-50 dark:bg-brand-950/50 hover:bg-brand-100 dark:hover:bg-brand-900/50 border border-brand-200 dark:border-brand-800 rounded-lg transition flex items-center gap-1"
        >
          <Sparkles class="w-3 h-3 text-brand-500" />
          <span>Load Standard Template</span>
        </button>

        <div class="flex items-center bg-slate-200 dark:bg-slate-700/60 p-0.5 rounded-lg text-xs font-semibold">
          <button
            type="button"
            @click="switchTab('editor')"
            class="px-2.5 py-1 rounded-md transition"
            :class="activeTab === 'editor' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'"
          >
            Visual
          </button>
          <button
            type="button"
            @click="switchTab('source')"
            class="px-2.5 py-1 rounded-md transition flex items-center gap-1"
            :class="activeTab === 'source' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'"
          >
            <Code2 class="w-3 h-3" />
            <span>HTML</span>
          </button>
          <button
            type="button"
            @click="switchTab('preview')"
            class="px-2.5 py-1 rounded-md transition flex items-center gap-1"
            :class="activeTab === 'preview' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'"
          >
            <Eye class="w-3 h-3" />
            <span>Preview</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Tab 1: Visual WYSIWYG ContentEditable -->
    <div
      v-show="activeTab === 'editor'"
      ref="editorRef"
      contenteditable="true"
      @input="onVisualInput"
      @blur="onVisualInput"
      class="p-4 min-h-[280px] max-h-[500px] overflow-y-auto focus:outline-none custom-prose text-slate-800 dark:text-slate-200 text-xs sm:text-sm bg-white dark:bg-slate-900"
      :data-placeholder="placeholder"
    ></div>

    <!-- Tab 2: Raw HTML Source Code -->
    <textarea
      v-show="activeTab === 'source'"
      :value="modelValue"
      @input="onSourceInput"
      rows="12"
      class="w-full p-4 font-mono text-xs bg-slate-950 text-emerald-400 focus:outline-none min-h-[280px] max-h-[500px] resize-y custom-scrollbar"
      placeholder="<!-- Paste or write custom HTML here (e.g. from CKEditor export) -->"
    ></textarea>

    <!-- Tab 3: Live Reader Preview -->
    <div
      v-show="activeTab === 'preview'"
      class="p-6 min-h-[280px] max-h-[500px] overflow-y-auto bg-slate-50/50 dark:bg-slate-950/50 border-t border-slate-100 dark:border-slate-800"
    >
      <div v-if="modelValue && modelValue.trim()" class="custom-prose text-sm text-slate-700 dark:text-slate-300" v-html="modelValue"></div>
      <div v-else class="text-center py-10 text-slate-400 text-xs italic">
        No content written yet. Switch to Visual or HTML tab to compose.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import {
  Bold,
  Italic,
  Underline,
  Strikethrough,
  List,
  ListOrdered,
  Quote,
  Link as LinkIcon,
  Minus,
  RemoveFormatting,
  Code2,
  Eye,
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write your policy content here...',
  },
  templateType: {
    type: String,
    default: '', // 'privacy' or 'terms'
  },
  orgName: {
    type: String,
    default: 'Institution',
  },
});

const emit = defineEmits(['update:modelValue']);

const activeTab = ref('editor'); // 'editor' | 'source' | 'preview'
const editorRef = ref(null);

const syncFromProp = () => {
  if (editorRef.value && editorRef.value.innerHTML !== props.modelValue) {
    editorRef.value.innerHTML = props.modelValue || '';
  }
};

onMounted(() => {
  syncFromProp();
});

watch(
  () => props.modelValue,
  (newVal) => {
    if (editorRef.value && editorRef.value.innerHTML !== newVal) {
      editorRef.value.innerHTML = newVal || '';
    }
  }
);

const onVisualInput = () => {
  if (editorRef.value) {
    emit('update:modelValue', editorRef.value.innerHTML);
  }
};

const onSourceInput = (e) => {
  emit('update:modelValue', e.target.value);
};

const switchTab = async (tab) => {
  if (activeTab.value === 'editor' && editorRef.value) {
    emit('update:modelValue', editorRef.value.innerHTML);
  }
  activeTab.value = tab;
  if (tab === 'editor') {
    await nextTick();
    syncFromProp();
  }
};

const execCmd = (cmd, val = null) => {
  if (editorRef.value) {
    editorRef.value.focus();
  }
  document.execCommand(cmd, false, val);
  onVisualInput();
};

const formatBlock = (tag) => {
  if (!tag) return;
  if (editorRef.value) {
    editorRef.value.focus();
  }
  document.execCommand('formatBlock', false, tag);
  onVisualInput();
};

const insertLink = () => {
  const url = prompt('Enter link URL (e.g., https://...):');
  if (url) {
    execCmd('createLink', url);
  }
};

const insertTemplate = () => {
  const org = props.orgName || 'Institution';
  let tpl = '';
  if (props.templateType === 'privacy') {
    tpl = `<h2>1. Commitment to Privacy</h2>
<p>At <strong>${org}</strong>, we are committed to safeguarding the privacy and personal data of our students, faculty, staff, and authorized meeting participants. This policy outlines how information is gathered, managed, and protected within the Zoom Pool Resource Manager.</p>

<h2>2. Information We Collect</h2>
<ul>
  <li><strong>Identity & Account Details:</strong> Name, institutional email address, user role, and department.</li>
  <li><strong>Meeting Schedules & Metadata:</strong> Session topics, reservation times, duration, allocated host pool licenses, and attendee email lists.</li>
  <li><strong>Cloud Recording Records:</strong> Playback URLs, recording duration, and access audit records.</li>
</ul>

<h2>3. Purpose of Processing</h2>
<p>Collected data is strictly used for:</p>
<ol>
  <li>Automatic provisioning and fair allocation of Zoom enterprise host pools.</li>
  <li>Preventing schedule collisions and managing institutional compliance.</li>
  <li>Maintaining an immutable cryptographic audit log of administrative actions.</li>
</ol>

<h2>4. Retention & User Rights</h2>
<p>In accordance with institutional guidelines and data protection regulations (including FERPA, GDPR, and DPDP), users may request data export or account anonymization by contacting institutional administrators.</p>`;
  } else if (props.templateType === 'terms') {
    tpl = `<h2>1. Acceptance of Terms</h2>
<p>By scheduling, hosting, or attending video sessions through the <strong>${org}</strong> Zoom Pool Manager platform, you agree to comply with these Terms of Service and institutional computing policies.</p>

<h2>2. Acceptable Use of Pooled Resources</h2>
<ul>
  <li>Zoom host licenses are a shared institutional resource managed dynamically. Users must reserve only the duration required for genuine academic or administrative activities.</li>
  <li>Host keys and meeting join credentials must not be shared with unauthorized third parties.</li>
  <li>Automated scripts or bot bookings without prior IT authorization are strictly prohibited.</li>
</ul>

<h2>3. Host Pool Responsibilities</h2>
<p>Hosts are responsible for concluding meetings on schedule so pooled licenses can be reclaimed and recycled for subsequent sessions. Consecutive unreleased overruns may result in scheduling quota restrictions.</p>

<h2>4. Compliance & Monitoring</h2>
<p>All reservation lifecycle events, host key reveals, and administrative overrides are permanently logged in the system audit trail for security and governance review.</p>`;
  }

  if (tpl) {
    emit('update:modelValue', tpl);
    if (editorRef.value) {
      editorRef.value.innerHTML = tpl;
    }
  }
};
</script>

<style>
[contenteditable="true"]:empty:before {
  content: attr(data-placeholder);
  color: #94a3b8;
  font-style: italic;
  pointer-events: none;
}
</style>
