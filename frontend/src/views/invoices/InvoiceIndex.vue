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
const selectedStatus = ref('all');

const fetchInvoices = async () => {
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
    if (selectedStatus.value !== 'all') {
      params.status = selectedStatus.value;
    }

    const { data } = await axios.get("/api/invoices", { params });
    invoices.value = data;
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de charger les factures.";
    error("Erreur", message);
  } finally {
    isLoading.value = false;
  }
};

const filteredInvoices = computed(() => {
  if (selectedStatus.value === 'all') return invoices.value;
  return invoices.value.filter(inv => inv.documentable?.status === selectedStatus.value);
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
  router.push({ name: 'invoice.edit', params: { id } });
};

const previewInvoice = (id) => {
  router.push(`/document/preview/${id}`);
};

const deleteInvoice = async (id, number) => {
  const result = await confirm(
    "Supprimer la facture",
    `Supprimer la facture ${number} définitivement ?`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    await axios.delete(`/api/documents/${id}`, {
      params: { company_id: companyId },
    });
    success("Supprimé !", "La facture a été supprimée.");
    await fetchInvoices();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de supprimer la facture.";
    error("Erreur", message);
  }
};

const finalizeInvoice = async (invoice) => {
  const result = await confirm(
    "Finaliser la facture",
    `Finaliser la facture ${invoice.number} ? Le numéro sera généré automatiquement.`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.put(`/api/invoices/${invoice.id}/finalize`, null, {
      params: { company_id: companyId },
    });
    success("Finalisé !", `La facture ${data.number} a été finalisée.`);
    await fetchInvoices();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de finaliser la facture.";
    error("Erreur", message);
  }
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("fr-MA");
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";
};

const createInvoice = () => {
  router.push({ name: 'invoice.create' });
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchInvoices();
};

onMounted(() => {
  fetchInvoices();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button
                  @click="changeStatusFilter('all')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'all' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-list"></i> Tous
                  <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ invoices.length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('DRAFT')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'DRAFT' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-pen"></i> Brouillons
                  <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'DRAFT').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('SENT')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'SENT' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-paper-plane"></i> Envoyés
                  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'SENT').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('FINALIZED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'FINALIZED' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-circle"></i> Finalisés
                  <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'FINALIZED').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('PAID')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'PAID' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-double"></i> Payés
                  <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'PAID').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('OVERDUE')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'OVERDUE' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-clock"></i> En retard
                  <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'OVERDUE').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('CANCELLED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'CANCELLED' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-times-circle"></i> Annulés
                  <span class="text-xs bg-gray-300 text-gray-700 px-2 py-0.5 rounded-full">
                    {{ invoices.filter(inv => inv.documentable?.status === 'CANCELLED').length }}
                  </span>
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
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des factures...</p>
            </div>

            <div v-else-if="filteredInvoices.length === 0" class="text-center py-12">
              <i class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{ selectedStatus === 'all' ? 'Aucune facture créée pour le moment.' : 'Aucune facture avec ce statut.' }}
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Facture</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date échéance</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="invoice in filteredInvoices" :key="invoice.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">#{{ invoice.number || '—' }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ invoice.customer?.name || "Client inconnu" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                        {{ invoice.documentable?.type === 'DEPOSIT' ? 'Acompte' : 'Standard' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(invoice.documentable?.due_date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(invoice.total_ttc) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(invoice.documentable?.status)]">
                        {{ getStatusText(invoice.documentable?.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="previewInvoice(invoice.id)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="editInvoice(invoice.id)" title="Modifier" class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button 
                          v-if="invoice.documentable?.status === 'DRAFT'"
                          @click="finalizeInvoice(invoice)" 
                          title="Finaliser" 
                          class="w-8 h-8 rounded-lg text-green-500 hover:bg-green-50 hover:text-green-700 transition-all duration-200"
                        >
                          <i class="fas fa-check-circle text-sm"></i>
                        </button>
                        <button @click="deleteInvoice(invoice.id, invoice.number)" title="Supprimer" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200">
                          <i class="fas fa-trash-alt text-sm"></i>
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
  </AuthenticatedLayout>
</template>