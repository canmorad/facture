<!-- src/views/delivery-notes/DeliveryNoteIndex.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const deliveryNotes = ref([]);
const isLoading = ref(false);
const selectedStatus = ref('all');

const fetchDeliveryNotes = async () => {
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

    const { data } = await axios.get("/api/delivery-notes", { params });
    deliveryNotes.value = data;
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de charger les bons de livraison.";
    error("Erreur", message);
  } finally {
    isLoading.value = false;
  }
};

const filteredDeliveryNotes = computed(() => {
  if (selectedStatus.value === 'all') return deliveryNotes.value;
  return deliveryNotes.value.filter(dn => dn.documentable?.status === selectedStatus.value);
});

const getStatusBadgeClass = (status) => {
  const classes = {
    DRAFT: "bg-gray-100 text-gray-700",
    FINALIZED: "bg-green-100 text-green-700",
    SENT: "bg-blue-100 text-blue-700",
    DELIVERED: "bg-purple-100 text-purple-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    DRAFT: "Brouillon",
    FINALIZED: "Finalisé",
    SENT: "Envoyé",
    DELIVERED: "Livré",
  };
  return texts[status] || status;
};

const editDeliveryNote = (id) => {
  router.push({ name: 'delivery-note.edit', params: { id } });
};

const previewDeliveryNote = (id) => {
  router.push(`/document/preview/${id}`);
};

const deleteDeliveryNote = async (id, number) => {
  const result = await confirm(
    "Supprimer le bon de livraison",
    `Supprimer le bon de livraison ${number} définitivement ?`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    await axios.delete(`/api/documents/${id}`, {
      params: { company_id: companyId },
    });
    success("Supprimé !", "Le bon de livraison a été supprimé.");
    await fetchDeliveryNotes();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de supprimer le bon de livraison.";
    error("Erreur", message);
  }
};

const finalizeDeliveryNote = async (deliveryNote) => {
  const result = await confirm(
    "Finaliser le bon de livraison",
    `Finaliser le bon de livraison ${deliveryNote.number} ? Le numéro sera généré automatiquement.`
  );
  if (!result.isConfirmed) return;

  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.put(`/api/delivery-notes/${deliveryNote.id}/finalize`, null, {
      params: { company_id: companyId },
    });
    success("Finalisé !", `Le bon de livraison ${data.number} a été finalisé.`);
    await fetchDeliveryNotes();
  } catch (err) {
    const message = err.response?.data?.error || "Impossible de finaliser le bon de livraison.";
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

const createDeliveryNote = () => {
  router.push({ name: 'delivery-note.create' });
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
  fetchDeliveryNotes();
};

onMounted(() => {
  fetchDeliveryNotes();
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
                    {{ deliveryNotes.length }}
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
                    {{ deliveryNotes.filter(dn => dn.documentable?.status === 'DRAFT').length }}
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
                    {{ deliveryNotes.filter(dn => dn.documentable?.status === 'FINALIZED').length }}
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
                    {{ deliveryNotes.filter(dn => dn.documentable?.status === 'SENT').length }}
                  </span>
                </button>
                <button
                  @click="changeStatusFilter('DELIVERED')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    selectedStatus === 'DELIVERED' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-check-double"></i> Livrés
                  <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                    {{ deliveryNotes.filter(dn => dn.documentable?.status === 'DELIVERED').length }}
                  </span>
                </button>
              </div>
              <button
                @click="createDeliveryNote"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer un Bon de Livraison
              </button>
            </div>
          </div>

          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des bons de livraison...</p>
            </div>

            <div v-else-if="filteredDeliveryNotes.length === 0" class="text-center py-12">
              <i class="fas fa-truck text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{ selectedStatus === 'all' ? 'Aucun bon de livraison créé pour le moment.' : 'Aucun bon de livraison avec ce statut.' }}
              </p>
              <button
                @click="createDeliveryNote"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre premier bon de livraison
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Bon de livraison</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Livraison prévue</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="dn in filteredDeliveryNotes" :key="dn.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">#{{ dn.number || '—' }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ dn.customer?.name || "Client inconnu" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(dn.created_at) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(dn.documentable?.delivery_date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(dn.total_ttc) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(dn.documentable?.status)]">
                        {{ getStatusText(dn.documentable?.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="previewDeliveryNote(dn.id)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="editDeliveryNote(dn.id)" title="Modifier" class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button 
                          v-if="dn.documentable?.status === 'DRAFT'"
                          @click="finalizeDeliveryNote(dn)" 
                          title="Finaliser" 
                          class="w-8 h-8 rounded-lg text-green-500 hover:bg-green-50 hover:text-green-700 transition-all duration-200"
                        >
                          <i class="fas fa-check-circle text-sm"></i>
                        </button>
                        <button @click="deleteDeliveryNote(dn.id, dn.number)" title="Supprimer" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200">
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