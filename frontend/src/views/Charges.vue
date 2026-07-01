<script setup>
import { reactive, ref, onMounted } from "vue";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import Swal from "sweetalert2";

const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingCharges = ref(false);
const isLoadingSuppliers = ref(false);
const editingChargeId = ref(null);

const charges = ref([]);
const suppliers = ref([]);

const form = reactive({
  fournisseur_id: "",
  title: "",
  amount: "",
  category: "",
  date: new Date().toISOString().split("T")[0],
});

const errors = reactive({
  fournisseur_id: "",
  title: "",
  amount: "",
  category: "",
  date: "",
  server: "",
});

const fetchCharges = async () => {
  isLoadingCharges.value = true;
  try {
    const { data } = await axios.get("/api/charges");
    charges.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les charges.",
      icon: "error",
    });
  } finally {
    isLoadingCharges.value = false;
  }
};

const fetchSuppliers = async () => {
  isLoadingSuppliers.value = true;
  try {
    const { data } = await axios.get("/api/fournisseurs");
    suppliers.value = data;
  } catch {
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
  form.fournisseur_id = "";
  form.title = "";
  form.amount = "";
  form.category = "";
  form.date = new Date().toISOString().split("T")[0];
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingChargeId.value = null;
};

const editCharge = (charge) => {
  editingChargeId.value = charge.id;
  form.fournisseur_id = charge.fournisseur_id || "";
  form.title = charge.title;
  form.amount = charge.amount;
  form.category = charge.category;
  form.date = charge.date.split("T")[0];
  activeTab.value = "add";
};

const submitCharge = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  try {
    if (editingChargeId.value) {
      await axios.put(`/api/charges/${editingChargeId.value}`, form);
      Swal.fire({
        title: "Charge modifiée !",
        text: "La charge a été modifiée avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    } else {
      await axios.post("/api/charges", form);
      Swal.fire({
        title: "Charge ajoutée !",
        text: "La charge a été enregistrée avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    }

    resetForm();
    await fetchCharges();
    activeTab.value = "list";
  } catch (error) {
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

const deleteCharge = async (id, title) => {
  const result = await Swal.fire({
    title: "Êtes-vous sûr ?",
    text: `Supprimer la charge "${title}" définitivement ?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
  });
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/charges/${id}`);
    Swal.fire("Supprimée !", "La charge a été supprimée.", "success");
    await fetchCharges();
  } catch {
    Swal.fire("Erreur", "Impossible de supprimer la charge.", "error");
  }
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA");
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (charges.value.length === 0) fetchCharges();
  }
};

onMounted(() => {
  fetchCharges();
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
                Liste des charges
                <span
                  v-if="charges.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ charges.length }}</span
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
                <i class="fas" :class="editingChargeId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingChargeId ? 'Modifier la charge' : 'Ajouter une charge' }}
              </button>
            </div>
          </div>

          <!-- Liste des charges -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingCharges" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des charges...</p>
            </div>

            <div v-else-if="charges.length === 0" class="text-center py-12">
              <i class="fas fa-chart-line text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucune charge enregistrée pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre première charge
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Titre</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Catégorie</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Montant</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fournisseur</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="charge in charges" :key="charge.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-gray-900">{{ charge.title }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                        {{ charge.category }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-[#062121]">{{ formatAmount(charge.amount) }} MAD</div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600">{{ formatDate(charge.date) }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-700">
                        {{ charge.fournisseur?.name || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="editCharge(charge)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteCharge(charge.id, charge.title)"
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
          <form v-else-if="activeTab === 'add'" @submit.prevent="submitCharge" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="title" value="Titre de la charge *" />
                  <TextInput
                    id="title"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.title"
                    placeholder="Ex: Loyer, Facture électricité, Achat fournitures..."
                    autofocus
                  />
                  <InputError class="mt-2" :message="errors.title" />
                </div>

                <div>
                  <InputLabel for="amount" value="Montant (MAD) *" />
                  <TextInput
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    v-model="form.amount"
                    placeholder="0.00"
                  />
                  <InputError class="mt-2" :message="errors.amount" />
                </div>

                <div>
                  <InputLabel for="category" value="Catégorie *" />
                  <select
                    id="category"
                    v-model="form.category"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  >
                    <option value="">-- Sélectionnez une catégorie --</option>
                    <option value="Logistique">Logistique</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Achat de marchandise">Achat de marchandise</option>
                    <option value="Autre">Autre</option>
                  </select>
                  <InputError class="mt-2" :message="errors.category" />
                </div>

                <div>
                  <InputLabel for="date" value="Date de la charge *" />
                  <input
                    id="date"
                    type="date"
                    v-model="form.date"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  />
                  <InputError class="mt-2" :message="errors.date" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="fournisseur_id" value="Fournisseur (optionnel)" />
                  <select
                    id="fournisseur_id"
                    v-model="form.fournisseur_id"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  >
                    <option value="">-- Aucun fournisseur --</option>
                    <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                  </select>
                  <InputError class="mt-2" :message="errors.fournisseur_id" />
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
                  <span v-else>{{ editingChargeId ? 'Modifier la charge' : 'Enregistrer la charge' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>