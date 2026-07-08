<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: {
    type: Number,
    default: null,
  },
  companies: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const dropdownRef = ref(null)

const selectedCompany = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const currentCompany = computed(() => {
  return props.companies.find(c => c.id === selectedCompany.value) || props.companies[0] || null
})

const toggleDropdown = () => {
  if (props.companies.length) isOpen.value = !isOpen.value
}

const selectCompany = (id) => {
  localStorage.setItem('current_company_id', String(id))
  window.location.reload()
}

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div ref="dropdownRef" class="relative inline-block">
    <button
      type="button"
      @click="toggleDropdown"
      class="flex items-center gap-2.5 px-3 py-2 text-sm font-semibold text-gray-700 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg transition-all duration-300 min-w-[180px] justify-between outline-none select-none"
      :class="{ 
        'border-[#C5F82A] bg-white ring-[3px] ring-[#C5F82A]/20': isOpen,
        'hover:bg-gray-100/50 hover:border-gray-300': !isOpen 
      }"
    >
      <span class="flex items-center gap-2 truncate">
        <i class="fa-solid fa-building text-gray-400 text-xs transition-colors duration-300" :class="{ 'text-[#062121]': isOpen }"></i>
        <span class="text-gray-800 font-medium">{{ currentCompany?.name || 'Sélectionner' }}</span>
      </span>
      <svg
        class="w-4 h-4 text-gray-400 transition-transform duration-300"
        :class="{ 'rotate-180 text-[#062121]': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div
      v-if="isOpen && companies.length"
      class="absolute left-0 z-20 mt-1.5 w-full min-w-[210px] bg-white border border-[#E2E8F0] rounded-xl shadow-[0_10px_25px_-5px_rgba(0,0,0,0.05),0_8px_10px_-6px_rgba(0,0,0,0.05)] overflow-hidden py-1.5 animate-fadeIn"
    >
      <div
        v-for="company in companies"
        :key="company.id"
        @click="selectCompany(company.id)"
        class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-600 hover:bg-[#F8FAFC] cursor-pointer transition-all duration-150 mx-1 rounded-lg"
        :class="{ 'bg-[#C5F82A]/15 !text-[#062121] font-bold': company.id === selectedCompany }"
      >
        <i
          class="fa-solid fa-building text-xs transition-colors"
          :class="company.id === selectedCompany ? 'text-[#062121]' : 'text-gray-400'"
        ></i>
        <span class="truncate">{{ company.name }}</span>
        <svg
          v-if="company.id === selectedCompany"
          class="w-3.5 h-3.5 text-[#062121] ml-auto flex-shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
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
  animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>