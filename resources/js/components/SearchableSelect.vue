<template>
  <div class="relative w-full text-left" :class="{ 'z-50': isOpen }" ref="containerRef">
    <!-- Trigger Button -->
    <button
      type="button"
      :id="id"
      :disabled="disabled"
      @click="toggleDropdown"
      @keydown.down.prevent="openAndFocus"
      @keydown.enter.prevent="openAndFocus"
      :class="[
        'w-full flex items-center justify-between text-left transition text-xs rounded-xl px-3 py-2 border shadow-sm outline-none',
        isOpen
          ? 'border-brand-500 ring-2 ring-brand-500/20 bg-white dark:bg-slate-900 text-slate-900 dark:text-white'
          : 'border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/80 hover:bg-white dark:hover:bg-slate-800 text-slate-800 dark:text-slate-100',
        disabled ? 'opacity-50 cursor-not-allowed bg-slate-100 dark:bg-slate-800' : 'cursor-pointer'
      ]"
    >
      <div class="flex items-center gap-2 overflow-hidden mr-2">
        <slot name="prefix" />

        <template v-if="selectedOption">
          <span class="font-medium truncate text-slate-900 dark:text-white">
            {{ getLabel(selectedOption) }}
          </span>
          <span v-if="getSublabel(selectedOption)" class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
            {{ getSublabel(selectedOption) }}
          </span>
          <span
            v-if="getBadge(selectedOption)"
            class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 border border-brand-200/60 dark:border-brand-800/60 shrink-0"
          >
            {{ getBadge(selectedOption) }}
          </span>
        </template>
        <template v-else>
          <span class="text-slate-400 dark:text-slate-500 truncate">
            {{ placeholder }}
          </span>
        </template>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <!-- Clear Button -->
        <span
          v-if="allowClear && selectedOption && !disabled"
          @click.stop="clearSelection"
          class="p-0.5 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
          title="Clear selection"
        >
          <X class="w-3.5 h-3.5" />
        </span>

        <!-- Chevron Icon -->
        <ChevronDown
          class="w-4 h-4 text-slate-400 transition-transform duration-200"
          :class="{ 'rotate-180 text-brand-500': isOpen }"
        />
      </div>
    </button>

    <!-- Floating Dropdown (Select2 Style with Body Teleport) -->
    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="dropdownRef"
        :style="dropdownStyle"
        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-100"
        @keydown.esc="closeDropdown"
      >
        <!-- Search Box with Search Icon -->
        <div class="p-2 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 shrink-0">
          <div class="relative flex items-center">
            <Search class="absolute left-2.5 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              autocapitalize="off"
              autocomplete="off"
              spellcheck="false"
              :placeholder="searchPlaceholder"
              @input="onSearchInput"
              @keydown.down.prevent="highlightNext"
              @keydown.up.prevent="highlightPrev"
              @keydown.enter.prevent="selectHighlighted"
              class="w-full pl-8 pr-7 py-1.5 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
            <span v-if="loading" class="absolute right-2.5">
              <Loader2 class="w-3.5 h-3.5 text-brand-500 animate-spin" />
            </span>
            <button
              v-else-if="searchQuery"
              type="button"
              @click="clearSearch"
              class="absolute right-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Result Count / Mode Note -->
          <div class="flex items-center justify-between px-1 pt-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-medium">
            <span v-if="loading" class="flex items-center gap-1.5 text-brand-500 font-medium">
              <Loader2 class="w-3 h-3 animate-spin" />
              Searching directory...
            </span>
            <span v-else>
              {{ filteredOptions.length }} {{ filteredOptions.length === 1 ? 'option' : 'options' }} available
            </span>
            <span v-if="remoteUrl">
              Live directory search
            </span>
          </div>
        </div>

        <!-- Options List -->
        <div
          ref="listRef"
          class="flex-1 overflow-y-auto divide-y divide-slate-100/60 dark:divide-slate-800/60 p-1 custom-scrollbar min-h-0"
        >
          <!-- Loading State when list is empty -->
          <div v-if="loading && filteredOptions.length === 0" class="py-8 px-4 text-center text-xs text-slate-400 dark:text-slate-500 space-y-2">
            <Loader2 class="w-5 h-5 text-brand-500 animate-spin mx-auto" />
            <p class="font-medium">Searching directory...</p>
          </div>

          <template v-else-if="filteredOptions.length > 0">
            <div
              v-for="(option, index) in filteredOptions"
              :key="getValue(option)"
              :ref="el => { if (index === highlightedIndex) highlightedItemRef = el }"
              @click="selectOption(option)"
              @mouseenter="highlightedIndex = index"
              :class="[
                'flex items-center justify-between px-3 py-2 rounded-xl text-xs cursor-pointer transition select-none',
                highlightedIndex === index
                  ? 'bg-brand-50 dark:bg-brand-950/40 text-brand-700 dark:text-brand-300 font-semibold'
                  : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60',
                isSelected(option) ? 'border border-brand-500/30 font-bold' : ''
              ]"
            >
              <div class="flex flex-col min-w-0 mr-2">
                <div class="flex items-center gap-2">
                  <span class="truncate">{{ getLabel(option) }}</span>
                  <span
                    v-if="getBadge(option)"
                    class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/50 dark:border-slate-700/50 shrink-0"
                  >
                    {{ getBadge(option) }}
                  </span>
                </div>
                <span
                  v-if="getSublabel(option)"
                  class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5"
                >
                  {{ getSublabel(option) }}
                </span>
              </div>

              <!-- Checkmark for selected item -->
              <Check
                v-if="isSelected(option)"
                class="w-4 h-4 text-brand-600 dark:text-brand-400 shrink-0 ml-2"
              />
            </div>
          </template>

          <!-- Empty State -->
          <div v-else class="py-6 px-4 text-center text-xs text-slate-400 dark:text-slate-500 space-y-1">
            <p class="font-medium">No matching options found</p>
            <p v-if="searchQuery" class="text-[11px] text-slate-400">
              No results for "<span class="text-slate-600 dark:text-slate-300 font-semibold">{{ searchQuery }}</span>"
            </p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import { ChevronDown, Search, Check, X, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: [String, Number, Object, null],
    default: null
  },
  options: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Select an option...'
  },
  searchPlaceholder: {
    type: String,
    default: 'Search 10,000+ records...'
  },
  remoteUrl: {
    type: String,
    default: null
  },
  initialOptions: {
    type: Array,
    default: () => []
  },
  labelKey: {
    type: String,
    default: 'name'
  },
  valueKey: {
    type: String,
    default: 'id'
  },
  sublabelKey: {
    type: String,
    default: 'email'
  },
  badgeKey: {
    type: String,
    default: 'department.name'
  },
  allowClear: {
    type: Boolean,
    default: true
  },
  disabled: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'select'])

const containerRef = ref(null)
const dropdownRef = ref(null)
const searchInputRef = ref(null)
const listRef = ref(null)
const highlightedItemRef = ref(null)
const dropdownStyle = ref({})

const isOpen = ref(false)
const searchQuery = ref('')
const loading = ref(false)
const remoteOptions = ref([])
const highlightedIndex = ref(0)
const cachedSelectedOption = ref(null)
let debounceTimer = null
let currentSearchSeq = 0

// Helper functions for keys
function getValue(item) {
  if (item === null || item === undefined) return null
  if (typeof item === 'object') {
    return item[props.valueKey] !== undefined ? item[props.valueKey] : item.value !== undefined ? item.value : item.id
  }
  return item
}

function getLabel(item) {
  if (item === null || item === undefined) return ''
  if (typeof item === 'object') {
    return item[props.labelKey] !== undefined ? item[props.labelKey] : item.label !== undefined ? item.label : item.name || String(item)
  }
  return String(item)
}

function getSublabel(item) {
  if (item === null || item === undefined || typeof item !== 'object') return ''
  if (props.sublabelKey && item[props.sublabelKey] !== undefined) {
    return item[props.sublabelKey]
  }
  return item.sublabel || item.email || item.description || ''
}

function getBadge(item) {
  if (item === null || item === undefined || typeof item !== 'object') return ''
  if (!props.badgeKey) return ''
  
  // Resolve dot notation e.g. "department.name"
  const parts = props.badgeKey.split('.')
  let val = item
  for (const part of parts) {
    if (val && typeof val === 'object' && part in val) {
      val = val[part]
    } else {
      return ''
    }
  }
  return typeof val === 'string' || typeof val === 'number' ? String(val) : ''
}

// Current Selected Option Object (searches cached, initialOptions, remoteOptions, props.options)
const selectedOption = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
    return null
  }
  // Check cached first if it matches modelValue
  if (cachedSelectedOption.value && getValue(cachedSelectedOption.value) === props.modelValue) {
    return cachedSelectedOption.value
  }
  const allKnown = [
    ...(props.options || []),
    ...(props.initialOptions || []),
    ...remoteOptions.value
  ]
  const found = allKnown.find(opt => getValue(opt) === props.modelValue)
  if (found) {
    cachedSelectedOption.value = found
    return found
  }
  return cachedSelectedOption.value
})

// Filtered options based on search query for the dropdown menu
const filteredOptions = computed(() => {
  if (props.remoteUrl) {
    const query = searchQuery.value.trim()
    // When actively searching via remote URL, return ONLY remoteOptions (do NOT pollute with initialOptions)
    if (query) {
      return remoteOptions.value
    }
    // When search query is empty, return remoteOptions if fetched, or fallback to initialOptions
    return remoteOptions.value.length > 0 ? remoteOptions.value : (props.initialOptions || [])
  }

  const query = searchQuery.value.trim().toLowerCase()
  const list = props.options || []
  if (!query) {
    return list
  }

  return list.filter(opt => {
    const label = getLabel(opt).toLowerCase()
    const sublabel = getSublabel(opt).toLowerCase()
    const badge = getBadge(opt).toLowerCase()
    return label.includes(query) || sublabel.includes(query) || badge.includes(query)
  })
})

function isSelected(option) {
  return getValue(option) === props.modelValue
}

function updateDropdownPosition() {
  if (typeof window === 'undefined' || !containerRef.value || !isOpen.value) return

  const rect = containerRef.value.getBoundingClientRect()

  // If container is scrolled out of view, close dropdown
  if (rect.bottom < 0 || rect.top > window.innerHeight) {
    closeDropdown()
    return
  }

  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top

  // If space below is limited and space above is greater, flip upwards
  const openUpward = spaceBelow < 260 && spaceAbove > spaceBelow

  const minWidth = Math.max(rect.width, 240)
  // Ensure dropdown doesn't overflow right edge of viewport
  const left = Math.min(rect.left, window.innerWidth - minWidth - 16)

  if (openUpward) {
    const maxHeight = Math.min(320, Math.max(160, spaceAbove - 16))
    dropdownStyle.value = {
      position: 'fixed',
      left: `${Math.max(16, left)}px`,
      bottom: `${window.innerHeight - rect.top + 6}px`,
      width: `${rect.width}px`,
      minWidth: `${minWidth}px`,
      maxHeight: `${maxHeight}px`,
      zIndex: 99999,
    }
  } else {
    const maxHeight = Math.min(320, Math.max(160, spaceBelow - 16))
    dropdownStyle.value = {
      position: 'fixed',
      left: `${Math.max(16, left)}px`,
      top: `${rect.bottom + 6}px`,
      width: `${rect.width}px`,
      minWidth: `${minWidth}px`,
      maxHeight: `${maxHeight}px`,
      zIndex: 99999,
    }
  }
}

function handleScrollOrResize() {
  if (isOpen.value) {
    updateDropdownPosition()
  }
}

function toggleDropdown() {
  if (props.disabled) return
  if (isOpen.value) {
    closeDropdown()
  } else {
    openDropdown()
  }
}

function openDropdown() {
  isOpen.value = true
  highlightedIndex.value = 0
  updateDropdownPosition()
  nextTick(() => {
    updateDropdownPosition()
    if (searchInputRef.value) {
      searchInputRef.value.focus()
    }
    // If remote, fetch initial list if we have neither remoteOptions nor initialOptions
    if (props.remoteUrl && remoteOptions.value.length === 0 && (!props.initialOptions || props.initialOptions.length === 0)) {
      fetchRemoteOptions('')
    }
  })
}

function openAndFocus() {
  if (!isOpen.value) {
    openDropdown()
  }
}

function closeDropdown() {
  isOpen.value = false
  searchQuery.value = ''
  loading.value = false
  remoteOptions.value = []
}

function clearSearch() {
  searchQuery.value = ''
  currentSearchSeq++
  loading.value = false
  remoteOptions.value = []
  if (props.remoteUrl) {
    fetchRemoteOptions('')
  }
  if (searchInputRef.value) {
    searchInputRef.value.focus()
  }
}

function selectOption(option) {
  cachedSelectedOption.value = option
  const val = getValue(option)
  emit('update:modelValue', val)
  emit('change', val)
  emit('select', option)
  closeDropdown()
}

function clearSelection() {
  cachedSelectedOption.value = null
  emit('update:modelValue', null)
  emit('change', null)
  emit('select', null)
}

function highlightNext() {
  if (filteredOptions.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + 1) % filteredOptions.value.length
  scrollToHighlighted()
}

function highlightPrev() {
  if (filteredOptions.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value - 1 + filteredOptions.value.length) % filteredOptions.value.length
  scrollToHighlighted()
}

function selectHighlighted() {
  if (filteredOptions.value.length > 0 && highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
    selectOption(filteredOptions.value[highlightedIndex.value])
  }
}

function scrollToHighlighted() {
  nextTick(() => {
    if (highlightedItemRef.value && listRef.value) {
      highlightedItemRef.value.scrollIntoView({ block: 'nearest' })
    }
  })
}

// Remote API search with debounce
function onSearchInput() {
  highlightedIndex.value = 0
  if (!props.remoteUrl) return

  loading.value = true
  clearTimeout(debounceTimer)
  const seq = ++currentSearchSeq
  const query = searchQuery.value

  debounceTimer = setTimeout(() => {
    fetchRemoteOptions(query, seq)
  }, 250)
}

async function fetchRemoteOptions(query, seq = null) {
  if (!props.remoteUrl) return
  loading.value = true
  try {
    const params = {
      q: query || '',
      limit: 50
    }
    if (props.modelValue) {
      params.include_id = props.modelValue
    }
    const response = await axios.get(props.remoteUrl, { params })
    if (seq !== null && seq !== currentSearchSeq) {
      return
    }
    const data = Array.isArray(response.data) ? response.data : (response.data.users || response.data.data || [])
    remoteOptions.value = data
  } catch (err) {
    if (seq === null || seq === currentSearchSeq) {
      console.error('SearchableSelect remote fetch error:', err)
    }
  } finally {
    if (seq === null || seq === currentSearchSeq) {
      loading.value = false
    }
  }
}

// Click outside handler
function handleClickOutside(event) {
  if (!isOpen.value) return
  const isInsideContainer = containerRef.value && containerRef.value.contains(event.target)
  const isInsideDropdown = dropdownRef.value && dropdownRef.value.contains(event.target)
  if (!isInsideContainer && !isInsideDropdown) {
    closeDropdown()
  }
}

// Pre-fetch remote option if modelValue is provided
watch(() => props.modelValue, (newVal) => {
  if (newVal === null || newVal === undefined || newVal === '') {
    cachedSelectedOption.value = null
    return
  }
  if (cachedSelectedOption.value && getValue(cachedSelectedOption.value) === newVal) {
    return
  }
  const allKnown = [
    ...(props.options || []),
    ...(props.initialOptions || []),
    ...remoteOptions.value
  ]
  const found = allKnown.find(opt => getValue(opt) === newVal)
  if (found) {
    cachedSelectedOption.value = found
  } else if (props.remoteUrl) {
    fetchRemoteOptions('')
  }
}, { immediate: true })

onMounted(() => {
  document.addEventListener('click', handleClickOutside, true)
  window.addEventListener('scroll', handleScrollOrResize, true)
  window.addEventListener('resize', handleScrollOrResize)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside, true)
  window.removeEventListener('scroll', handleScrollOrResize, true)
  window.removeEventListener('resize', handleScrollOrResize)
  clearTimeout(debounceTimer)
})
</script>
