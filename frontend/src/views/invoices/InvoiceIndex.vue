<!-- src/views/invoices/InvoiceIndex.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const invoices = ref([]);
const isLoading = ref(false);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const fetchInvoices = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (selectedStatus.value !== "all") params.status = selectedStatus.value;
    const { data } = await axios.get("/api/invoices", { params });
    invoices.value = data;
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de charger les factures.",
    );
  } finally {
    isLoading.value = false;
  }
};

const filteredInvoices = computed(() => {
  if (selectedStatus.value === "all") return invoices.value;
  return invoices.value.filter(
    (inv) => inv.documentable?.status === selectedStatus.value,
  );
});

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    SENT: "bg-blue-100 text-blue-700",
    FINALIZED: "bg-green-100 text-green-700",
    PAID: "bg-purple-100 text-purple-700",
    OVERDUE: "bg-red-100 text-red-700",
    CANCELLED: "bg-gray-300 text-gray-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    SENT: "Envoyé",
    FINALIZED: "Finalisé",
    PAID: "Payé",
    OVERDUE: "En retard",
    CANCELLED: "Annulé",
  };
  return texts[status] || status;
};

const editInvoice = (id) => {
  closeDropdown();
  router.push({ name: "invoice.edit", params: { id } });
};
const previewInvoice = (id) => {
  closeDropdown();
  router.push(`/document/preview/${id}`);
};
const downloadInvoicePdf = (id) => {
  closeDropdown();
  window.open(`/document/print/${id}`, "_blank");
};

const deleteInvoice = async (id, number) => {
  closeDropdown();
  const result = await confirm(
    "Supprimer la facture",
    `Supprimer la facture ${number} définitivement ?`,
  );
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/documents/${id}`);
    success("Supprimé !", "La facture a été supprimée.");
    await fetchInvoices();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de supprimer la facture.",
    );
  }
};

const finalizeInvoice = async (invoice) => {
  closeDropdown();
  const result = await confirm(
    "Finaliser la facture",
    `Finaliser la facture ${invoice.number} ? Le numéro sera généré automatiquement.`,
  );
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.put(`/api/invoices/${invoice.id}/finalize`);
    success("Finalisé !", `La facture ${data.number} a été finalisée.`);
    await fetchInvoices();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.error || "Impossible de finaliser la facture.",
    );
  }
};

const sendInvoice = async (invoice) => {
  closeDropdown();
  try {
    await axios.put(`/api/invoices/${invoice.id}/send`);
    success("Envoyé !", `La facture ${invoice.number} a été envoyée.`);
    await fetchInvoices();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'envoyer la facture.",
    );
  }
};

const markInvoicePaid = async (id) => {
  closeDropdown();
  const result = await confirm("Marquer payé", "Confirmer le paiement ?");
  if (!result.isConfirmed) return;
  try {
    await axios.put(`/api/invoices/${id}/mark-paid`);
    success("Payé !", "La facture a été marquée comme payée.");
    await fetchInvoices();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de marquer comme payée.",
    );
  }
};

const cancelInvoice = async (id) => {
  closeDropdown();
  const result = await confirm("Annuler", "Confirmer l'annulation ?");
  if (!result.isConfirmed) return;
  try {
    await axios.put(`/api/invoices/${id}/cancel`);
    success("Annulé !", "La facture a été annulée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'annuler.");
  }
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
  if (left + dropdownWidth > windowWidth - 10)
    left = windowWidth - dropdownWidth - 10;
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

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";
const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";
const openSendPage = (inv) => {
  closeDropdown();
  router.push({
    name: "document.send",
    params: { id: inv.id },
    query: { type: "invoice", page: "invoice" },
  });
};
const createInvoice = () => router.push({ name: "invoice.create" });
const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchInvoices();
};

onMounted(() => fetchInvoices());
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
                    >{{ invoices.length }}</span
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
                  <i class="fas fa-pen"></i> Brouillons
                  <span
                    class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "DRAFT",
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
                  <i class="fas fa-paper-plane"></i> Envoyés
                  <span
                    class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "SENT",
                      ).length
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
                  <i class="fas fa-check-circle"></i> Finalisés
                  <span
                    class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "FINALIZED",
                      ).length
                    }}</span
                  >
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
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "PAID",
                      ).length
                    }}</span
                  >
                </button>
                <button
                  @click="changeStatusFilter('OVERDUE')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'OVERDUE'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-clock"></i> En retard
                  <span
                    class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full"
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "OVERDUE",
                      ).length
                    }}</span
                  >
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
                    class="text-xs bg-gray-300 text-gray-700 px-2 py-0.5 rounded-full"
                    >{{
                      invoices.filter(
                        (inv) => inv.documentable?.status === "CANCELLED",
                      ).length
                    }}</span
                  >
                </button>
              </div>
              <button
                @click="createInvoice"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une Facture
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
              <p class="mt-2 text-gray-500">Chargement des factures...</p>
            </div>
            <div
              v-else-if="filteredInvoices.length === 0"
              class="text-center py-12"
            >
              <i
                class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"
              ></i>
              <p class="text-gray-500">
                {{
                  selectedStatus === "all"
                    ? "Aucune facture créée pour le moment."
                    : "Aucune facture avec ce statut."
                }}
              </p>
              <button
                @click="createInvoice"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre première facture
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      N° Facture
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Client
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Type
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Date échéance
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
                    v-for="invoice in filteredInvoices"
                    :key="invoice.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">
                        #{{ invoice.number || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">
                        {{
                              invoice.customer?.customerable
                                ? invoice.customer.type === "b2b"
                                  ? invoice.customer.customerable.legal_name
                                  : invoice.customer.customerable.name
                                : invoice.customer?.name || "—"
                            }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span
                        class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800"
                        >{{
                          invoice.documentable?.type === "ACOMPTE"
                            ? "Acompte"
                            : "Standard"
                        }}</span
                      >
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">
                        {{ formatDate(invoice.documentable?.due_date) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">
                        {{ formatCurrency(invoice.total_ttc) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span
                        :class="[
                          'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                          getStatusBadgeClass(invoice.documentable?.status),
                        ]"
                        >{{ getStatusText(invoice.documentable?.status) }}</span
                      >
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="previewInvoice(invoice.id)"
                          title="Aperçu"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button
                          @click="toggleDropdown(invoice.id, $event)"
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
        <template v-for="inv in filteredInvoices" :key="inv.id">
          <div v-if="openDropdownId === inv.id">
            <button
              @click="editInvoice(inv.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
            </button>
            <button
              v-if="inv.documentable?.status === 'DRAFT'"
              @click="finalizeInvoice(inv)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-check-circle w-4 text-green-500"></i> Finaliser
            </button>
            <button
              v-if="
                inv.documentable?.status === 'FINALIZED' ||
                inv.documentable?.status === 'SENT' ||
                inv.documentable?.status === 'PAID'
              "
              @click="openSendPage(inv)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-envelope w-4 text-blue-500"></i> Envoyer par
              email
            </button>
            <button
              v-if="
                inv.documentable?.status === 'FINALIZED' ||
                inv.documentable?.status === 'SENT'
              "
              @click="markInvoicePaid(inv.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-check-double w-4 text-green-500"></i> Marquer
              payé
            </button>
            <button
              v-if="
                inv.documentable?.status === 'SENT' ||
                inv.documentable?.status === 'OVERDUE'
              "
              @click="cancelInvoice(inv.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-times-circle w-4 text-orange-500"></i> Annuler
            </button>
            <div class="border-t border-gray-100 my-1"></div>
            <button
              @click="downloadInvoicePdf(inv.id)"
              class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
            >
              <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
            </button>
            <button
              @click="deleteInvoice(inv.id, inv.number)"
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
