<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();
const invoices = ref([]);
const isLoading = ref(false);

const fetchInvoices = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/invoices");
    invoices.value = data.filter((invoice) => invoice.type === "invoice");
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les factures.",
      icon: "error",
      confirmButtonColor: "#062121",
    });
  } finally {
    isLoading.value = false;
  }
};

const getStatusBadgeClass = (status) => {
  const classes = {
    draft: "bg-gray-100 text-gray-700",
    sent: "bg-blue-100 text-blue-700",
    paid: "bg-green-100 text-green-700",
    overdue: "bg-red-100 text-red-700",
    cancelled: "bg-gray-100 text-gray-500",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    draft: "Brouillon",
    sent: "Envoyée",
    paid: "Payée",
    overdue: "En retard",
    cancelled: "Annulée",
  };
  return texts[status] || status;
};

const editInvoice = (id) => {
  router.push(`/document/edit/${id}?type=invoice`);
};

const previewInvoice = (id) => {
  router.push(`/document/preview/${id}`);
};

const deleteInvoice = async (id, number) => {
  const result = await Swal.fire({
    title: "Êtes-vous sûr ?",
    text: `Supprimer la facture ${number} définitivement ?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
  });

  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/invoices/${id}`);
    Swal.fire("Supprimée !", "La facture a été supprimée.", "success");
    await fetchInvoices();
  } catch {
    Swal.fire("Erreur", "Impossible de supprimer la facture.", "error");
  }
};

const generateDeliveryNote = (id) => {
  router.push(`/deliveries/create?invoice_id=${id}`);
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

const createInvoice = () => {
  router.push("/document/create?type=invoice");
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
            <div class="flex items-center justify-between">
              <div class="flex gap-6">
                <div class="pb-3 text-sm font-bold text-[#062121] border-b-2 border-[#C5F82A] flex items-center gap-2">
                  <i class="fas fa-file-invoice"></i>
                  Factures
                  <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ invoices.length }}
                  </span>
                </div>
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

            <div v-else-if="invoices.length === 0" class="text-center py-12">
              <i class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucune facture créée pour le moment.</p>
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date d'émission</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="invoice in invoices" :key="invoice.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">#{{ invoice.number }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ invoice.client?.name || "Client inconnu" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(invoice.date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(invoice.total_ttc) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(invoice.status)]">
                        {{ getStatusText(invoice.status) }}
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
                        <button @click="generateDeliveryNote(invoice.id)" title="Générer BL" class="w-8 h-8 rounded-lg text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700 transition-all duration-200">
                          <i class="fas fa-truck text-sm"></i>
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