<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
          <Mail class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          <span>Email Notification Templates</span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Configure, brand, preview, and test all automated transactional emails, host credential delivery, and reports
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Search input -->
        <div class="relative w-48 sm:w-64">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search templates or merge tags..."
            class="w-full pl-9 pr-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Reset All Button -->
        <button
          @click="showResetAllModal = true"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 dark:hover:bg-rose-950/30 transition shadow-sm cursor-pointer"
          title="Restore all templates to institutional defaults"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span class="hidden md:inline">Reset Defaults</span>
        </button>

        <!-- Refresh Button -->
        <button
          @click="fetchTemplates"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition cursor-pointer"
          title="Refresh Templates"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Quick Stats & Category Pills -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/80 dark:border-slate-800 pb-3">
      <!-- Category Pills -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
        <button
          v-for="cat in categories"
          :key="cat.id"
          @click="selectedCategory = cat.id"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
          :class="selectedCategory === cat.id ? 'bg-brand-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
        >
          <component :is="cat.icon" class="w-3.5 h-3.5" />
          <span>{{ cat.label }}</span>
          <span
            class="px-1.5 py-0.2 rounded-md text-[10px]"
            :class="selectedCategory === cat.id ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
          >
            {{ getCategoryCount(cat.id) }}
          </span>
        </button>
      </div>

      <!-- Quick Metrics -->
      <div class="flex items-center gap-3 text-xs text-slate-500 shrink-0">
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span><strong>{{ activeCount }}</strong> Active</span>
        </span>
        <span>•</span>
        <span><strong>{{ templates.length }}</strong> Total Templates</span>
      </div>
    </div>

    <!-- Feedback Banner -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl text-xs font-semibold flex items-center justify-between transition-all"
      :class="feedbackType === 'error' ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="feedbackType !== 'error'" class="w-4 h-4 shrink-0" />
        <AlertCircle v-else class="w-4 h-4 shrink-0" />
        <span>{{ feedback }}</span>
      </div>
      <button @click="feedback = ''" class="hover:underline cursor-pointer">Dismiss</button>
    </div>

    <!-- Templates Grid -->
    <div v-if="loading && !templates.length" class="p-16 text-center text-slate-400 glass-card rounded-2xl">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Loading system email templates...</p>
    </div>

    <div v-else-if="!filteredTemplates.length" class="p-16 text-center text-slate-400 glass-card rounded-2xl space-y-2">
      <FileText class="w-10 h-10 mx-auto opacity-40 text-slate-400" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
        {{ searchQuery ? 'No Matching Templates' : 'No Templates in this Category' }}
      </p>
      <p class="text-xs text-slate-400">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Switch categories above to view other templates.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="tpl in filteredTemplates"
        :key="tpl.id"
        class="glass-card rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition flex flex-col justify-between space-y-4 group"
      >
        <div class="space-y-3">
          <!-- Card Header: Title, Category & Status -->
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-sm text-slate-900 dark:text-white">{{ tpl.name }}</span>
                <span
                  class="px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wider"
                  :class="getCategoryBadgeClass(tpl.category)"
                >
                  {{ tpl.category || 'General' }}
                </span>
              </div>
              <div class="flex items-center gap-1.5 text-xs text-slate-400 font-mono">
                <code>{{ tpl.key }}</code>
              </div>
            </div>

            <!-- Active / Inactive Pill -->
            <button
              @click="toggleTemplateActive(tpl)"
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition cursor-pointer"
              :class="tpl.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20' : 'bg-slate-200 dark:bg-slate-800 text-slate-500 hover:bg-slate-300'"
              :title="tpl.is_active ? 'Click to disable' : 'Click to enable'"
            >
              {{ tpl.is_active ? 'Active' : 'Disabled' }}
            </button>
          </div>

          <!-- Description -->
          <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
            {{ tpl.description || 'Automated notification template.' }}
          </p>

          <!-- Subject Preview Line -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 text-xs font-mono space-y-0.5">
            <div class="text-[10px] uppercase font-bold text-slate-400">Subject Format</div>
            <div class="text-slate-800 dark:text-slate-200 truncate" :title="tpl.subject_template">
              {{ tpl.subject_template }}
            </div>
          </div>

          <!-- Key Variables Chips -->
          <div v-if="tpl.available_variables?.length" class="space-y-1">
            <div class="text-[10px] uppercase font-bold text-slate-400">Merge Tags ({{ tpl.available_variables.length }})</div>
            <div class="flex items-center gap-1 flex-wrap">
              <span
                v-for="tag in tpl.available_variables.slice(0, 4)"
                :key="tag"
                class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono text-[10px]"
              >
                &#123;&#123; {{ tag }} &#125;&#125;
              </span>
              <span
                v-if="tpl.available_variables.length > 4"
                class="text-[10px] font-semibold text-slate-400"
              >
                +{{ tpl.available_variables.length - 4 }} more
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5">
            <!-- Send Test Quick Button -->
            <button
              @click="openQuickTestModal(tpl)"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
              title="Send a sample test email"
            >
              <Send class="w-3.5 h-3.5 text-sky-500" />
              <span>Test</span>
            </button>

            <!-- Reset to Default Button -->
            <button
              @click="confirmResetSingle(tpl)"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
              title="Reset this template to factory default"
            >
              <RotateCcw class="w-3.5 h-3.5 text-amber-500" />
              <span>Default</span>
            </button>
          </div>

          <!-- Edit Template Button -->
          <button
            @click="openEditModal(tpl)"
            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-bold bg-brand-600 hover:bg-brand-700 text-white shadow-xs shadow-brand-500/20 transition cursor-pointer"
          >
            <Edit3 class="w-3.5 h-3.5" />
            <span>Configure &amp; Edit</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Full Edit & Live Preview Modal -->
    <Teleport to="body">
      <div
        v-if="editingModal"
        v-scroll-lock
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/75 backdrop-blur-sm overflow-y-auto"
      >
        <div class="w-full max-w-5xl max-h-[92vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="p-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between bg-slate-50/60 dark:bg-slate-900/60 shrink-0">
            <div class="flex items-center gap-3 truncate">
              <div class="w-8 h-8 rounded-xl bg-brand-50 dark:bg-brand-950/60 border border-brand-200 dark:border-brand-800 flex items-center justify-center text-brand-600 dark:text-brand-400 shrink-0">
                <Mail class="w-4 h-4" />
              </div>
              <div class="truncate">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                  Configure {{ selectedTemplate?.name }}
                </h2>
                <div class="text-[11px] text-slate-400 font-mono">
                  key: {{ selectedTemplate?.key }} • category: {{ selectedTemplate?.category || 'general' }}
                </div>
              </div>
            </div>

            <!-- Tab Switchers in Header -->
            <div class="flex items-center gap-2">
              <div class="flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700">
                <button
                  type="button"
                  @click="activeEditorTab = 'editor'"
                  class="px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                  :class="activeEditorTab === 'editor' ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                  <span>Editor</span>
                </button>
                <button
                  type="button"
                  @click="switchToPreviewTab"
                  class="px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                  :class="activeEditorTab === 'preview' ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                  <Eye class="w-3.5 h-3.5" />
                  <span>Live Preview</span>
                </button>
                <button
                  type="button"
                  @click="activeEditorTab = 'test'"
                  class="px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                  :class="activeEditorTab === 'test' ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                  <Send class="w-3.5 h-3.5 text-sky-500" />
                  <span>Send Test</span>
                </button>
              </div>

              <button
                @click="editingModal = false"
                class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <!-- TAB 1: Editor Form -->
            <div v-show="activeEditorTab === 'editor'" class="space-y-4">
              <!-- Name & Subject Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Template Display Name *
                  </label>
                  <input
                    v-model="editForm.name"
                    type="text"
                    required
                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                  />
                </div>

                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                      Email Subject Template *
                    </label>
                    <span class="text-[10px] text-slate-400 font-mono">Supports merge tags</span>
                  </div>
                  <input
                    ref="subjectInput"
                    v-model="editForm.subject_template"
                    type="text"
                    required
                    class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                  />
                </div>
              </div>

              <!-- Categorized Merge Variables Palette -->
              <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <Tag class="w-3.5 h-3.5 text-brand-500" />
                    <span>Dynamic Merge Tags (Click to insert into HTML or Subject)</span>
                  </span>
                  <span class="text-[10px] text-slate-400">Target: {{ focusedField === 'subject' ? 'Subject' : 'HTML Body' }}</span>
                </div>

                <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto">
                  <button
                    type="button"
                    v-for="tag in activeAvailableTags"
                    :key="tag"
                    @click="insertTag(tag)"
                    class="px-2 py-0.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 font-mono text-[10px] text-brand-600 dark:text-brand-400 hover:border-brand-500 hover:bg-brand-50 dark:hover:bg-brand-950/40 transition cursor-pointer"
                    :title="`Click to insert {{ ${tag} }}`"
                  >
                    &#123;&#123; {{ tag }} &#125;&#125;
                  </button>
                </div>
              </div>

              <!-- HTML Body Template -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <span>HTML Body Template *</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded font-mono bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 font-bold">Responsive HTML</span>
                  </label>
                  <button
                    type="button"
                    @click="restoreCurrentFactoryDefault"
                    class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer flex items-center gap-1"
                  >
                    <RotateCcw class="w-3 h-3" />
                    <span>Reset HTML to Default</span>
                  </button>
                </div>
                <textarea
                  ref="htmlTextarea"
                  v-model="editForm.body_html_template"
                  rows="12"
                  required
                  @focus="focusedField = 'html'"
                  class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 leading-relaxed font-mono"
                  placeholder="<div>Write custom HTML email layout here...</div>"
                ></textarea>
              </div>

              <!-- Plain Text Fallback Body -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Plain-Text Fallback Body *
                  </label>
                  <span class="text-[10px] text-slate-400">Delivered to clients without HTML support</span>
                </div>
                <textarea
                  ref="plainTextarea"
                  v-model="editForm.body_text_template"
                  rows="4"
                  required
                  @focus="focusedField = 'text'"
                  class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                  placeholder="Plain-text fallback message..."
                ></textarea>
              </div>
            </div>

            <!-- TAB 2: Live Preview -->
            <div v-show="activeEditorTab === 'preview'" class="space-y-4">
              <!-- Preview Controls Bar -->
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                  <span class="text-slate-500 font-semibold">Device Viewport:</span>
                  <div class="flex items-center p-0.5 rounded-lg bg-slate-200/80 dark:bg-slate-700">
                    <button
                      type="button"
                      @click="previewDevice = 'desktop'"
                      class="px-2.5 py-1 rounded text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                      :class="previewDevice === 'desktop' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'"
                    >
                      <Monitor class="w-3.5 h-3.5" />
                      <span>Desktop (600px)</span>
                    </button>
                    <button
                      type="button"
                      @click="previewDevice = 'mobile'"
                      class="px-2.5 py-1 rounded text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                      :class="previewDevice === 'mobile' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'"
                    >
                      <Smartphone class="w-3.5 h-3.5" />
                      <span>Mobile (375px)</span>
                    </button>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <!-- Mode Switcher -->
                  <div class="flex items-center p-0.5 rounded-lg bg-slate-200/80 dark:bg-slate-700">
                    <button
                      type="button"
                      @click="previewFormat = 'html'"
                      class="px-2.5 py-1 rounded text-xs font-bold transition cursor-pointer"
                      :class="previewFormat === 'html' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'"
                    >
                      HTML View
                    </button>
                    <button
                      type="button"
                      @click="previewFormat = 'text'"
                      class="px-2.5 py-1 rounded text-xs font-bold transition cursor-pointer"
                      :class="previewFormat === 'text' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'"
                    >
                      Plain Text View
                    </button>
                  </div>

                  <button
                    type="button"
                    @click="fetchLivePreview"
                    :disabled="loadingPreview"
                    class="p-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 transition cursor-pointer"
                    title="Refresh Preview"
                  >
                    <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingPreview }" />
                  </button>
                </div>
              </div>

              <!-- Subject Line Preview -->
              <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono space-y-1">
                <div class="text-[10px] uppercase font-bold text-slate-400">Rendered Subject Line</div>
                <div class="font-bold text-slate-900 dark:text-white text-sm">
                  {{ renderedPreview?.subject || 'Generating preview...' }}
                </div>
              </div>

              <!-- Rendered Preview Area -->
              <div class="flex justify-center p-4 bg-slate-100/80 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-x-auto min-h-[350px]">
                <!-- HTML Preview Viewport -->
                <div
                  v-if="previewFormat === 'html'"
                  class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 transition-all"
                  :style="{ width: previewDevice === 'mobile' ? '375px' : '600px' }"
                >
                  <div v-html="renderedPreview?.html"></div>
                </div>

                <!-- Plain Text Viewport -->
                <div
                  v-else
                  class="bg-slate-900 text-slate-100 font-mono text-xs p-5 rounded-xl shadow-lg border border-slate-800 whitespace-pre-wrap transition-all"
                  :style="{ width: previewDevice === 'mobile' ? '375px' : '600px' }"
                >
                  {{ renderedPreview?.text }}
                </div>
              </div>
            </div>

            <!-- TAB 3: Send Test Email -->
            <div v-show="activeEditorTab === 'test'" class="space-y-4 max-w-xl mx-auto py-6">
              <div class="p-4 rounded-xl bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 text-xs space-y-1 text-sky-800 dark:text-sky-200">
                <div class="font-bold flex items-center gap-1.5 text-sm">
                  <Send class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                  <span>Send Real Test Email</span>
                </div>
                <p>
                  Dispatch a real test message rendered using your current template edits and realistic sample context directly to your email inbox.
                </p>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                    Target Recipient Email *
                  </label>
                  <input
                    v-model="testRecipientEmail"
                    type="email"
                    required
                    placeholder="admin@university.edu"
                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 font-mono"
                  />
                  <p class="text-[11px] text-slate-400 mt-1">
                    Defaults to your logged-in administrator account email.
                  </p>
                </div>

                <div class="pt-2">
                  <button
                    type="button"
                    @click="executeSendTest"
                    :disabled="sendingTestEmail"
                    class="w-full py-2.5 rounded-xl text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 shadow-md shadow-sky-500/20 transition disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    <RefreshCw v-if="sendingTestEmail" class="w-4 h-4 animate-spin" />
                    <Send v-else class="w-4 h-4" />
                    <span>{{ sendingTestEmail ? 'Sending Test Message...' : 'Send Test Email Now' }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-between bg-slate-50/60 dark:bg-slate-900/60 shrink-0">
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
              <input
                type="checkbox"
                v-model="editForm.is_active"
                class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
              />
              <span>Enable this template for automated delivery</span>
            </label>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="editingModal = false"
                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                @click="saveTemplate"
                :disabled="saving"
                class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 flex items-center gap-2 cursor-pointer"
              >
                <RefreshCw v-if="saving" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ saving ? 'Saving Changes...' : 'Save Template' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Quick Test Send Modal (from Card) -->
    <Teleport to="body">
      <div
        v-if="quickTestModal"
        v-scroll-lock
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm overflow-y-auto"
      >
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-4 my-auto">
          <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <Send class="w-4 h-4 text-sky-500" />
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">Send Test Email</h3>
            </div>
            <button @click="quickTestModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="w-4 h-4" />
            </button>
          </div>

          <div class="text-xs bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60 space-y-1">
            <div class="font-bold text-slate-900 dark:text-white">{{ targetTestTemplate?.name }}</div>
            <div class="text-slate-400 font-mono text-[11px] truncate">Subject: {{ targetTestTemplate?.subject_template }}</div>
          </div>

          <form @submit.prevent="executeQuickSendTest" class="space-y-3.5 text-xs">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Recipient Email *</label>
              <input
                v-model="quickTestEmail"
                type="email"
                required
                placeholder="you@university.edu"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 font-mono"
              />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="quickTestModal = false"
                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="sendingQuickTest"
                class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 shadow-md shadow-sky-500/20 transition disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
              >
                <RefreshCw v-if="sendingQuickTest" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ sendingQuickTest ? 'Sending...' : 'Send Test' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Reset All Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="showResetAllModal"
        v-scroll-lock
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm overflow-y-auto"
      >
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-4 my-auto">
          <div class="flex items-center gap-3 text-rose-600">
            <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800">
              <RotateCcw class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reset All Templates?</h3>
              <p class="text-xs text-slate-500">Restore factory institutional HTML and copy</p>
            </div>
          </div>

          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            This will overwrite custom HTML and subject lines across all {{ templates.length }} templates with standard factory designs. This action cannot be undone.
          </p>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="showResetAllModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="executeResetAll"
              :disabled="resettingAll"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-md shadow-rose-500/20 transition disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
            >
              <RefreshCw v-if="resettingAll" class="w-3.5 h-3.5 animate-spin" />
              <span>{{ resettingAll ? 'Resetting...' : 'Yes, Reset All' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import {
  Mail,
  FileText,
  Edit3,
  Eye,
  RefreshCw,
  Search,
  X,
  Send,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  Tag,
  Monitor,
  Smartphone,
  Calendar,
  ShieldCheck,
  Clock,
  Video,
  Users,
  Ban,
  Layers,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const toast = useToastStore();

const templates = ref([]);
const loading = ref(false);
const feedback = ref('');
const feedbackType = ref('success');
const searchQuery = ref('');
const selectedCategory = ref('all');

// Modals state
const editingModal = ref(false);
const selectedTemplate = ref(null);
const activeEditorTab = ref('editor');
const focusedField = ref('html'); // 'subject', 'html', 'text'
const saving = ref(false);

// Live preview state
const loadingPreview = ref(false);
const renderedPreview = ref(null);
const previewDevice = ref('desktop'); // 'desktop' or 'mobile'
const previewFormat = ref('html'); // 'html' or 'text'

// Send test email state
const testRecipientEmail = ref('');
const sendingTestEmail = ref(false);

// Quick test modal
const quickTestModal = ref(false);
const targetTestTemplate = ref(null);
const quickTestEmail = ref('');
const sendingQuickTest = ref(false);

// Reset all modal
const showResetAllModal = ref(false);
const resettingAll = ref(false);

// Refs for cursor insertion
const subjectInput = ref(null);
const htmlTextarea = ref(null);
const plainTextarea = ref(null);

const editForm = ref({
  name: '',
  category: '',
  description: '',
  subject_template: '',
  body_html_template: '',
  body_text_template: '',
  is_active: true,
});

const categories = [
  { id: 'all', label: 'All Templates', icon: Layers },
  { id: 'bookings', label: 'Bookings & Schedules', icon: Calendar },
  { id: 'approvals', label: 'Approvals & Workflow', icon: ShieldCheck },
  { id: 'waitlist', label: 'Waitlist Queue', icon: Clock },
  { id: 'recordings', label: 'Recordings & Media', icon: Video },
  { id: 'attendance', label: 'Attendance & Reports', icon: Users },
  { id: 'cancellations', label: 'Cancellations', icon: Ban },
];

const activeCount = computed(() => {
  return templates.value.filter((t) => t.is_active).length;
});

const getCategoryCount = (catId) => {
  if (catId === 'all') return templates.value.length;
  return templates.value.filter((t) => t.category === catId).length;
};

const getCategoryBadgeClass = (category) => {
  switch (category) {
    case 'bookings':
      return 'bg-blue-500/10 text-blue-600 dark:text-blue-400';
    case 'approvals':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
    case 'waitlist':
      return 'bg-purple-500/10 text-purple-600 dark:text-purple-400';
    case 'recordings':
      return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
    case 'attendance':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    case 'cancellations':
      return 'bg-rose-500/10 text-rose-600 dark:text-rose-400';
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
  }
};

const filteredTemplates = computed(() => {
  let list = templates.value;

  // Filter by category
  if (selectedCategory.value !== 'all') {
    list = list.filter((t) => t.category === selectedCategory.value);
  }

  // Filter by query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter((t) => {
      const name = (t.name || '').toLowerCase();
      const key = (t.key || '').toLowerCase();
      const subj = (t.subject_template || '').toLowerCase();
      const desc = (t.description || '').toLowerCase();
      const vars = (t.available_variables || []).join(' ').toLowerCase();

      return name.includes(q) || key.includes(q) || subj.includes(q) || desc.includes(q) || vars.includes(q);
    });
  }

  return list;
});

const activeAvailableTags = computed(() => {
  if (selectedTemplate.value?.available_variables?.length) {
    return selectedTemplate.value.available_variables;
  }
  return [
    'org.name',
    'org.website',
    'recipient.name',
    'recipient.email',
    'meeting.title',
    'meeting.starts_at',
    'meeting.ends_at',
    'meeting.duration_minutes',
    'meeting.join_url',
    'meeting.passcode',
    'meeting.host_key',
    'review_url',
    'reason',
  ];
});

const fetchTemplates = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/email-templates', {
      headers: { Accept: 'application/json' },
    });
    templates.value = Array.isArray(res.data) ? res.data : (res.data?.templates || []);
  } catch (err) {
    console.error('Failed to load email templates', err);
    toast.error('Failed to load email templates.');
  } finally {
    loading.value = false;
  }
};

const toggleTemplateActive = async (tpl) => {
  try {
    const updatedStatus = !tpl.is_active;
    await axios.put(
      `/admin/email-templates/${tpl.id}`,
      {
        ...tpl,
        is_active: updatedStatus,
      },
      { headers: { Accept: 'application/json' } }
    );
    tpl.is_active = updatedStatus;
    toast.success(`"${tpl.name}" is now ${updatedStatus ? 'Active' : 'Disabled'}.`);
  } catch (err) {
    toast.error('Failed to update template status.');
  }
};

const openEditModal = (tpl) => {
  selectedTemplate.value = tpl;
  editForm.value = {
    name: tpl.name,
    category: tpl.category || '',
    description: tpl.description || '',
    subject_template: tpl.subject_template,
    body_html_template: tpl.body_html_template,
    body_text_template: tpl.body_text_template,
    is_active: !!tpl.is_active,
  };
  activeEditorTab.value = 'editor';
  renderedPreview.value = null;
  focusedField.value = 'html';
  testRecipientEmail.value = authStore.user?.email || '';
  editingModal.value = true;
};

const insertTag = (tag) => {
  const insertText = `{{ ${tag} }}`;

  if (focusedField.value === 'subject' && subjectInput.value) {
    const input = subjectInput.value;
    const start = input.selectionStart || editForm.value.subject_template.length;
    const end = input.selectionEnd || editForm.value.subject_template.length;
    const current = editForm.value.subject_template;
    editForm.value.subject_template = current.substring(0, start) + insertText + current.substring(end);
    return;
  }

  if (focusedField.value === 'text' && plainTextarea.value) {
    const ta = plainTextarea.value;
    const start = ta.selectionStart || editForm.value.body_text_template.length;
    const end = ta.selectionEnd || editForm.value.body_text_template.length;
    const current = editForm.value.body_text_template;
    editForm.value.body_text_template = current.substring(0, start) + insertText + current.substring(end);
    return;
  }

  // Default to HTML textarea
  if (htmlTextarea.value) {
    const ta = htmlTextarea.value;
    const start = ta.selectionStart || editForm.value.body_html_template.length;
    const end = ta.selectionEnd || editForm.value.body_html_template.length;
    const current = editForm.value.body_html_template;
    editForm.value.body_html_template = current.substring(0, start) + insertText + current.substring(end);
  } else {
    editForm.value.body_html_template += ` ${insertText}`;
  }
};

const switchToPreviewTab = async () => {
  activeEditorTab.value = 'preview';
  await fetchLivePreview();
};

const fetchLivePreview = async () => {
  if (!selectedTemplate.value) return;
  loadingPreview.value = true;
  try {
    const res = await axios.post(
      `/admin/email-templates/${selectedTemplate.value.id}/preview`,
      {
        subject_template: editForm.value.subject_template,
        body_html_template: editForm.value.body_html_template,
        body_text_template: editForm.value.body_text_template,
      },
      { headers: { Accept: 'application/json' } }
    );
    renderedPreview.value = res.data;
  } catch (err) {
    console.error('Failed to generate preview', err);
    toast.error('Failed to generate live preview.');
  } finally {
    loadingPreview.value = false;
  }
};

const restoreCurrentFactoryDefault = async () => {
  if (!confirm(`Restore default HTML layout for "${selectedTemplate.value?.name}"? Your edits will be replaced.`)) return;
  try {
    const res = await axios.post(`/admin/email-templates/${selectedTemplate.value.id}/reset`);
    if (res.data.template) {
      editForm.value.body_html_template = res.data.template.body_html_template;
      editForm.value.body_text_template = res.data.template.body_text_template;
      editForm.value.subject_template = res.data.template.subject_template;
      toast.info('Template content restored to factory default.');
      if (activeEditorTab.value === 'preview') {
        await fetchLivePreview();
      }
    }
  } catch (err) {
    toast.error('Failed to restore default.');
  }
};

const saveTemplate = async () => {
  if (!selectedTemplate.value) return;
  saving.value = true;
  try {
    const res = await axios.put(
      `/admin/email-templates/${selectedTemplate.value.id}`,
      editForm.value,
      { headers: { Accept: 'application/json' } }
    );
    toast.success(res.data.message || `Template "${editForm.value.name}" saved successfully.`);
    feedback.value = `Template "${editForm.value.name}" saved successfully.`;
    feedbackType.value = 'success';
    editingModal.value = false;
    await fetchTemplates();
  } catch (err) {
    console.error('Failed to save template', err);
    toast.error(err.response?.data?.message || 'Failed to save template changes.');
  } finally {
    saving.value = false;
  }
};

const executeSendTest = async () => {
  if (!testRecipientEmail.value) {
    toast.error('Please enter a recipient email address.');
    return;
  }
  sendingTestEmail.value = true;
  try {
    const res = await axios.post(
      `/admin/email-templates/${selectedTemplate.value.id}/send-test`,
      {
        recipient_email: testRecipientEmail.value,
        subject_template: editForm.value.subject_template,
        body_html_template: editForm.value.body_html_template,
        body_text_template: editForm.value.body_text_template,
      },
      { headers: { Accept: 'application/json' } }
    );
    toast.success(res.data.message || 'Test email dispatched successfully!');
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to send test email.');
  } finally {
    sendingTestEmail.value = false;
  }
};

const openQuickTestModal = (tpl) => {
  targetTestTemplate.value = tpl;
  quickTestEmail.value = authStore.user?.email || '';
  quickTestModal.value = true;
};

const executeQuickSendTest = async () => {
  if (!targetTestTemplate.value || !quickTestEmail.value) return;
  sendingQuickTest.value = true;
  try {
    const res = await axios.post(
      `/admin/email-templates/${targetTestTemplate.value.id}/send-test`,
      {
        recipient_email: quickTestEmail.value,
      },
      { headers: { Accept: 'application/json' } }
    );
    toast.success(res.data.message || 'Test email sent successfully!');
    quickTestModal.value = false;
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to send test email.');
  } finally {
    sendingQuickTest.value = false;
  }
};

const confirmResetSingle = async (tpl) => {
  if (!confirm(`Reset "${tpl.name}" back to its factory default HTML and subject layout?`)) return;
  try {
    const res = await axios.post(`/admin/email-templates/${tpl.id}/reset`);
    toast.success(res.data.message || `Reset "${tpl.name}" to default.`);
    await fetchTemplates();
  } catch (err) {
    toast.error('Failed to reset template.');
  }
};

const executeResetAll = async () => {
  resettingAll.value = true;
  try {
    const res = await axios.post('/admin/email-templates/reset-all');
    toast.success(res.data.message || 'All templates restored to defaults.');
    showResetAllModal.value = false;
    await fetchTemplates();
  } catch (err) {
    toast.error('Failed to reset templates.');
  } finally {
    resettingAll.value = false;
  }
};

onMounted(() => {
  fetchTemplates();
});
</script>
