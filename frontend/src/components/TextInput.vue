<script setup>
import { onMounted, ref } from "vue";

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
});

const model = defineModel({
  type: String,
  required: true,
});

const input = ref(null);

onMounted(() => {
  if (!props.disabled && input.value?.hasAttribute("autofocus")) {
    input.value.focus();
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
    :disabled="props.disabled"
    class="block w-full p-3 pr-10 rounded-lg border text-sm transition-all duration-300 outline-none"
    :class="{
      'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20':
        !props.disabled,
      'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400': props.disabled,
    }"
  />
</template>
