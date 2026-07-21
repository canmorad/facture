<script setup>
import { reactive, ref, watch, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { success, error } from '@/helpers/notifications';
import InputError from './InputError.vue';
import InputLabel from './InputLabel.vue';
import TextInput from './TextInput.vue';
import CustomSelect from './CustomSelect.vue';
import PrimaryButton from './PrimaryButton.vue';

const props = defineProps({
  tabId: {
    type: [String, Number],
    required: true
  },
  onProductCreated: {
    type: Function,
    default: null
  }
});

const emit = defineEmits(['close', 'product-created']);

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
    success('Produit ajouté !', 'Le produit a été enregistré avec succès.');

    // Emit the created product
    emit('product-created', data);
    if (props.onProductCreated) {
      props.onProductCreated(data);
    }

    resetForm();

    // Auto-close the tab after successful creation
    setTimeout(() => {
      emit('close');
    }, 300);
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
  <div class="p-6 lg:p-8 space-y-8 h-full overflow-y-auto">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-[#062121]">Ajouter un produit</h3>
        <p class="text-sm text-gray-500 mt-1">Créez un nouveau produit et ajoutez-le instantanément à votre facture.</p>
      </div>
      <button
        @click="emit('close')"
        class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200 flex items-center justify-center"
      >
        <i class="fas fa-times"></i>
      </button>
    </div>

    <InputError :message="errors.server" />

    <div v-if="isLoadingLookups" class="text-center py-8">
      <svg class="animate-spin h-6 w-6 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <p class="mt-2 text-sm text-gray-500">Chargement des données...</p>
    </div>

    <form v-else @submit.prevent="submit" class="space-y-8">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
          <InputLabel for="name" value="Nom du produit / service *" />
          <TextInput
            id="name"
            type="text"
            v-model="form.name"
            placeholder="Ex: Écran 24 pouces"
            autofocus
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.name" />
        </div>

        <div>
          <InputLabel for="price" value="Prix unitaire HT (MAD) *" />
          <TextInput
            id="price"
            type="number"
            step="0.01"
            min="0"
            v-model="form.price"
            placeholder="0.00"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.price" />
        </div>

        <div>
          <InputLabel for="category_id" value="Catégorie" />
          <CustomSelect
            id="category_id"
            v-model="form.category_id"
            :options="categories"
            label-key="label"
            value-key="value"
            placeholder="Sélectionner une catégorie"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.category_id" />
        </div>

        <div>
          <InputLabel for="tax_rate_id" value="Taux de TVA" />
          <CustomSelect
            id="tax_rate_id"
            v-model="form.tax_rate_id"
            :options="taxRates"
            label-key="label"
            value-key="value"
            placeholder="Sélectionner un taux"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.tax_rate_id" />
        </div>
      </div>

      <div class="border-t border-gray-100 pt-6">
        <InputLabel for="description" value="Description" />
        <textarea
          id="description"
          v-model="form.description"
          rows="3"
          placeholder="Description du produit..."
          class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
        ></textarea>
        <InputError class="mt-2" :message="errors.description" />
      </div>

      <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
        <button
          type="button"
          @click="emit('close')"
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
