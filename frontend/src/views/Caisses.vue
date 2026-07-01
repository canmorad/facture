<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import Swal from "sweetalert2";

const activeTab = ref("session");
const caisses = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const activeSession = ref(null);
const selectedCaisseForOpen = ref(null);

const newCaisseForm = reactive({
  name: "",
  initial_balance: "",
  type: "espèce",
});
const newCaisseErrors = reactive({
  name: "",
  initial_balance: "",
  type: "",
  server: "",
});

const openSessionForm = reactive({
  opening_balance: 0,
});
const openSessionErrors = reactive({ opening_balance: "", server: "" });

const transactionForm = reactive({
  type: "entrée",
  amount: "",
  description: "",
  date: new Date().toISOString().split("T")[0],
  category: "",
});

const transactionErrors = reactive({
  type: "",
  amount: "",
  description: "",
  date: "",
  category: "",
  server: "",
});

const filters = reactive({
  date_start: "",
  date_end: "",
  type: "",
  search: "",
});

const sortieCategories = [
  "Fournitures",
  "Transport",
  "Repas",
  "Abonnements",
  "Autre",
];
const entreeCategories = [
  "Vente direct",
  "Apport personnel",
  "Ajustement",
  "Autre",
];

const allTransactions = computed(() => {
  const transactions = [];
  caisses.value.forEach((caisse) => {
    caisse.transactions?.forEach((trans) => {
      transactions.push({
        ...trans,
        caisse_name: caisse.name,
        caisse_type: caisse.type,
      });
    });
  });
  return transactions.sort((a, b) => new Date(b.date) - new Date(a.date));
});

const filteredTransactions = computed(() => {
  let result = [...allTransactions.value];
  if (filters.date_start) {
    result = result.filter((t) => t.date >= filters.date_start);
  }
  if (filters.date_end) {
    result = result.filter((t) => t.date <= filters.date_end);
  }
  if (filters.type) {
    result = result.filter((t) => t.type === filters.type);
  }
  if (filters.search) {
    const term = filters.search.toLowerCase();
    result = result.filter(
      (t) =>
        t.description.toLowerCase().includes(term) ||
        t.caisse_name.toLowerCase().includes(term),
    );
  }
  return result;
});

const fetchCaisses = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/caisses");
    caisses.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les caisses.",
      icon: "error",
    });
  } finally {
    isLoading.value = false;
  }
};

const fetchActiveSession = async () => {
  try {
    const { data } = await axios.get("/api/caisses/active-session");
    activeSession.value = data;
    if (data && data.caisse_id) {
      selectedCaisseForOpen.value = data.caisse_id;
    }
  } catch {
    activeSession.value = null;
  }
};

const createCaisse = async () => {
  Object.keys(newCaisseErrors).forEach((k) => (newCaisseErrors[k] = ""));
  if (!newCaisseForm.name.trim()) {
    newCaisseErrors.name = "Le nom est requis.";
    return;
  }
  if (!newCaisseForm.initial_balance || newCaisseForm.initial_balance < 0) {
    newCaisseErrors.initial_balance = "Solde initial invalide.";
    return;
  }
  isSubmitting.value = true;
  try {
    const { data } = await axios.post("/api/caisses", newCaisseForm);
    Swal.fire({
      title: "Caisse créée",
      text: "La caisse a été ajoutée avec succès.",
      icon: "success",
      confirmButtonColor: "#062121",
    });
    // Reset form
    newCaisseForm.name = "";
    newCaisseForm.initial_balance = "";
    newCaisseForm.type = "espèce";
    await fetchCaisses();
    // Switch to session tab and select the newly created caisse
    activeTab.value = "session";
    selectedCaisseForOpen.value = data.id;
    await fetchActiveSession();
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      if (e.name) newCaisseErrors.name = e.name[0];
      if (e.initial_balance)
        newCaisseErrors.initial_balance = e.initial_balance[0];
      if (e.type) newCaisseErrors.type = e.type[0];
    } else {
      newCaisseErrors.server = "Une erreur est survenue.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

const openSession = async () => {
  openSessionErrors.opening_balance = "";
  openSessionErrors.server = "";
  if (!selectedCaisseForOpen.value) {
    Swal.fire("Erreur", "Veuillez sélectionner une caisse.", "error");
    return;
  }
  if (
    !openSessionForm.opening_balance ||
    openSessionForm.opening_balance <= 0
  ) {
    openSessionErrors.opening_balance =
      "Le solde d'ouverture doit être supérieur à 0.";
    return;
  }
  isSubmitting.value = true;
  try {
    await axios.post("/api/caisses/open-session", {
      caisse_id: selectedCaisseForOpen.value,
      opening_balance: openSessionForm.opening_balance,
    });
    Swal.fire(
      "Session ouverte",
      "La session a été ouverte avec succès.",
      "success",
    );
    openSessionForm.opening_balance = 0;
    await fetchActiveSession();
    await fetchCaisses();
  } catch (error) {
    if (error.response?.data?.message) {
      openSessionErrors.server = error.response.data.message;
    } else {
      openSessionErrors.server = "Erreur lors de l'ouverture de la session.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

const closeSession = async () => {
  const confirm = await Swal.fire({
    title: "Clôturer la session ?",
    text: "Cette action va fermer la session en cours.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, clôturer",
    cancelButtonText: "Annuler",
  });
  if (!confirm.isConfirmed) return;
  isSubmitting.value = true;
  try {
    await axios.post("/api/caisses/close-session");
    Swal.fire(
      "Session clôturée",
      "La session a été clôturée avec succès.",
      "success",
    );
    await fetchActiveSession();
    await fetchCaisses();
  } catch (error) {
    Swal.fire(
      "Erreur",
      error.response?.data?.message || "Impossible de clôturer la session.",
      "error",
    );
  } finally {
    isSubmitting.value = false;
  }
};

const addTransaction = async () => {
  Object.keys(transactionErrors).forEach((k) => (transactionErrors[k] = ""));
  if (!transactionForm.amount || transactionForm.amount <= 0) {
    transactionErrors.amount = "Montant invalide.";
    return;
  }
  if (!transactionForm.description.trim()) {
    transactionErrors.description = "Description requise.";
    return;
  }
  if (!transactionForm.category) {
    transactionErrors.category = "Catégorie requise.";
    return;
  }
  isSubmitting.value = true;
  try {
    await axios.post("/api/caisses/transaction", {
      type: transactionForm.type,
      amount: transactionForm.amount,
      description: transactionForm.description,
      date: transactionForm.date,
      source: transactionForm.category,
    });
    Swal.fire({
      title: "Opération enregistrée !",
      text: `Le solde de la caisse a été mis à jour.`,
      icon: "success",
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 2500,
    });
    transactionForm.amount = "";
    transactionForm.description = "";
    transactionForm.date = new Date().toISOString().split("T")[0];
    transactionForm.category = "";
    await fetchCaisses();
    await fetchActiveSession();
    activeTab.value = "history";
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      if (e.type) transactionErrors.type = e.type[0];
      if (e.amount) transactionErrors.amount = e.amount[0];
      if (e.description) transactionErrors.description = e.description[0];
      if (e.date) transactionErrors.date = e.date[0];
      if (e.source) transactionErrors.category = e.source[0];
    } else if (error.response?.data?.message) {
      transactionErrors.server = error.response.data.message;
    } else {
      transactionErrors.server = "Erreur lors de l’opération.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

const formatMoney = (value) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA");
};

const formatDateTime = (dateTimeStr) => {
  if (!dateTimeStr) return "—";
  return new Date(dateTimeStr).toLocaleString("fr-MA");
};

const getTypeBadge = (type) => {
  return type === "espèce"
    ? "bg-green-100 text-green-700"
    : "bg-blue-100 text-blue-700";
};

const getTransactionBadge = (type) => {
  return type === "entrée"
    ? "bg-green-100 text-green-700"
    : "bg-red-100 text-red-700";
};

const getTransactionSign = (type) => {
  return type === "entrée" ? "+" : "-";
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "session") {
    fetchActiveSession();
  }
  if (tab === "operation") {
    Object.keys(transactionErrors).forEach((k) => (transactionErrors[k] = ""));
  }
  if (tab === "create_caisse") {
    Object.keys(newCaisseErrors).forEach((k) => (newCaisseErrors[k] = ""));
  }
};

onMounted(async () => {
  await fetchCaisses();
  await fetchActiveSession();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div class="border-b border-gray-200 px-6 pt-4 pb-0">
            <div class="flex gap-6 flex-wrap">
              <button
                @click="changeTab('session')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'session'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-clock"></i>
                Gestion de Session
              </button>
              <button
                @click="changeTab('operation')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'operation'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-exchange-alt"></i>
                Faire une opération
              </button>
              <button
                @click="changeTab('history')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'history'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-history"></i>
                Historique
              </button>
              <button
                @click="changeTab('create_caisse')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'create_caisse'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-plus-circle"></i>
                Nouvelle Caisse
              </button>
            </div>
          </div>

          <!-- ========== TAB SESSION ========== -->
          <div v-if="activeTab === 'session'" class="p-6 lg:p-8">
            <div class="mb-6">
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                  <InputLabel value="Caisse à ouvrir *" />
                  <!-- Message si aucune caisse -->
                  <div
                    v-if="caisses.length === 0"
                    class="mt-2 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800 text-sm"
                  >
                    <i class="fas fa-info-circle mr-2"></i>
                    Aucune caisse enregistrée. Veuillez créer une caisse dans
                    l'onglet <strong>"Nouvelle Caisse"</strong>.
                  </div>
                  <select
                    v-else
                    v-model="selectedCaisseForOpen"
                    :disabled="!!activeSession && activeSession.caisse"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 disabled:bg-gray-100 disabled:cursor-not-allowed"
                  >
                    <option :value="null">-- Sélectionnez une caisse --</option>
                    <option v-for="c in caisses" :key="c.id" :value="c.id">
                      {{ c.name }} ({{
                        c.type === "espèce" ? "Espèce" : "Banque"
                      }})
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Session active valide (avec caisse associée) -->
            <div
              v-if="activeSession && activeSession.caisse && caisses.length > 0"
            >
              <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="text-lg font-bold text-[#062121] mb-4">
                  Session en cours
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <span class="font-semibold">Caisse :</span>
                    {{ activeSession.caisse.name }}
                  </div>
                  <div>
                    <span class="font-semibold">Solde d'ouverture :</span>
                    {{ formatMoney(activeSession.opening_balance) }} MAD
                  </div>
                  <div>
                    <span class="font-semibold">Ouverte le :</span>
                    {{ formatDateTime(activeSession.opened_at) }}
                  </div>
                  <div>
                    <span class="font-semibold">Solde actuel :</span>
                    {{ formatMoney(activeSession.caisse.current_balance) }} MAD
                  </div>
                </div>
                <div class="mt-6 flex justify-end">
                  <button
                    @click="closeSession"
                    :disabled="isSubmitting"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition"
                  >
                    <i class="fas fa-lock"></i> Clôturer la session
                  </button>
                </div>
              </div>
            </div>

            <!-- Formulaire d'ouverture (uniquement s'il existe des caisses et pas de session active valide) -->
            <div
              v-else-if="
                caisses.length > 0 && (!activeSession || !activeSession.caisse)
              "
              class="rounded-xl border border-gray-200 bg-white p-6"
            >
              <h3 class="text-lg font-bold text-[#062121] mb-4">
                Ouvrir une session
              </h3>
              <form @submit.prevent="openSession" class="space-y-4">
                <div>
                  <InputLabel value="Solde d'ouverture (MAD) *" />
                  <TextInput
                    type="number"
                    step="0.01"
                    min="0"
                    v-model="openSessionForm.opening_balance"
                    placeholder="0.00"
                  />
                  <InputError :message="openSessionErrors.opening_balance" />
                </div>
                <InputError :message="openSessionErrors.server" />
                <div class="flex justify-end">
                  <PrimaryButton
                    :disabled="isSubmitting || caisses.length === 0"
                  >
                    <span v-if="isSubmitting">Ouverture...</span>
                    <span v-else>Ouvrir la session</span>
                  </PrimaryButton>
                </div>
              </form>
            </div>
          </div>

          <!-- ========== TAB OPÉRATION ========== -->
          <div v-else-if="activeTab === 'operation'" class="p-6 lg:p-8">
            <form @submit.prevent="addTransaction" class="space-y-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <InputLabel value="Type *" />
                  <div class="flex gap-4 mt-1">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        value="entrée"
                        v-model="transactionForm.type"
                        class="text-[#C5F82A]"
                      />
                      <span class="text-sm text-gray-700">Entrée</span>
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        value="sortie"
                        v-model="transactionForm.type"
                        class="text-red-500"
                      />
                      <span class="text-sm text-gray-700">Sortie</span>
                    </label>
                  </div>
                </div>
                <div>
                  <InputLabel value="Montant *" />
                  <TextInput
                    type="number"
                    step="0.01"
                    min="0"
                    v-model="transactionForm.amount"
                    placeholder="0.00"
                  />
                  <InputError :message="transactionErrors.amount" />
                </div>
              </div>
              <div>
                <InputLabel value="Description *" />
                <TextInput
                  v-model="transactionForm.description"
                  placeholder="Ex: Achat fournitures, Vente client..."
                />
                <InputError :message="transactionErrors.description" />
              </div>
              <div>
                <InputLabel value="Date *" />
                <input
                  type="date"
                  v-model="transactionForm.date"
                  class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                />
                <InputError :message="transactionErrors.date" />
              </div>
              <div>
                <InputLabel
                  :value="
                    transactionForm.type === 'sortie'
                      ? 'Catégorie de charge *'
                      : 'Source de l\'opération *'
                  "
                />
                <select
                  v-model="transactionForm.category"
                  class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                >
                  <option value="">-- Choisir --</option>
                  <option
                    v-for="opt in transactionForm.type === 'sortie'
                      ? sortieCategories
                      : entreeCategories"
                    :key="opt"
                    :value="opt"
                  >
                    {{ opt }}
                  </option>
                </select>
                <InputError :message="transactionErrors.category" />
              </div>
              <InputError :message="transactionErrors.server" />
              <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button
                  type="button"
                  @click="activeTab = 'history'"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">Enregistrement...</span>
                  <span v-else>Enregistrer l'opération</span>
                </PrimaryButton>
              </div>
            </form>
          </div>

          <!-- ========== TAB HISTORIQUE ========== -->
          <div v-else-if="activeTab === 'history'" class="p-6 lg:p-8">
            <div
              class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-white p-4 rounded-xl border border-gray-200"
            >
              <div>
                <InputLabel value="Date début" />
                <input
                  type="date"
                  v-model="filters.date_start"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <InputLabel value="Date fin" />
                <input
                  type="date"
                  v-model="filters.date_end"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <InputLabel value="Type" />
                <select
                  v-model="filters.type"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                >
                  <option value="">Tous</option>
                  <option value="entrée">Entrée</option>
                  <option value="sortie">Sortie</option>
                </select>
              </div>
              <div>
                <InputLabel value="Recherche" />
                <input
                  type="text"
                  v-model="filters.search"
                  placeholder="Description ou caisse..."
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Date
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Caisse
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Type
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Catégorie
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Montant
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Description
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="trans in filteredTransactions"
                    :key="trans.id"
                    class="hover:bg-gray-50 transition-colors"
                  >
                    <td
                      class="px-4 py-4 whitespace-nowrap text-sm text-gray-600"
                    >
                      {{ formatDate(trans.date) }}
                    </td>
                    <td
                      class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                      {{ trans.caisse_name }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span
                        :class="[
                          'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                          getTransactionBadge(trans.type),
                        ]"
                      >
                        {{ trans.type === "entrée" ? "Entrée" : "Sortie" }}
                      </span>
                    </td>
                    <td
                      class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"
                    >
                      {{ trans.source || "—" }}
                    </td>
                    <td
                      class="px-4 py-4 whitespace-nowrap text-right text-sm font-mono font-semibold"
                      :class="
                        trans.type === 'entrée'
                          ? 'text-green-600'
                          : 'text-red-600'
                      "
                    >
                      {{ getTransactionSign(trans.type)
                      }}{{ formatMoney(trans.amount) }} MAD
                    </td>
                    <td
                      class="px-4 py-4 text-sm text-gray-500 max-w-[250px] truncate"
                      :title="trans.description"
                    >
                      {{ trans.description }}
                    </td>
                  </tr>
                  <tr v-if="filteredTransactions.length === 0">
                    <td
                      colspan="6"
                      class="px-4 py-8 text-center text-gray-400 italic"
                    >
                      Aucune transaction trouvée
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ========== TAB NOUVELLE CAISSE ========== -->
          <div v-else-if="activeTab === 'create_caisse'" class="p-6 lg:p-8">
            <div
              class="rounded-xl border border-gray-200 bg-white shadow-sm p-6"
            >
              <form @submit.prevent="createCaisse" class="space-y-6">
                <InputError :message="newCaisseErrors.server" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <InputLabel value="Nom de la caisse *" />
                    <TextInput
                      v-model="newCaisseForm.name"
                      placeholder="Ex: Caisse Principale, Compte BMCE"
                      autofocus
                    />
                    <InputError :message="newCaisseErrors.name" />
                  </div>

                  <div>
                    <InputLabel value="Solde initial (MAD) *" />
                    <TextInput
                      type="number"
                      step="0.01"
                      min="0"
                      v-model="newCaisseForm.initial_balance"
                      placeholder="0.00"
                    />
                    <InputError :message="newCaisseErrors.initial_balance" />
                  </div>

                  <div>
                    <InputLabel value="Type *" />
                    <div class="flex gap-5 mt-2">
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input
                          type="radio"
                          value="espèce"
                          v-model="newCaisseForm.type"
                          class="text-[#C5F82A]"
                        />
                        <span
                          ><i class="fas fa-money-bill-wave mr-1"></i>
                          Espèce</span
                        >
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input
                          type="radio"
                          value="banque"
                          v-model="newCaisseForm.type"
                          class="text-[#C5F82A]"
                        />
                        <span
                          ><i class="fas fa-university mr-1"></i> Banque</span
                        >
                      </label>
                    </div>
                    <InputError :message="newCaisseErrors.type" />
                  </div>
                </div>

                <div
                  class="flex justify-end gap-3 pt-4 border-t border-gray-100"
                >
                  <button
                    type="button"
                    @click="activeTab = 'session'"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    Annuler
                  </button>
                  <PrimaryButton :disabled="isSubmitting">
                    <span v-if="isSubmitting">Création...</span>
                    <span v-else>Créer la caisse</span>
                  </PrimaryButton>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
