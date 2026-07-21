<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  tabs: {
    type: Array,
    default: () => []
  },
  modelValue: {
    type: [String, Number],
    default: null
  }
});

const emit = defineEmits(['update:modelValue', 'close', 'tab-change']);

const activeTab = ref(props.modelValue || (props.tabs.length > 0 ? props.tabs[0].id : null));

// Expose the active tab to parent
const setActiveTab = (tabId) => {
  activeTab.value = tabId;
  emit('update:modelValue', tabId);
  emit('tab-change', tabId);
};

// Close a tab
const closeTab = (tabId, event) => {
  event.stopPropagation();
  emit('close', tabId);

  // If closing the active tab, switch to the previous one
  if (activeTab.value === tabId) {
    const tabIndex = props.tabs.findIndex(t => t.id === tabId);
    if (tabIndex > 0) {
      setActiveTab(props.tabs[tabIndex - 1].id);
    } else if (props.tabs.length > 1) {
      setActiveTab(props.tabs[1].id);
    } else {
      activeTab.value = null;
    }
  }
};

// Handle tab click
const selectTab = (tabId) => {
  if (!tabId.disabled) {
    setActiveTab(tabId);
  }
};

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (newValue !== activeTab.value) {
    activeTab.value = newValue;
  }
});

// Computed properties
const currentTab = computed(() => props.tabs.find(t => t.id === activeTab.value));

// Expose methods
defineExpose({
  setActiveTab,
  closeTab,
  activeTab,
  currentTab
});
</script>

<template>
  <div class="dynamic-tabs-container">
    <!-- Tab Navigation -->
    <div class="flex items-center gap-1 px-2 pt-2 bg-gray-50 border-b border-gray-200">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="selectTab(tab.id)"
        :class="[
          'relative flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-all duration-200 min-w-[140px] max-w-[200px]',
          activeTab === tab.id
            ? 'text-[#062121] bg-white border-t-2 border-t-[#C5F82A] shadow-sm -mb-[1px] z-10'
            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100/50',
          tab.disabled && 'opacity-50 cursor-not-allowed'
        ]"
        :disabled="tab.disabled"
      >
        <i v-if="tab.icon" :class="tab.icon"></i>
        <span class="truncate flex-1">{{ tab.label }}</span>

        <!-- Close button for closable tabs -->
        <button
          v-if="tab.closable && !tab.disabled"
          @click="closeTab(tab.id, $event)"
          class="ml-1 flex-shrink-0 w-5 h-5 rounded-full hover:bg-gray-200 transition-colors flex items-center justify-center"
          :class="activeTab === tab.id ? 'hover:bg-gray-200' : 'hover:bg-gray-300'"
        >
          <i class="fas fa-times text-xs"></i>
        </button>

        <!-- Active indicator -->
        <div
          v-if="activeTab === tab.id"
          class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#C5F82A]"
        ></div>
      </button>

      <!-- Add tab button slot -->
      <slot name="add-tab-button"></slot>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <slot :current-tab="currentTab" :active-tab="activeTab"></slot>
    </div>
  </div>
</template>

<style scoped>
.dynamic-tabs-container {
  background-color: white;
  border-radius: 1rem;
  border: 1px solid rgb(229 231 235);
  overflow: hidden;
}

.tab-content {
  background-color: white;
}

/* Smooth transitions */
button {
  transition: all 0.2s;
}
</style>
