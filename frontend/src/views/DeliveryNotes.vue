<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();
const deliveries = ref([]);
const isLoading = ref(false);

const fetchDeliveries = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/deliveries");
    deliveries.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les bons de livraison.",
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
    pending: "bg-blue-100 text-blue-700",
    shipped: "bg-indigo-100 text-indigo-700",
    delivered: "bg-green-100 text-green-700",
    cancelled: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    draft: "Brouillon",
    pending: "En préparation",
    shipped: "Expédié",
    delivered: "Livré",
    cancelled: "Annulé",
  };
  return texts[status] || status;
};

const editDelivery = (id) => {
  router.push(`/document/edit/${id}?type=delivery`);
};

const previewDelivery = (id) => {
  router.push(`/delivery/preview/${id}`);
};

const deleteDelivery = async (id, number) => {
  const result = await Swal.fire({
    title: "Êtes-vous sûr ?",
    text: `Supprimer le bon de livraison ${number} définitivement ?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
  });

  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/deliveries/${id}`);
    Swal.fire("Supprimé !", "Le bon de livraison a été supprimé.", "success");
    await fetchDeliveries();
  } catch {
    Swal.fire("Erreur", "Impossible de supprimer le bon de livraison.", "error");
  }
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("fr-MA");
};

const createDelivery = () => {
  router.push("/deliveries/create");
};

onMounted(() => {
  fetchDeliveries();
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
                  <i class="fas fa-truck"></i>
                  Bons de livraison
                  <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ deliveries.length }}
                  </span>
                </div>
              </div>
              <button
                @click="createDelivery"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i>
                Créer un bon de livraison
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

            <div v-else-if="deliveries.length === 0" class="text-center py-12">
              <i class="fas fa-truck text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun bon de livraison créé pour le moment.</p>
              <button
                @click="createDelivery"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre premier bon de livraison
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° BL</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date de livraison</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Transporteur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="delivery in deliveries" :key="delivery.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">{{ delivery.number }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ delivery.client?.name || "Client inconnu" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(delivery.date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ delivery.transporteur || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(delivery.status)]">
                        {{ getStatusText(delivery.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="previewDelivery (delivery.id)"
                          title="Aperçu"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button
                          @click="editDelivery(delivery.id)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteDelivery(delivery.id, delivery.number)"
                          title="Supprimer"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200"
                        >
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