<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import axios from "axios";
import { success, error, confirm as confirmModal } from "@/helpers/notifications";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const invoice = ref({
  supplier_invoice_number: "",
  invoice_number: "",
  invoice_date: "",
  due_date: "",
  amount_ht: 0,
  amount_tva: 0,
  amount_ttc: 0,
  apply_withholding_tax: false,
  withholding_tax_rate: 0,
  withholding_tax_amount: 0,
  amount_after_withholding: 0,
  status: "draft",
  payment_terms: "",
  payment_mode: "",
  notes: "",
  items: [],
  fournisseur: null,
  created_at: "",
  paid_at: null,
  validated_at: null,
});
const isLoading = ref(true);
const showDropdown = ref(false);

const statusLabels = {
  draft: "Brouillon",
  validated: "Validée",
  paid: "Payée",
  overdue: "En retard",
  cancelled: "Annulée",
};

const statusColors = {
  draft: "#f59e0b",
  validated: "#3b82f6",
  paid: "#22c55e",
  overdue: "#ef4444",
  cancelled: "#6b7280",
};

const statusBadgeColors = {
  draft: "bg-yellow-100 text-yellow-700",
  validated: "bg-blue-100 text-blue-700",
  paid: "bg-green-100 text-green-700",
  overdue: "bg-red-100 text-red-700",
  cancelled: "bg-gray-100 text-gray-700",
};

const statusLabel = computed(() => statusLabels[invoice.value.status] || invoice.value.status);
const statusColor = computed(() => statusColors[invoice.value.status] || "#6b7280");
const statusBadgeColor = computed(() => statusBadgeColors[invoice.value.status] || "bg-gray-100 text-gray-700");

const fmt = (n) =>
  new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", { year: "numeric", month: "short", day: "numeric" });
};

const fetchInvoice = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/purchase-invoices/${route.params.id}`);
    invoice.value = data;
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de charger la facture d'achat.");
    router.push({ name: 'purchase-invoices' });
  } finally {
    isLoading.value = false;
  }
};

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
  showDropdown.value = false;
};

const goBack = () => {
  router.push({ name: 'purchase-invoices' });
};

const editInvoice = () => {
  closeDropdown();
  router.push({ name: 'purchase-invoices' });
};

const downloadPdf = () => {
  closeDropdown();
  // TODO: Implement PDF download when backend endpoint is available
  error("Info", "Le téléchargement PDF sera bientôt disponible.");
};

const markAsPaid = async () => {
  closeDropdown();
  const result = await confirmModal("Marquer comme payée", "Confirmer que cette facture a été payée ?");
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/purchase-invoices/${invoice.value.id}/mark-paid`);
    success("Succès", "La facture a été marquée comme payée.");
    await fetchInvoice();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de marquer la facture comme payée.");
  }
};

const deleteInvoice = async () => {
  closeDropdown();
  const result = await confirmModal("Supprimer", `Supprimer définitivement la facture "${invoice.value.supplier_invoice_number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/purchase-invoices/${invoice.value.id}`);
    success("Supprimée !", "La facture a été supprimée.");
    router.push({ name: 'purchase-invoices' });
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer la facture.");
  }
};

const actionButtons = computed(() => {
  const actions = [];

  if (invoice.value.status === 'draft') {
    actions.push({ key: 'edit', label: 'Modifier', icon: 'fas fa-edit', color: '#3b82f6', action: editInvoice });
    actions.push({ key: 'delete', label: 'Supprimer', icon: 'fas fa-trash-alt', color: '#ef4444', action: deleteInvoice });
  }

  actions.push({ key: 'download', label: 'Télécharger PDF', icon: 'fas fa-file-pdf', color: '#dc2626', action: downloadPdf });

  return actions;
});

onMounted(() => fetchInvoice());
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Loading State -->
          <div v-if="isLoading" class="text-center py-12">
            <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-2 text-gray-500">Chargement de la facture...</p>
          </div>

          <!-- Invoice Content -->
          <div v-else>
            <!-- Header -->
            <div class="px-6 pt-4 pb-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <button class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]">
                  <i class="fas fa-file-invoice-dollar"></i>
                  Facture d'achat {{ invoice.supplier_invoice_number || 'Brouillon' }}
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
                      v-for="btn in actionButtons"
                      :key="btn.key"
                      @click="btn.action(); closeDropdown()"
                      class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                    >
                      <i :class="[btn.icon, 'w-4 text-center']" :style="{ color: btn.color }"></i>
                      {{ btn.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-6 lg:p-8 space-y-5">

              <!-- Information Section -->
              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-info-circle text-gray-400"></i> Informations
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Statut</span>
                    <span class="font-semibold" :style="{ color: statusColor }">{{ statusLabel }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">N° Facture</span>
                    <span class="font-semibold text-gray-800">{{ invoice.supplier_invoice_number || '—' }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Créée le</span>
                    <span class="text-gray-800">{{ fmtDate(invoice.created_at) }}</span>
                  </div>
                  <div v-if="invoice.validated_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Validée le</span>
                    <span class="text-gray-800">{{ fmtDate(invoice.validated_at) }}</span>
                  </div>
                  <div v-if="invoice.paid_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Payée le</span>
                    <span class="text-gray-800">{{ fmtDate(invoice.paid_at) }}</span>
                  </div>
                  <div v-if="invoice.due_date" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Date d'échéance</span>
                    <span class="text-gray-800">{{ fmtDate(invoice.due_date) }}</span>
                  </div>
                </div>
              </div>

              <!-- Supplier Section -->
              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-truck text-gray-400"></i> Fournisseur
                </h3>
                <div v-if="invoice.fournisseur" class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Nom</span>
                    <span class="font-semibold text-gray-800">{{ invoice.fournisseur.name }}</span>
                  </div>
                  <div v-if="invoice.fournisseur.ice" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">ICE</span>
                    <span class="text-gray-800">{{ invoice.fournisseur.ice }}</span>
                  </div>
                  <div v-if="invoice.fournisseur.email" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Email</span>
                    <span class="text-gray-800">{{ invoice.fournisseur.email }}</span>
                  </div>
                  <div v-if="invoice.fournisseur.phone" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Téléphone</span>
                    <span class="text-gray-800">{{ invoice.fournisseur.phone }}</span>
                  </div>
                  <div v-if="invoice.fournisseur.address" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Adresse</span>
                    <span class="text-gray-800">{{ invoice.fournisseur.address }}</span>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400 italic">Aucun fournisseur</div>
              </div>

              <!-- Payment Conditions -->
              <div v-if="invoice.payment_terms || invoice.payment_mode">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-handshake text-gray-400"></i> Conditions
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div v-if="invoice.payment_terms" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Conditions de règlement</span>
                    <span class="text-gray-800">{{ invoice.payment_terms }}</span>
                  </div>
                  <div v-if="invoice.payment_mode" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Mode de règlement</span>
                    <span class="text-gray-800">{{ invoice.payment_mode }}</span>
                  </div>
                </div>
              </div>

              <!-- Items Table -->
              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-list-ul text-gray-400"></i> Détail
                </h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Désignation</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-[#062121] uppercase tracking-wider w-14">Qté</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Prix unit. HT</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-[#062121] uppercase tracking-wider w-14">TVA</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total HT</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr v-for="(item, i) in invoice.items" :key="i" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-2.5 text-gray-700">{{ item.designation }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ item.quantity }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-700">{{ fmt(item.unit_price) }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ item.tax_rate }}%</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ fmt(item.quantity * item.unit_price) }}</td>
                      </tr>
                      <tr v-if="invoice.items.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">
                          Aucun article
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Totals -->
              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-calculator text-gray-400"></i> Totaux
                </h3>
                <div class="max-w-sm bg-gray-50 rounded-lg border border-gray-100 p-4 space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Total HT</span>
                    <span class="font-medium text-gray-800">{{ fmt(invoice.amount_ht) }}</span>
                  </div>
                  <div class="flex justify-between border-t border-gray-200 pt-1.5">
                    <span class="text-gray-500">TVA</span>
                    <span class="font-medium text-gray-800">{{ fmt(invoice.amount_tva) }}</span>
                  </div>
                  <div
                    v-if="invoice.apply_withholding_tax && invoice.withholding_tax_amount > 0"
                    class="flex justify-between border-t border-gray-200 pt-1.5"
                  >
                    <span class="text-orange-500">Retenue à la source ({{ invoice.withholding_tax_rate }}%)</span>
                    <span class="font-medium text-orange-500">- {{ fmt(invoice.withholding_tax_amount) }}</span>
                  </div>
                  <div class="flex justify-between items-center pt-1.5 border-t-2 border-[#C5F82A]">
                    <span class="text-base font-bold text-[#062121]">Net à payer</span>
                    <span class="text-base font-black text-[#062121]">{{ fmt(invoice.amount_after_withholding || invoice.amount_ttc) }}</span>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div v-if="invoice.notes">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-file-alt text-gray-400"></i> Notes
                </h3>
                <div class="text-sm text-gray-700">
                  <p class="whitespace-pre-line">{{ invoice.notes }}</p>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Back Button -->
        <div class="mt-6">
          <button
            @click="goBack"
            class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all flex items-center gap-2"
          >
            <i class="fas fa-arrow-left"></i> Retour à la liste
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
