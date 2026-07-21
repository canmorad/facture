<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { error } from '@/helpers/notifications';
import InputError from './InputError.vue';
import InputLabel from './InputLabel.vue';
import TextInput from './TextInput.vue';
import PrimaryButton from './PrimaryButton.vue';

const emit = defineEmits(['supplier-created', 'cancel']);

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

    // Emit the created supplier
    emit('supplier-created', data);
    resetForm();
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
  <div class="space-y-6">
    <InputError :message="errors.server" />

    <form @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
          <InputLabel for="supplier_name" value="Nom du fournisseur *" />
          <TextInput
            id="supplier_name"
            type="text"
            v-model="form.name"
            placeholder="Ex: Société ABC"
            autofocus
          />
          <InputError :message="errors.name" />
        </div>

        <div>
          <InputLabel for="supplier_ice" value="ICE" />
          <TextInput
            id="supplier_ice"
            type="text"
            v-model="form.ice"
            placeholder="000000000000000"
          />
          <InputError :message="errors.ice" />
        </div>

        <div>
          <InputLabel for="supplier_email" value="Email" />
          <TextInput
            id="supplier_email"
            type="email"
            v-model="form.email"
            placeholder="contact@fournisseur.com"
          />
          <InputError :message="errors.email" />
        </div>

        <div>
          <InputLabel for="supplier_phone" value="Téléphone" />
          <TextInput
            id="supplier_phone"
            type="tel"
            v-model="form.phone"
            placeholder="+212 5XX XXX XXX"
          />
          <InputError :message="errors.phone" />
        </div>

        <div class="md:col-span-2">
          <InputLabel for="supplier_address" value="Adresse" />
          <TextInput
            id="supplier_address"
            type="text"
            v-model="form.address"
            placeholder="Rue, numéro, quartier..."
          />
          <InputError :message="errors.address" />
        </div>
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
            Ajouter le fournisseur
          </span>
        </PrimaryButton>
      </div>
    </form>
  </div>
</template>
