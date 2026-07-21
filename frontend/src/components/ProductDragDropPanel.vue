<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { formatPrice } from '@/helpers/format';

const props = defineProps({
  onProductSelect: {
    type: Function,
    required: true
  },
  excludedProductIds: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['product-dropped']);

const products = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref(null);
const categories = ref([]);

// Fetch products on mount
const fetchProducts = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get('/api/products', {
      params: { with: 'category' }
    });
    products.value = data;

    // Extract unique categories
    const uniqueCategories = [...new Set(data.map(p => p.category).filter(Boolean))];
    categories.value = uniqueCategories.map(cat => ({
      id: cat.id,
      name: cat.name
    }));
  } catch {
    // Silent fail
  } finally {
    isLoading.value = false;
  }
};

// Filter products
const filteredProducts = computed(() => {
  let filtered = products.value;

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(p =>
      p.name.toLowerCase().includes(query) ||
      (p.description && p.description.toLowerCase().includes(query))
    );
  }

  // Filter by category
  if (selectedCategory.value) {
    filtered = filtered.filter(p => p.category_id === selectedCategory.value);
  }

  // Exclude already selected products
  if (props.excludedProductIds.length > 0) {
    filtered = filtered.filter(p => !props.excludedProductIds.includes(p.id));
  }

  return filtered;
});

// Drag and drop handlers
const handleDragStart = (event, product) => {
  event.dataTransfer.effectAllowed = 'copy';
  event.dataTransfer.setData('application/json', JSON.stringify({
    type: 'product',
    data: product
  }));

  // Add visual feedback
  event.target.classList.add('opacity-50', 'scale-95');
};

const handleDragEnd = (event) => {
  event.target.classList.remove('opacity-50', 'scale-95');
};

// Quick add button (alternative to drag & drop)
const handleQuickAdd = (product) => {
  emit('product-dropped', product);
  props.onProductSelect(product);
};

// Initialize
fetchProducts();
</script>

<template>
  <div class="product-drag-drop-panel h-full flex flex-col bg-gray-50">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200 bg-white">
      <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider mb-3">
        <i class="fas fa-cubes mr-2"></i>
        Produits disponibles
      </h3>

      <!-- Search -->
      <div class="relative mb-3">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher un produit..."
          class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg focus:border-[#C5F82A] focus:ring-2 focus:ring-[#C5F82A]/20 outline-none transition-all"
        />
      </div>

      <!-- Category filter -->
      <select
        v-model="selectedCategory"
        class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:border-[#C5F82A] focus:ring-2 focus:ring-[#C5F82A]/20 outline-none transition-all bg-white"
      >
        <option :value="null">Toutes les catégories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
          {{ cat.name }}
        </option>
      </select>
    </div>

    <!-- Product list -->
    <div class="flex-1 overflow-y-auto p-3 space-y-2">
      <div v-if="isLoading" class="text-center py-8">
        <svg class="animate-spin h-6 w-6 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p class="mt-2 text-xs text-gray-500">Chargement...</p>
      </div>

      <div v-else-if="filteredProducts.length === 0" class="text-center py-8">
        <i class="fas fa-box-open text-3xl text-gray-300 mb-2 block"></i>
        <p class="text-xs text-gray-500">Aucun produit trouvé</p>
      </div>

      <div
        v-for="product in filteredProducts"
        :key="product.id"
        draggable="true"
        @dragstart="handleDragStart($event, product)"
        @dragend="handleDragEnd"
        class="product-card bg-white rounded-lg border border-gray-200 p-3 cursor-move hover:border-[#C5F82A] hover:shadow-md transition-all duration-200 group"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <i class="fas fa-grip-vertical text-gray-300 group-hover:text-[#C5F82A] transition-colors"></i>
              <h4 class="text-sm font-semibold text-gray-900 truncate">{{ product.name }}</h4>
            </div>
            <p v-if="product.description" class="text-xs text-gray-500 truncate mt-1">
              {{ product.description }}
            </p>
            <div class="flex items-center gap-2 mt-2">
              <span class="text-xs font-bold text-[#062121]">{{ formatPrice(product.price) }} MAD</span>
              <span v-if="product.category" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                {{ product.category.name }}
              </span>
            </div>
          </div>

          <!-- Quick add button -->
          <button
            @click="handleQuickAdd(product)"
            class="flex-shrink-0 w-8 h-8 rounded-lg bg-[#062121]/5 text-[#C5F82A] border border-[#C5F82A]/20 hover:bg-[#C5F82A] hover:text-[#062121] transition-all duration-200 flex items-center justify-center"
            title="Ajouter à la facture"
          >
            <i class="fas fa-plus text-xs"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Footer hint -->
    <div class="p-3 bg-gray-100 border-t border-gray-200">
      <p class="text-xs text-gray-500 text-center">
        <i class="fas fa-info-circle mr-1"></i>
        Glissez-déposez ou utilisez <strong class="text-[#062121]">+</strong> pour ajouter
      </p>
    </div>
  </div>
</template>

<style scoped>
.product-card {
  user-select: none;
  touch-action: none;
}

.product-card:active {
  cursor: grabbing;
}

/* Drag preview styles */
.product-card.dragging {
  opacity: 0.5;
  transform: scale(0.95);
}

.product-panel-container {
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: rgb(249 250 251);
}
</style>
