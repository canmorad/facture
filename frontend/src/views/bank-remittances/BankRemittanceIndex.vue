<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import Checkbox from "@/components/Checkbox.vue";
import InputLabel from "@/components/InputLabel.vue";
import InputError from "@/components/InputError.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import TextInput from "@/components/TextInput.vue";
import { bankRemittanceApi } from "@/services/bankRemittanceApi";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();

const remittances = ref([]);
const pendingDocuments = ref({
  cheques: [],
  lcn: [],
  total_amount: 0,
  total_count: 0,
});
const creationData = ref(null);
const isLoading = ref(false);
const isLoadingPending = ref(false);
const selectedStatus = ref("all");
const activeTab = ref("remittances");
const selectedRemittance = ref(null);
const depositSlipRef = ref("");
const depositValueDate = ref(new Date().toISOString().split("T")[0]);
const depositBankFees = ref(0);
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0 });

// Create remittance form state
const selectedBankAccountId = ref(null);
const remittanceDate = ref(new Date().toISOString().split("T")[0]);
const isCreatingRemittance = ref(false);
const formErrors = ref({});

// Checkbox selection state
const selectedDocumentIds = ref(new Set());
const selectAllPending = ref(false);

const statusTabs = [
  { value: "all", label: "Toutes", icon: "fa-list" },
  { value: "DRAFT", label: "Brouillons", icon: "fa-pen" },
  { value: "FINALIZED", label: "Finalisées", icon: "fa-check-circle" },
  { value: "SENT", label: "Envoyées", icon: "fa-paper-plane" },
  { value: "DEPOSITED", label: "Déposées", icon: "fa-university" },
];

const fetchRemittances = async () => {
  isLoading.value = true;
  try {
    const filters = {};
    if (selectedStatus.value !== "all") filters.status = selectedStatus.value;

    const data = await bankRemittanceApi.getAll(filters);
    remittances.value = data.data || data;
  } catch (err) {
    console.error("Error fetching remittances:", err);
    error("Erreur", "Impossible de charger les remises bancaires.");
  } finally {
    isLoading.value = false;
  }
};

const fetchPendingDocuments = async () => {
  isLoadingPending.value = true;
  try {
    const data = await bankRemittanceApi.getPendingDocuments();
    pendingDocuments.value = {
      cheques: data.cheques || [],
      lcn: data.lcn || [],
      total_amount: data.total_amount || 0,
      total_count: data.total_count || 0,
    };
  } catch (err) {
    console.error("Error fetching pending documents:", err);
  } finally {
    isLoadingPending.value = false;
  }
};

const fetchCreationData = async () => {
  try {
    const data = await bankRemittanceApi.getCreationData();
    creationData.value = data;
    // Set default bank account
    if (data?.bank_accounts?.[0]?.id) {
      selectedBankAccountId.value = data.bank_accounts[0].id;
    }
  } catch (err) {
    console.error("Error fetching creation data:", err);
  }
};

// Toggle document selection
const toggleDocumentSelection = (docId) => {
  if (selectedDocumentIds.value.has(docId)) {
    selectedDocumentIds.value.delete(docId);
  } else {
    selectedDocumentIds.value.add(docId);
  }
  selectedDocumentIds.value = new Set(selectedDocumentIds.value);
  updateSelectAllState();
};

// Toggle select all
const toggleSelectAll = () => {
  selectAllPending.value = !selectAllPending.value;
  if (selectAllPending.value) {
    allPendingDocuments.value.forEach(doc => {
      selectedDocumentIds.value.add(doc.id);
    });
  } else {
    selectedDocumentIds.value.clear();
  }
  selectedDocumentIds.value = new Set(selectedDocumentIds.value);
};

// Update select all checkbox state
const updateSelectAllState = () => {
  const allIds = allPendingDocuments.value.map(d => d.id);
  const allSelected = allIds.every(id => selectedDocumentIds.value.has(id));
  selectAllPending.value = allSelected && allIds.length > 0;
};

const createRemittance = async () => {
  // Clear previous errors
  formErrors.value = {};

  if (selectedDocumentIds.value.size === 0) {
    formErrors.value.documents = "Veuillez sélectionner au moins un document.";
    error("Erreur", "Veuillez sélectionner au moins un document.");
    return;
  }

  if (!selectedBankAccountId.value) {
    formErrors.value.bank_account = "Veuillez sélectionner un compte bancaire.";
    error("Erreur", "Veuillez sélectionner un compte bancaire.");
    return;
  }

  if (!remittanceDate.value) {
    formErrors.value.date = "Veuillez sélectionner une date de remise.";
    error("Erreur", "Veuillez sélectionner une date de remise.");
    return;
  }

  isCreatingRemittance.value = true;

  try {
    const pendingIds = Array.from(selectedDocumentIds.value);

    await bankRemittanceApi.createDraft({
      bank_account_id: selectedBankAccountId.value,
      remittance_date: remittanceDate.value,
      payment_document_ids: pendingIds,
    });

    success("Créé !", "La remise bancaire a été créée avec succès.");

    // Reset form and selections
    selectedDocumentIds.value.clear();
    selectAllPending.value = false;
    selectedBankAccountId.value = creationData.value?.bank_accounts?.[0]?.id || null;
    remittanceDate.value = new Date().toISOString().split("T")[0];
    formErrors.value = {};

    // Switch to remittances tab
    activeTab.value = "remittances";

    await fetchRemittances();
    await fetchPendingDocuments();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de créer la remise.");
  } finally {
    isCreatingRemittance.value = false;
  }
};

const finalizeRemittance = async (remittance) => {
  const result = await confirm(
    "Finaliser la remise",
    `Finaliser la remise ? Un numéro sera généré automatiquement.`
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.finalize(remittance.id);
    success("Finalisée !", "La remise a été finalisée avec succès.");
    await fetchRemittances();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de finaliser la remise.");
  }
};

const sendRemittance = async (remittance) => {
  const result = await confirm(
    "Envoyer à la banque",
    `Marquer cette remise comme envoyée à la banque ?`
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.send(remittance.id);
    success("Envoyée !", "La remise a été marquée comme envoyée.");
    await fetchRemittances();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'envoyer la remise.");
  }
};

const printRemittance = (remittance) => {
  closeDropdown();
  const url = router.resolve({ name: 'bank-remittance.print', params: { id: remittance.id } }).href;
  window.open(url, '_blank');
};

const openDepositModal = (remittance) => {
  closeDropdown();
  selectedRemittance.value = remittance;
  depositSlipRef.value = remittance.deposit_slip_reference || "";
  depositValueDate.value = new Date().toISOString().split("T")[0];
  depositBankFees.value = 0;
  activeTab.value = "deposit";
};

const markAsDeposited = async () => {
  if (!selectedRemittance.value) return;

  try {
    await bankRemittanceApi.markDeposited(
      selectedRemittance.value.id,
      depositSlipRef.value || null
    );
    success(
      "Déposée !",
      "La remise a été marquée comme déposée. Les factures associées seront mises à jour."
    );
    // Reset form and go back to remittances tab
    selectedRemittance.value = null;
    depositSlipRef.value = "";
    depositValueDate.value = new Date().toISOString().split("T")[0];
    depositBankFees.value = 0;
    activeTab.value = "remittances";
    await fetchRemittances();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de marquer comme déposée.");
  }
};

const cancelRemittance = async (remittance) => {
  closeDropdown();
  const result = await confirm(
    "Annuler la remise",
    `Annuler la remise ${remittance.number || `#${remittance.id}`} ? Les documents seront libérés.`
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.cancel(remittance.id);
    success("Annulée !", "La remise a été annulée.");
    await fetchRemittances();
    await fetchPendingDocuments();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'annuler la remise.");
  }
};

const deleteRemittance = async (remittance) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer la remise",
    `Supprimer la remise brouillon ? Cette action est irréversible.`
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.delete(remittance.id);
    success("Supprimée !", "La remise a été supprimée.");
    await fetchRemittances();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer la remise.");
  }
};

const previewRemittance = (id) => {
  closeDropdown();
  router.push({ name: 'bank-remittance.show', params: { id } });
};

const toggleDropdown = (id, event) => {
  if (openDropdownId.value === id) {
    closeDropdown();
    return;
  }
  const target = event.currentTarget;
  const rect = target.getBoundingClientRect();
  dropdownPosition.value = {
    top: rect.bottom + window.scrollY + 4,
  };
  openDropdownId.value = id;
};

const closeDropdown = () => {
  openDropdownId.value = null;
};

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    DEPOSITED: "bg-purple-100 text-purple-700",
    RETURNED: "bg-red-100 text-red-700",
    CANCELLED: "bg-gray-300 text-gray-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusLabel = (status) => {
  const labels = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisée",
    SENT: "Envoyée",
    DEPOSITED: "Déposée",
    RETURNED: "Rejetée",
    CANCELLED: "Annulée",
  };
  return labels[status] || status;
};

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchRemittances();
};

const filteredRemittances = computed(() => {
  if (selectedStatus.value === "all") return remittances.value;
  return remittances.value.filter(r => r.status === selectedStatus.value);
});

const allPendingDocuments = computed(() => {
  return [
    ...(pendingDocuments.value.cheques || []).map(d => ({ ...d, docType: 'chèque' })),
    ...(pendingDocuments.value.lcn || []).map(d => ({ ...d, docType: 'LCN' }))
  ];
});

// Selected documents with their details
const selectedDocuments = computed(() => {
  return allPendingDocuments.value.filter(doc => selectedDocumentIds.value.has(doc.id));
});

// Calculate totals for selected documents
const selectedTotal = computed(() => {
  return selectedDocuments.value.reduce((sum, doc) => sum + (doc.amount || 0), 0);
});

// Check if a document is selected
const isDocumentSelected = (docId) => {
  return selectedDocumentIds.value.has(docId);
};

// Bank accounts options for CustomSelect
const bankAccountOptions = computed(() => {
  if (!creationData.value?.bank_accounts) return [];
  return creationData.value.bank_accounts.map(account => ({
    label: `${account.label} (${account.bank_name})`,
    value: account.id,
  }));
});

onMounted(async () => {
  await Promise.all([fetchRemittances(), fetchPendingDocuments(), fetchCreationData()]);
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <!-- Main Navigation Tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex gap-6">
              <button
                @click="activeTab = 'remittances'"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'remittances'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-university"></i>
                Remises Bancaires
                <span
                  v-if="remittances.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                >{{ remittances.length }}</span>
              </button>

              <button
                @click="activeTab = 'pending'"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'pending'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-clock"></i>
                En attente
                <span
                  v-if="pendingDocuments.total_count > 0"
                  class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                >{{ pendingDocuments.total_count }}</span>
              </button>

              <button
                @click="activeTab = 'create'"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'create'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-plus-circle"></i>
                Créer une remise
                <span
                  v-if="selectedDocumentIds.size > 0"
                  class="text-xs bg-[#C5F82A] text-[#062121] px-2 py-0.5 rounded-full font-bold"
                >{{ selectedDocumentIds.size }}</span>
              </button>

              <!-- Deposit Tab (shown only when a remittance is selected for deposit) -->
              <button
                v-if="selectedRemittance"
                @click="activeTab = 'deposit'"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'deposit'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-university"></i>
                Déposer la remise
              </button>
            </div>

            <!-- Status Filters (only show in remittances tab) -->
            <div
              v-if="activeTab === 'remittances'"
              class="flex gap-4 pb-3 mt-2 overflow-x-auto"
            >
              <button
                v-for="tab in statusTabs"
                :key="tab.value"
                @click="changeStatusFilter(tab.value)"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg whitespace-nowrap',
                  selectedStatus === tab.value
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i :class="['fas', tab.icon]"></i>
                {{ tab.label }}
                <span
                  v-if="tab.value === 'all'"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                >{{ remittances.length }}</span>
              </button>
            </div>
          </div>

          <!-- Content Area -->
          <div class="p-6 lg:p-8">
            <!-- REMITTANCES TAB -->
            <template v-if="activeTab === 'remittances'">
              <div v-if="isLoading" class="text-center py-12">
                <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <p class="mt-2 text-gray-500">Chargement...</p>
              </div>

              <div v-else-if="filteredRemittances.length === 0" class="text-center py-12">
                <i class="fas fa-inbox text-gray-300 text-5xl mb-4 block"></i>
                <p class="text-gray-500">Aucune remise bancaire trouvée.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        N° Remise
                      </th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Date
                      </th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Banque
                      </th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Montant
                      </th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Documents
                      </th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Statut
                      </th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="remittance in filteredRemittances"
                      :key="remittance.id"
                      class="group hover:bg-white/50 transition-colors duration-200"
                    >
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">
                          {{ remittance.number || `Brouillon #${remittance.id}` }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-700">
                          {{ formatDate(remittance.remittance_date) }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-700">
                          {{ remittance.bank_account?.label || "—" }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-bold text-[#062121]">
                          {{ formatCurrency(remittance.total_amount) }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-700">
                          {{ remittance.document_count }} document{{ remittance.document_count > 1 ? 's' : '' }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <span
                          :class="[
                            'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                            getStatusBadgeClass(remittance.status),
                          ]"
                        >
                          {{ getStatusLabel(remittance.status) }}
                        </span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                          <button
                            @click="previewRemittance(remittance.id)"
                            class="w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                            title="Voir détails"
                          >
                            <i class="fas fa-eye text-sm"></i>
                          </button>
                          <button
                            v-if="remittance.status !== 'CANCELLED'"
                            @click="toggleDropdown(remittance.id, $event)"
                            class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                          >
                            <i class="fas fa-ellipsis-v text-sm"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <!-- PENDING TAB -->
            <template v-else-if="activeTab === 'pending'">
              <div v-if="isLoadingPending" class="text-center py-12">
                <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <p class="mt-2 text-gray-500">Chargement...</p>
              </div>

              <div v-else-if="allPendingDocuments.length === 0" class="text-center py-12">
                <i class="fas fa-check-circle text-green-300 text-5xl mb-4 block"></i>
                <p class="text-gray-500">Aucun document en attente.</p>
              </div>

              <div v-else>
                <div class="overflow-x-auto">
                  <table class="min-w-full">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-left w-12">
                          <Checkbox v-model="selectAllPending" @change="toggleSelectAll" />
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                          Type
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                          Numéro
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                          Montant
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                          Échéance
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                          Statut
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr
                        v-for="doc in allPendingDocuments"
                        :key="doc.id"
                        :class="[
                          'group transition-colors duration-200',
                          isDocumentSelected(doc.id) ? 'bg-[#C5F82A]/10' : 'hover:bg-white/50'
                        ]"
                      >
                        <td class="px-4 py-4 whitespace-nowrap">
                          <Checkbox
                            :model-value="isDocumentSelected(doc.id)"
                            @update:model-value="toggleDocumentSelection(doc.id)"
                          />
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <span
                            class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full"
                            :class="doc.docType === 'chèque' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'"
                          >
                            {{ doc.docType === 'chèque' ? 'Chèque' : 'LCN' }}
                          </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <div class="text-sm font-semibold text-gray-900">
                            #{{ doc.number }}
                          </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <div class="text-sm font-bold text-[#062121]">
                            {{ formatCurrency(doc.amount) }}
                          </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <div class="text-sm text-gray-700">
                            {{ formatDate(doc.due_date) }}
                          </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            En attente
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <!-- CREATE REMITTANCE TAB -->
            <template v-else-if="activeTab === 'create'">
              <!-- Empty Selection Warning -->
              <div v-if="selectedDocumentIds.size === 0" class="text-center py-12">
                <i class="fas fa-tasks text-gray-300 text-5xl mb-4 block"></i>
                <p class="text-gray-500 mb-2">Aucun document sélectionné.</p>
                <p class="text-sm text-gray-400 mb-4">Sélectionnez des documents dans l'onglet "En attente" pour créer une remise.</p>
                <button
                  @click="activeTab = 'pending'"
                  class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
                >
                  <i class="fas fa-arrow-left"></i>
                  Retour à la sélection
                </button>
              </div>

              <!-- Create Form (Product Form Style) -->
              <form v-else @submit.prevent="createRemittance" class="space-y-8">
                <!-- Selected Documents Section (DocumentPreview Style) -->
                <div>
                  <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                    <i class="fas fa-list-ul text-gray-400"></i>
                    Documents sélectionnés ({{ selectedDocumentIds.size }})
                  </h3>
                  <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="border-b border-gray-200">
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type</th>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Numéro</th>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Échéance</th>
                          <th class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Montant</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="doc in selectedDocuments" :key="doc.id" class="hover:bg-gray-50/50 transition-colors">
                          <td class="px-4 py-2.5">
                            <span
                              class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                              :class="doc.docType === 'chèque' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'"
                            >
                              {{ doc.docType === 'chèque' ? 'Chèque' : 'LCN' }}
                            </span>
                          </td>
                          <td class="px-4 py-2.5 text-gray-700">#{{ doc.number }}</td>
                          <td class="px-4 py-2.5 text-gray-700">{{ formatDate(doc.due_date) }}</td>
                          <td class="px-4 py-2.5 text-right font-semibold text-[#062121]">{{ formatCurrency(doc.amount) }}</td>
                        </tr>
                      </tbody>
                      <tfoot class="bg-gray-50">
                        <tr class="border-t-2 border-[#C5F82A]">
                          <td colspan="3" class="px-4 py-3 text-base font-bold text-[#062121]">Total</td>
                          <td class="px-4 py-3 text-right text-base font-black text-[#062121]">{{ formatCurrency(selectedTotal) }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

                <!-- Form Section -->
                <div class="border-t border-gray-100 pt-6">
                  <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                    <i class="fas fa-cog text-gray-400"></i>
                    Paramètres de la remise
                  </h3>
                  <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Bank Account -->
                    <div>
                      <InputLabel for="bank_account" value="Compte bancaire de destination *" />
                      <CustomSelect
                        id="bank_account"
                        v-model="selectedBankAccountId"
                        :options="bankAccountOptions"
                        label-key="label"
                        value-key="value"
                        placeholder="Sélectionner un compte bancaire"
                      />
                      <InputError class="mt-2" :message="formErrors.bank_account" />
                    </div>

                    <!-- Remittance Date -->
                    <div>
                      <InputLabel for="remittance_date" value="Date de remise *" />
                      <TextInput
                        id="remittance_date"
                        type="date"
                        class="mt-1 block w-full"
                        v-model="remittanceDate"
                      />
                      <InputError class="mt-2" :message="formErrors.date" />
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                  <button
                    type="button"
                    @click="activeTab = 'pending'"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    Annuler
                  </button>
                  <PrimaryButton :disabled="isCreatingRemittance">
                    <span v-if="isCreatingRemittance">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                      </svg>
                      Création...
                    </span>
                    <span v-else>Créer la remise</span>
                  </PrimaryButton>
                </div>
              </form>
            </template>

            <!-- DEPOSIT REMITTANCE TAB -->
            <template v-else-if="activeTab === 'deposit' && selectedRemittance">
              <!-- Remittance Summary -->
              <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-100">
                  <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                    <i class="fas fa-university text-purple-500"></i>
                    Remise à déposer
                  </h3>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-gray-500">N° Remise :</span>
                      <span class="ml-2 font-semibold text-gray-900">
                        {{ selectedRemittance.number || `Brouillon #${selectedRemittance.id}` }}
                      </span>
                    </div>
                    <div>
                      <span class="text-gray-500">Banque :</span>
                      <span class="ml-2 font-semibold text-gray-900">
                        {{ selectedRemittance.bank_account?.label || "—" }}
                      </span>
                    </div>
                    <div>
                      <span class="text-gray-500">Montant :</span>
                      <span class="ml-2 font-bold text-[#062121]">
                        {{ formatCurrency(selectedRemittance.total_amount) }}
                      </span>
                    </div>
                    <div>
                      <span class="text-gray-500">Documents :</span>
                      <span class="ml-2 font-semibold text-gray-900">
                        {{ selectedRemittance.document_count }} document{{ selectedRemittance.document_count > 1 ? 's' : '' }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Deposit Form (Product Form Style) -->
                <form @submit.prevent="markAsDeposited" class="space-y-6">
                  <div>
                    <h3 class="text-sm font-bold text-[#062121] mb-4 flex items-center gap-2">
                      <i class="fas fa-cog text-gray-400"></i>
                      Paramètres du dépôt
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                      <!-- Deposit Slip Reference -->
                      <div>
                        <InputLabel for="deposit_slip_ref" value="Référence du bordereau" />
                        <TextInput
                          id="deposit_slip_ref"
                          type="text"
                          placeholder="Numéro de bordereau de dépôt"
                          class="mt-1 block w-full"
                          v-model="depositSlipRef"
                        />
                        <p class="mt-1 text-xs text-gray-400">Optionnel - Référence du bordereau bancaire</p>
                      </div>

                      <!-- Value Date -->
                      <div>
                        <InputLabel for="value_date" value="Date de valeur" />
                        <TextInput
                          id="value_date"
                          type="date"
                          class="mt-1 block w-full"
                          v-model="depositValueDate"
                        />
                        <p class="mt-1 text-xs text-gray-400">Date à laquelle le montant sera crédité</p>
                      </div>

                      <!-- Bank Fees -->
                      <div>
                        <InputLabel for="bank_fees" value="Frais bancaires (DH)" />
                        <TextInput
                          id="bank_fees"
                          type="number"
                          min="0"
                          step="0.01"
                          placeholder="0.00"
                          class="mt-1 block w-full"
                          v-model="depositBankFees"
                        />
                        <p class="mt-1 text-xs text-gray-400">Frais appliqués par la banque</p>
                      </div>

                      <!-- Net Amount Preview -->
                      <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <span class="text-xs text-gray-500 block mb-1">Montant net estimé</span>
                        <span class="text-lg font-bold text-[#062121]">
                          {{ formatCurrency(selectedRemittance.total_amount - (depositBankFees || 0)) }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                      type="button"
                      @click="activeTab = 'remittances'; selectedRemittance = null;"
                      class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                    >
                      Annuler
                    </button>
                    <PrimaryButton>
                      <i class="fas fa-check mr-2"></i>
                      Confirmer le dépôt
                    </PrimaryButton>
                  </div>
                </form>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Dropdown Menu -->
    <Teleport to="body">
      <div
        v-if="openDropdownId"
        class="fixed inset-0 z-30"
        @click.self="closeDropdown"
      ></div>
      <div
        v-if="openDropdownId"
        class="fixed z-40 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1 max-h-[360px] overflow-y-auto"
        :style="{
          top: dropdownPosition.top + 'px',
          right: '16px',
        }"
        @click.stop
      >
        <template v-for="remittance in filteredRemittances" :key="remittance.id">
          <div v-if="openDropdownId === remittance.id">
            <button
              v-if="remittance.status === 'DRAFT'"
              @click="finalizeRemittance(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
            </button>
            <button
              v-if="remittance.status === 'FINALIZED'"
              @click="sendRemittance(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-paper-plane w-4 text-blue-500"></i> Envoyer
            </button>
            <button
              v-if="['FINALIZED', 'SENT'].includes(remittance.status)"
              @click="openDepositModal(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-university w-4 text-purple-500"></i> Déposer
            </button>
            <button
              v-if="['FINALIZED', 'DEPOSITED'].includes(remittance.status)"
              @click="printRemittance(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
            </button>
            <button
              v-if="!['DEPOSITED', 'CANCELLED'].includes(remittance.status)"
              @click="cancelRemittance(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-times-circle w-4 text-orange-500"></i> Annuler
            </button>
            <button
              v-if="remittance.status === 'DRAFT'"
              @click="deleteRemittance(remittance)"
              class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
            >
              <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
            </button>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
