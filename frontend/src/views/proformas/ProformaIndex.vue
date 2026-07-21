<!-- src/views/proformas/ProformaIndex.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const proformas = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

const fetchProformas = async () => {
  isLoading.value = true;
  try {
    const params = {
      per_page: 10,
    };
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/proformas", { params });
    proformas.value = data.data;
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      per_page: data.per_page,
      total: data.total,
    };
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de charger les factures proforma.",
    );
  } finally {
    isLoading.value = false;
  }
};

const filteredProformas = computed(() => {
  if (selectedStatus.value === "all") return proformas.value;
  return proformas.value.filter(
    (pro) => pro.documentable?.status === selectedStatus.value,
  );
});

const isImmutable = (pro) => {
  const status = pro.documentable?.status;
  return pro.is_locked ||
         pro.parent_document_id ||
         ['FINALIZED', 'SENT', 'CONVERTED', 'EXPIRED', 'CANCELLED'].includes(status);
};

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    SENT: "bg-blue-100 text-blue-700",
    FINALIZED: "bg-green-100 text-green-700",
    EXPIRED: "bg-orange-100 text-orange-700",
    CONVERTED: "bg-purple-100 text-purple-700",
    CANCELLED: "bg-gray-300 text-gray-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    SENT: "Envoyé",
    FINALIZED: "Finalisé",
    EXPIRED: "Expiré",
    CONVERTED: "Converti",
    CANCELLED: "Annulé",
  };
  return texts[status] || status;
};

const editProforma = (id) => {
  closeDropdown();
  router.push({ name: "proforma.edit", params: { id } });
};

const previewProforma = (id) => {
  closeDropdown();
  router.push(`/document/preview/${id}`);
};

const downloadProformaPdf = (id) => {
  closeDropdown();
  window.open(`/document/print/${id}`, "_blank");
};

const deleteProforma = async (id, number) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer la facture proforma",
    `Supprimer la facture proforma ${number} définitivement ?`,
  );
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/documents/${id}`);
    success("Supprimé !", "La facture proforma a été supprimée.");
    await fetchProformas();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de supprimer la facture proforma.",
    );
  }
};

const finalizeProforma = async (proforma) => {
  closeDropdown();
  const result = await confirm(
    "Finaliser la facture proforma",
    `Finaliser la facture proforma ${proforma.number || '#'+proforma.id} ? Le numéro sera généré automatiquement.`,
  );
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.put(`/api/proformas/${proforma.id}/finalize`);
    success("Finalisé !", `La facture proforma ${data.number} a été finalisée.`);
    await fetchProformas();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de finaliser la facture proforma.",
    );
  }
};

const sendProforma = async (proforma) => {
  closeDropdown();
  try {
    await axios.put(`/api/proformas/${proforma.id}/send`);
    success("Envoyé !", `La facture proforma ${proforma.number} a été envoyée.`);
    await fetchProformas();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'envoyer la facture proforma.",
    );
  }
};

const markExpiredProforma = async (id) => {
  closeDropdown();
  const result = await confirm("Marquer comme expiré", "Confirmer que cette proforma est expirée ?");
  if (!result.isConfirmed) return;
  try {
    await axios.put(`/api/proformas/${id}/mark-expired`);
    success("Expiré !", "La facture proforma a été marquée comme expirée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de marquer comme expiré.");
  }
};

const cancelProforma = async (id) => {
  closeDropdown();
  const result = await confirm("Annuler", "Confirmer l'annulation ?");
  if (!result.isConfirmed) return;
  try {
    await axios.put(`/api/proformas/${id}/cancel`);
    success("Annulé !", "La facture proforma a été annulée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'annuler.");
  }
};

const convertToInvoice = async (proforma) => {
  closeDropdown();
  const result = await confirm(
    "Convertir en Facture",
    `Convertir la facture proforma ${proforma.number || '#'+proforma.id} en facture définitive ?`
  );
  if (!result.isConfirmed) return;

  try {
    const { data } = await axios.post(`/api/proformas/${proforma.id}/convert-to-invoice`);
    success('Converti !', `La facture ${data.number || '#'+data.id} a été créée à partir de la proforma.`);
    await fetchProformas();
  } catch (err) {
    error(
      'Erreur',
      err.response?.data?.message || 'Impossible de convertir en facture.',
    );
  }
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

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

const openSendPage = (pro) => {
  closeDropdown();
  router.push({
    name: "document.send",
    params: { id: pro.id },
    query: { type: "proforma", page: "proforma" },
  });
};

const createProforma = () => router.push({ name: "proforma.create" });

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchProformas();
};

onMounted(async () => {
  await fetchProformas();
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
                    'text-[#062121] border-b-2 border-[#C5F82A]'
                  ]"
                >
                  <i class="fas fa-file-invoice"></i>
                  Liste des Factures Proforma
                  <span
                    v-if="proformas.length > 0"
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                    >{{ proformas.length }}</span
                  >
                </button>
              </div>

              <button
                @click="createProforma"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une Facture Proforma
              </button>
            </div>

            <!-- Status Filters -->
            <div
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
                  proformas.length
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
                    proformas.filter(
                      (pro) => pro.documentable?.status === "DRAFT",
                    ).length
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
                  class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                  >{{
                    proformas.filter(
                      (pro) => pro.documentable?.status === "FINALIZED",
                    ).length
                  }}</span
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
                <i class="fas fa-paper-plane"></i> Envoyés
                <span
                  class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"
                  >{{
                    proformas.filter(
                      (pro) => pro.documentable?.status === "SENT",
                    ).length
                  }}</span
                >
              </button>
              <button
                @click="changeStatusFilter('CONVERTED')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'CONVERTED'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-exchange-alt"></i> Convertis
                <span
                  class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full"
                  >{{
                    proformas.filter(
                      (pro) => pro.documentable?.status === "CONVERTED",
                    ).length
                  }}</span
                >
              </button>
              <button
                @click="changeStatusFilter('EXPIRED')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'EXPIRED'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-clock"></i> Expirés
                <span
                  class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full"
                  >{{
                    proformas.filter(
                      (pro) => pro.documentable?.status === "EXPIRED",
                    ).length
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
                  class="text-xs bg-gray-300 text-gray-700 px-2 py-0.5 rounded-full"
                  >{{
                    proformas.filter(
                      (pro) => pro.documentable?.status === "CANCELLED",
                    ).length
                  }}</span
                >
              </button>
            </div>
          </div>

          <!-- Content Area -->
          <div class="p-6 lg:p-8">
            <!-- Proforma List -->
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
              <p class="mt-2 text-gray-500">Chargement des factures proforma...</p>
            </div>
            <div
              v-else-if="filteredProformas.length === 0"
              class="text-center py-12"
            >
              <i
                class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"
              ></i>
              <p class="text-gray-500">
                {{
                  selectedStatus === "all"
                    ? "Aucune facture proforma créée pour le moment."
                    : "Aucune facture proforma avec ce statut."
                }}
              </p>
              <button
                @click="createProforma"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre première facture proforma
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      N° Proforma
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Client
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Date de validité
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
                    v-for="proforma in filteredProformas"
                    :key="proforma.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">
                        #{{ proforma.number || (proforma.documentable?.status === 'DRAFT' ? 'Brouillon' : '—') }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{
                              proforma.customer?.customerable
                                ? proforma.customer.type === "b2b"
                                  ? proforma.customer.customerable.legal_name
                                  : proforma.customer.customerable.name
                                : proforma.customer?.name || "—"
                            }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">
                        {{ formatDate(proforma.documentable?.validity_date) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">
                        {{ formatCurrency(proforma.total_ttc) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span
                        :class="[
                          'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                          getStatusBadgeClass(proforma.documentable?.status),
                        ]"
                        >{{ getStatusText(proforma.documentable?.status) }}</span
                      >
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="previewProforma(proforma.id)"
                          title="Aperçu"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button
                          @click="toggleDropdown(proforma.id, $event)"
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
        <template v-for="pro in filteredProformas" :key="pro.id">
          <div v-if="openDropdownId === pro.id">
            <button
              v-if="!isImmutable(pro)"
              @click="editProforma(pro.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
            </button>
            <button
              v-if="pro.documentable?.status === 'DRAFT'"
              @click="finalizeProforma(pro)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
            </button>
            <button
              v-if="
                pro.documentable?.status === 'FINALIZED' ||
                pro.documentable?.status === 'SENT'
              "
              @click="convertToInvoice(pro)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-exchange-alt w-4 text-purple-500"></i> Convertir en Facture
            </button>
            <button
              v-if="
                pro.documentable?.status === 'FINALIZED' ||
                pro.documentable?.status === 'SENT'
              "
              @click="openSendPage(pro)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
              email
            </button>
            <button
              v-if="pro.documentable?.status === 'SENT'"
              @click="markExpiredProforma(pro.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-clock w-4 text-orange-500"></i> Marquer Expiré
            </button>
            <button
              v-if="
                pro.documentable?.status === 'SENT' ||
                pro.documentable?.status === 'EXPIRED'
              "
              @click="cancelProforma(pro.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-times-circle w-4 text-orange-500"></i> Annuler
            </button>
            <div class="border-t border-gray-100 my-1"></div>
            <button
              @click="downloadProformaPdf(pro.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
            </button>
            <button
              v-if="!isImmutable(pro)"
              @click="deleteProforma(pro.id, pro.number)"
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
