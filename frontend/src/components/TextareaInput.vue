<script setup>
defineProps({
  modelValue: [String, Number],
  rows: { type: Number, default: 3 },
  placeholder: { type: String, default: '' },
  id: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <textarea
    :id="id"
    :rows="rows"
    :placeholder="placeholder"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
  ></textarea>
</template>