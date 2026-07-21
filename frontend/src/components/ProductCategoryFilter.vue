<script setup>
import { ref, computed, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: null,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: "Toutes les catégories",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "change"]);

const isOpen = ref(false);
const dropdownRef = ref(null);

const selectedOption = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const currentLabel = computed(() => {
  if (!props.modelValue) return props.placeholder;
  const found = props.categories.find((c) => c.id === props.modelValue);
  return found ? found.name : props.placeholder;
});

const toggleDropdown = () => {
  if (props.disabled) return;
  if (props.categories.length) {
    isOpen.value = !isOpen.value;
  }
};

const selectOption = (categoryId) => {
  if (props.disabled) return;
  selectedOption.value = categoryId;
  isOpen.value = false;
  emit("change", categoryId);
};

const clearSelection = () => {
  if (props.disabled) return;
  selectedOption.value = null;
  emit("change", null);
};

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
};

// Watch for categories changes
watch(
  () => props.categories,
  () => {
    if (props.modelValue) {
      const exists = props.categories.find((c) => c.id === props.modelValue);
      if (!exists) {
        selectedOption.value = null;
      }
    }
  }
);

// Expose method
defineExpose({
  clear: () => {
    selectedOption.value = null;
  },
});
</script>

<template>
  <div ref="dropdownRef" class="relative w-full">
    <button
      type="button"
      :disabled="props.disabled"
      @click="toggleDropdown"
      class="block w-full p-3 rounded-lg border text-sm transition-all duration-300 outline-none"
      :class="{
        'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20':
          !props.disabled,
        'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400':
          props.disabled,
      }"
    >
      <div class="flex items-center justify-between w-full">
        <span
          class="truncate text-left flex-1"
          :class="props.disabled ? 'text-gray-400' : 'text-gray-800'"
        >
          {{ currentLabel }}
        </span>
        <div class="flex items-center gap-2">
          <svg
            v-if="selectedOption"
            @click.stop="clearSelection"
            class="w-4 h-4 text-gray-400 hover:text-gray-600 cursor-pointer flex-shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
          <svg
            class="w-4 h-4 transition-transform duration-300 flex-shrink-0"
            :class="{
              'rotate-180 text-[#062121]': isOpen && !props.disabled,
              'text-gray-400': !isOpen || props.disabled,
            }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </div>
      </div>
    </button>

    <div
      v-if="isOpen && categories.length && !props.disabled"
      class="absolute left-0 z-20 mt-1 w-full overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
    >
      <div
        @click="selectOption(null)"
        class="flex cursor-pointer items-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC]"
        :class="{
          'bg-[#C5F82A]/15 font-semibold text-[#062121]': !selectedOption,
        }"
      >
        <span class="flex-1 truncate">{{ placeholder }}</span>
        <svg
          v-if="!selectedOption"
          class="h-4 w-4 text-[#062121]"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.5"
            d="M5 13l4 4L19 7"
          />
        </svg>
      </div>
      <div
        v-for="category in categories"
        :key="category.id"
        @click="selectOption(category.id)"
        class="flex cursor-pointer items-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC]"
        :class="{
          'bg-[#C5F82A]/15 font-semibold text-[#062121]':
            category.id === selectedOption,
        }"
      >
        <span class="flex-1 truncate">{{ category.name }}</span>
        <svg
          v-if="category.id === selectedOption"
          class="h-4 w-4 text-[#062121]"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.5"
            d="M5 13l4 4L19 7"
          />
        </svg>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.2s ease;
}
</style>
