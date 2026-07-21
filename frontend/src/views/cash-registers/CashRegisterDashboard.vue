<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const activeTab = ref("overview");
const isLoading = ref(true);
const isSubmitting = ref(false);
const editingTransactionId = ref(null);

const dashboardData = ref({
  register: null,
  active_session: null,
  today_transactions: [],
  today_total_in: 0,
  today_total_out: 0,
  expected_balance: 0,
  recent_sessions: [],
});

const openSessionForm = ref({
  opening_balance: 0,
  notes: "",
});

const closeSessionForm = ref({
  actual_closing_balance: 0,
  notes: "",
});

const transactionForm = ref({
  type: "in",
  amount: "",
  payment_method: "cash",
  description: "",
  to_cash_register_id: null,
  transaction_date: null,
});

const transactionErrors = ref({
  type: "",
  amount: "",
  payment_method: "",
  description: "",
  to_cash_register_id: "",
  cash_register_id: "",
  transaction_date: "",
});

const transactionTypeOptions = [
  { value: "in", label: "Entrée d'argent" },
  { value: "out", label: "Sortie d'argent" },
  { value: "transfer", label: "Transfert" },
];

const cashRegisterOptions = ref([]);

const paymentMethodOptions = [
  { value: "cash", label: "Espèces" },
  { value: "card", label: "Carte bancaire" },
  { value: "check", label: "Chèque" },
  { value: "transfer", label: "Virement" },
  { value: "other", label: "Autre" },
];

const cashRegisterId = computed(() => route.params.id);
const activeSession = computed(() => dashboardData.value.active_session);
const hasOpenSession = computed(() => !!activeSession.value);
const discrepancy = computed(() => {
  if (!activeSession.value) return 0;
  const expected = dashboardData.value.expected_balance;
  const actual = closeSessionForm.value.actual_closing_balance;
  return actual - expected;
});

const formatCurrency = (amount) => {
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : (amount ?? 0);
  if (isNaN(numAmount)) return '0,00 DH';

  const currency = dashboardData.value?.register?.currency || 'MAD';
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: currency,
  }).format(numAmount);
};

const fetchDashboard = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/cash-registers/${cashRegisterId.value}/dashboard`);
    dashboardData.value = data;

    if (data.active_session) {
      closeSessionForm.value.actual_closing_balance = data.expected_balance;
    }
  } catch {
    error("Erreur", "Impossible de charger le tableau de bord.");
    router.push({ name: "cash-register.index" });
  } finally {
    isLoading.value = false;
  }
};

const fetchCashRegisters = async () => {
  try {
    const { data } = await axios.get("/api/cash-registers/create");
    return data;
  } catch {
    return { cash_registers: [], active_sessions: {} };
  }
};

const openSession = async () => {
  isSubmitting.value = true;
  try {
    const payload = {
      opening_balance: openSessionForm.value.opening_balance,
      notes: openSessionForm.value.notes,
    };

    await axios.post(`/api/cash-registers/${cashRegisterId.value}/open-session`, payload);
    success("Session ouverte", "La session a été ouverte avec succès.");
    openSessionForm.value = { opening_balance: 0, notes: "" };
    activeTab.value = "overview";
    await fetchDashboard();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'ouvrir la session.");
  } finally {
    isSubmitting.value = false;
  }
};

const closeSession = async () => {
  isSubmitting.value = true;
  try {
    const payload = {
      actual_closing_balance: closeSessionForm.value.actual_closing_balance,
      notes: closeSessionForm.value.notes,
    };

    await axios.post(`/api/cash-registers/${activeSession.value.id}/close-session`, payload);
    success("Session clôturée", "La session a été clôturée avec succès.");
    closeSessionForm.value = { actual_closing_balance: 0, notes: "" };
    activeTab.value = "overview";
    await fetchDashboard();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de clôturer la session.");
  } finally {
    isSubmitting.value = false;
  }
};

const initOpenSessionForm = async () => {
  openSessionForm.value = { opening_balance: 0, notes: "" };
  const data = await fetchCashRegisters();
  const currentRegister = data.cash_registers?.find(cr => cr.id === cashRegisterId.value);
  openSessionForm.value.opening_balance = currentRegister?.current_balance || 0;
};

const initCloseSessionForm = () => {
  closeSessionForm.value.actual_closing_balance = dashboardData.value.expected_balance;
  closeSessionForm.value.notes = "";
};

const resetTransactionForm = () => {
  transactionForm.value = {
    type: "in",
    amount: "",
    payment_method: "cash",
    description: "",
    to_cash_register_id: null,
    transaction_date: null,
  };
  cashRegisterOptions.value = [];
  Object.keys(transactionErrors.value).forEach((k) => (transactionErrors.value[k] = ""));
  editingTransactionId.value = null;
};

const initTransactionForm = async (type = "in") => {
  resetTransactionForm();
  transactionForm.value.type = type;

  if (type === "transfer") {
    const data = await fetchCashRegisters();
    cashRegisterOptions.value = data.cash_registers
      ?.filter(cr => cr.id !== cashRegisterId.value)
      .map(cr => ({ value: cr.id, label: cr.name })) || [];
  }
};

const editTransaction = async (transaction) => {
  editingTransactionId.value = transaction.id;
  transactionForm.value = {
    type: transaction.type,
    amount: transaction.amount,
    payment_method: transaction.payment_method || 'cash',
    description: transaction.description,
    to_cash_register_id: transaction.to_cash_register_id || null,
    transaction_date: transaction.transaction_date ? new Date(transaction.transaction_date).toISOString().slice(0, 16) : null,
  };

  if (transaction.type === "transfer") {
    const data = await fetchCashRegisters();
    cashRegisterOptions.value = data.cash_registers
      ?.filter(cr => cr.id !== cashRegisterId.value)
      .map(cr => ({ value: cr.id, label: cr.name })) || [];
  }

  const tabMap = {
    'in': 'transaction-in',
    'out': 'transaction-out',
    'transfer': 'transaction-transfer'
  };
  activeTab.value = tabMap[transaction.type] || 'transaction-in';
};

const saveTransaction = async () => {
  Object.keys(transactionErrors.value).forEach((k) => (transactionErrors.value[k] = ""));
  isSubmitting.value = true;

  try {
    const amount = parseFloat(transactionForm.value.amount);
    if (!amount || amount <= 0) {
      transactionErrors.value.amount = "Le montant doit être supérieur à 0.";
      error("Erreur", "Le montant doit être supérieur à 0.");
      isSubmitting.value = false;
      return;
    }

    const payload = {
      type: transactionForm.value.type,
      amount: amount,
      payment_method: transactionForm.value.payment_method || 'cash',
      description: transactionForm.value.description,
      cash_register_id: parseInt(cashRegisterId.value),
      transaction_date: transactionForm.value.transaction_date || new Date().toISOString(),
    };

    if (transactionForm.value.type === 'transfer') {
      payload.to_cash_register_id = parseInt(transactionForm.value.to_cash_register_id);
    }

    if (editingTransactionId.value) {
      await axios.put(`/api/cash-registers/transactions/${editingTransactionId.value}`, payload);
      success("Transaction modifiée", "La transaction a été modifiée avec succès.");
    } else {
      await axios.post("/api/cash-registers/transactions", payload);
      success("Transaction créée", "La transaction a été enregistrée avec succès.");
    }

    resetTransactionForm();
    activeTab.value = "overview";
    await fetchDashboard();
  } catch (err) {
    if (err.response?.status === 422) {
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (transactionErrors.value[key] !== undefined) {
          transactionErrors.value[key] = validationErrors[key][0];
        }
      });
      const firstError = Object.values(validationErrors)[0];
      const errorMsg = Array.isArray(firstError) ? firstError[0] : firstError;
      error("Erreur de validation", errorMsg || "Veuillez vérifier les champs du formulaire.");
    } else {
      error("Erreur", err.response?.data?.message || "Impossible d'enregistrer la transaction.");
    }
  } finally {
    isSubmitting.value = false;
  }
};

const deleteTransaction = async (transactionId) => {
  const transaction = dashboardData.value.today_transactions.find(t => t.id === transactionId);
  if (!transaction) return;

  const result = await confirm(
    "Supprimer la transaction",
    `Supprimer "${transaction.description}" (${formatCurrency(transaction.amount)}) ? Le solde sera recalculé automatiquement.`
  );
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/cash-registers/transactions/${transactionId}`);
    success("Supprimée", "La transaction a été supprimée et le solde recalculé.");
    await fetchDashboard();
  } catch {
    error("Erreur", "Impossible de supprimer la transaction.");
  }
};

const getTransactionIcon = (type) => {
  switch (type) {
    case "in":
      return "fa-arrow-down text-emerald-700";
    case "out":
      return "fa-arrow-up text-rose-700";
    case "transfer":
      return "fa-exchange-alt text-indigo-700";
    default:
      return "fa-circle text-gray-400";
  }
};

const getTransactionTypeLabel = (type) => {
  switch (type) {
    case "in":
      return "Entrée";
    case "out":
      return "Sortie";
    case "transfer":
      return "Transfert";
    default:
      return type;
  }
};

const getTransactionTypeClass = (type) => {
  switch (type) {
    case "in":
      return "bg-emerald-50 text-emerald-700 border border-emerald-200";
    case "out":
      return "bg-rose-50 text-rose-700 border border-rose-200";
    case "transfer":
      return "bg-indigo-50 text-indigo-700 border border-indigo-200";
    default:
      return "bg-gray-100 text-gray-700";
  }
};

const getTransactionIconBg = (type) => {
  switch (type) {
    case "in":
      return "bg-emerald-100";
    case "out":
      return "bg-rose-100";
    case "transfer":
      return "bg-indigo-100";
    default:
      return "bg-gray-100";
  }
};

const getTabIcon = (tab) => {
  switch (tab) {
    case "overview":
      return "fa-chart-pie";
    case "session":
      return "fa-clock";
    case "transactions":
      return "fa-receipt";
    case "sessions":
      return "fa-history";
    case "open-session":
      return "fa-play";
    case "close-session":
      return "fa-stop";
    case "transaction-in":
      return "fa-arrow-down";
    case "transaction-out":
      return "fa-arrow-up";
    case "transaction-transfer":
      return "fa-exchange-alt";
    default:
      return "fa-circle";
  }
};

const getTabLabel = (tab) => {
  switch (tab) {
    case "overview":
      return "Vue d'ensemble";
    case "session":
      return "Session en cours";
    case "transactions":
      return "Transactions";
    case "sessions":
      return "Historique";
    case "open-session":
      return "Ouvrir Session";
    case "close-session":
      return "Clôture Session";
    case "transaction-in":
      return "Entrée";
    case "transaction-out":
      return "Sortie";
    case "transaction-transfer":
      return "Transfert";
    default:
      return tab;
  }
};

const changeTab = async (tab) => {
  if (tab.startsWith('transaction-') && editingTransactionId.value) {
    resetTransactionForm();
  }
  activeTab.value = tab;
  if (tab === "open-session") {
    await initOpenSessionForm();
  } else if (tab === "close-session") {
    initCloseSessionForm();
  } else if (tab.startsWith("transaction-") && !editingTransactionId.value) {
    const type = tab.replace("transaction-", "");
    await initTransactionForm(type);
  }
};

onMounted(fetchDashboard);
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div v-if="isLoading" class="flex items-center justify-center h-64">
          <svg class="animate-spin h-8 w-8 text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else class="space-y-6">
          <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-[#C5F82A]/10 flex items-center justify-center">
                      <i class="fas fa-cash-register text-[#C5F82A] text-xl"></i>
                    </div>
                    <div>
                      <h1 class="text-2xl font-bold text-[#062121]">{{ dashboardData.register?.name }}</h1>
                      <p class="text-sm text-gray-500 flex items-center gap-3 mt-1">
                        <span class="flex items-center gap-1">
                          <i class="fas fa-hashtag text-[10px]"></i>
                          {{ dashboardData.register?.code }}
                        </span>
                        <span class="flex items-center gap-1">
                          <i class="fas fa-coins text-xs"></i>
                          {{ dashboardData.register?.currency }}
                        </span>
                        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium', getTransactionTypeClass(dashboardData.register?.type)]">
                          <i :class="['fas text-[10px]', dashboardData.register?.type === 'cash' ? 'fa-money-bill-wave' : dashboardData.register?.type === 'bank' ? 'fa-university' : dashboardData.register?.type === 'vault' ? 'fa-safe' : 'fa-coins']"></i>
                          {{ dashboardData.register?.type_label }}
                        </span>
                      </p>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    v-if="!hasOpenSession"
                    @click="changeTab('open-session')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-all duration-200 shadow-sm hover:shadow-md"
                  >
                    <i class="fas fa-play text-xs"></i>
                    Ouvrir Session
                  </button>
                  <button
                    v-if="hasOpenSession"
                    @click="changeTab('close-session')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 text-white rounded-lg text-sm font-medium hover:bg-rose-700 transition-all duration-200 shadow-sm hover:shadow-md"
                  >
                    <i class="fas fa-stop text-xs"></i>
                    Clôturer Session
                  </button>
                </div>
              </div>
            </div>

            <div class="border-b border-gray-200 px-6 pt-4 pb-3">
              <div class="flex gap-6 overflow-x-auto">
                <button
                  @click="changeTab('overview')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'overview'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i :class="['fas text-xs', getTabIcon('overview')]"></i>
                  {{ getTabLabel('overview') }}
                </button>

                <button
                  v-if="hasOpenSession"
                  @click="changeTab('session')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'session'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i :class="['fas text-xs', getTabIcon('session')]"></i>
                  {{ getTabLabel('session') }}
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-100 text-emerald-700">
                    <i class="fas fa-circle text-[4px]"></i>
                    Ouverte
                  </span>
                </button>

                <button
                  @click="changeTab('transactions')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'transactions'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i :class="['fas text-xs', getTabIcon('transactions')]"></i>
                  {{ getTabLabel('transactions') }}
                  <span
                    v-if="dashboardData.today_transactions.length > 0"
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ dashboardData.today_transactions.length }}</span>
                </button>

                <button
                  @click="changeTab('sessions')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'sessions'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i :class="['fas text-xs', getTabIcon('sessions')]"></i>
                  {{ getTabLabel('sessions') }}
                  <span
                    v-if="dashboardData.recent_sessions.length > 0"
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ dashboardData.recent_sessions.length }}</span>
                </button>

                <template v-if="hasOpenSession">
                  <div class="h-6 w-px bg-gray-300"></div>

                  <button
                    @click="changeTab('transaction-in')"
                    :class="[
                      'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                      activeTab === 'transaction-in'
                        ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                        : 'text-gray-500 hover:text-gray-700',
                    ]"
                  >
                    <i :class="['fas text-xs', getTabIcon('transaction-in')]"></i>
                    {{ getTabLabel('transaction-in') }}
                  </button>

                  <button
                    @click="changeTab('transaction-out')"
                    :class="[
                      'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                      activeTab === 'transaction-out'
                        ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                        : 'text-gray-500 hover:text-gray-700',
                    ]"
                  >
                    <i :class="['fas text-xs', getTabIcon('transaction-out')]"></i>
                    {{ getTabLabel('transaction-out') }}
                  </button>

                  <button
                    @click="changeTab('transaction-transfer')"
                    :class="[
                      'pb-3 text-sm font-bold transition-colors flex items-center gap-2 whitespace-nowrap',
                      activeTab === 'transaction-transfer'
                        ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                        : 'text-gray-500 hover:text-gray-700',
                    ]"
                  >
                    <i :class="['fas text-xs', getTabIcon('transaction-transfer')]"></i>
                    {{ getTabLabel('transaction-transfer') }}
                  </button>
                </template>
              </div>
            </div>

            <div v-if="activeTab === 'overview'" class="p-6 lg:p-8">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl border border-emerald-200 p-5 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-emerald-800">Solde Actuel</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                      <i class="fas fa-wallet text-white text-sm"></i>
                    </div>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-3">{{ formatCurrency(dashboardData.register?.current_balance) }}</p>
                </div>

                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl border border-emerald-200 p-5 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-emerald-800">Entrées du jour</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                      <i class="fas fa-arrow-down text-white text-sm"></i>
                    </div>
                  </div>
                  <p class="text-2xl font-bold text-emerald-700 mt-3">+{{ formatCurrency(dashboardData.today_total_in) }}</p>
                </div>

                <div class="bg-gradient-to-br from-rose-50 to-rose-100/50 rounded-xl border border-rose-200 p-5 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-rose-800">Sorties du jour</span>
                    <div class="w-8 h-8 rounded-lg bg-rose-500 flex items-center justify-center">
                      <i class="fas fa-arrow-up text-white text-sm"></i>
                    </div>
                  </div>
                  <p class="text-2xl font-bold text-rose-700 mt-3">-{{ formatCurrency(dashboardData.today_total_out) }}</p>
                </div>

                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100/50 rounded-xl border border-indigo-200 p-5 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-indigo-800">Solde Attendu</span>
                    <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center">
                      <i class="fas fa-calculator text-white text-sm"></i>
                    </div>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-3">{{ formatCurrency(dashboardData.expected_balance) }}</p>
                </div>
              </div>

              <div v-if="!hasOpenSession" class="rounded-xl border border-dashed border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100/50 p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-200 flex items-center justify-center mx-auto mb-4">
                  <i class="fas fa-clock text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Aucune session ouverte</h3>
                <p class="text-gray-500 mt-1">Ouvrez une session pour commencer à enregistrer les transactions.</p>
                <button
                  @click="changeTab('open-session')"
                  class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white rounded-lg font-semibold text-sm hover:bg-emerald-700 transition-all duration-300 shadow-sm hover:shadow-md"
                >
                  <i class="fas fa-play text-xs"></i>
                  Ouvrir une session
                </button>
              </div>
            </div>

            <div v-if="activeTab === 'session'" class="p-6 lg:p-8">
              <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                      <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <i class="fas fa-clock text-emerald-600 text-xl"></i>
                      </div>
                      <div>
                        <h3 class="text-lg font-semibold text-[#062121]">Session en cours</h3>
                        <p class="text-sm text-gray-500 mt-1">
                          Ouverte à {{ new Date(activeSession.opened_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                          par {{ activeSession.opened_by?.name || 'Système' }}
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button
                        @click="changeTab('transaction-in')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-all duration-200 shadow-sm hover:shadow-md"
                      >
                        <i class="fas fa-plus text-xs"></i>
                        Entrée
                      </button>
                      <button
                        @click="changeTab('transaction-out')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-medium hover:bg-rose-700 transition-all duration-200 shadow-sm hover:shadow-md"
                      >
                        <i class="fas fa-minus text-xs"></i>
                        Sortie
                      </button>
                      <button
                        @click="changeTab('transaction-transfer')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-all duration-200 shadow-sm hover:shadow-md"
                      >
                        <i class="fas fa-exchange-alt text-xs"></i>
                        Transfert
                      </button>
                    </div>
                  </div>
                </div>

                <div v-if="dashboardData.today_transactions.length === 0" class="p-8 text-center">
                  <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-receipt text-2xl text-gray-400"></i>
                  </div>
                  <p class="text-gray-500">Aucune transaction aujourd'hui</p>
                </div>

                <div v-else class="divide-y divide-gray-100">
                  <div
                    v-for="transaction in dashboardData.today_transactions"
                    :key="transaction.id"
                    class="p-4 hover:bg-gray-50 transition-colors duration-200"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                          :class="getTransactionIconBg(transaction.type)">
                          <i :class="['fas text-sm', getTransactionIcon(transaction.type)]"></i>
                        </div>
                        <div>
                          <p class="text-sm font-medium text-gray-900">{{ transaction.description }}</p>
                          <p class="text-xs text-gray-500 flex items-center gap-2">
                            <span>{{ new Date(transaction.transaction_date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</span>
                            <span>•</span>
                            <span>{{ transaction.user?.name || 'Système' }}</span>
                          </p>
                        </div>
                      </div>
                      <div class="flex items-center gap-4">
                        <p :class="[
                          'text-sm font-bold',
                          transaction.type === 'in' ? 'text-emerald-600' : 'text-rose-600',
                        ]">
                          {{ transaction.type === 'in' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                        </p>
                        <div class="flex items-center gap-1">
                          <button
                            @click="editTransaction(transaction)"
                            title="Modifier"
                            class="w-8 h-8 rounded-lg text-gray-400 hover:bg-blue-50 hover:text-blue-600 transition-all duration-200"
                          >
                            <i class="fas fa-edit text-xs"></i>
                          </button>
                          <button
                            @click="deleteTransaction(transaction.id)"
                            title="Supprimer"
                            class="w-8 h-8 rounded-lg text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition-all duration-200"
                          >
                            <i class="fas fa-trash-alt text-xs"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="activeTab === 'transactions'" class="p-6 lg:p-8">
              <div v-if="dashboardData.today_transactions.length === 0" class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                  <i class="fas fa-receipt text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500">Aucune transaction enregistrée.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Heure</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Description</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Mode</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Montant</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Utilisateur</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="transaction in dashboardData.today_transactions"
                      :key="transaction.id"
                      class="hover:bg-white/50 transition-colors duration-200"
                    >
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                        {{ new Date(transaction.transaction_date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold', getTransactionTypeClass(transaction.type)]">
                          <i :class="['fas text-[10px]', getTransactionIcon(transaction.type).split(' ')[0]]"></i>
                          {{ getTransactionTypeLabel(transaction.type) }}
                        </span>
                      </td>
                      <td class="px-4 py-4 text-sm text-gray-900">{{ transaction.description }}</td>
                      <td class="px-4 py-4 text-sm text-gray-600 capitalize">{{ transaction.payment_method }}</td>
                      <td :class="[
                        'px-4 py-4 whitespace-nowrap text-sm font-bold text-right',
                        transaction.type === 'in' ? 'text-emerald-600' : 'text-rose-600',
                      ]">
                        {{ transaction.type === 'in' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ transaction.user?.name || '-' }}</td>
                      <td class="px-4 py-4 whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                          <button
                            @click="editTransaction(transaction)"
                            title="Modifier"
                            class="w-8 h-8 rounded-lg text-gray-400 hover:bg-blue-50 hover:text-blue-600 transition-all duration-200"
                          >
                            <i class="fas fa-edit text-xs"></i>
                          </button>
                          <button
                            @click="deleteTransaction(transaction.id)"
                            title="Supprimer"
                            class="w-8 h-8 rounded-lg text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition-all duration-200"
                          >
                            <i class="fas fa-trash-alt text-xs"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="activeTab === 'sessions'" class="p-6 lg:p-8">
              <div v-if="dashboardData.recent_sessions.length === 0" class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                  <i class="fas fa-history text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500">Aucune session précédente.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Ouverture</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fermeture</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Ouverte par</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Solde Initial</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Solde Final</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Écart</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="session in dashboardData.recent_sessions"
                      :key="session.id"
                      class="hover:bg-white/50 transition-colors duration-200"
                    >
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ new Date(session.opened_at).toLocaleDateString('fr-FR') }}
                        <span class="text-xs text-gray-500 ml-1">{{ new Date(session.opened_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                        {{ session.closed_at ? new Date(session.closed_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—' }}
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ session.opened_by?.name || '-' }}</td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 text-right">{{ formatCurrency(session.opening_balance) }}</td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatCurrency(session.actual_closing_balance) }}</td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <span :class="[
                          'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold',
                          session.discrepancy > 0 ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : session.discrepancy < 0 ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-gray-100 text-gray-700 border border-gray-200',
                        ]">
                          <i :class="['fas text-[10px]', session.discrepancy > 0 ? 'fa-arrow-up' : session.discrepancy < 0 ? 'fa-arrow-down' : 'fa-minus']"></i>
                          {{ session.discrepancy > 0 ? '+' : '' }}{{ formatCurrency(Math.abs(session.discrepancy)) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="activeTab === 'open-session'" class="p-6 lg:p-8">
              <div class="max-w-2xl mx-auto">
                <div class="text-center mb-8">
                  <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-50 mb-4">
                    <i class="fas fa-play text-2xl text-emerald-600"></i>
                  </div>
                  <h3 class="text-xl font-bold text-[#062121]">Ouvrir une Session</h3>
                  <p class="text-sm text-gray-500 mt-1">Démarrez une nouvelle session pour cette caisse</p>
                </div>

                <form @submit.prevent="openSession" class="space-y-8">
                  <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                      <InputLabel for="opening_balance" value="Solde d'ouverture" />
                      <TextInput
                        id="opening_balance"
                        type="number"
                        step="0.01"
                        class="mt-1 block w-full"
                        v-model.number="openSessionForm.opening_balance"
                        placeholder="0.00"
                      />
                    </div>

                    <div class="md:col-span-2">
                      <InputLabel for="open_notes" value="Notes" />
                      <textarea
                        id="open_notes"
                        v-model="openSessionForm.notes"
                        rows="3"
                        placeholder="Notes d'ouverture..."
                        class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      ></textarea>
                    </div>
                  </div>

                  <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                    <button
                      type="button"
                      @click="changeTab('overview')"
                      class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                    >
                      Annuler
                    </button>
                    <PrimaryButton :disabled="isSubmitting">
                      <span v-if="isSubmitting">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Ouverture...
                      </span>
                      <span v-else>Ouvrir la session</span>
                    </PrimaryButton>
                  </div>
                </form>
              </div>
            </div>

            <div v-if="activeTab === 'close-session'" class="p-6 lg:p-8">
              <div class="max-w-2xl mx-auto">
                <div class="text-center mb-8">
                  <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-rose-50 mb-4">
                    <i class="fas fa-stop text-2xl text-rose-600"></i>
                  </div>
                  <h3 class="text-xl font-bold text-[#062121]">Clôturer la Session</h3>
                  <p class="text-sm text-gray-500 mt-1">Terminez la session en cours</p>
                </div>

                <form @submit.prevent="closeSession" class="space-y-8">
                  <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-600">Solde attendu:</span>
                      <span class="font-semibold text-[#062121]">{{ formatCurrency(dashboardData.expected_balance) }}</span>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                      <InputLabel for="actual_balance" value="Solde réel (compté)" />
                      <TextInput
                        id="actual_balance"
                        type="number"
                        step="0.01"
                        class="mt-1 block w-full"
                        v-model.number="closeSessionForm.actual_closing_balance"
                        placeholder="0.00"
                      />
                    </div>

                    <div v-if="discrepancy !== 0" :class="[
                      'flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-semibold',
                      discrepancy > 0 ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-200',
                    ]">
                      <i :class="['fas', discrepancy > 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                      Écart: {{ discrepancy > 0 ? '+' : '' }}{{ formatCurrency(Math.abs(discrepancy)) }}
                    </div>

                    <div class="md:col-span-2">
                      <InputLabel for="close_notes" value="Notes de clôture" />
                      <textarea
                        id="close_notes"
                        v-model="closeSessionForm.notes"
                        rows="3"
                        placeholder="Notes de clôture..."
                        class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      ></textarea>
                    </div>
                  </div>

                  <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                    <button
                      type="button"
                      @click="changeTab('overview')"
                      class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                    >
                      Annuler
                    </button>
                    <PrimaryButton :disabled="isSubmitting" class="bg-rose-600 hover:bg-rose-700">
                      <span v-if="isSubmitting">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Clôture...
                      </span>
                      <span v-else>Clôturer la session</span>
                    </PrimaryButton>
                  </div>
                </form>
              </div>
            </div>

            <form v-if="activeTab.startsWith('transaction-')" @submit.prevent="saveTransaction" class="p-6 lg:p-8">
              <div class="space-y-8">
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center gap-4">
                    <div :class="[
                      'w-14 h-14 rounded-2xl flex items-center justify-center',
                      activeTab === 'transaction-in' ? 'bg-emerald-100' : activeTab === 'transaction-out' ? 'bg-rose-100' : 'bg-indigo-100'
                    ]">
                      <i :class="['fas text-2xl', activeTab === 'transaction-in' ? 'fa-arrow-down text-emerald-600' : activeTab === 'transaction-out' ? 'fa-arrow-up text-rose-600' : 'fa-exchange-alt text-indigo-600']"></i>
                    </div>
                    <div>
                      <h3 class="text-xl font-bold text-[#062121]">
                        {{ editingTransactionId ? 'Modifier la transaction' : (activeTab === 'transaction-in' ? "Entrée d'Argent" : activeTab === 'transaction-out' ? "Sortie d'Argent" : "Transfert") }}
                      </h3>
                      <p class="text-sm text-gray-500 mt-1">
                        {{ editingTransactionId ? 'Modifiez les informations de la transaction' : (activeTab === 'transaction-in' ? "Enregistrez une entrée de fonds" : activeTab === 'transaction-out' ? "Enregistrez une sortie de fonds" : "Transférez des fonds vers une autre caisse") }}
                      </p>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                  <div>
                    <InputLabel for="amount" value="Montant *" />
                    <TextInput
                      id="amount"
                      type="number"
                      step="0.01"
                      min="0.01"
                      class="mt-1 block w-full text-lg font-semibold"
                      v-model.number="transactionForm.amount"
                      placeholder="0.00"
                    />
                    <InputError class="mt-2" :message="transactionErrors.amount" />
                  </div>

                  <div>
                    <InputLabel for="payment_method" value="Mode de paiement" />
                    <CustomSelect
                      id="payment_method"
                      v-model="transactionForm.payment_method"
                      :options="paymentMethodOptions"
                      label-key="label"
                      value-key="value"
                      placeholder="Sélectionner"
                    />
                    <InputError class="mt-2" :message="transactionErrors.payment_method" />
                  </div>

                  <div>
                    <InputLabel for="transaction_date" value="Date" />
                    <TextInput
                      id="transaction_date"
                      type="datetime-local"
                      class="mt-1 block w-full"
                      v-model="transactionForm.transaction_date"
                    />
                    <InputError class="mt-2" :message="transactionErrors.transaction_date" />
                  </div>

                  <div class="md:col-span-2">
                    <InputLabel for="description" value="Description *" />
                    <textarea
                      id="description"
                      v-model="transactionForm.description"
                      rows="2"
                      placeholder="Description de la transaction..."
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                    ></textarea>
                    <InputError class="mt-2" :message="transactionErrors.description" />
                  </div>

                  <div v-if="activeTab === 'transaction-transfer'">
                    <InputLabel for="to_cash_register_id" value="Caisse de destination *" />
                    <CustomSelect
                      id="to_cash_register_id"
                      v-model="transactionForm.to_cash_register_id"
                      :options="cashRegisterOptions"
                      label-key="label"
                      value-key="value"
                      placeholder="Sélectionner"
                    />
                    <InputError class="mt-2" :message="transactionErrors.to_cash_register_id" />
                  </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                  <button
                    type="button"
                    @click="editingTransactionId ? resetTransactionForm() : changeTab('overview')"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    {{ editingTransactionId ? 'Annuler' : 'Retour' }}
                  </button>
                  <PrimaryButton :disabled="isSubmitting || !transactionForm.amount || parseFloat(transactionForm.amount) <= 0 || !transactionForm.description || (activeTab === 'transaction-transfer' && !transactionForm.to_cash_register_id)">
                    <span v-if="isSubmitting">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                      </svg>
                      {{ editingTransactionId ? 'Modification...' : 'Enregistrement...' }}
                    </span>
                    <span v-else>{{ editingTransactionId ? 'Modifier' : 'Enregistrer' }}</span>
                  </PrimaryButton>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
