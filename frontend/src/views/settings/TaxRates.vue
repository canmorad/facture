<!-- TaxRates.vue -->
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
                Liste des taux de TVA
                <span v-if="taxRates.length > 0" class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ taxRates.length }}</span>
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
                {{ editingId ? 'Modifier le taux' : 'Ajouter un taux' }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des taux...</p>
            </div>
            <div v-else-if="taxRates.length === 0" class="text-center py-12">
              <i class="fas fa-percent text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun taux de TVA enregistré.</p>
              <button @click="changeTab('add')" class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4">
                <i class="fas fa-plus"></i> Ajouter votre premier taux
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Libellé</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Taux (%)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Motif d'exonération</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="tax in taxRates" :key="tax.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ tax.libelle }}</td>
                    <td class="px-4 py-4 text-sm text-gray-800">{{ tax.rate.toFixed(2) }} %</td>
                    <td class="px-4 py-4 text-sm text-gray-600">{{ tax.motif_exoneration || '—' }}</td>
                    <td class="px-4 py-4">
                      <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', tax.is_actif ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600']">
                        {{ tax.is_actif ? 'Actif' : 'Inactif' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="editTax(tax)" title="Modifier" class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button @click="toggleStatus(tax)" :title="tax.is_actif ? 'Désactiver' : 'Activer'" :class="['w-8 h-8 rounded-lg transition-all duration-200', tax.is_actif ? 'text-yellow-600 hover:bg-yellow-50 hover:text-yellow-800' : 'text-green-600 hover:bg-green-50 hover:text-green-800']">
                          <i :class="tax.is_actif ? 'fas fa-pause' : 'fas fa-play'"></i>
                        </button>
                        <button @click="deleteTax(tax)" title="Supprimer" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200">
                          <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <form v-else-if="activeTab === 'add'" @submit.prevent="submitTax" class="p-6 lg:p-8">
            <div class="space-y-6">
              <InputError :message="errors.server" />
              <div>
                <InputLabel for="libelle" value="Libellé *" />
                <TextInput id="libelle" type="text" class="mt-1 block w-full" v-model="form.libelle" placeholder="Ex: TVA 20%" autofocus />
                <InputError class="mt-2" :message="errors.libelle" />
              </div>
              <div>
                <InputLabel for="rate" value="Taux (%) *" />
                <TextInput id="rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" v-model.number="form.rate" placeholder="20.00" />
                <InputError class="mt-2" :message="errors.rate" />
              </div>
              <div v-if="form.rate == 0">
                <InputLabel for="motif_exoneration" value="Motif d'exonération" />
                <TextInput id="motif_exoneration" type="text" class="mt-1 block w-full" v-model="form.motif_exoneration" placeholder="Ex: Art. 293 B du CGI" />
                <InputError class="mt-2" :message="errors.motif_exoneration" />
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> Obligatoire si le taux est 0 %.</p>
              </div>
              <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <Checkbox v-model="form.is_actif" />
                  Actif
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <Checkbox v-model="form.is_default" />
                  Défaut
                </label>
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
                  <span v-else>{{ editingId ? 'Modifier le taux' : 'Enregistrer le taux' }}</span>
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
import Checkbox from "../../components/Checkbox.vue";
import axios from "axios";
import { success, error, validation, confirm } from "../../helpers/notifications";

const authStore = useAuthStore();

const activeTab = ref("list");
const isLoading = ref(false);
const isSubmitting = ref(false);
const editingId = ref(null);
const taxRates = ref([]);

const form = reactive({
  libelle: "",
  rate: 0,
  motif_exoneration: "",
  is_actif: true,
  is_default: false,
});

const errors = reactive({
  libelle: "",
  rate: "",
  motif_exoneration: "",
  server: "",
});

const fetchTaxRates = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get("/api/tax-rates", { params: { company_id: companyId } });
    taxRates.value = data;
  } catch {
    error("Erreur", "Impossible de charger les taux.");
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  form.libelle = "";
  form.rate = 0;
  form.motif_exoneration = "";
  form.is_actif = true;
  form.is_default = false;
  errors.libelle = "";
  errors.rate = "";
  errors.motif_exoneration = "";
  errors.server = "";
  editingId.value = null;
};

const editTax = (tax) => {
  editingId.value = tax.id;
  form.libelle = tax.libelle;
  form.rate = tax.rate;
  form.motif_exoneration = tax.motif_exoneration || "";
  form.is_actif = tax.is_actif;
  form.is_default = tax.is_default || false;
  activeTab.value = "add";
};

const submitTax = async () => {
  Object.keys(errors).forEach(k => errors[k] = "");
  isSubmitting.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isSubmitting.value = false;
    return;
  }

  if (!form.libelle.trim()) {
    errors.libelle = "Le libellé est requis.";
    isSubmitting.value = false;
    return;
  }
  if (form.rate === undefined || form.rate === null || isNaN(form.rate)) {
    errors.rate = "Le taux est requis.";
    isSubmitting.value = false;
    return;
  }
  if (form.rate < 0 || form.rate > 100) {
    errors.rate = "Le taux doit être entre 0 et 100.";
    isSubmitting.value = false;
    return;
  }
  if (form.rate == 0 && !form.motif_exoneration?.trim()) {
    errors.motif_exoneration = "Veuillez saisir un motif d'exonération.";
    isSubmitting.value = false;
    return;
  }

  const payload = {
    libelle: form.libelle,
    rate: form.rate,
    motif_exoneration: form.motif_exoneration || null,
    company_id: companyId,
    is_actif: form.is_actif,
    is_default: form.is_default,
  };

  try {
    if (editingId.value) {
      await axios.put(`/api/tax-rates/${editingId.value}`, payload);
      success("Modifié !", "Le taux a été modifié.");
    } else {
      await axios.post("/api/tax-rates", payload);
      success("Ajouté !", "Le taux a été ajouté.");
    }
    resetForm();
    await fetchTaxRates();
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

const toggleStatus = async (tax) => {
  const action = tax.is_actif ? "désactiver" : "activer";
  const result = await confirm("Confirmer", `Voulez-vous ${action} ce taux ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.patch(`/api/tax-rates/${tax.id}/toggle`);
    tax.is_actif = !tax.is_actif;
    success("Succès", `Taux ${action} avec succès.`);
  } catch {
    error("Erreur", "Impossible de modifier le statut.");
  }
};

const deleteTax = async (tax) => {
  const result = await confirm("Supprimer le taux", `Voulez-vous vraiment supprimer "${tax.libelle}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/tax-rates/${tax.id}`);
    taxRates.value = taxRates.value.filter(t => t.id !== tax.id);
    success("Supprimé !", "Le taux a été supprimé.");
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.error) {
      error("Erreur", err.response.data.error);
    } else {
      error("Erreur", "Impossible de supprimer le taux.");
    }
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (taxRates.value.length === 0) fetchTaxRates();
  }
};

onMounted(fetchTaxRates);
</script>