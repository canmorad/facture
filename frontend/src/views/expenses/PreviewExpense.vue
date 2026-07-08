<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <div v-if="isLoading" class="text-center py-12">
            <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-2 text-gray-500">Chargement...</p>
          </div>

          <div v-else>
            <div class="px-6 pt-4 pb-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <button class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]">
                  <i class="fas fa-money-bill-wave"></i>
                  Dépense {{ expense.reference || '#' + expense.id }}
                </button>
                <div class="relative">
                  <button
                    @click="toggleDropdown"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                  >
                    <i class="fas fa-ellipsis-v text-sm"></i>
                  </button>
                  <div
                    v-if="showDropdown"
                    v-click-outside="closeDropdown"
                    class="absolute right-0 top-full mt-1 z-50 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1"
                  >
                    <button
                      @click="deleteExpense(); closeDropdown()"
                      class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                    >
                      <i class="fas fa-trash-alt w-4 text-center" style="color: #ef4444"></i>
                      Supprimer
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-6 lg:p-8 space-y-5">

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-info-circle text-gray-400"></i> Informations
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Statut</span>
                    <span :class="['font-semibold', expense.status === 'paid' ? 'text-green-600' : 'text-yellow-600']">{{ expense.status === 'paid' ? 'Payé' : 'Impayé' }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Date d'émission</span>
                    <span class="text-gray-800">{{ fmtDate(expense.issue_date) }}</span>
                  </div>
                  <div v-if="expense.due_date" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Date d'échéance</span>
                    <span class="text-gray-800">{{ fmtDate(expense.due_date) }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Mode de paiement</span>
                    <span class="text-gray-800">{{ getMethodText(expense.payment_method) }}</span>
                  </div>
                  <div v-if="expense.reference" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Référence</span>
                    <span class="text-gray-800">{{ expense.reference }}</span>
                  </div>
                </div>
              </div>

              <div v-if="expense.supplier">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-truck-field text-gray-400"></i> Fournisseur
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Nom</span>
                    <span class="font-semibold text-gray-800">{{ expense.supplier.name }}</span>
                  </div>
                  <div v-if="expense.supplier.email" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Email</span>
                    <span class="text-gray-800">{{ expense.supplier.email }}</span>
                  </div>
                  <div v-if="expense.supplier.phone" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Téléphone</span>
                    <span class="text-gray-800">{{ expense.supplier.phone }}</span>
                  </div>
                  <div v-if="expense.supplier.ice" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">ICE</span>
                    <span class="text-gray-800">{{ expense.supplier.ice }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-calculator text-gray-400"></i> Montants
                </h3>
                <div class="max-w-sm bg-gray-50 rounded-lg border border-gray-100 p-4 space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Total HT</span>
                    <span class="font-medium text-gray-800">{{ fmtCurrency(expense.total_ht) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500">TVA</span>
                    <span class="font-medium text-gray-800">{{ fmtCurrency(expense.total_tva) }}</span>
                  </div>
                  <div class="flex justify-between items-center pt-1.5 border-t-2 border-[#C5F82A]">
                    <span class="text-base font-bold text-[#062121]">Total TTC</span>
                    <span class="text-base font-black text-[#062121]">{{ fmtCurrency(expense.total_ttc) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="expense.notes">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-file-alt text-gray-400"></i> Notes
                </h3>
                <div class="text-sm text-gray-700">
                  <p class="whitespace-pre-line">{{ expense.notes }}</p>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-paperclip text-gray-400"></i> Pièces jointes ({{ expense.media?.length || 0 }})
                </h3>
                <div v-if="!expense.media || expense.media.length === 0" class="text-sm text-gray-500 italic">Aucune pièce jointe</div>
                <div v-else class="grid grid-cols-2 gap-3">
                  <div v-for="media in expense.media" :key="media.id" class="bg-white border border-gray-200 rounded-lg p-3 flex items-center gap-3">
                    <div v-if="isImage(media.mime_type)" class="w-16 h-16 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                      <img :src="getFileUrl(media.file_path)" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-16 h-16 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                      <i class="fas fa-file-pdf text-2xl text-red-500"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ media.file_name }}</p>
                      <p class="text-xs text-gray-400">{{ formatFileSize(media.file_size) }}</p>
                      <a :href="media.url || getFileUrl(media.file_path)" target="_blank" class="text-xs text-blue-600 hover:underline mt-1 inline-block">
                        <i class="fas fa-external-link-alt mr-1"></i>Ouvrir
                      </a>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import axios from "axios";
import { success, error, confirm as confirmModal } from "@/helpers/notifications";

const route = useRoute();
const router = useRouter();

const expense = ref({});
const isLoading = ref(true);
const showDropdown = ref(false);

const getMethodText = (method) => {
  const texts = { virement: "Virement", cheque: "Chèque", espece: "Espèce", carte: "Carte" };
  return texts[method] || method;
};

const fmtCurrency = (n) =>
  new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0) + " DH";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", { year: "numeric", month: "short", day: "numeric" });
};

const formatFileSize = (bytes) => {
  if (!bytes) return "0 o";
  if (bytes < 1024) return bytes + " o";
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " Ko";
  return (bytes / 1048576).toFixed(1) + " Mo";
};

const getFileUrl = (path) => "/storage/" + path;

const isImage = (mime) => mime && mime.startsWith("image/");

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
  showDropdown.value = false;
};

const fetchExpense = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/expenses`);
    const found = data.find((e) => e.id == route.params.id);
    if (found) {
      expense.value = found;
    } else {
      error("Erreur", "Dépense introuvable.");
      router.push("/expenses");
    }
  } catch (err) {
    error("Erreur", "Impossible de charger la dépense.");
    router.push("/expenses");
  } finally {
    isLoading.value = false;
  }
};

const deleteExpense = async () => {
  const result = await confirmModal("Supprimer", "Supprimer cette dépense définitivement ?");
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/expenses/${expense.value.id}`);
    success("Supprimé", "La dépense a été supprimée.");
    router.push("/expenses");
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer.");
  }
};

onMounted(() => fetchExpense());
</script>