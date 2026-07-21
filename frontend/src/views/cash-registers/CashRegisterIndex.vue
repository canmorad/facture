<script setup>
import { reactive, ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import Checkbox from "@/components/Checkbox.vue";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingList = ref(false);
const editingCashRegisterId = ref(null);

const cashRegisters = ref([]);

const typeOptions = [
  { value: "cash", label: "Espèces" },
  { value: "bank", label: "Banque" },
  { value: "vault", label: "Coffre-fort" },
  { value: "petty_cash", label: "Caisse Petite Monnaie" },
];

const currencyOptions = [
  { value: "MAD", label: "MAD - Dirham Marocain" },
  { value: "USD", label: "USD - Dollar Américain" },
  { value: "EUR", label: "EUR - Euro" },
  { value: "GBP", label: "GBP - Livre Sterling" },
];

const form = reactive({
  name: "",
  type: "cash",
  currency: "MAD",
  opening_balance: 0,
  is_active: true,
  is_default: false,
  notes: "",
});

const errors = reactive({
  name: "",
  type: "",
  currency: "",
  opening_balance: "",
  notes: "",
  server: "",
});

const getTypeLabel = (type) => {
  return typeOptions.find((opt) => opt.value === type)?.label || type;
};

const getTypeClass = (type) => {
  const classes = {
    cash: "bg-emerald-50 text-emerald-700",
    bank: "bg-indigo-50 text-indigo-700",
    vault: "bg-amber-50 text-amber-700",
    petty_cash: "bg-teal-50 text-teal-700",
  };
  return classes[type] || "bg-gray-100 text-gray-700";
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    style: "currency",
    currency: "MAD",
  }).format(amount || 0);
};

const fetchCashRegisters = async () => {
  isLoadingList.value = true;
  try {
    const { data } = await axios.get("/api/cash-registers/all");
    cashRegisters.value = data.cash_registers || [];
  } catch {
    error("Erreur", "Impossible de charger les caisses.");
  } finally {
    isLoadingList.value = false;
  }
};

const resetForm = () => {
  form.name = "";
  form.type = "cash";
  form.currency = "MAD";
  form.opening_balance = 0;
  form.is_active = true;
  form.is_default = false;
  form.notes = "";
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingCashRegisterId.value = null;
};

const editCashRegister = (cr) => {
  editingCashRegisterId.value = cr.id;
  form.name = cr.name;
  form.type = cr.type;
  form.currency = cr.currency;
  form.opening_balance = cr.opening_balance || 0;
  form.is_active = cr.is_active ?? true;
  form.is_default = cr.is_default ?? false;
  form.notes = cr.notes || "";
  activeTab.value = "add";
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  isLoading.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isLoading.value = false;
    return;
  }

  try {
    const payload = { ...form };
    payload.opening_balance = Number(payload.opening_balance);

    if (editingCashRegisterId.value) {
      await axios.put(`/api/cash-registers/${editingCashRegisterId.value}`, payload);
      success("Modifiée !", "La caisse a été modifiée avec succès.");
    } else {
      payload.code = generateCode();
      await axios.post("/api/cash-registers", payload);
      success("Créée !", "La caisse a été créée avec succès.");
    }
    resetForm();
    await fetchCashRegisters();
    activeTab.value = "list";
  } catch (err) {
    if (err.response && err.response.status === 422) {
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
      error("Erreur", "Impossible d'enregistrer la caisse.");
    }
  } finally {
    isLoading.value = false;
  }
};

const generateCode = () => {
  const prefix = "CAISSE";
  const count = cashRegisters.value.length + 1;
  return `${prefix}-${String(count).padStart(3, '0')}`;
};

const deleteCashRegister = async (id, name) => {
  const result = await confirm("Supprimer la caisse", `Supprimer "${name}" définitivement ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/cash-registers/${id}`);
    success("Supprimée !", "La caisse a été supprimée.");
    await fetchCashRegisters();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer la caisse.");
  }
};

const toggleStatus = async (id, isActive) => {
  try {
    await axios.patch(`/api/cash-registers/${id}/toggle-status`);
    success(isActive ? "Désactivée" : "Activée", `La caisse a été ${isActive ? "désactivée" : "activée"}.`);
    await fetchCashRegisters();
  } catch {
    error("Erreur", "Impossible de modifier le statut.");
  }
};

const setAsDefault = async (id) => {
  try {
    await axios.put(`/api/cash-registers/${id}/set-default`);
    success("Définie par défaut", "Cette caisse est maintenant la caisse par défaut.");
    await fetchCashRegisters();
  } catch {
    error("Erreur", "Impossible de définir par défaut.");
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (cashRegisters.value.length === 0) fetchCashRegisters();
  }
};

const openDefaultSession = async () => {
  try {
    const { data } = await axios.get("/api/cash-registers/all");
    const cashRegisters = data.cash_registers || [];

    if (cashRegisters.length === 0) {
      error("Erreur", "Aucune caisse configurée.");
      router.push({ name: 'invoice.index' });
      return;
    }

    // Find default or first register
    const targetRegister = cashRegisters.find(cr => cr.is_default) || cashRegisters[0];
    const openingBalance = targetRegister.current_balance || 0;

    await axios.post(`/api/cash-registers/${targetRegister.id}/open-session`, {
      opening_balance: openingBalance,
      notes: "Ouverture automatique via formulaire de paiement",
    });

    success("Session ouverte !", "La session de caisse a été ouverte avec succès.");

    // Check if we should return to payment form
    if (sessionStorage.getItem('returnToPayment') === 'true') {
      sessionStorage.removeItem('returnToPayment');
      router.push({ name: 'invoice.index' });
    }
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'ouvrir la session de caisse."
    );
    router.push({ name: 'invoice.index' });
  }
};

onMounted(async () => {
  await fetchCashRegisters();

  // Check if we should open a session automatically
  if (route.query.openSession === 'true') {
    await openDefaultSession();
  }
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <div class="border-b border-gray-200 px-6 pt-2">
            <nav class="flex space-x-6 overflow-x-auto">
              <button
                @click="changeTab('list')"
                class="py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2"
                :class="activeTab === 'list'
                  ? 'border-[#C5F82A] text-[#062121]'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              >
                <i class="fas fa-list text-xs"></i>
                Liste des caisses
                <span
                  v-if="cashRegisters.length > 0"
                  class="ml-1 px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full text-xs"
                >{{ cashRegisters.length }}</span>
              </button>

              <button
                @click="changeTab('add')"
                class="py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2"
                :class="activeTab === 'add'
                  ? 'border-[#C5F82A] text-[#062121]'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              >
                <i :class="['fas text-xs', editingCashRegisterId ? 'fa-edit' : 'fa-plus-circle']"></i>
                {{ editingCashRegisterId ? 'Modifier la caisse' : 'Ajouter une caisse' }}
              </button>
            </nav>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingList" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des caisses...</p>
            </div>

            <div v-else-if="cashRegisters.length === 0" class="text-center py-12">
              <i class="fas fa-cash-register text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucune caisse enregistrée pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre première caisse
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Caisse</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Devise</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Solde actuel</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Session</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="cr in cashRegisters" :key="cr.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div>
                          <div class="text-sm font-semibold text-gray-900">{{ cr.name }}</div>
                          <div class="text-xs text-gray-400 flex items-center gap-1">
                            <i class="fas fa-hashtag text-[10px]"></i>
                            {{ cr.code }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium', getTypeClass(cr.type)]">
                        <i :class="['fas text-[10px]', cr.type === 'cash' ? 'fa-money-bill-wave' : cr.type === 'bank' ? 'fa-university' : cr.type === 'vault' ? 'fa-safe' : 'fa-coins']"></i>
                        {{ getTypeLabel(cr.type) }}
                      </span>
                      <div v-if="cr.is_default" class="mt-1">
                        <span class="inline-flex items-center gap-1 text-[10px] text-[#062121]">
                          <i class="fas fa-star text-[#C5F82A]"></i>
                          Par défaut
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="inline-flex items-center gap-1 text-sm text-gray-700">
                        <i class="fas fa-coins text-xs text-gray-400"></i>
                        {{ cr.currency }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-[#062121]">
                        {{ formatCurrency(cr.calculated_balance ?? cr.current_balance) }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div v-if="cr.active_session" class="flex items-center gap-1">
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600">
                          <i class="fas fa-clock"></i>
                          {{ new Date(cr.active_session.opened_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                      </div>
                      <span v-else class="text-xs text-gray-400">—</span>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="$router.push({ name: 'cash-register.dashboard', params: { id: cr.id } })"
                          title="Tableau de bord"
                          class="w-8 h-8 rounded-lg text-[#062121] hover:bg-[#C5F82A] transition-all duration-200"
                        >
                          <i class="fas fa-chart-line text-sm"></i>
                        </button>
                        <button
                          @click="editCashRegister(cr)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          v-if="!cr.is_default"
                          @click="setAsDefault(cr.id)"
                          title="Définir par défaut"
                          class="w-8 h-8 rounded-lg text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 transition-all duration-200"
                        >
                          <i class="fas fa-star text-sm"></i>
                        </button>
                        <button
                          @click="toggleStatus(cr.id, cr.is_active)"
                          :title="cr.is_active ? 'Désactiver' : 'Activer'"
                          class="w-8 h-8 rounded-lg"
                          :class="cr.is_active ? 'text-emerald-500 hover:bg-emerald-50' : 'text-gray-400 hover:bg-gray-100'"
                        >
                          <i :class="['fas text-sm', cr.is_active ? 'fa-toggle-on' : 'fa-toggle-off']"></i>
                        </button>
                        <button
                          @click="deleteCashRegister(cr.id, cr.name)"
                          title="Supprimer"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-rose-500 hover:text-white transition-all duration-200"
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

              <div>
                <h3 class="mb-4 text-base font-semibold text-[#062121]">Informations générales</h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="md:col-span-2">
                    <InputLabel for="name" value="Nom de la caisse *" />
                    <TextInput
                      id="name"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.name"
                      placeholder="Ex: Caisse Principale"
                      autofocus
                    />
                    <InputError class="mt-2" :message="errors.name" />
                  </div>

                  <div>
                    <InputLabel for="type" value="Type de caisse *" />
                    <CustomSelect
                      id="type"
                      v-model="form.type"
                      :options="typeOptions"
                      label-key="label"
                      value-key="value"
                      placeholder="Sélectionner un type"
                    />
                    <InputError class="mt-2" :message="errors.type" />
                  </div>

                  <div>
                    <InputLabel for="currency" value="Devise" />
                    <CustomSelect
                      id="currency"
                      v-model="form.currency"
                      :options="currencyOptions"
                      label-key="label"
                      value-key="value"
                      placeholder="Sélectionner une devise"
                    />
                    <InputError class="mt-2" :message="errors.currency" />
                  </div>
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">Configuration</h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                    <InputLabel for="opening_balance" value="Solde d'ouverture" />
                    <TextInput
                      id="opening_balance"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1 block w-full"
                      v-model="form.opening_balance"
                      placeholder="0.00"
                    />
                    <InputError class="mt-2" :message="errors.opening_balance" />
                  </div>

                  <div class="flex items-center gap-6 pt-6">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                      <Checkbox v-model="form.is_active" />
                      <span class="text-sm text-gray-700">Caisse active</span>
                    </label>

                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                      <Checkbox v-model="form.is_default" />
                      <span class="text-sm text-gray-700">Définir par défaut</span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <InputLabel for="notes" value="Notes" />
                <textarea
                  id="notes"
                  v-model="form.notes"
                  rows="3"
                  placeholder="Notes supplémentaires..."
                  class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                ></textarea>
                <InputError class="mt-2" :message="errors.notes" />
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
                  <span v-else>{{ editingCashRegisterId ? 'Modifier la caisse' : 'Enregistrer la caisse' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
