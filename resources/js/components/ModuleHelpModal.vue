<template>
  <Modal :show="show" @close="$emit('close')" max-width="3xl" :title="currentGuide.title">
    <div class="space-y-4 max-h-[72vh] overflow-y-auto overflow-x-hidden pr-2 custom-scrollbar">
      <!-- Top Bar: Category & Module Switcher -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 min-w-0">
        <div class="flex items-center gap-2 min-w-0 flex-wrap">
          <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-brand-500/15 text-brand-600 dark:text-brand-400 border border-brand-500/20 whitespace-nowrap">
            {{ currentGuide.category }}
          </span>
          <span class="text-xs text-slate-500 dark:text-slate-400 hidden xs:inline">Interactive Guide</span>
        </div>

        <!-- Switch to Another Guide -->
        <div class="flex items-center gap-2 min-w-0 w-full sm:w-auto">
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0 whitespace-nowrap">
            Switch Module:
          </label>
          <div class="relative min-w-0 flex-1 sm:w-60">
            <select
              v-model="selectedKey"
              class="w-full text-xs rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-brand-500 truncate"
            >
              <optgroup v-for="(guides, cat) in groupedGuides" :key="cat" :label="cat">
                <option v-for="g in guides" :key="g.key" :value="g.key">
                  {{ g.title }}
                </option>
              </optgroup>
            </select>
          </div>
        </div>
      </div>

      <!-- Summary Overview -->
      <div class="p-3.5 sm:p-4 rounded-xl bg-gradient-to-r from-brand-500/10 via-sky-500/10 to-indigo-500/10 border border-brand-500/20 text-xs sm:text-sm text-slate-800 dark:text-slate-200 leading-relaxed min-w-0">
        <div class="flex items-start gap-2.5">
          <BookOpen class="w-4 sm:w-5 h-4 sm:h-5 text-brand-600 dark:text-brand-400 shrink-0 mt-0.5" />
          <div class="min-w-0 flex-1">
            <p class="font-medium leading-relaxed break-words">{{ currentGuide.summary }}</p>
          </div>
        </div>
      </div>

      <!-- Allowed Roles & Access -->
      <div class="flex items-center gap-2 text-xs p-3 rounded-xl bg-slate-100/70 dark:bg-slate-800/40 text-slate-600 dark:text-slate-400 border border-slate-200/50 dark:border-slate-700/50 min-w-0">
        <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
        <div class="min-w-0 flex-1 break-words">
          <span class="font-semibold text-slate-700 dark:text-slate-300">Authorized Roles:</span>
          <span class="ml-1.5">{{ currentGuide.whoCanUse }}</span>
        </div>
      </div>

      <!-- Step-by-Step Instructions -->
      <div class="space-y-2.5 min-w-0">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
          <ListOrdered class="w-4 h-4 text-brand-600 dark:text-brand-400" />
          How to Use this Module (Step-by-Step)
        </h4>

        <div class="space-y-2">
          <div
            v-for="s in currentGuide.steps"
            :key="s.step"
            class="flex items-start gap-3 p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 shadow-xs min-w-0"
          >
            <div class="w-5 sm:w-6 h-5 sm:h-6 rounded-full bg-brand-600 text-white font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
              {{ s.step }}
            </div>
            <div class="min-w-0 flex-1">
              <h5 class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">
                {{ s.title }}
              </h5>
              <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 leading-normal break-words">
                {{ s.desc }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Key Features & Controls -->
      <div v-if="currentGuide.keyFeatures && currentGuide.keyFeatures.length" class="space-y-2.5 min-w-0">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
          <Sliders class="w-4 h-4 text-brand-600 dark:text-brand-400" />
          Key Features & Controls
        </h4>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 min-w-0">
          <div
            v-for="(f, i) in currentGuide.keyFeatures"
            :key="i"
            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 min-w-0"
          >
            <div class="text-xs font-bold text-slate-900 dark:text-slate-100 flex items-center gap-1.5 min-w-0">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
              <span class="truncate">{{ f.name }}</span>
            </div>
            <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 leading-normal break-words">
              {{ f.desc }}
            </p>
          </div>
        </div>
      </div>

      <!-- Pro-Tips & Best Practices -->
      <div v-if="currentGuide.tips && currentGuide.tips.length" class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-1.5 min-w-0">
        <h5 class="text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
          <Lightbulb class="w-4 h-4 text-amber-500 shrink-0" />
          Pro-Tips & Best Practices
        </h5>
        <ul class="text-xs text-amber-900 dark:text-amber-200/90 space-y-1 list-disc list-inside break-words">
          <li v-for="(tip, idx) in currentGuide.tips" :key="idx">
            {{ tip }}
          </li>
        </ul>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        @click="$emit('close')"
        class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-xs transition-colors cursor-pointer"
      >
        Got it, thanks!
      </button>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/components/Modal.vue';
import { moduleGuides } from '@/data/moduleGuides';
import {
  BookOpen,
  ShieldCheck,
  ListOrdered,
  Sliders,
  CheckCircle2,
  Lightbulb
} from 'lucide-vue-next';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  moduleKey: {
    type: String,
    default: 'dashboard',
  },
});

defineEmits(['close']);

const allGuides = moduleGuides;
const selectedKey = ref(props.moduleKey);

watch(() => props.moduleKey, (newVal) => {
  if (newVal && allGuides[newVal]) {
    selectedKey.value = newVal;
  }
});

const groupedGuides = computed(() => {
  const groups = {};
  for (const [key, guide] of Object.entries(allGuides)) {
    const cat = guide.category || 'General';
    if (!groups[cat]) {
      groups[cat] = [];
    }
    groups[cat].push({
      key,
      title: guide.title,
    });
  }
  return groups;
});

const currentGuide = computed(() => {
  return allGuides[selectedKey.value] || allGuides['dashboard'];
});
</script>
