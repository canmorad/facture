<!-- src/views/deposits/DepositIndex.vue -->
<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const deposits = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const fetchDeposits = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    if (!companyId) {
      error("Erreur", "Aucune entreprise sélectionnée.");
      return;
    }

    const params = {
      company_id: companyId,
    };
    if (selectedStatus.value !== "all") {
      params.status = selectedStatus.value;
    }

    const { data } = await axios.get("/api/deposits", { params });
    deposits.value = data;
  } catch (err) {
    const message =
      err.response?.data?.error || "Impossible de charger les acomptes.";
    error("Erreur", message);
  } finally {
    isLoading.value = false;
  }
};

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
  const dropdownWidth = 240;
  const windowWidth = window.innerWidth;
  let left = rect.left;
  if (left + dropdownWidth > windowWidth - 10) {
    left = windowWidth - dropdownWidth - 10;
  }
  if (left < 10) {
    left = 10;
  }

  dropdownPosition.value = {
    top: rect.bottom + window.scrollY + 4,
    left: left,
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

const deleteDeposit = async (id, number) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer l'acompte",
    `Supprimer l'acompte ${number} définitivement ?`,
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    await axios.delete(`/api/documents/${id}`, {
      params: { company_id: companyId },
    });
    success("Supprimé !", "L'acompte a été supprimé.");
    await fetchDeposits();
  } catch (err) {
    const message =
      err.response?.data?.error || "Impossible de supprimer l'acompte.";
    error("Erreur", message);
  }
};

const finalizeDeposit = async (deposit) => {
  closeDropdown();
  const result = await confirm(
    "Finaliser l'acompte",
    `Finaliser l'acompte ${deposit.number} ? Le numéro sera généré automatiquement et le statut passera à Payé.`,
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.put(
      `/api/deposits/${deposit.id}/finalize`,
      null,
      {
        params: { company_id: companyId },
      },
    );
    success("Finalisé !", `L'acompte ${data.number} a été finalisé et marqué comme payé.`);
    await fetchDeposits();
  } catch (err) {
    const message =
      err.response?.data?.error || "Impossible de finaliser l'acompte.";
    error("Erreur", message);
  }
};

const downloadPdf = (id) => {
  closeDropdown();
  window.open(`/api/documents/${id}/pdf`, '_blank');
};

const sendEmail = (id) => {
  closeDropdown();
  // TODO: Implémenter l'envoi par email
  success("Email envoyé", "L'acompte a été envoyé par email.");
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("fr-MA");
};

const formatCurrency = (amount) => {
  return (
    new Intl.NumberFormat("fr-MA", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " DH"
  );
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchDeposits();
};

onMounted(() => {
  fetchDeposits();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button
                  @click="changeStatusFilter('all')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'all'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-list"></i> Tous
                  <span
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >
                    {{ deposits.length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('DRAFT')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'DRAFT'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-pen"></i> Brouillons
                  <span
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >
                    {{
                      deposits.filter((d) => d.documentable?.status === "DRAFT")
                        .length
                    }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('FINALIZED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'FINALIZED'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-circle"></i> Finalisés
                  <span
                    class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                  >
                    {{
                      deposits.filter(
                        (d) => d.documentable?.status === "FINALIZED",
                      ).length
                    }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('PAID')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'PAID'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-double"></i> Payés
                  <span
                    class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full"
                  >
                    {{
                      deposits.filter((d) => d.documentable?.status === "PAID")
                        .length
                    }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('CANCELLED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'CANCELLED'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-times-circle"></i> Annulés
                  <span
                    class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full"
                  >
                    {{
                      deposits.filter(
                        (d) => d.documentable?.status === "CANCELLED",
                      ).length
                    }}
                  </span>
                </button>
              </div>
            </div>
          </div>

          <div class="p-6 lg:p-8">
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
                    ? "Aucun acompte créé pour le moment."
                    : "Aucun acompte avec ce statut."
                }}
              </p>
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
                        #{{ deposit.number || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{
                          deposit.documentable?.quote?.document?.number || "—"
                        }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{ deposit.customer?.name || "Client inconnu" }}
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
                      >
                        {{ getStatusText(deposit.documentable?.status) }}
                      </span>
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
          </div>
        </div>
      </div>
    </div>

    <!-- Dropdown menu -->
    <Teleport to="body">
      <div
        v-if="openDropdownId"
        class="fixed inset-0 z-30"
        @click.self="closeDropdown"
      ></div>

      <div
        v-if="openDropdownId"
        class="fixed z-40 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1"
        :style="{
          top: dropdownPosition.top + 'px',
          left: dropdownPosition.left + 'px',
        }"
        @click.stop
      >
        <template v-for="deposit in filteredDeposits" :key="deposit.id">
          <div v-if="openDropdownId === deposit.id">
            <template v-if="deposit.documentable?.status === 'DRAFT'">
              <button
                @click="editDeposit(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-gray-400"></i> Modifier
              </button>
              <button
                @click="deleteDeposit(deposit.id, deposit.number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
              <button
                @click="finalizeDeposit(deposit)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
              </button>
            </template>
            <template v-else-if="deposit.documentable?.status === 'PAID'">
              <button
                @click="previewDeposit(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="downloadPdf(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="sendEmail(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par email
              </button>
            </template>
            <!-- Pour les autres statuts (FINALIZED, CANCELLED) -->
            <template v-else>
              <button
                @click="previewDeposit(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="downloadPdf(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="sendEmail(deposit.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par email
              </button>
            </template>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>