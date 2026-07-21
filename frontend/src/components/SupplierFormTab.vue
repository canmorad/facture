<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { success, error } from '@/helpers/notifications';
import InputError from './InputError.vue';
import InputLabel from './InputLabel.vue';
import TextInput from './TextInput.vue';
import PrimaryButton from './PrimaryButton.vue';

const props = defineProps({
  tabId: {
    type: [String, Number],
    required: true
  },
  onSupplierCreated: {
    type: Function,
    default: null
  }
});

const emit = defineEmits(['close', 'supplier-created']);

const isLoading = ref(false);

const form = reactive({
  name: '',
  ice: '',
  email: '',
  phone: '',
  address: ''
});

const errors = reactive({
  name: '',
  ice: '',
  email: '',
  phone: '',
  address: '',
  server: ''
});

const resetForm = () => {
  form.name = '';
  form.ice = '';
  form.email = '';
  form.phone = '';
  form.address = '';
  Object.keys(errors).forEach((k) => (errors[k] = ''));
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ''));
  isLoading.value = true;

  const payload = { ...form };

  try {
    const { data } = await axios.post('/api/suppliers', payload);
    success('Fournisseur ajouté !', 'Le fournisseur a été enregistré avec succès.');

    // Emit the created supplier
    emit('supplier-created', data);
    if (props.onSupplierCreated) {
      props.onSupplierCreated(data);
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
      error('Erreur', 'Impossible d\'enregistrer le fournisseur.');
    }
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="p-6 lg:p-8 space-y-8 h-full overflow-y-auto">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-[#062121]">Ajouter un fournisseur</h3>
        <p class="text-sm text-gray-500 mt-1">Créez un nouveau fournisseur et sélectionnez-le instantanément.</p>
      </div>
      <button
        @click="emit('close')"
        class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200 flex items-center justify-center"
      >
        <i class="fas fa-times"></i>
      </button>
    </div>

    <InputError :message="errors.server" />

    <form @submit.prevent="submit" class="space-y-8">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
          <InputLabel for="name" value="Nom du fournisseur *" />
          <TextInput
            id="name"
            type="text"
            v-model="form.name"
            placeholder="Ex: Société ABC"
            autofocus
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.name" />
        </div>

        <div>
          <InputLabel for="ice" value="ICE" />
          <TextInput
            id="ice"
            type="text"
            v-model="form.ice"
            placeholder="000000000000000"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.ice" />
        </div>

        <div>
          <InputLabel for="email" value="Email" />
          <TextInput
            id="email"
            type="email"
            v-model="form.email"
            placeholder="contact@fournisseur.com"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.email" />
        </div>

        <div>
          <InputLabel for="phone" value="Téléphone" />
          <TextInput
            id="phone"
            type="tel"
            v-model="form.phone"
            placeholder="+212 5XX XXX XXX"
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.phone" />
        </div>

        <div class="md:col-span-2">
          <InputLabel for="address" value="Adresse" />
          <TextInput
            id="address"
            type="text"
            v-model="form.address"
            placeholder="Rue, numéro, quartier..."
            class="mt-1"
          />
          <InputError class="mt-2" :message="errors.address" />
        </div>
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
            Ajouter le fournisseur
          </span>
        </PrimaryButton>
      </div>
    </form>
  </div>
</template>
