<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { bankRemittanceApi } from "@/services/bankRemittanceApi";
import {
  success,
  error,
  confirm as confirmModal,
} from "@/helpers/notifications";

const router = useRouter();
const route = useRoute();

const remittance = ref(null);
const isLoading = ref(true);
const showDropdown = ref(false);
const availableActions = ref({});

const formatDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount || 0) + " DH";

// Status helpers
const statusLabels = {
  DRAFT: "Brouillon",
  FINALIZED: "Finalisée",
  SENT: "Envoyée",
  DEPOSITED: "Déposée",
  RETURNED: "Rejetée",
  CANCELLED: "Annulée",
};

const statusColors = {
  DRAFT: "#94a3b8",
  FINALIZED: "#22c55e",
  SENT: "#3b82f6",
  DEPOSITED: "#8b5cf6",
  RETURNED: "#ef4444",
  CANCELLED: "#f97316",
};

const statusLabel = computed(
  () =>
    statusLabels[remittance.value?.status] || remittance.value?.status || "",
);
const statusBadgeColor = computed(
  () => statusColors[remittance.value?.status] || "#94a3b8",
);

// Payment document helpers
const paymentDocTypeLabel = (type) => (type === "cheque" ? "Chèque" : "LCN");
const paymentDocTypeClass = (type) =>
  type === "cheque"
    ? "bg-blue-100 text-blue-700"
    : "bg-orange-100 text-orange-700";

// Customer name display using exact B2B/B2C ternary formula
const getCustomerName = (doc) => {
  return doc.customer?.customerable
    ? doc.customer.type === "b2b"
      ? doc.customer.customerable.legal_name
      : doc.customer.customerable.name
    : doc.customer?.name || "—";
};

// Navigate to invoice document
const goToInvoice = (documentId) => {
  if (!documentId) return;
  router.push({ name: "document.preview", params: { id: documentId } });
};

const paymentDocStatusLabels = {
  pending: "En attente",
  remitted: "Remis",
  deposited: "Déposé",
  returned: "Rejeté",
  paid: "Encaissé",
  cancelled: "Annulé",
};

const paymentDocStatusClasses = {
  pending: "bg-yellow-100 text-yellow-800",
  remitted: "bg-blue-100 text-blue-800",
  deposited: "bg-purple-100 text-purple-800",
  returned: "bg-red-100 text-red-800",
  paid: "bg-green-100 text-green-800",
  cancelled: "bg-gray-100 text-gray-800",
};

const getPaymentDocStatusLabel = (status) =>
  paymentDocStatusLabels[status] || status;
const getPaymentDocStatusClass = (status) =>
  paymentDocStatusClasses[status] || "bg-gray-100 text-gray-800";

// Computed for header
const docLabel = computed(() => "Remise Bancaire");
const docIcon = computed(() => "fas fa-university");

// Action buttons
const actionButtons = computed(() => {
  const actions = [];
  const a = availableActions.value;

  if (a.can_edit)
    actions.push({
      key: "edit",
      label: "Modifier",
      icon: "fas fa-edit",
      color: "#3b82f6",
      action: editRemittance,
    });
  if (a.can_finalize)
    actions.push({
      key: "finalize",
      label: "Finaliser",
      icon: "fas fa-check-circle",
      color: "#22c55e",
      action: finalizeRemittance,
    });
  if (a.can_send)
    actions.push({
      key: "send",
      label: "Envoyer",
      icon: "fas fa-paper-plane",
      color: "#3b82f6",
      action: sendRemittance,
    });
  if (a.can_deposit)
    actions.push({
      key: "deposit",
      label: "Déposer",
      icon: "fas fa-university",
      color: "#8b5cf6",
      action: depositRemittance,
    });
  if (a.can_cancel)
    actions.push({
      key: "cancel",
      label: "Annuler",
      icon: "fas fa-times-circle",
      color: "#f97316",
      action: cancelRemittance,
    });
  if (a.can_delete)
    actions.push({
      key: "delete",
      label: "Supprimer",
      icon: "fas fa-trash-alt",
      color: "#ef4444",
      action: deleteRemittance,
    });

  // Add print action for FINALIZED and DEPOSITED statuses
  if (remittance.value && ['FINALIZED', 'DEPOSITED'].includes(remittance.value.status)) {
    actions.push({
      key: "print",
      label: "Imprimer / PDF",
      icon: "fas fa-print",
      color: "#6b7280",
      action: printRemittance,
    });
  }

  return actions;
});

// Dropdown
const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
  showDropdown.value = false;
};

// Actions
const editRemittance = () => {
  closeDropdown();
  router.push({
    name: "bank-remittance.edit",
    params: { id: route.params.id },
  });
};

const finalizeRemittance = async () => {
  closeDropdown();
  const result = await confirmModal(
    "Finaliser",
    "Finaliser cette remise ? Un numéro sera généré automatiquement.",
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.finalize(route.params.id);
    success("Finalisée !", "La remise a été finalisée avec succès.");
    await fetchRemittance();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de finaliser la remise.",
    );
  }
};

const sendRemittance = async () => {
  closeDropdown();
  const result = await confirmModal(
    "Envoyer",
    "Marquer cette remise comme envoyée à la banque ?",
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.send(route.params.id);
    success("Envoyée !", "La remise a été marquée comme envoyée.");
    await fetchRemittance();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'envoyer la remise.",
    );
  }
};

const depositRemittance = async () => {
  closeDropdown();
  router.push({
    name: "bank-remittance.index",
    query: { deposit: route.params.id },
  });
};

const cancelRemittance = async () => {
  closeDropdown();
  const result = await confirmModal(
    "Annuler",
    "Annuler cette remise ? Les documents seront libérés.",
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.cancel(route.params.id);
    success("Annulée !", "La remise a été annulée.");
    await fetchRemittance();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'annuler la remise.",
    );
  }
};

const deleteRemittance = async () => {
  closeDropdown();
  const result = await confirmModal(
    "Supprimer",
    "Supprimer cette remise brouillon ? Cette action est irréversible.",
  );
  if (!result.isConfirmed) return;

  try {
    await bankRemittanceApi.delete(route.params.id);
    success("Supprimée !", "La remise a été supprimée.");
    router.push({ name: "bank-remittance.index" });
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de supprimer la remise.",
    );
  }
};

const printRemittance = () => {
  closeDropdown();
  const url = router.resolve({ name: "bank-remittance.print", params: { id: route.params.id } }).href;
  window.open(url, '_blank');
};

const fetchRemittance = async () => {
  isLoading.value = true;
  try {
    const data = await bankRemittanceApi.getById(route.params.id);
    remittance.value = data;

    // Fetch available actions
    try {
      const actionsData = await bankRemittanceApi.getActions(route.params.id);
      availableActions.value = actionsData.actions || {};
    } catch (err) {
      availableActions.value = {};
    }
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de charger la remise.",
    );
    router.push({ name: "bank-remittance.index" });
  } finally {
    isLoading.value = false;
  }
};

const goBack = () => {
  router.push({ name: "bank-remittance.index" });
};

onMounted(() => {
  fetchRemittance();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div v-if="isLoading" class="text-center py-12">
            <svg
              class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
              />
            </svg>
            <p class="mt-2 text-gray-500">Chargement de la remise...</p>
          </div>

          <div v-else>
            <div class="px-6 pt-4 pb-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <button
                  class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]"
                >
                  <i :class="docIcon"></i>
                  {{ docLabel }} {{ remittance.number || "Brouillon" }}
                </button>
                <div v-if="remittance.status !== 'CANCELLED'" class="relative">
                  <button
                    @click="toggleDropdown"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                  >
                    <i class="fas fa-ellipsis-v text-sm"></i>
                  </button>
                  <div
                    v-if="showDropdown"
                    v-click-outside="closeDropdown"
                    class="absolute right-0 top-full mt-1 z-50 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1"
                  >
                    <button
                      v-for="btn in actionButtons"
                      :key="btn.key"
                      @click="
                        btn.action();
                        closeDropdown();
                      "
                      class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                    >
                      <i
                        :class="[btn.icon, 'w-4 text-center']"
                        :style="{ color: btn.color }"
                      ></i>
                      {{ btn.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-6 lg:p-8 space-y-5">
              <div>
                <h3
                  class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2"
                >
                  <i class="fas fa-info-circle text-gray-400"></i> Informations
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Statut</span>
                    <span
                      class="font-semibold"
                      :style="{ color: statusBadgeColor }"
                      >{{ statusLabel }}</span
                    >
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Créée le</span>
                    <span class="text-gray-800">{{
                      formatDate(remittance.created_at)
                    }}</span>
                  </div>
                  <div v-if="remittance.finalized_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28"
                      >Finalisée le</span
                    >
                    <span class="text-gray-800">{{
                      formatDate(remittance.finalized_at)
                    }}</span>
                  </div>
                  <div v-if="remittance.sent_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28"
                      >Envoyée le</span
                    >
                    <span class="text-gray-800">{{
                      formatDate(remittance.sent_at)
                    }}</span>
                  </div>
                  <div v-if="remittance.deposited_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28"
                      >Déposée le</span
                    >
                    <span class="text-gray-800">{{
                      formatDate(remittance.deposited_at)
                    }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3
                  class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2"
                >
                  <i class="fas fa-university text-gray-400"></i> Banque de
                  destination
                </h3>
                <div
                  v-if="remittance.bank_account"
                  class="text-sm text-gray-700 space-y-1.5"
                >
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Banque</span>
                    <span class="font-semibold text-gray-800">{{
                      remittance.bank_account.bank_name
                    }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Compte</span>
                    <span class="text-gray-800">{{
                      remittance.bank_account.label
                    }}</span>
                  </div>
                  <div v-if="remittance.bank_account.iban" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">IBAN</span>
                    <span class="text-gray-800">{{
                      remittance.bank_account.iban
                    }}</span>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400 italic">
                  Aucun compte bancaire associé
                </div>
              </div>

              <div>
                <h3
                  class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2"
                >
                  <i class="fas fa-calculator text-gray-400"></i> Totaux
                </h3>
                <div
                  class="max-w-sm bg-gray-50 rounded-lg border border-gray-100 p-4 space-y-2 text-sm"
                >
                  <div class="flex justify-between">
                    <span class="text-gray-500">Nombre de documents</span>
                    <span class="font-medium text-gray-800">{{
                      remittance.document_count || 0
                    }}</span>
                  </div>
                  <div
                    class="flex justify-between items-center pt-1.5 border-t-2 border-[#C5F82A]"
                  >
                    <span class="text-base font-bold text-[#062121]"
                      >Montant total</span
                    >
                    <span class="text-base font-black text-[#062121]">{{
                      formatCurrency(remittance.total_amount)
                    }}</span>
                  </div>
                </div>
              </div>

              <div v-if="remittance.deposit_slip_reference || remittance.notes">
                <h3
                  class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2"
                >
                  <i class="fas fa-file-alt text-gray-400"></i> Détails
                  supplémentaires
                </h3>
                <div class="space-y-3 text-sm text-gray-700">
                  <div
                    v-if="remittance.deposit_slip_reference"
                    class="flex gap-3"
                  >
                    <span class="text-gray-500 font-medium w-40"
                      >Référence du bordereau</span
                    >
                    <span class="text-gray-800">{{
                      remittance.deposit_slip_reference
                    }}</span>
                  </div>
                  <div v-if="remittance.notes" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Notes</span>
                    <span class="text-gray-800">{{ remittance.notes }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3
                  class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2"
                >
                  <i class="fas fa-receipt text-gray-400"></i> Documents de
                  paiement ({{ remittance.payment_documents?.length || 0 }})
                </h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Type
                        </th>
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Numéro
                        </th>
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          N° Facture
                        </th>
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Client
                        </th>
                        <th
                          class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Montant
                        </th>
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Échéance
                        </th>
                        <th
                          class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                        >
                          Statut
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr
                        v-for="doc in remittance.payment_documents"
                        :key="doc.id"
                        class="hover:bg-gray-50/50 transition-colors"
                      >
                        <td class="px-4 py-2.5">
                          <span
                            :class="[
                              'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                              paymentDocTypeClass(doc.type),
                            ]"
                          >
                            {{ paymentDocTypeLabel(doc.type) }}
                          </span>
                        </td>
                        <td class="px-4 py-2.5">
                          <span class="font-semibold text-gray-800">{{ doc.number }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                          <a
                            v-if="doc.document"
                            @click="goToInvoice(doc.document.id)"
                            class="text-[#062121] hover:text-blue-600 hover:underline cursor-pointer font-medium"
                          >
                            {{ doc.document.number }}
                          </a>
                          <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-700">
                          {{
                              doc.customer?.customerable
                                ? doc.customer.type === "b2b"
                                  ? doc.customer.customerable.legal_name
                                  : doc.customer.customerable.name
                                : doc.customer?.name || "—"
                            }}
                        </td>
                        <td
                          class="px-4 py-2.5 text-right font-semibold text-gray-800"
                        >
                          {{ formatCurrency(doc.amount) }}
                        </td>
                        <td class="px-4 py-2.5 text-gray-700">
                          {{ formatDate(doc.due_date) }}
                        </td>
                        <td class="px-4 py-2.5">
                          <span
                            :class="[
                              'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                              getPaymentDocStatusClass(doc.status),
                            ]"
                          >
                            {{ getPaymentDocStatusLabel(doc.status) }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
