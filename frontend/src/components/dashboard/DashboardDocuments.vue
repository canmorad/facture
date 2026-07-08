<script setup>
defineProps({
  documentsByType: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <div v-if="loading" class="text-center py-12">
    <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
  </div>
  <div v-else class="space-y-3">
    <div
      v-for="doc in documentsByType"
      :key="doc.type"
      class="flex items-center justify-between bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow transition-shadow"
    >
      <div class="flex items-center gap-3">
        <span :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white', 'bg-' + doc.color + '-500']">
          <i :class="['fas', doc.icon]"></i>
        </span>
        <span class="text-sm font-medium text-gray-700">{{ doc.type }}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-lg font-bold text-[#062121]">{{ doc.count }}</span>
        <span class="text-xs text-gray-400">document(s)</span>
      </div>
    </div>
  </div>
</template>
