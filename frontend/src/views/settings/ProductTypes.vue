<!-- ProductTypes.vue -->
<template>
  <SettingsLayout>
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
                Liste des types
                <span v-if="types.length > 0" class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ types.length }}</span>
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
                <i class="fas" :class="editingId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingId ? 'Modifier le type' : 'Ajouter un type' }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des types...</p>
            </div>
            <div v-else-if="types.length === 0" class="text-center py-12">
              <i class="fas fa-tags text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun type d'article enregistré.</p>
              <button @click="changeTab('add')" class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4">
                <i class="fas fa-plus"></i> Ajouter votre premier type
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Nom</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="type in types" :key="type.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ type.name }}</td>
                    <td class="px-4 py-4 text-sm text-gray-600">{{ type.description || '—' }}</td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button v-if="!type.is_default" @click="editType(type)" title="Modifier" class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button v-if="!type.is_default" @click="deleteType(type)" title="Supprimer" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200">
                          <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                        <span v-else class="text-xs text-gray-400 italic">Défaut</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <form v-else-if="activeTab === 'add'" @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-6">
              <InputError :message="errors.server" />
              <div>
                <InputLabel for="name" value="Nom *" />
                <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" placeholder="Ex: Acompte" autofocus />
                <InputError class="mt-2" :message="errors.name" />
              </div>
              <div>
                <InputLabel for="description" value="Description" />
                <TextInput id="description" type="text" class="mt-1 block w-full" v-model="form.description" placeholder="Ex: Down payment" />
                <InputError class="mt-2" :message="errors.description" />
              </div>
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button type="button" @click="changeTab('list')" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">Annuler</button>
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>{{ editingId ? 'Modifier le type' : 'Enregistrer le type' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import { useAuthStore } from "../../stores/auth";
import SettingsLayout from "../../layouts/SettingsLayout.vue";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import TextInput from "../../components/TextInput.vue";
import axios from "axios";
import { success, error, validation, confirm } from "../../helpers/notifications";

const authStore = useAuthStore();

const activeTab = ref("list");
const isLoading = ref(false);
const isSubmitting = ref(false);
const editingId = ref(null);
const types = ref([]);

const form = reactive({
  name: "",
  description: "",
});

const errors = reactive({
  name: "",
  description: "",
  server: "",
});

const fetchTypes = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get("/api/product-categories", { params: { company_id: companyId } });
    types.value = data;
  } catch {
    error("Erreur", "Impossible de charger les types.");
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  form.name = "";
  form.description = "";
  errors.name = "";
  errors.description = "";
  errors.server = "";
  editingId.value = null;
};

const editType = (type) => {
  editingId.value = type.id;
  form.name = type.name;
  form.description = type.description || "";
  activeTab.value = "add";
};

const submit = async () => {
  Object.keys(errors).forEach(k => errors[k] = "");
  isSubmitting.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isSubmitting.value = false;
    return;
  }

  if (!form.name.trim()) {
    errors.name = "Le nom est requis.";
    isSubmitting.value = false;
    return;
  }

  const payload = {
    name: form.name,
    description: form.description || null,
    company_id: companyId,
    is_active: true,
  };

  try {
    if (editingId.value) {
      await axios.put(`/api/product-categories/${editingId.value}`, payload);
      success("Modifié !", "Le type a été modifié.");
    } else {
      await axios.post("/api/product-categories", payload);
      success("Ajouté !", "Le type a été ajouté.");
    }
    resetForm();
    await fetchTypes();
    activeTab.value = "list";
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach(k => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
      validation(Object.values(e).flat().join("\n"));
    } else {
      errors.server = "Une erreur est survenue.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

const deleteType = async (type) => {
  const result = await confirm("Supprimer le type", `Voulez-vous vraiment supprimer "${type.name}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/product-categories/${type.id}`);
    types.value = types.value.filter(t => t.id !== type.id);
    success("Supprimé !", "Le type a été supprimé.");
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.error) {
      error("Erreur", err.response.data.error);
    } else {
      error("Erreur", "Impossible de supprimer le type.");
    }
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (types.value.length === 0) fetchTypes();
  }
};

onMounted(fetchTypes);
</script>