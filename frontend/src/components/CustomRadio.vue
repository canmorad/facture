<!-- components/CustomRadio.vue -->
<script setup>
defineProps({
  modelValue: {
    type: [String, Number, Boolean],
    default: null,
  },
  value: {
    type: [String, Number, Boolean],
    required: true,
  },
  name: {
    type: String,
    default: 'radio',
  },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <label class="relative inline-flex items-center cursor-pointer select-none">
    <input
      type="radio"
      :name="name"
      :value="value"
      :checked="modelValue === value"
      @change="$emit('update:modelValue', value)"
      class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-10"
    />
    <div
      class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all duration-200"
      :class="[
        modelValue === value
          ? 'bg-[#062121] border-[#C5F82A]'
          : 'bg-white border-gray-400 hover:border-[#C5F82A]'
      ]"
    >
      <div
        class="w-2 h-2 rounded-full bg-[#C5F82A] transition-transform duration-200"
        :class="modelValue === value ? 'scale-100' : 'scale-0'"
      ></div>
    </div>
  </label>
</template>