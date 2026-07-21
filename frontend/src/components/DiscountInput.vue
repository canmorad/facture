<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
  modelValue: {
    type: Number,
    default: 0,
  },
  discountType: {
    type: String,
    default: "percentage",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: "0.00",
  },
});

const emit = defineEmits(["update:modelValue", "update:discountType"]);

const isOpen = ref(false);
const dropdownRef = ref(null);

const discountTypes = [
  { label: "%", value: "percentage" },
  { label: "DH", value: "fixed" },
];

const currentType = computed({
  get: () => props.discountType,
  set: (val) => emit("update:discountType", val),
});

const toggleDropdown = () => {
  if (!props.disabled) {
    isOpen.value = !isOpen.value;
  }
};

const selectType = (type) => {
  if (props.disabled) return;
  currentType.value = type;
  isOpen.value = false;
};

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
};

const handleChange = (event) => {
  const value = event.target.value;
  if (value === "") {
    emit("update:modelValue", 0);
  } else {
    const num = parseFloat(value);
    emit("update:modelValue", isNaN(num) ? 0 : num);
  }
};

const currentLabel = computed(() => {
  const found = discountTypes.find((t) => t.value === props.discountType);
  return found ? found.label : "%";
});

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
  <div class="flex items-center gap-1">
    <input
      :value="modelValue"
      @input="handleChange"
      type="number"
      min="0"
      step="0.01"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      class="block w-full p-3 rounded-lg border text-sm text-center transition-all duration-300 outline-none"
      :class="{
        'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 text-gray-700':
          !props.disabled,
        'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400': props.disabled,
      }"
    />
    <div ref="dropdownRef" class="relative w-16">
      <button
        type="button"
        :disabled="props.disabled"
        @click="toggleDropdown"
        class="block w-full p-3 rounded-lg border text-sm transition-all duration-300 outline-none"
        :class="{
          'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 text-center text-gray-700':
            !props.disabled,
          'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400':
            props.disabled,
        }"
      >
        <span class="text-sm">{{ currentLabel }}</span>
      </button>

      <div
        v-if="isOpen && !props.disabled"
        class="absolute left-0 z-20 mt-1 w-full overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
      >
        <div
          v-for="type in discountTypes"
          :key="type.value"
          @click="selectType(type.value)"
          class="flex cursor-pointer items-center justify-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC]"
          :class="{
            'bg-[#C5F82A]/15 font-semibold text-[#062121]':
              type.value === discountType,
          }"
        >
          {{ type.label }}
        </div>
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
