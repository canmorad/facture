<!-- components/CustomCombobox.vue -->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: null,
  },
  options: {
    type: Array,
    required: true,
  },
  placeholder: {
    type: String,
    default: "Sélectionner ou saisir...",
  },
  labelKey: {
    type: String,
    default: "label",
  },
  valueKey: {
    type: String,
    default: "value",
  },
});

const emit = defineEmits(["update:modelValue"]);

const isOpen = ref(false);
const dropdownRef = ref(null);
const inputRef = ref(null);
const searchTerm = ref("");

const selectedOption = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const currentLabel = computed(() => {
  const found = props.options.find(
    (opt) => opt[props.valueKey] === selectedOption.value,
  );
  return found ? found[props.labelKey] : searchTerm.value || props.placeholder;
});

const filteredOptions = computed(() => {
  const query = searchTerm.value.toLowerCase().trim();
  if (!query) return props.options;
  return props.options.filter(
    (opt) =>
      String(opt[props.labelKey]).toLowerCase().includes(query) ||
      String(opt[props.valueKey]).toLowerCase().includes(query),
  );
});

const toggleDropdown = () => {
  if (props.options.length) {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
      searchTerm.value =
        currentLabel.value !== props.placeholder ? currentLabel.value : "";
    }
  }
};

const selectOption = (value) => {
  const found = props.options.find((opt) => opt[props.valueKey] === value);
  if (found) {
    selectedOption.value = value;
    searchTerm.value = found[props.labelKey];
  }
  isOpen.value = false;
};

const handleInput = (event) => {
  const inputValue = event.target.value;
  searchTerm.value = inputValue;

  const exactMatch = props.options.find(
    (opt) => String(opt[props.labelKey]) === inputValue,
  );
  if (exactMatch) {
    selectedOption.value = exactMatch[props.valueKey];
  } else {
    const numericValue = parseFloat(inputValue);
    if (!isNaN(numericValue)) {
      selectedOption.value = numericValue;
    } else {
      selectedOption.value = inputValue;
    }
  }
};

const handleInputFocus = () => {
  if (props.options.length) {
    isOpen.value = true;
    searchTerm.value =
      currentLabel.value !== props.placeholder ? currentLabel.value : "";
  }
};

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
};

const closeDropdown = () => {
  isOpen.value = false;
};

const handleBlur = () => {
  setTimeout(closeDropdown, 200);
};

watch(
  () => props.modelValue,
  (newVal) => {
    const found = props.options.find((opt) => opt[props.valueKey] === newVal);
    if (found) {
      searchTerm.value = found[props.labelKey];
    } else if (newVal !== null && newVal !== undefined && newVal !== "") {
      searchTerm.value = String(newVal);
    } else {
      searchTerm.value = "";
    }
  },
  { immediate: true },
);

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  const found = props.options.find(
    (opt) => opt[props.valueKey] === selectedOption.value,
  );
  if (found) {
    searchTerm.value = found[props.labelKey];
  }
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
  <div ref="dropdownRef" class="relative inline-block w-full">
    <div class="relative flex items-center">
      <input
        ref="inputRef"
        type="text"
        :value="searchTerm"
        @input="handleInput"
        @focus="handleInputFocus"
        @blur="handleBlur"
        :placeholder="placeholder"
        class="w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 pr-10 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 transition-all duration-300"
      />
      <button
        type="button"
        @click="toggleDropdown"
        class="absolute right-2 flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 transition-colors"
      >
        <svg
          class="w-4 h-4 transition-transform duration-300"
          :class="{ 'rotate-180': isOpen }"
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
      </button>
    </div>

    <div
      v-if="isOpen && options.length"
      class="absolute left-0 z-20 mt-1 w-full bg-white border border-[#E2E8F0] rounded-xl shadow-lg max-h-60 overflow-y-auto py-1.5 animate-fadeIn"
    >
      <div
        v-for="opt in filteredOptions"
        :key="opt[valueKey]"
        @mousedown.prevent="selectOption(opt[valueKey])"
        class="px-4 py-2.5 text-sm text-gray-600 hover:bg-[#F8FAFC] cursor-pointer transition-all duration-150 mx-1 rounded-lg flex items-center justify-between"
        :class="{
          'bg-[#C5F82A]/15 !text-[#062121] font-bold':
            opt[valueKey] === selectedOption,
        }"
      >
        <span>{{ opt[labelKey] }}</span>
        <svg
          v-if="opt[valueKey] === selectedOption"
          class="w-3.5 h-3.5 text-[#062121] ml-auto flex-shrink-0"
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
        v-if="filteredOptions.length === 0"
        class="px-4 py-2.5 text-sm text-gray-500 italic text-center"
      >
        Aucun résultat trouvé
      </div>
    </div>
  </div>
</template>

<style scoped>
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
  animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>