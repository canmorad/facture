<script setup>
import { computed, onMounted, ref } from "vue";
import { requestAutofocus } from "../utils/autofocus";

// Prevent props from falling through to root element as native attributes
defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: "text",
  },
  maxlength: {
    type: [String, Number],
    default: null,
  },
  autofocus: {
    type: Boolean,
    default: false,
  },
});

const innerModel = defineModel({
  type: [String, Number],
  required: true,
});

const input = ref(null);

// Convert number to string for number inputs to avoid type mismatch
const model = computed({
  get: () => {
    if (props.type === "number" && typeof innerModel.value === "number") {
      return innerModel.value.toString();
    }
    return innerModel.value;
  },
  set: (value) => {
    if (props.type === "number" && value !== "") {
      innerModel.value = parseFloat(value) || 0;
    } else {
      innerModel.value = value;
    }
  },
});

onMounted(() => {
  if (!props.disabled && props.autofocus) {
    requestAutofocus(() => input.value?.focus());
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
    v-model="model"
    :type="props.type"
    :disabled="props.disabled"
    :maxlength="props.maxlength"
    class="block w-full p-3 pr-10 rounded-lg border text-sm transition-all duration-300 outline-none"
    :class="{
      'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20':
        !props.disabled,
      'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400': props.disabled,
    }"
  />
</template>
