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

const fetchProformas = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/proformas", { params });
    proformas.value = data;
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de charger les factures proforma.");
  } finally {
    isLoading.value = false;
  }
};

const filteredProformas = computed(() => {
  if (selectedStatus.value === "all") return proformas.value;
  return proformas.value.filter((p) => p.documentable?.status === selectedStatus.value);
});

const isImmutable = (proforma) => {
  const status = proforma.documentable?.status;
  return proforma.is_locked || proforma.parent_document_id || ['SENT', 'CONVERTED', 'EXPIRED', 'CANCELLED'].includes(status);
};

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    CONVERTED: "bg-purple-100 text-purple-700",
    EXPIRED: "bg-red-100 text-red-700",
    CANCELLED: "bg-gray-200 text-gray-600",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    SENT: "Envoyé",
    CONVERTED: "Converti",
    EXPIRED: "Expiré",
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

const viewProforma = (proforma) => {
  closeDropdown();
  router.push({ name: 'document.preview', params: { id: proforma.document_id } });
};

const editProforma = async (proforma) => {
  closeDropdown();
  router.push({ name: 'proforma.edit', params: { id: proforma.id } });
};

const deleteProforma = async (id, number) => {
  closeDropdown();
  const result = await confirm("Êtes-vous sûr ?", `Supprimer la facture proforma "${number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/proformas/${id}`);
    success("Supprimée !", "La facture proforma a été supprimée.");
    await fetchProformas();
  } catch {
    error("Erreur", "Impossible de supprimer la facture proforma.");
  }
};

const finalizeProforma = async (proforma) => {
  closeDropdown();
  const result = await confirm("Finaliser", `Finaliser la facture proforma "${proforma.document?.number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/proformas/${proforma.id}/finalize`);
    success("Finalisée !", "La facture proforma a été finalisée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de finaliser la facture proforma.");
  }
};

const sendProforma = async (proforma) => {
  closeDropdown();
  const result = await confirm("Envoyer", `Envoyer la facture proforma "${proforma.document?.number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/proformas/${proforma.id}/send`);
    success("Envoyée !", "La facture proforma a été envoyée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'envoyer la facture proforma.");
  }
};

const markExpired = async (proforma) => {
  closeDropdown();
  const result = await confirm("Expirer", `Marquer la facture proforma "${proforma.document?.number}" comme expirée ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/proformas/${proforma.id}/mark-expired`);
    success("Expirée !", "La facture proforma a été marquée comme expirée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de marquer la facture proforma.");
  }
};

const cancelProforma = async (proforma) => {
  closeDropdown();
  const result = await confirm("Annuler", `Annuler la facture proforma "${proforma.document?.number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/proformas/${proforma.id}/cancel`);
    success("Annulée !", "La facture proforma a été annulée.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'annuler la facture proforma.");
  }
};

const convertToInvoice = async (proforma) => {
  closeDropdown();
  const result = await confirm("Convertir", `Convertir la facture proforma "${proforma.document?.number}" en facture ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.post(`/api/proformas/${proforma.id}/convert-to-invoice`);
    success("Convertie !", "La facture proforma a été convertie en facture.");
    await fetchProformas();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de convertir la facture proforma.");
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
  fetchProformas();
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
                  <i class="fas fa-file-invoice"></i>
                  Factures Proforma
                  <span v-if="proformas.length > 0" class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ proformas.length }}
                  </span>
                </button>
              </div>

              <button
                @click="router.push({ name: 'proforma.create' })"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une proforma
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
                  class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                >{{
                  proformas.filter((p) => p.documentable?.status === "DRAFT").length
                }}</span>
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
                >{{
                  proformas.filter((p) => p.documentable?.status === "FINALIZED").length
                }}</span>
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
                >{{
                  proformas.filter((p) => p.documentable?.status === "SENT").length
                }}</span>
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
                <i class="fas fa-exchange-alt"></i> Converties
                <span
                  class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full"
                >{{
                  proformas.filter((p) => p.documentable?.status === "CONVERTED").length
                }}</span>
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
                <i class="fas fa-clock"></i> Expirées
                <span
                  class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full"
                >{{
                  proformas.filter((p) => p.documentable?.status === "EXPIRED").length
                }}</span>
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

            <div v-else-if="filteredProformas.length === 0" class="text-center py-12">
              <i class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{ selectedStatus === "all" ? "Aucune facture proforma enregistrée." : "Aucune facture proforma avec ce statut." }}
              </p>
              <button
                @click="router.push({ name: 'proforma.create' })"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Créer une proforma
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Proforma</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="proforma in filteredProformas" :key="proforma.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">{{ proforma.document?.number || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ proforma.document?.customer?.name || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(proforma.document?.created_at) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(proforma.document?.total_ttc || 0) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(proforma.documentable?.status)]">
                        {{ getStatusText(proforma.documentable?.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="viewProforma(proforma)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="toggleDropdown(proforma.id, $event)" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
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
        <template v-for="proforma in filteredProformas" :key="proforma.id">
          <div v-if="openDropdownId === proforma.id">
            <!-- Draft Actions -->
            <template v-if="proforma.documentable?.status === 'DRAFT'">
              <button
                @click="editProforma(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
              </button>
              <button
                @click="finalizeProforma(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="deleteProforma(proforma.id, proforma.document?.number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
            </template>

            <!-- Finalized Actions -->
            <template v-else-if="proforma.documentable?.status === 'FINALIZED'">
              <button
                @click="viewProforma(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="sendProforma(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-paper-plane w-4 text-blue-500"></i> Envoyer
              </button>
              <button
                @click="convertToInvoice(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-exchange-alt w-4 text-purple-500"></i> Convertir en facture
              </button>
            </template>

            <!-- Sent Actions -->
            <template v-else-if="proforma.documentable?.status === 'SENT'">
              <button
                @click="viewProforma(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="convertToInvoice(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-exchange-alt w-4 text-purple-500"></i> Convertir en facture
              </button>
              <button
                @click="markExpired(proforma)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-clock w-4 text-orange-500"></i> Marquer expirée
              </button>
            </template>

            <!-- Converted/Expired Actions -->
            <template v-else-if="['CONVERTED', 'EXPIRED'].includes(proforma.documentable?.status)">
              <button
                @click="viewProforma(proforma)"
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
