<script setup>
import { computed, onMounted, ref } from "vue";
import { requestAutofocus } from "../utils/autofocus";

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  min: {
    type: Number,
    default: undefined,
  },
  max: {
    type: Number,
    default: undefined,
  },
  step: {
    type: String,
    default: "1",
  },
  placeholder: {
    type: String,
    default: "",
  },
  autofocus: {
    type: Boolean,
    default: false,
  },
});

const innerModel = defineModel({
  type: Number,
  required: true,
});

const input = ref(null);

// Handle input change
const handleChange = (event) => {
  const value = event.target.value;
  if (value === "") {
    innerModel.value = 0;
  } else {
    const num = parseFloat(value);
    innerModel.value = isNaN(num) ? 0 : num;
  }
};

onMounted(() => {
  if (!props.disabled && props.autofocus) {
    requestAutofocus(() => input.value.focus());
  }
});

defineExpose({
  focus: () => {
    if (!props.disabled) {
      input.value?.focus();
    }
  },
});
</script>

<template>
  <input
    ref="input"
    :value="innerModel"
    @input="handleChange"
    type="number"
    :min="props.min"
    :max="props.max"
    :step="props.step"
    :placeholder="props.placeholder"
    :disabled="props.disabled"
    class="block w-full p-3 rounded-lg border text-sm text-center transition-all duration-300 outline-none"
    :class="{
      'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 text-gray-700':
        !props.disabled,
      'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400': props.disabled,
    }"
  />
</template>
