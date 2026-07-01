<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import Swal from "sweetalert2";

const route = useRoute();
const router = useRouter();
const delivery = ref(null);
const companySettings = ref(null);
const isLoading = ref(false);

const deliveryId = computed(() => route.params.id ? parseInt(route.params.id) : null);

const fetchDelivery = async () => {
  if (!deliveryId.value) {
    router.push("/deliveries");
    return;
  }
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/deliveries/${deliveryId.value}`);
    delivery.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger le bon de livraison.",
      icon: "error",
      confirmButtonColor: "#062121",
    });
    router.push("/deliveries");
  } finally {
    isLoading.value = false;
  }
};

const fetchCompanySettings = async () => {
  try {
    const { data } = await axios.get("/api/company-settings");
    companySettings.value = data;
  } catch {
    companySettings.value = null;
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const getStatusBadgeClass = (status) => {
  // "draft" n’affiche pas de badge (retourne une classe invisible)
  if (status === 'draft') return "hidden";
  const classes = {
    pending: "bg-blue-100 text-blue-700",
    shipped: "bg-indigo-100 text-indigo-700",
    delivered: "bg-green-100 text-green-700",
    cancelled: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  if (status === 'draft') return "";
  const texts = {
    pending: "En préparation",
    shipped: "Expédié",
    delivered: "Livré",
    cancelled: "Annulé",
  };
  return texts[status] || status;
};

const printDocument = () => window.print();

onMounted(() => {
  Promise.all([fetchDelivery(), fetchCompanySettings()]);
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-100 min-h-screen">
      
      <!-- Toolbar (masquée à l'impression) -->
      <div class="print:hidden sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between gap-4 flex-wrap">
          <div class="flex items-center gap-3">
            <button
              @click="router.back()"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"
            >
              <i class="fas fa-arrow-left text-xs"></i> Retour
            </button>
            <div v-if="delivery" class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-800">{{ delivery.number }}</span>
              <span
                v-if="delivery.status && delivery.status !== 'draft'"
                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                :class="getStatusBadgeClass(delivery.status)"
              >
                {{ getStatusText(delivery.status) }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="printDocument"
              class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-95"
              style="background-color: #062121"
            >
              <i class="fas fa-print text-xs"></i> Imprimer / PDF
            </button>
          </div>
        </div>
      </div>

      <!-- Chargement -->
      <div v-if="isLoading" class="flex items-center justify-center py-24">
        <div class="text-center">
          <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <p class="mt-3 text-sm text-gray-500">Chargement du bon de livraison...</p>
        </div>
      </div>

      <!-- Document preview -->
      <div v-else-if="delivery" class="max-w-4xl mx-auto my-8 print:my-0 print:max-w-none">
        <div class="bg-white shadow-xl print:shadow-none rounded-2xl print:rounded-none overflow-hidden">
          <div class="p-8 lg:p-12">
            
            <!-- En-tête entreprise + document -->
            <div class="flex justify-between items-start mb-10 pb-6 border-b border-gray-200">
              <!-- Colonne gauche : logo + infos entreprise -->
              <div class="flex gap-5">
                <div class="flex-shrink-0">
                  <div v-if="companySettings?.logo" class="w-20 h-20">
                    <img :src="companySettings.logo" alt="Logo" class="w-full h-full object-contain" />
                  </div>
                  <div v-else class="w-20 h-20 rounded-xl bg-gray-100 flex items-center justify-center border border-gray-200">
                    <i class="fas fa-building text-gray-400 text-3xl"></i>
                  </div>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-gray-800">{{ companySettings?.company_name || 'Votre entreprise' }}</h2>
                  <div class="text-xs text-gray-500 space-y-0.5 mt-1">
                    <p>{{ companySettings?.address }}</p>
                    <p>{{ companySettings?.city }}, {{ companySettings?.country }} - {{ companySettings?.postal_code }}</p>
                    <p>Tél : {{ companySettings?.phone }} | Email : {{ companySettings?.email }}</p>
                    <div class="flex gap-3 mt-1 flex-wrap text-gray-400">
                      <span v-if="companySettings?.ice">ICE : {{ companySettings.ice }}</span>
                      <span v-if="companySettings?.if">IF : {{ companySettings.if }}</span>
                      <span v-if="companySettings?.rc">RC : {{ companySettings.rc }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Colonne droite : titre et statut -->
              <div class="text-right">
                <h1 class="text-2xl font-black text-[#062121]">BON DE LIVRAISON</h1>
                <p class="text-sm text-gray-500 mt-1">N° {{ delivery.number }}</p>
                <div
                  v-if="delivery.status && delivery.status !== 'draft'"
                  class="mt-2 inline-block px-4 py-1.5 rounded-full text-sm font-black uppercase tracking-widest text-white"
                  style="background-color: #062121"
                >
                  {{ getStatusText(delivery.status) }}
                </div>
              </div>
            </div>

            <!-- Informations générales (client + logistique) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
              <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Destinataire</h3>
                <p class="font-bold text-gray-800 text-lg">{{ delivery.client?.name }}</p>
                <p class="text-sm text-gray-600">{{ delivery.client?.address }}</p>
                <p class="text-sm text-gray-600">{{ delivery.client?.city }}, {{ delivery.client?.country }}</p>
                <p class="text-sm text-gray-600">{{ delivery.client?.email }}</p>
                <p class="text-sm text-gray-600">{{ delivery.client?.phone }}</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 text-right">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Informations de livraison</h3>
                <div class="space-y-3">
                  <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Date de livraison</p>
                    <p class="text-base font-semibold">{{ formatDate(delivery.date) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Transporteur / Chauffeur</p>
                    <p class="text-base font-semibold">{{ delivery.transporteur || "—" }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">N° de suivi / Matricule</p>
                    <p class="text-base font-semibold font-mono">{{ delivery.tracking_number || "—" }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tableau des articles (style premium) -->
            <div class="mb-12">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-boxes"></i> Articles à livrer
              </h3>
              <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full">
                  <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Produit</th>
                      <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-32">Quantité</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(item, i) in delivery.items" :key="i" class="hover:bg-gray-50 transition-colors">
                      <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                          <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-box text-gray-500 text-sm"></i>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900">{{ item.product?.name || "Produit inconnu" }}</div>
                            <div class="text-xs text-gray-400">Réf: {{ item.product?.sku || "—" }}</div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 text-center text-sm font-semibold text-gray-800">
                        {{ item.quantity }} unité(s)
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Pied de page -->
            <div class="border-t border-gray-200 pt-6 text-center text-sm text-gray-500">
              <p>Merci pour votre confiance</p>
              <p class="text-xs text-gray-400 mt-2">Ce document atteste de la livraison des marchandises – sans valeur financière.</p>
              <div class="mt-4 pt-4 border-t border-gray-100 text-[10px] text-gray-400">
                {{ companySettings?.company_name }} · IF {{ companySettings?.if }} · RC {{ companySettings?.rc }} · ICE {{ companySettings?.ice }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@media print {
  .print\:hidden {
    display: none !important;
  }
  @page {
    size: A4;
    margin: 1.5cm;
  }
  body {
    background-color: white;
  }
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .bg-gray-50, .bg-white, .bg-gray-100 {
    background-color: white !important;
    box-shadow: none !important;
    border-color: #e5e7eb !important;
  }
  .shadow-xl, .shadow-sm {
    box-shadow: none !important;
  }
  .rounded-xl, .rounded-2xl, .rounded-lg {
    border-radius: 0 !important;
  }
}
</style>