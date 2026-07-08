<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const devis = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const fetchDevis = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/quotes", { params });
    devis.value = data;
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de charger les devis.",
    );
  } finally {
    isLoading.value = false;
  }
};

const filteredDevis = computed(() => {
  if (selectedStatus.value === "all") return devis.value;
  return devis.value.filter(
    (d) => d.documentable?.status === selectedStatus.value,
  );
});

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    SIGNED: "bg-purple-100 text-purple-700",
    EXPIRED: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    SENT: "Envoyé",
    SIGNED: "Signé",
    EXPIRED: "Expiré",
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
  let left = rect.left;
  if (left + dropdownWidth > window.innerWidth - 10)
    left = window.innerWidth - dropdownWidth - 10;
  if (left < 10) left = 10;
  dropdownPosition.value = {
    top: rect.bottom + window.scrollY + 4,
    left: left,
  };
  openDropdownId.value = id;
};

const closeDropdown = () => {
  openDropdownId.value = null;
};
const editDevis = (id) => {
  closeDropdown();
  router.push({ name: "quote.edit", params: { id } });
};
const previewDevis = (id) => {
  closeDropdown();
  router.push(`/document/preview/${id}`);
};

const deleteDevis = async (id, number) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer le devis",
    `Supprimer le devis ${number} définitivement ?`,
  );
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/documents/${id}`);
    success("Supprimé !", "Le devis a été supprimé.");
    await fetchDevis();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de supprimer le devis.",
    );
  }
};

const finalizeDevis = async (id) => {
  closeDropdown();
  const result = await confirm(
    "Finaliser le devis",
    "Le numéro de devis sera généré automatiquement.",
  );
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.put(`/api/quotes/${id}/finalize`);
    success("Finalisé !", `Le devis ${data.number} a été finalisé.`);
    await fetchDevis();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de finaliser le devis.",
    );
  }
};

const signDevis = async (quote) => {
  closeDropdown();
  try {
    await axios.put(`/api/quotes/${quote.id}/sign`);
    success("Signé !", `Le devis ${quote.number} a été marqué comme signé.`);
    await fetchDevis();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de signer le devis.",
    );
  }
};

const convertToInvoice = (quote) => {
  closeDropdown();
  router.push({ name: "invoice.create", query: { quote_id: quote.id } });
};

const convertToPurchaseOrder = (quote) => {
  closeDropdown();
  router.push({ name: "purchase-order.create", query: { quote_id: quote.id } });
};

const copyUrl = (id) => {
  closeDropdown();
  navigator.clipboard
    .writeText(`${window.location.origin}/document/preview/${id}`)
    .then(() => success("Copié !", "L'URL a été copiée."))
    .catch(() => error("Erreur", "Impossible de copier l'URL."));
};

const duplicateDevis = async (id) => {
  closeDropdown();
  try {
    const { data } = await axios.post(`/api/quotes/${id}/duplicate`);
    success("Dupliqué !", `Le devis a été dupliqué.`);
    await fetchDevis();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de dupliquer le devis.",
    );
  }
};

const createDeposit = (quote) => {
  closeDropdown();
  const quoteId = quote.documentable?.id || quote.id;
  router.push({ name: "deposit.create", query: { quote_id: quoteId } });
};

const createDeliveryNoteFromQuote = (quote) => {
  closeDropdown();
  router.push({ name: "delivery-note.create", query: { quote_id: quote.id } });
};

const convertToSoldeInvoice = async (quote) => {
  closeDropdown();
  if (quote.documentable?.status !== "SIGNED") {
    error("Erreur", "Le devis doit être signé.");
    return;
  }
  const result = await confirm(
    "Créer une facture de solde",
    `Créer une facture de solde à partir du devis ${quote.number} ?`,
  );
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.post(
      `/api/quotes/${quote.id}/convert-to-invoice`,
      { type: "SOLDE" },
    );
    success("Créée !", `La facture de solde ${data.number} a été créée.`);
    await fetchDevis();
    router.push("/invoices");
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de créer la facture de solde.",
    );
  }
};

const downloadPdf = (id) => {
  closeDropdown();
  window.open(`/document/print/${id}`, "_blank");
};

const openSendPage = (quote) => {
  closeDropdown();
  router.push({
    name: "document.send",
    params: { id: quote.id },
    query: { type: "devis", page: "quote" },
  });
};

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";
const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";
const createDevis = () => {
  router.push({ name: "quote.create" });
};
const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchDevis();
};
onMounted(() => {
  fetchDevis();
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
                  <i class="fas fa-list"></i> Tous<span
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-1"
                    >{{ devis.length }}</span
                  >
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
                  <i class="fas fa-pen"></i> Brouillons<span
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-1"
                    >{{
                      devis.filter((d) => d.documentable?.status === "DRAFT")
                        .length
                    }}</span
                  >
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
                  <i class="fas fa-check-circle"></i> Finalisés<span
                    class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full ml-1"
                    >{{
                      devis.filter(
                        (d) => d.documentable?.status === "FINALIZED",
                      ).length
                    }}</span
                  >
                </button>
                <button
                  @click="changeStatusFilter('SENT')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'SENT'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-paper-plane"></i> Envoyés<span
                    class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-1"
                    >{{
                      devis.filter((d) => d.documentable?.status === "SENT")
                        .length
                    }}</span
                  >
                </button>
                <button
                  @click="changeStatusFilter('SIGNED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'SIGNED'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-file-signature"></i> Signés<span
                    class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full ml-1"
                    >{{
                      devis.filter((d) => d.documentable?.status === "SIGNED")
                        .length
                    }}</span
                  >
                </button>
                <button
                  @click="changeStatusFilter('EXPIRED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'EXPIRED'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-clock"></i> Expirés<span
                    class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full ml-1"
                    >{{
                      devis.filter((d) => d.documentable?.status === "EXPIRED")
                        .length
                    }}</span
                  >
                </button>
              </div>
              <button
                @click="createDevis"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer un Devis
              </button>
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
              <p class="mt-2 text-gray-500">Chargement des devis...</p>
            </div>
            <div
              v-else-if="filteredDevis.length === 0"
              class="text-center py-12"
            >
              <i
                class="fas fa-calculator text-5xl text-gray-300 mb-4 block"
              ></i>
              <p class="text-gray-500">
                {{
                  selectedStatus === "all"
                    ? "Aucun devis créé pour le moment."
                    : "Aucun devis avec ce statut."
                }}
              </p>
              <button
                @click="createDevis"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre premier devis
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      N° Devis
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Client
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Date
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
                    v-for="quote in filteredDevis"
                    :key="quote.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">
                        #{{ quote.number || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{
                          quote.customer.customerable
                            ? quote.customer.type === "b2b"
                              ? quote.customer.customerable.legal_name
                              : quote.customer.customerable.name
                            : quote.customer.name || "—"
                        }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">
                        {{ formatDate(quote.created_at) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">
                        {{ formatCurrency(quote.total_ttc) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span
                        :class="[
                          'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                          getStatusBadgeClass(quote.documentable?.status),
                        ]"
                        >{{ getStatusText(quote.documentable?.status) }}</span
                      >
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="previewDevis(quote.id)"
                          title="Aperçu"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button
                          @click="toggleDropdown(quote.id, $event)"
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
        class="fixed z-40 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1"
        :style="{
          top: dropdownPosition.top + 'px',
          left: dropdownPosition.left + 'px',
        }"
        @click.stop
      >
        <template v-for="quote in filteredDevis" :key="quote.id">
          <div v-if="openDropdownId === quote.id">
            <template v-if="quote.documentable?.status === 'DRAFT'">
              <button
                @click="editDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-gray-400"></i> Modifier
              </button>
              <button
                @click="finalizeDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="duplicateDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
              <button
                @click="deleteDevis(quote.id, quote.number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
            </template>
            <template v-else-if="quote.documentable?.status === 'FINALIZED'">
              <button
                @click="previewDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="openSendPage(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
                email
              </button>
              <button
                @click="signDevis(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-signature w-4 text-purple-500"></i> Signer
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="createDeliveryNoteFromQuote(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-truck w-4 text-orange-500"></i> Créer un bon de
                livraison
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="copyUrl(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-link w-4 text-gray-400"></i> Copier l'URL
              </button>
              <button
                @click="duplicateDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
            </template>
            <template v-else-if="quote.documentable?.status === 'SENT'">
              <button
                @click="previewDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="openSendPage(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
                email
              </button>
              <button
                @click="signDevis(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-signature w-4 text-purple-500"></i> Signer
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="createDeliveryNoteFromQuote(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-truck w-4 text-orange-500"></i> Créer un bon de
                livraison
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="copyUrl(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-link w-4 text-gray-400"></i> Copier l'URL
              </button>
              <button
                @click="duplicateDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
            </template>
            <template v-else-if="quote.documentable?.status === 'SIGNED'">
              <button
                @click="previewDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="openSendPage(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
                email
              </button>
              <button
                @click="downloadPdf(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="copyUrl(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-link w-4 text-gray-400"></i> Copier l'URL
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <div
                class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider"
              >
                Convertir
              </div>
              <button
                @click="convertToInvoice(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-invoice-dollar w-4 text-emerald-500"></i>
                Convertir en facture
              </button>
              <button
                @click="convertToSoldeInvoice(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-invoice w-4 text-emerald-600"></i>
                Convertir en facture de solde
              </button>
              <button
                @click="convertToPurchaseOrder(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-contract w-4 text-blue-500"></i> Convertir
                en commande
              </button>
              <button
                @click="createDeposit(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-invoice w-4 text-purple-500"></i> Créer un
                acompte
              </button>
              <button
                @click="createDeliveryNoteFromQuote(quote)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-truck w-4 text-orange-500"></i> Créer un bon de
                livraison
              </button>
            </template>
            <template v-else-if="quote.documentable?.status === 'EXPIRED'">
              <button
                @click="previewDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="downloadPdf(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="duplicateDevis(quote.id)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
            </template>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
