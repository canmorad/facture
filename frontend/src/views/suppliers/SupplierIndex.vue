<script setup>
import { reactive, ref, onMounted } from "vue";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const isLoading = ref(false);
const isLoadingSuppliers = ref(false);
const activeTab = ref("list");
const editingSupplierId = ref(null);

const suppliers = ref([]);

const form = reactive({
  name: "",
  ice: "",
  email: "",
  phone: "",
  address: "",
});

const errors = reactive({
  name: "",
  ice: "",
  email: "",
  phone: "",
  address: "",
  server: "",
});

const fetchSuppliers = async () => {
  isLoadingSuppliers.value = true;
  try {
    const { data } = await axios.get("/api/suppliers");
    suppliers.value = data;
  } catch {
    error("Erreur", "Impossible de charger les fournisseurs.");
  } finally {
    isLoadingSuppliers.value = false;
  }
};

const resetForm = () => {
  Object.keys(form).forEach((k) => (form[k] = ""));
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingSupplierId.value = null;
};

const editSupplier = (supplier) => {
  editingSupplierId.value = supplier.id;
  form.name = supplier.name || "";
  form.ice = supplier.ice || "";
  form.email = supplier.email || "";
  form.phone = supplier.phone || "";
  form.address = supplier.address || "";
  activeTab.value = "add";
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  isLoading.value = true;

  const payload = { ...form };

  try {
    if (editingSupplierId.value) {
      await axios.put(`/api/suppliers/${editingSupplierId.value}`, payload);
      success("Fournisseur modifié !", "Le fournisseur a été modifié avec succès.");
    } else {
      await axios.post("/api/suppliers", payload);
      success("Fournisseur ajouté !", "Le fournisseur a été enregistré avec succès.");
    }
    resetForm();
    await fetchSuppliers();
    activeTab.value = "list";
  } catch (err) {
    if (err.response && err.response.status === 422) {
      const validationErrors = err.response.data.errors;
      // Handle Laravel validation errors format
      if (validationErrors && typeof validationErrors === 'object') {
        Object.keys(validationErrors).forEach((key) => {
          if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
        });
      } else if (err.response.data?.message) {
        // Handle simple message format (from our custom exceptions)
        errors.server = err.response.data.message;
      } else {
        errors.server = "Une erreur est survenue lors de l'enregistrement.";
      }
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
      error("Erreur", "Impossible d'enregistrer le fournisseur.");
    }
  } finally {
    isLoading.value = false;
  }
};

const deleteSupplier = async (id, name) => {
  const result = await confirm(
    "Supprimer le fournisseur",
    `Supprimer "${name}" définitivement ?`
  );
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/suppliers/${id}`);
    success("Supprimé !", "Le fournisseur a été supprimé.");
    await fetchSuppliers();
  } catch {
    error("Erreur", "Impossible de supprimer le fournisseur.");
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (suppliers.value.length === 0) fetchSuppliers();
  }
};

onMounted(() => {
  fetchSuppliers();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex gap-6">
              <button
                @click="changeTab('list')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'list'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-list"></i>
                Liste des fournisseurs
                <span
                  v-if="suppliers.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ suppliers.length }}</span
                >
              </button>

              <button
                @click="changeTab('add')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'add'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas" :class="editingSupplierId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingSupplierId ? 'Modifier le fournisseur' : 'Ajouter un fournisseur' }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingSuppliers" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des fournisseurs...</p>
            </div>

            <div v-else-if="suppliers.length === 0" class="text-center py-12">
              <i class="fas fa-truck-field text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun fournisseur enregistré pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre premier fournisseur
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fournisseur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">ICE</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Téléphone</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Adresse</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="supplier in suppliers" :key="supplier.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div>
                          <div class="text-sm font-semibold text-gray-900">{{ supplier.name }}</div>
                          <div class="text-xs text-gray-400">ID #{{ supplier.id }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600">{{ supplier.ice || "—" }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600">{{ supplier.email || "—" }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600">{{ supplier.phone || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 max-w-[200px]">
                      <div class="text-sm text-gray-600 truncate">{{ supplier.address || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="editSupplier(supplier)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteSupplier(supplier.id, supplier.name)"
                          title="Supprimer"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <form v-else-if="activeTab === 'add'" @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nom du fournisseur *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="Ex: Société ABC"
                    autofocus
                  />
                  <InputError class="mt-2" :message="errors.name" />
                </div>

                <div>
                  <InputLabel for="ice" value="ICE" />
                  <TextInput
                    id="ice"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.ice"
                    placeholder="000000000000000"
                  />
                  <InputError class="mt-2" :message="errors.ice" />
                </div>

                <div>
                  <InputLabel for="email" value="Email" />
                  <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    placeholder="contact@fournisseur.com"
                  />
                  <InputError class="mt-2" :message="errors.email" />
                </div>

                <div>
                  <InputLabel for="phone" value="Téléphone" />
                  <TextInput
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    placeholder="+212 5XX XXX XXX"
                  />
                  <InputError class="mt-2" :message="errors.phone" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="address" value="Adresse" />
                  <TextInput
                    id="address"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.address"
                    placeholder="Rue, numéro, quartier..."
                  />
                  <InputError class="mt-2" :message="errors.address" />
                </div>
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
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
                  <span v-else>{{ editingSupplierId ? 'Modifier le fournisseur' : 'Enregistrer le fournisseur' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>