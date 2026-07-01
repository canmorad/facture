<!-- src/views/purchase-orders/PurchaseOrderIndex.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const purchaseOrders = ref([]);
const isLoading = ref(false);
const selectedStatus = ref('all');

const fetchPurchaseOrders = async () => {
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

    const { data } = await axios.get("/api/purchase-orders", { params });
    purchaseOrders.value = data;
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de charger les bons de commande.";
    error("Erreur", message);
  } finally {
    isLoading.value = false;
  }
};

const filteredPurchaseOrders = computed(() => {
  if (selectedStatus.value === 'all') return purchaseOrders.value;
  return purchaseOrders.value.filter(po => po.documentable?.status === selectedStatus.value);
});

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    CONFIRMED: "bg-purple-100 text-purple-700",
    CANCELLED: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    SENT: "Envoyé",
    CONFIRMED: "Confirmé",
    CANCELLED: "Annulé",
  };
  return texts[status] || status;
};

const editPurchaseOrder = (id) => {
  router.push({ name: 'purchase-order.edit', params: { id } });
};

const previewPurchaseOrder = (id) => {
  router.push(`/document/preview/${id}`);
};

const deletePurchaseOrder = async (id, number) => {
  const result = await confirm(
    "Supprimer le bon de commande",
    `Supprimer le bon de commande ${number} définitivement ?`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    await axios.delete(`/api/documents/${id}`, {
      params: { company_id: companyId },
    });
    success("Supprimé !", "Le bon de commande a été supprimé.");
    await fetchPurchaseOrders();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de supprimer le bon de commande.";
    error("Erreur", message);
  }
};

const finalizePurchaseOrder = async (purchaseOrder) => {
  const result = await confirm(
    "Finaliser le bon de commande",
    `Finaliser le bon de commande ${purchaseOrder.number} ? Le numéro sera généré automatiquement.`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.put(`/api/purchase-orders/${purchaseOrder.id}/finalize`, null, {
      params: { company_id: companyId },
    });
    success("Finalisé !", `Le bon de commande ${data.number} a été finalisé.`);
    await fetchPurchaseOrders();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de finaliser le bon de commande.";
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

const createPurchaseOrder = () => {
  router.push({ name: 'purchase-order.create' });
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchPurchaseOrders();
};

onMounted(() => {
  fetchPurchaseOrders();
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
                    {{ purchaseOrders.length }}
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
                    {{ purchaseOrders.filter(po => po.documentable?.status === 'DRAFT').length }}
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
                    {{ purchaseOrders.filter(po => po.documentable?.status === 'FINALIZED').length }}
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
                    {{ purchaseOrders.filter(po => po.documentable?.status === 'SENT').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('CONFIRMED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'CONFIRMED' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-double"></i> Confirmés
                  <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                    {{ purchaseOrders.filter(po => po.documentable?.status === 'CONFIRMED').length }}
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
                  <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">
                    {{ purchaseOrders.filter(po => po.documentable?.status === 'CANCELLED').length }}
                  </span>
                </button>
              </div>
              <button
                @click="createPurchaseOrder"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer un Bon de Commande
              </button>
            </div>
          </div>

          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des bons de commande...</p>
            </div>

            <div v-else-if="filteredPurchaseOrders.length === 0" class="text-center py-12">
              <i class="fas fa-file-purchase text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{ selectedStatus === 'all' ? 'Aucun bon de commande créé pour le moment.' : 'Aucun bon de commande avec ce statut.' }}
              </p>
              <button
                @click="createPurchaseOrder"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre premier bon de commande
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Bon de commande</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Livraison prévue</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="po in filteredPurchaseOrders" :key="po.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">#{{ po.number || '—' }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ po.customer?.name || "Client inconnu" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(po.created_at) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(po.documentable?.expected_date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(po.total_ttc) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(po.documentable?.status)]">
                        {{ getStatusText(po.documentable?.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="previewPurchaseOrder(po.id)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="editPurchaseOrder(po.id)" title="Modifier" class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button 
                          v-if="po.documentable?.status === 'DRAFT'"
                          @click="finalizePurchaseOrder(po)" 
                          title="Finaliser" 
                          class="w-8 h-8 rounded-lg text-green-500 hover:bg-green-50 hover:text-green-700 transition-all duration-200"
                        >
                          <i class="fas fa-check-circle text-sm"></i>
                        </button>
                        <button @click="deletePurchaseOrder(po.id, po.number)" title="Supprimer" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200">
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