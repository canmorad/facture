<!-- src/views/deposits/DepositIndex.vue -->
<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import RegisterPaymentForm from "@/components/RegisterPaymentForm.vue";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const deposits = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const activeTab = ref("list");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

// Payment form state
const selectedDepositForPayment = ref(null);

const openPaymentForm = async (deposit) => {
  closeDropdown();
  selectedDepositForPayment.value = deposit;
  await nextTick();
  activeTab.value = "payment";
};

const closePaymentForm = () => {
  activeTab.value = "list";
  selectedDepositForPayment.value = null;
};

const handlePaymentRegistered = async () => {
  await fetchDeposits();
  activeTab.value = "list";
  selectedDepositForPayment.value = null;
};

const handleOpenCashSession = () => {
  // Store return path to come back after opening session
  sessionStorage.setItem('returnToPayment', 'true');
  sessionStorage.setItem('returnToDepositId', selectedDepositForPayment.value?.id);
  router.push({ name: 'cash-register.index', query: { openSession: true } });
};

// Check if we should return to payment form after opening session
const checkReturnToPayment = () => {
  if (sessionStorage.getItem('returnToPayment') === 'true') {
    const depositId = sessionStorage.getItem('returnToDepositId');
    sessionStorage.removeItem('returnToPayment');
    sessionStorage.removeItem('returnToDepositId');

    const deposit = deposits.value.find(dep => dep.id == depositId);
    if (deposit) {
      selectedDepositForPayment.value = deposit;
      activeTab.value = 'payment';
    }
  }
};

const createNewDeposit = () => {
  router.push({ name: "deposit.create" });
};

const isImmutable = (dep) => {
  const status = dep.documentable?.status;
  return dep.is_locked ||
         dep.parent_document_id ||
         ['FINALIZED', 'PAID', 'CANCELLED'].includes(status);
};

const canRegisterPayment = (deposit) => {
  const status = deposit.documentable?.status;
  return ['FINALIZED'].includes(status);
};

const fetchDeposits = async () => {
  isLoading.value = true;
  try {
    const params = {
      per_page: 10,
    };
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/deposits", { params });
    deposits.value = data.data;
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      per_page: data.per_page,
      total: data.total,
    };
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de charger les acomptes.",
    );
  } finally {
    isLoading.value = false;
  }
};

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

const filteredDeposits = computed(() => {
  if (selectedStatus.value === "all") return deposits.value;
  return deposits.value.filter(
    (d) => d.documentable?.status === selectedStatus.value,
  );
});

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    PAID: "bg-purple-100 text-purple-700",
    CANCELLED: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    PAID: "Payé",
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

const editDeposit = (id) => {
  closeDropdown();
  router.push({ name: "deposit.edit", params: { id } });
};

const previewDeposit = (id) => {
  closeDropdown();
  router.push(`/document/preview/${id}`);
};

const downloadPdf = (id) => {
  closeDropdown();
  window.open(`/document/print/${id}`, "_blank");
};

const openSendPage = (dep) => {
  closeDropdown();
  router.push({
    name: "document.send",
    params: { id: dep.id },
    query: { type: "deposit", page: "deposit" },
  });
};

const deleteDeposit = async (id, number) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer l'acompte",
    `Supprimer l'acompte ${number} définitivement ?`,
  );
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/documents/${id}`);
    success("Supprimé !", "L'acompte a été supprimé.");
    await fetchDeposits();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de supprimer l'acompte.",
    );
  }
};

const finalizeDeposit = async (deposit) => {
  closeDropdown();
  const result = await confirm(
    "Finaliser l'acompte",
    `Finaliser l'acompte ${deposit.number || 'Brouillon'} ?`,
  );
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.put(`/api/deposits/${deposit.id}/finalize`);
    success("Finalisé !", `L'acompte ${data.number} a été finalisé.`);
    await fetchDeposits();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de finaliser l'acompte.",
    );
  }
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
  fetchDeposits();
};

onMounted(async () => {
  await fetchDeposits();
  checkReturnToPayment();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <!-- Main Navigation Tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-0">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button
                  @click="activeTab = 'list'"
                  :class="[
                    'pb-4 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'list'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-file-invoice-dollar"></i>
                  Liste des acomptes
                  <span
                    v-if="deposits.length > 0"
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                    >{{ deposits.length }}</span
                  >
                </button>

                <button
                  @click="activeTab = 'payment'"
                  :class="[
                    'pb-4 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'payment'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-money-bill-wave"></i>
                  Enregistrer un paiement
                </button>
              </div>

              <button
                v-if="activeTab === 'list'"
                @click="createNewDeposit"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une facture d'acompte
              </button>
            </div>

            <!-- Status Filters (only show in list tab) -->
            <div
              v-if="activeTab === 'list'"
              class="flex gap-4 pb-3 mt-2 overflow-x-auto"
            >
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
                  deposits.length
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
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{
                    deposits.filter((d) => d.documentable?.status === "DRAFT")
                      .length
                  }}</span
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
                <i class="fas fa-check-circle"></i> Finalisés
                <span
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{
                    deposits.filter((d) => d.documentable?.status === "FINALIZED")
                      .length
                  }}</span
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
                <i class="fas fa-check-double"></i> Payés
                <span
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{
                    deposits.filter((d) => d.documentable?.status === "PAID")
                      .length
                  }}</span
                >
              </button>
              <button
                @click="changeStatusFilter('CANCELLED')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'CANCELLED'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-times-circle"></i> Annulés
                <span
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{
                    deposits.filter((d) => d.documentable?.status === "CANCELLED")
                      .length
                  }}</span
                >
              </button>
            </div>
          </div>

          <!-- Content Area -->
          <div class="p-6 lg:p-8">
            <!-- LIST TAB: Deposits Table -->
            <template v-if="activeTab === 'list'">
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
                <p class="mt-2 text-gray-500">Chargement des acomptes...</p>
              </div>
              <div
                v-else-if="filteredDeposits.length === 0"
                class="text-center py-12"
              >
                <i
                  class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"
                ></i>
                <p class="text-gray-500">
                  {{
                    selectedStatus === "all"
                      ? "Aucune facture d'acompte créée pour le moment."
                      : "Aucune facture d'acompte avec ce statut."
                  }}
                </p>
                <button
                  v-if="selectedStatus === 'all'"
                  @click="createNewDeposit"
                  class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
                >
                  <i class="fas fa-plus"></i> Créer votre première facture d'acompte
                </button>
              </div>
              <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th
                        class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        N° Acompte
                      </th>
                      <th
                        class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        Devis
                      </th>
                      <th
                        class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        Client
                      </th>
                      <th
                        class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        Total TTC
                      </th>
                      <th
                        class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        Statut
                      </th>
                      <th
                        class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider"
                      >
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="deposit in filteredDeposits"
                      :key="deposit.id"
                      class="group hover:bg-white/50 transition-colors duration-200"
                    >
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">
                          #{{ deposit.number || (deposit.documentable?.status === 'DRAFT' ? 'Brouillon' : '—') }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <router-link
                          v-if="deposit.parent_document_id && deposit.parent?.number"
                          :to="`/document/preview/${deposit.parent_document_id}`"
                          class="text-sm font-semibold text-blue-600 hover:text-blue-800 hover:underline transition-colors"
                        >
                          #{{ deposit.parent?.number }}
                        </router-link>
                        <div v-else class="text-sm text-gray-500">—</div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-700">
                          {{
                            deposit.customer.customerable
                              ? deposit.customer.type === "b2b"
                                ? deposit.customer.customerable.legal_name
                                : deposit.customer.customerable.name
                              : deposit.customer.name || "—"
                          }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-bold text-[#062121]">
                          {{ formatCurrency(deposit.total_ttc) }}
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <span
                          :class="[
                            'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                            getStatusBadgeClass(deposit.documentable?.status),
                          ]"
                          >{{ getStatusText(deposit.documentable?.status) }}</span
                        >
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right">
                        <button
                          @click="toggleDropdown(deposit.id, $event)"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-ellipsis-v text-sm"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

          <!-- PAYMENT TAB: Payment Form -->
          <template v-else-if="activeTab === 'payment'">
            <div v-if="selectedDepositForPayment">
              <RegisterPaymentForm
                :document="selectedDepositForPayment"
                document-type="deposit"
                @payment-registered="handlePaymentRegistered"
                @cancel="closePaymentForm"
                @open-cash-session="handleOpenCashSession"
              />
            </div>
            <div v-else class="text-center py-12">
              <i class="fas fa-hand-pointer text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                Sélectionnez un acompte dans la liste pour enregistrer un paiement.
              </p>
              <button
                @click="activeTab = 'list'"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-list"></i> Voir les acomptes
              </button>
            </div>
          </template>
          </div>
        </div>
      </div>
    </div>

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
        <template v-for="deposit in filteredDeposits" :key="deposit.id">
          <div v-if="openDropdownId === deposit.id">
            <template v-if="!isImmutable(deposit)">
              <button
                @click="editDeposit(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-gray-400"></i> Modifier
              </button>
              <button
                v-if="deposit.documentable?.status === 'DRAFT'"
                @click="finalizeDeposit(deposit)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
              </button>
              <button
                @click="deleteDeposit(deposit.id, deposit.number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
            </template>
            <template v-else>
              <button
                @click="previewDeposit(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                v-if="canRegisterPayment(deposit)"
                @click="openPaymentForm(deposit)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-money-bill-wave w-4 text-green-500"></i> Enregistrer un paiement
              </button>
              <button
                @click="openSendPage(deposit)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
                email
              </button>
              <button
                @click="downloadPdf(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
            </template>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
