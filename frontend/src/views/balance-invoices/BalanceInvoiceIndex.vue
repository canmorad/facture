<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const balanceInvoices = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const fetchBalanceInvoices = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/balance-invoices", { params });
    balanceInvoices.value = data.data || data || [];
  } catch (err) {
    balanceInvoices.value = [];
    error("Erreur", err.response?.data?.message || "Impossible de charger les factures de solde.");
  } finally {
    isLoading.value = false;
  }
};

const filteredBalanceInvoices = computed(() => {
  if (selectedStatus.value === "all") return balanceInvoices.value;
  return balanceInvoices.value.filter((b) => b.documentable?.status === selectedStatus.value);
});

const draftCount = computed(() =>
  balanceInvoices.value.filter((b) => b.documentable?.status === "DRAFT").length
);

const finalizedCount = computed(() =>
  balanceInvoices.value.filter((b) => b.documentable?.status === "FINALIZED").length
);

const sentCount = computed(() =>
  balanceInvoices.value.filter((b) => b.documentable?.status === "SENT").length
);

const paidCount = computed(() =>
  balanceInvoices.value.filter((b) => b.documentable?.status === "PAID").length
);

const cancelledCount = computed(() =>
  balanceInvoices.value.filter((b) => b.documentable?.status === "CANCELLED").length
);

const isImmutable = (balanceInvoice) => {
  const status = balanceInvoice.documentable?.status;
  return balanceInvoice.is_locked || balanceInvoice.parent_document_id || ['FINALIZED', 'SENT', 'PAID'].includes(status);
};

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    PAID: "bg-emerald-100 text-emerald-700",
    CANCELLED: "bg-gray-200 text-gray-600",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    SENT: "Envoyé",
    PAID: "Payée",
    CANCELLED: "Annulé",
  };
  return texts[status] || status;
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

const viewBalanceInvoice = (balanceInvoice) => {
  closeDropdown();
  router.push({ name: 'document.preview', params: { id: balanceInvoice.id } });
};

const editBalanceInvoice = async (balanceInvoice) => {
  closeDropdown();
  router.push({ name: 'balance-invoice.edit', params: { id: balanceInvoice.id } });
};

const deleteBalanceInvoice = async (id, number) => {
  closeDropdown();
  const result = await confirm("Êtes-vous sûr ?", `Supprimer la facture de solde "${number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/balance-invoices/${id}`);
    success("Supprimée !", "La facture de solde a été supprimée.");
    await fetchBalanceInvoices();
  } catch {
    error("Erreur", "Impossible de supprimer la facture de solde.");
  }
};

const finalizeBalanceInvoice = async (balanceInvoice) => {
  closeDropdown();
  const result = await confirm("Finaliser", `Finaliser la facture de solde "${balanceInvoice.number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/balance-invoices/${balanceInvoice.id}/finalize`);
    success("Finalisée !", "La facture de solde a été finalisée.");
    await fetchBalanceInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de finaliser la facture de solde.");
  }
};

const sendBalanceInvoice = async (balanceInvoice) => {
  closeDropdown();
  const result = await confirm("Envoyer", `Envoyer la facture de solde "${balanceInvoice.number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/balance-invoices/${balanceInvoice.id}/send`);
    success("Envoyée !", "La facture de solde a été envoyée.");
    await fetchBalanceInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'envoyer la facture de solde.");
  }
};

const updateStatus = async (balanceInvoice, status) => {
  closeDropdown();
  const statusText = status === 'PAID' ? 'payée' : 'annulée';
  const result = await confirm("Mettre à jour", `Marquer la facture de solde "${balanceInvoice.number}" comme ${statusText} ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/balance-invoices/${balanceInvoice.id}/status`, { status });
    success("Mise à jour !", `La facture de solde a été marquée comme ${statusText}.`);
    await fetchBalanceInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de mettre à jour la facture de solde.");
  }
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA");
};

onMounted(() => {
  fetchBalanceInvoices();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Tab Navigation -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button
                  class="pb-3 text-sm font-bold text-[#062121] border-b-2 border-[#C5F82A] flex items-center gap-2"
                >
                  <i class="fas fa-file-invoice-dollar"></i>
                  Factures de Solde
                  <span v-if="balanceInvoices.length > 0" class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ balanceInvoices.length }}
                  </span>
                </button>
              </div>

              <button
                @click="router.push({ name: 'balance-invoice.create' })"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une facture de solde
              </button>
            </div>

            <!-- Status Filters -->
            <div class="flex gap-4 pb-3 mt-2 overflow-x-auto">
              <button
                @click="changeStatusFilter('all')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'all'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-list"></i> Tous
                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{
                  balanceInvoices.length
                }}</span>
              </button>
              <button
                @click="changeStatusFilter('DRAFT')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'DRAFT'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-pen"></i> Brouillons
                <span
                  class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                >{{ draftCount }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('FINALIZED')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'FINALIZED'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-check-circle"></i> Finalisées
                <span
                  class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                >{{ finalizedCount }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('SENT')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'SENT'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-paper-plane"></i> Envoyées
                <span
                  class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"
                >{{ sentCount }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('PAID')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'PAID'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-check-double"></i> Payées
                <span
                  class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full"
                >{{ paidCount }}</span>
              >
              </button>
            </div>
          </div>

          <!-- TABLE CONTENT -->
          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement...</p>
            </div>

            <div v-else-if="filteredBalanceInvoices.length === 0" class="text-center py-12">
              <i class="fas fa-file-invoice-dollar text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{ selectedStatus === "all" ? "Aucune facture de solde enregistrée." : "Aucune facture de solde avec ce statut." }}
              </p>
              <button
                @click="router.push({ name: 'balance-invoice.create' })"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Créer une facture de solde
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Facture</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Devis associé</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Solde TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="balanceInvoice in filteredBalanceInvoices" :key="balanceInvoice.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">{{ balanceInvoice.number || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ balanceInvoice.parent?.number || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{ balanceInvoice.customer?.customerable
                          ? (balanceInvoice.customer.type === 'b2b'
                              ? balanceInvoice.customer.customerable.legal_name
                              : balanceInvoice.customer.customerable.name)
                          : balanceInvoice.customer?.name || "—"
                        }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(balanceInvoice.total_ttc || 0) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(balanceInvoice.documentable?.status)]">
                        {{ getStatusText(balanceInvoice.documentable?.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="viewBalanceInvoice(balanceInvoice)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="toggleDropdown(balanceInvoice.id, $event)" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-ellipsis-v text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ACTIONS DROPDOWN (Teleport to body) -->
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
        <template v-for="balanceInvoice in filteredBalanceInvoices" :key="balanceInvoice.id">
          <div v-if="openDropdownId === balanceInvoice.id">
            <!-- Draft Actions -->
            <template v-if="balanceInvoice.documentable?.status === 'DRAFT'">
              <button
                @click="editBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
              </button>
              <button
                @click="finalizeBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="deleteBalanceInvoice(balanceInvoice.id, balanceInvoice.number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
            </template>

            <!-- Finalized Actions -->
            <template v-else-if="balanceInvoice.documentable?.status === 'FINALIZED'">
              <button
                @click="viewBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="sendBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-paper-plane w-4 text-blue-500"></i> Envoyer
              </button>
              <button
                @click="updateStatus(balanceInvoice, 'PAID')"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-money-bill-wave w-4 text-green-500"></i> Marquer payée
              </button>
            </template>

            <!-- Sent Actions -->
            <template v-else-if="balanceInvoice.documentable?.status === 'SENT'">
              <button
                @click="viewBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="updateStatus(balanceInvoice, 'PAID')"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-money-bill-wave w-4 text-green-500"></i> Marquer payée
              </button>
            </template>

            <!-- Paid Actions -->
            <template v-else-if="balanceInvoice.documentable?.status === 'PAID'">
              <button
                @click="viewBalanceInvoice(balanceInvoice)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
            </template>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
