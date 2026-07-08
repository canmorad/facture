<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";

const router = useRouter();

const recurringInvoices = ref([]);
const isLoading = ref(false);
const selectedStatus = ref('all');
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const fetchRecurringInvoices = async () => {
  isLoading.value = true;
  try {
    const params = {};
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value;
    const { data } = await axios.get("/api/recurring-invoices", { params });
    recurringInvoices.value = data;
  } catch (err) {
    error("Erreur", err.response?.data?.error || "Impossible de charger les factures récurrentes.");
  } finally {
    isLoading.value = false;
  }
};

const filteredInvoices = computed(() => {
  if (selectedStatus.value === 'all') return recurringInvoices.value;
  return recurringInvoices.value.filter(inv => inv.status === selectedStatus.value);
});

const getFrequencyText = (frequency) => {
  const texts = { weekly: "Hebdomadaire", monthly: "Mensuel", quarterly: "Trimestriel", yearly: "Annuel" };
  return texts[frequency] || frequency;
};

const getStatusBadgeClass = (status) => {
  const classes = { active: "bg-green-100 text-green-700", paused: "bg-yellow-100 text-yellow-700", completed: "bg-gray-300 text-gray-700" };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = { active: "Actif", paused: "En pause", completed: "Terminé" };
  return texts[status] || status;
};

const customerName = (client) => {
  if (!client) return "—";
  return client.customerable
    ? client.type === "b2b"
      ? client.customerable.legal_name
      : client.customerable.name
    : client.name || "—";
};

const editRecurring = (id) => { closeDropdown(); router.push({ name: 'recurring-invoice.edit', params: { id } }); };

const deleteRecurring = async (id) => {
  closeDropdown();
  const result = await confirm("Supprimer le modèle", "Supprimer ce modèle récurrent définitivement ?");
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/recurring-invoices/${id}`);
    success("Supprimé !", "Le modèle récurrent a été supprimé.");
    await fetchRecurringInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer le modèle.");
  }
};

const toggleStatus = async (recurring) => {
  closeDropdown();
  const newStatus = recurring.status === 'active' ? 'paused' : 'active';
  try {
    await axios.put(`/api/recurring-invoices/${recurring.id}`, { status: newStatus });
    success("Statut mis à jour", `Le modèle est maintenant ${newStatus === 'active' ? 'actif' : 'en pause'}.`);
    await fetchRecurringInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de changer le statut.");
  }
};

const toggleDropdown = (id, event) => {
  if (openDropdownId.value === id) { closeDropdown(); return; }
  const target = event.currentTarget;
  const rect = target.getBoundingClientRect();
  const dropdownWidth = 240;
  const windowWidth = window.innerWidth;
  let left = rect.left;
  if (left + dropdownWidth > windowWidth - 10) left = windowWidth - dropdownWidth - 10;
  if (left < 10) left = 10;
  dropdownPosition.value = { top: rect.bottom + window.scrollY + 4, left: left };
  openDropdownId.value = id;
};

const closeDropdown = () => { openDropdownId.value = null; };

const formatDate = (date) => date ? new Date(date).toLocaleDateString("fr-MA") : "—";

const createRecurring = () => router.push({ name: 'recurring-invoice.create' });

const changeStatusFilter = (status) => { selectedStatus.value = status; fetchRecurringInvoices(); };

onMounted(() => fetchRecurringInvoices());
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button @click="changeStatusFilter('all')" :class="['pb-3 text-sm font-bold transition-colors flex items-center gap-2', selectedStatus === 'all' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700']">
                  <i class="fas fa-list"></i> Tous
                  <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ recurringInvoices.length }}</span>
                </button>
                <button @click="changeStatusFilter('active')" :class="['pb-3 text-sm font-bold transition-colors flex items-center gap-2', selectedStatus === 'active' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700']">
                  <i class="fas fa-play-circle"></i> Actifs
                  <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">{{ recurringInvoices.filter(inv => inv.status === 'active').length }}</span>
                </button>
                <button @click="changeStatusFilter('paused')" :class="['pb-3 text-sm font-bold transition-colors flex items-center gap-2', selectedStatus === 'paused' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700']">
                  <i class="fas fa-pause-circle"></i> En pause
                  <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">{{ recurringInvoices.filter(inv => inv.status === 'paused').length }}</span>
                </button>
                <button @click="changeStatusFilter('completed')" :class="['pb-3 text-sm font-bold transition-colors flex items-center gap-2', selectedStatus === 'completed' ? 'text-[#062121] border-b-2 border-[#C5F82A]' : 'text-gray-500 hover:text-gray-700']">
                  <i class="fas fa-check-circle"></i> Terminés
                  <span class="text-xs bg-gray-300 text-gray-700 px-2 py-0.5 rounded-full">{{ recurringInvoices.filter(inv => inv.status === 'completed').length }}</span>
                </button>
              </div>
              <button @click="createRecurring" class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2">
                <i class="fas fa-plus"></i> Créer un modèle récurrent
              </button>
            </div>
          </div>

          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des modèles récurrents...</p>
            </div>
            <div v-else-if="filteredInvoices.length === 0" class="text-center py-12">
              <i class="fas fa-rotate text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">{{ selectedStatus === 'all' ? 'Aucun modèle récurrent créé pour le moment.' : 'Aucun modèle avec ce statut.' }}</p>
              <button @click="createRecurring" class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4">
                <i class="fas fa-plus"></i> Créer votre premier modèle récurrent
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fréquence</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Prochaine exécution</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Début</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fin</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Dernière génération</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="inv in filteredInvoices" :key="inv.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap"><div class="text-sm text-gray-700">{{ customerName(inv.client) }}</div></td>
                    <td class="px-4 py-4 whitespace-nowrap"><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">{{ getFrequencyText(inv.frequency) }}</span></td>
                    <td class="px-4 py-4 whitespace-nowrap"><div class="text-sm font-semibold text-[#062121]">{{ formatDate(inv.next_run_date) }}</div></td>
                    <td class="px-4 py-4 whitespace-nowrap"><div class="text-sm text-gray-600">{{ formatDate(inv.start_date) }}</div></td>
                    <td class="px-4 py-4 whitespace-nowrap"><div class="text-sm text-gray-600">{{ inv.end_date ? formatDate(inv.end_date) : '—' }}</div></td>
                    <td class="px-4 py-4 whitespace-nowrap"><div class="text-sm text-gray-600">{{ inv.last_generated_at ? formatDate(inv.last_generated_at) : '—' }}</div></td>
                    <td class="px-4 py-4 whitespace-nowrap"><span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(inv.status)]">{{ getStatusText(inv.status) }}</span></td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="toggleDropdown(inv.id, $event)" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
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
      <div v-if="openDropdownId" class="fixed inset-0 z-30" @click.self="closeDropdown"></div>
      <div v-if="openDropdownId" class="fixed z-40 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1" :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }" @click.stop>
        <template v-for="inv in filteredInvoices" :key="inv.id">
          <div v-if="openDropdownId === inv.id">
            <button @click="editRecurring(inv.id)" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
              <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
            </button>
            <button @click="toggleStatus(inv)" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
              <i :class="inv.status === 'active' ? 'fas fa-pause-circle w-4 text-yellow-500' : 'fas fa-play-circle w-4 text-green-500'"></i>
              {{ inv.status === 'active' ? 'Mettre en pause' : 'Activer' }}
            </button>
            <div class="border-t border-gray-100 my-1"></div>
            <button @click="deleteRecurring(inv.id)" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3">
              <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
            </button>
          </div>
        </template>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>