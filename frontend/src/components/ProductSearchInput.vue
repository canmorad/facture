<script setup>
import { ref, computed, nextTick } from "vue";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  products: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: "— Saisie libre —",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "select-product"]);

const isOpen = ref(false);
const searchQuery = ref(props.modelValue);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

const filteredProducts = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return props.products;
  return props.products.filter((p) =>
    p.name.toLowerCase().includes(query)
  );
});

const calculateDropdownPosition = () => {
  if (!triggerRef.value) return;

  const rect = triggerRef.value.getBoundingClientRect();

  // For position: fixed, we use viewport coordinates directly
  dropdownPosition.value = {
    top: rect.bottom + 4,
    left: rect.left,
    width: rect.width,
  };
};

const toggleDropdown = () => {
  if (!props.disabled) {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
      searchQuery.value = props.modelValue;
      nextTick(() => {
        calculateDropdownPosition();
      });
    }
  }
};

const selectProduct = (product) => {
  emit("select-product", product);
  emit("update:modelValue", product.name);
  isOpen.value = false;
  searchQuery.value = "";
};

const hideDropdownWithDelay = () => {
  setTimeout(() => {
    isOpen.value = false;
  }, 200);
};

const handleClickOutside = (event) => {
  const trigger = triggerRef.value;
  const dropdown = dropdownRef.value;

  const clickedOutsideTrigger = trigger && !trigger.contains(event.target);
  const clickedOutsideDropdown = dropdown && !dropdown.contains(event.target);

  if (clickedOutsideTrigger && clickedOutsideDropdown) {
    isOpen.value = false;
    searchQuery.value = "";
  }
};

// Expose closeDropdown method
defineExpose({
  closeDropdown: () => {
    isOpen.value = false;
  },
});

// Set up event listeners
import { onMounted, onUnmounted } from "vue";

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  window.addEventListener("scroll", calculateDropdownPosition, true);
  window.addEventListener("resize", calculateDropdownPosition);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  window.removeEventListener("scroll", calculateDropdownPosition, true);
  window.removeEventListener("resize", calculateDropdownPosition);
});
</script>

<template>
  <div class="relative overflow-visible">
    <input
      ref="triggerRef"
      :value="modelValue"
      @input="(e) => emit('update:modelValue', e.target.value)"
      @focus="toggleDropdown"
      @blur="hideDropdownWithDelay"
      type="text"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      class="block w-full p-3 rounded-lg border text-sm transition-all duration-300 outline-none"
      :class="{
        'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 text-gray-700':
          !props.disabled,
        'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400': props.disabled,
      }"
    />

    <!-- Teleported Dropdown (renders to body) -->
    <Teleport to="body" v-if="isOpen && !props.disabled">
      <div
        ref="dropdownRef"
        class="fixed z-[9999] overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
        :style="{
          top: dropdownPosition.top + 'px',
          left: dropdownPosition.left + 'px',
          width: dropdownPosition.width + 'px',
        }"
      >
        <div class="p-3 border-b border-gray-100">
          <input
            :value="searchQuery"
            @input="(e) => searchQuery = e.target.value"
            type="text"
            placeholder="Rechercher un produit..."
            class="w-full px-3 py-2 text-sm border border-gray-200 rounded focus:outline-none focus:border-[#C5F82A]"
          />
        </div>
        <div class="max-h-60 overflow-y-auto">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            @mousedown.prevent="selectProduct(product)"
            class="px-4 py-3 text-sm text-gray-700 hover:bg-[#F8FAFC] cursor-pointer flex items-center justify-between transition-colors border-b border-gray-50 last:border-b-0"
          >
            <span class="flex-1 truncate">{{ product.name }}</span>
            <span class="text-xs text-gray-400 font-mono ml-3 flex-shrink-0">
              {{ product.price }} DH
            </span>
          </div>
          <div
            v-if="filteredProducts.length === 0"
            class="px-4 py-3 text-sm text-gray-500 italic text-center"
          >
            Aucun produit trouvé
          </div>
        </div>
      </div>
    </Teleport>
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

/* Custom scrollbar for the dropdown */
.max-h-60.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 0 0 8px 0;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>
