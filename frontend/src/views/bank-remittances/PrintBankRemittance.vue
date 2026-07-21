<template>
  <div class="min-h-screen bg-white font-sans antialiased print:bg-white">
    <div class="print:hidden sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
      <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
            style="background-color: #062121"
          >
            <i class="fas fa-university"></i>
          </div>
          <span class="text-sm font-semibold text-slate-700">
            Bordereau de Remise — {{ remittance?.number || 'Brouillon' }}
          </span>
        </div>
        <button
          @click="printDocument"
          class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-95"
          style="background-color: #062121"
        >
          <i class="fas fa-print"></i>
          Imprimer / PDF
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-24">
      <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <p class="mt-2 text-gray-500">Chargement du bordereau...</p>
    </div>

    <div v-else class="max-w-4xl mx-auto my-8 print:my-0 print:max-w-none print:mx-0 print:shadow-none">
      <div
        class="bg-white shadow-xl print:shadow-none"
        style="min-height: 297mm; padding: 14mm 16mm;"
      >
        <!-- Header with Logo and Reference -->
        <div class="flex justify-between items-start mb-8">
          <div>
            <h1 class="text-xl font-bold" style="color: #062121">
              Bordereau de Remise {{ remittance?.number || 'Brouillon' }}
            </h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(remittance?.remittance_date) }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span
                class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                :style="{
                  backgroundColor: statusBadgeColor,
                  color: '#fff'
                }"
              >
                {{ statusLabel }}
              </span>
            </div>
          </div>
          <img
            v-if="company.logo"
            :src="company.logo"
            alt="Logo"
            class="h-20 w-auto object-contain"
          />
        </div>

        <div class="h-px w-full mb-6" style="background-color: #06212140"></div>

        <!-- Company Info (Issuer) -->
        <div class="grid grid-cols-2 gap-6 mb-6">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: #062121">Émetteur</p>
            <div class="space-y-0.5 text-[11px]">
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Société :</span>
                <span class="font-semibold text-gray-800">{{ company.company_name }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Adresse :</span>
                <span class="text-gray-700">{{ company.address }}<br />{{ company.postal_code }} {{ company.city }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">ICE :</span>
                <span class="font-semibold text-gray-800">{{ company.ice || '—' }}</span>
              </div>
              <div v-if="company.email" class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Email :</span>
                <span class="text-gray-700">{{ company.email }}</span>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: #062121">Banque de destination</p>
            <div class="space-y-0.5 text-[11px]" v-if="remittance?.bank_account">
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Banque :</span>
                <span class="font-semibold text-gray-800">{{ remittance.bank_account.bank_name }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Compte :</span>
                <span class="text-gray-700">{{ remittance.bank_account.label }}</span>
              </div>
              <div v-if="remittance.bank_account.rib" class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">RIB :</span>
                <span class="font-mono font-semibold tracking-wide text-gray-800">{{ remittance.bank_account.rib }}</span>
              </div>
            </div>
            <div v-else class="text-[11px] text-gray-400 italic">Aucun compte bancaire associé</div>
          </div>
        </div>

        <div v-if="remittance?.deposit_slip_reference" class="mb-6">
          <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: #062121">Détails supplémentaires</p>
          <div class="space-y-0.5 text-[11px] text-gray-700">
            <div class="grid grid-cols-[100px_1fr] gap-1">
              <span class="text-gray-500">Référence du bordereau</span>
              <span class="text-gray-800">{{ remittance.deposit_slip_reference }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Documents Table -->
        <div class="mb-6">
          <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: #062121">Documents de paiement</p>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[11px]">
              <thead>
                <tr style="background-color: #062121">
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">Type</th>
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">N° Document</th>
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">N° Facture</th>
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">Client</th>
                  <th class="py-1.5 px-2 text-right text-[10px] font-bold text-white uppercase tracking-wider">Montant</th>
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">Échéance</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(doc, i) in remittance?.payment_documents"
                  :key="i"
                  :class="i % 2 === 1 ? 'bg-gray-50' : 'bg-white'"
                  style="border-bottom: 1px solid #e5e7eb"
                >
                  <td class="py-1.5 px-2 text-gray-700">
                    <span
                      :class="[
                        'inline-flex px-2 py-0.5 text-[10px] font-semibold rounded',
                        doc.type === 'cheque' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'
                      ]"
                    >
                      {{ doc.type === 'cheque' ? 'Chèque' : 'LCN' }}
                    </span>
                  </td>
                  <td class="py-1.5 px-2 text-gray-700">{{ doc.number }}</td>
                  <td class="py-1.5 px-2 text-gray-700">{{ doc.document?.number || '—' }}</td>
                  <td class="py-1.5 px-2 text-gray-700">
                    {{
                      doc.customer?.customerable
                        ? doc.customer.type === "b2b"
                          ? doc.customer.customerable.legal_name
                          : doc.customer.customerable.name
                        : doc.customer?.name || "—"
                    }}
                  </td>
                  <td class="py-1.5 px-2 text-right font-semibold text-gray-800">{{ fmt(doc.amount) }}</td>
                  <td class="py-1.5 px-2 text-gray-700">{{ fmtDate(doc.due_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Total Section -->
        <div class="flex justify-end mb-6">
          <div class="w-2/3 sm:w-1/2 space-y-0.5 text-[11px]">
            <div class="flex justify-between items-center pt-1.5 border-t-2" style="border-color: #062121">
              <span class="text-sm font-bold" style="color: #062121">Montant Total de la Remise</span>
              <span class="text-base font-black" style="color: #062121">{{ fmt(remittance?.total_amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Bank Stamp Area -->
        <div class="mt-12 pt-8 border-t-2 border-dashed border-gray-300">
          <div class="flex justify-between items-start">
            <div class="w-1/2">
              <p class="text-xs font-bold uppercase tracking-wider mb-2 text-gray-500">Cachet de la Banque</p>
              <div class="h-24 border border-gray-300 rounded flex items-center justify-center">
                <p class="text-[10px] text-gray-400 italic">Zone réservée pour le cachet</p>
              </div>
            </div>
            <div class="w-1/3 text-right">
              <p class="text-[10px] text-gray-400">Document généré le {{ fmtDate(new Date()) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { error } from "@/helpers/notifications";

const route = useRoute();
const router = useRouter();

const remittance = ref(null);
const company = ref({});
const isLoading = ref(true);

const statusLabels = {
  DRAFT: "Brouillon",
  FINALIZED: "Finalisée",
  SENT: "Envoyée",
  DEPOSITED: "Déposée",
  RETURNED: "Rejetée",
  CANCELLED: "Annulée",
};

const statusColors = {
  DRAFT: "#94a3b8",
  FINALIZED: "#22c55e",
  SENT: "#3b82f6",
  DEPOSITED: "#8b5cf6",
  RETURNED: "#ef4444",
  CANCELLED: "#f97316",
};

const statusLabel = computed(() => statusLabels[remittance.value?.status] || remittance.value?.status || "");
const statusBadgeColor = computed(() => statusColors[remittance.value?.status] || "#94a3b8");

const fmt = (n) => new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0) + " DH";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", { year: "numeric", month: "short", day: "numeric" });
};

const printDocument = () => {
  window.print();
};

const fetchRemittance = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/bank-remittances/${route.params.id}/preview`);
    remittance.value = data.remittance;
    company.value = data.company || {};
  } catch (err) {
    error("Erreur", "Impossible de charger la remise.");
    router.push({ name: 'bank-remittance.index' });
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchRemittance();
});
</script>

<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
@media print {
  @page { size: A4; margin: 0; }
  body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>
