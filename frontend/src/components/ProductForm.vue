<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { error } from '@/helpers/notifications';
import InputError from './InputError.vue';
import InputLabel from './InputLabel.vue';
import TextInput from './TextInput.vue';
import PrimaryButton from './PrimaryButton.vue';
import CustomSelect from './CustomSelect.vue';

const emit = defineEmits(['product-created', 'cancel']);

const authStore = useAuthStore();
const isLoading = ref(false);
const isLoadingLookups = ref(false);

const form = reactive({
  name: '',
  price: '',
  description: '',
  category_id: null,
  tax_rate_id: null
});

const errors = reactive({
  name: '',
  price: '',
  description: '',
  category_id: '',
  tax_rate_id: '',
  server: ''
});

const taxRates = ref([]);
const categories = ref([]);

// ========== Computed Options for CustomSelect ==========
const categoryOptions = computed(() => [
  { label: 'Sélectionner une catégorie', value: '' },
  ...categories.value
]);

const taxRateOptions = computed(() => [
  { label: 'Sélectionner un taux', value: '' },
  ...taxRates.value
]);

const fetchLookups = async () => {
  isLoadingLookups.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const response = await axios.get('/api/products/create', { params });

    categories.value = response.data.categories.map(cat => ({
      label: cat.label || cat.name || `Catégorie ${cat.id}`,
      value: cat.id
    }));

    taxRates.value = response.data.tax_rates.map(tax => ({
      label: `${tax.libelle || tax.label || 'TVA'} (${tax.rate}%)`,
      value: tax.id
    }));
  } catch {
    error('Erreur', 'Impossible de charger les données du formulaire.');
  } finally {
    isLoadingLookups.value = false;
  }
};

const resetForm = () => {
  form.name = '';
  form.price = '';
  form.description = '';
  form.category_id = null;
  form.tax_rate_id = null;
  Object.keys(errors).forEach((k) => (errors[k] = ''));
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ''));
  isLoading.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = 'Veuillez sélectionner une entreprise.';
    isLoading.value = false;
    return;
  }

  const payload = {
    ...form,
    company_id: companyId
  };

  try {
    const { data } = await axios.post('/api/products', payload);

    // Emit the created product
    emit('product-created', data);
    resetForm();
  } catch (err) {
    if (err.response && err.response.status === 422) {
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
      });
    } else {
      errors.server = 'Une erreur est survenue lors de l\'enregistrement.';
      error('Erreur', 'Impossible d\'enregistrer le produit.');
    }
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchLookups();
});
</script>

<template>
  <div class="space-y-6">
    <InputError :message="errors.server" />

    <div v-if="isLoadingLookups" class="text-center py-8">
      <svg class="animate-spin h-6 w-6 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <p class="mt-2 text-sm text-gray-500">Chargement des données...</p>
    </div>

    <form v-else @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
          <InputLabel for="product_name" value="Nom du produit / service *" />
          <TextInput
            id="product_name"
            type="text"
            v-model="form.name"
            placeholder="Ex: Écran 24 pouces"
            autofocus
          />
          <InputError :message="errors.name" />
        </div>

        <div>
          <InputLabel for="product_price" value="Prix unitaire HT (MAD) *" />
          <TextInput
            id="product_price"
            type="number"
            step="0.01"
            min="0"
            v-model="form.price"
            placeholder="0.00"
          />
          <InputError :message="errors.price" />
        </div>

        <div>
          <InputLabel for="product_category" value="Catégorie" />
          <CustomSelect
            v-model="form.category_id"
            :options="categoryOptions"
            label-key="label"
            value-key="value"
            placeholder="Sélectionner une catégorie"
          />
          <InputError :message="errors.category_id" />
        </div>

        <div>
          <InputLabel for="product_tax" value="Taux de TVA" />
          <CustomSelect
            v-model="form.tax_rate_id"
            :options="taxRateOptions"
            label-key="label"
            value-key="value"
            placeholder="Sélectionner un taux"
          />
          <InputError :message="errors.tax_rate_id" />
        </div>
      </div>

      <div class="border-t border-gray-100 pt-6">
        <InputLabel for="product_description" value="Description" />
        <textarea
          id="product_description"
          v-model="form.description"
          rows="3"
          placeholder="Description du produit..."
          class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
        ></textarea>
        <InputError :message="errors.description" />
      </div>

      <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
        <button
          type="button"
          @click="emit('cancel')"
          class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
        >
          Annuler
        </button>
        <PrimaryButton :disabled="isLoading">
          <span v-if="isLoading">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Enregistrement...
          </span>
          <span v-else>
            <i class="fas fa-plus-circle mr-2"></i>
            Ajouter le produit
          </span>
        </PrimaryButton>
      </div>
    </form>
  </div>
</template>
