<script setup>
import { reactive, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();

const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingSuppliers = ref(false);
const editingSupplierId = ref(null);

const fournisseurs = ref([]);

const form = reactive({
  name: "",
  email: "",
  phone: "",
  address: "",
  city: "",
  country: "",
  ice: "",
});

const errors = reactive({
  name: "",
  email: "",
  phone: "",
  address: "",
  city: "",
  country: "",
  ice: "",
  server: "",
});

const fetchFournisseurs = async () => {
  isLoadingSuppliers.value = true;
  try {
    const { data } = await axios.get("/api/fournisseurs");
    fournisseurs.value = data;
  } catch {
    console.error(error.response?.data?.message || error.message);
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les fournisseurs.",
      icon: "error",
    });
  } finally {
    isLoadingSuppliers.value = false;
  }
};

const resetForm = () => {
  Object.keys(form).forEach((k) => (form[k] = ""));
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingSupplierId.value = null;
};

const editSupplier = (fournisseur) => {
  editingSupplierId.value = fournisseur.id;
  Object.keys(form).forEach((key) => {
    form[key] = fournisseur[key] || "";
  });
  activeTab.value = "add";
};

const submitSupplier = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  try {
    if (editingSupplierId.value) {
      await axios.put(`/api/fournisseurs/${editingSupplierId.value}`, form);
      Swal.fire({
        title: "Fournisseur modifié !",
        text: "Le fournisseur a été modifié avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    } else {
      await axios.post("/api/fournisseurs", form);
      Swal.fire({
        title: "Fournisseur ajouté !",
        text: "Le fournisseur a été enregistré avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    }

    resetForm();
    await fetchFournisseurs();
    activeTab.value = "list";
  } catch (error) {
    console.error(error.response?.data?.message || error.message);
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
    }
  } finally {
    isLoading.value = false;
  }
};

const deleteSupplier = async (id, name) => {
  const result = await Swal.fire({
    title: "Êtes-vous sûr ?",
    text: `Supprimer "${name}" définitivement ?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
  });
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/fournisseurs/${id}`);
    Swal.fire("Supprimé !", "Le fournisseur a été supprimé.", "success");
    await fetchFournisseurs();
  } catch {
    Swal.fire("Erreur", "Impossible de supprimer le fournisseur.", "error");
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (fournisseurs.value.length === 0) fetchFournisseurs();
  }
};

onMounted(() => {
  fetchFournisseurs();
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
                  v-if="fournisseurs.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ fournisseurs.length }}</span
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
                <i class="fas" :class="editingSupplierId ? 'fa-edit' : 'fa-truck'"></i>
                {{ editingSupplierId ? 'Modifier le fournisseur' : 'Ajouter un fournisseur' }}
              </button>
            </div>
          </div>

          <!-- Liste des fournisseurs -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingSuppliers" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des fournisseurs...</p>
            </div>

            <div v-else-if="fournisseurs.length === 0" class="text-center py-12">
              <i class="fas fa-truck text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun fournisseur enregistré pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-truck"></i> Ajouter votre premier fournisseur
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fournisseur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Contact</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">ICE</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Ville / Pays</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="supplier in fournisseurs" :key="supplier.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div>
                        <div class="text-sm font-semibold text-gray-900">{{ supplier.name }}</div>
                        <div class="text-xs text-gray-400">ID #{{ supplier.id }}</div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="space-y-0.5">
                        <div v-if="supplier.email" class="text-sm text-gray-700 flex items-center gap-1">
                          <i class="fas fa-envelope text-xs text-gray-400"></i> {{ supplier.email }}
                        </div>
                        <div v-if="supplier.phone" class="text-sm text-gray-700 flex items-center gap-1">
                          <i class="fas fa-phone text-xs text-gray-400"></i> {{ supplier.phone }}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm font-mono text-gray-600">{{ supplier.ice || "—" }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="space-y-0.5">
                        <div class="text-sm text-gray-700 flex items-center gap-1">
                          <i class="fas fa-city text-xs text-gray-400"></i> {{ supplier.city || "—" }}
                        </div>
                        <div v-if="supplier.country" class="text-xs text-gray-400 flex items-center gap-1">
                          <i class="fas fa-globe text-xs"></i> {{ supplier.country }}
                        </div>
                      </div>
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

          <!-- Formulaire d'ajout / modification -->
          <form v-else-if="activeTab === 'add'" @submit.prevent="submitSupplier" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nom / Raison sociale *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="Nom du fournisseur"
                    autofocus
                  />
                  <InputError class="mt-2" :message="errors.name" />
                </div>
                <div>
                  <InputLabel for="email" value="Email" />
                  <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    placeholder="fournisseur@exemple.com"
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
                <div>
                  <InputLabel for="ice" value="ICE (Identifiant Commun)" />
                  <TextInput
                    id="ice"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.ice"
                    placeholder="001234567000045"
                  />
                  <InputError class="mt-2" :message="errors.ice" />
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">Adresse</h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                  <div>
                    <InputLabel for="city" value="Ville" />
                    <TextInput
                      id="city"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.city"
                      placeholder="Casablanca"
                    />
                    <InputError class="mt-2" :message="errors.city" />
                  </div>
                  <div>
                    <InputLabel for="country" value="Pays" />
                    <TextInput
                      id="country"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.country"
                      placeholder="Maroc"
                    />
                    <InputError class="mt-2" :message="errors.country" />
                  </div>
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