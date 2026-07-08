<template>
  <div class="min-h-screen bg-white font-sans antialiased print:bg-white">
    <div class="print:hidden sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
      <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
            :style="{ backgroundColor: styleVariables.primaryColor }"
          >
            <i :class="docIcon"></i>
          </div>
          <span class="text-sm font-semibold text-slate-700">
            {{ docLabel }} — {{ document.number || 'Brouillon' }}
          </span>
        </div>
        <button
          @click="printDocument"
          class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-95"
          :style="{ backgroundColor: styleVariables.primaryColor }"
        >
          Imprimer / PDF
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-24">
      <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <p class="mt-2 text-gray-500">Chargement du document...</p>
    </div>

    <div v-else class="max-w-4xl mx-auto my-8 print:my-0 print:max-w-none print:mx-0 print:shadow-none">
      <div
        class="bg-white shadow-xl print:shadow-none"
        style="min-height: 297mm; padding: 14mm 16mm;"
        :style="{
          fontFamily: styleVariables.fontFamily,
          backgroundColor: styleVariables.backgroundPattern === 'none' ? 'white' : '#f8fafc',
          backgroundImage: styleVariables.backgroundPattern === 'dots'
            ? 'radial-gradient(circle, #e2e8f0 1px, transparent 1px)'
            : styleVariables.backgroundPattern === 'lines'
              ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)'
              : styleVariables.backgroundPattern === 'grid'
                ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px), repeating-linear-gradient(90deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)'
                : 'none',
          backgroundSize: styleVariables.backgroundPattern === 'dots' ? '20px 20px' : '20px 20px',
          borderRadius: theme.table_border_style === 'rounded' ? '4px' : '0px',
        }"
      >
        <div class="flex justify-between items-start mb-8">
          <div>
            <h1 class="text-xl font-bold" :style="{ color: styleVariables.primaryColor }">
              {{ docLabel }} {{ document.number || 'Brouillon' }}
            </h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(document.date) }}</p>
            <p v-if="document.due_date" class="text-xs text-gray-400 mt-0.5">
              {{ dueDateLabel }} : {{ fmtDate(document.due_date) }}
            </p>
          </div>
          <img
            v-if="company.logo_url"
            :src="company.logo_url"
            alt="Logo"
            class="h-20 w-auto object-contain"
          />
        </div>

        <div class="h-px w-full mb-6" :style="{ backgroundColor: styleVariables.primaryColor + '40' }"></div>

        <div class="grid grid-cols-2 gap-6 mb-6">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider mb-2" :style="{ color: styleVariables.primaryColor }">Émetteur</p>
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
                <span class="text-gray-500">Numéro d'entreprise :</span>
                <span class="font-semibold text-gray-800">{{ company.ice || '—' }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Adresse email :</span>
                <span class="text-gray-700">{{ company.email }}</span>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-bold uppercase tracking-wider mb-2" :style="{ color: styleVariables.primaryColor }">Destinataire</p>
            <div class="space-y-0.5 text-[11px]" v-if="document.customer">
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Nom :</span>
                <span class="font-semibold text-gray-800">{{ document.customer.name }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Adresse :</span>
                <span class="text-gray-700">{{ document.customer.address_street }}<br />{{ document.customer.postal_code }} {{ document.customer.city }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Pays :</span>
                <span class="text-gray-700">{{ document.customer.country }}</span>
              </div>
              <div class="grid grid-cols-[75px_1fr] gap-1">
                <span class="text-gray-500">Email :</span>
                <span class="text-gray-700">{{ document.customer.email }}</span>
              </div>
            </div>
            <div v-else class="text-[11px] text-gray-400 italic">Aucun client</div>
          </div>
        </div>

        <div v-if="document.intro_text" class="text-[11px] text-gray-600 mb-5 italic">{{ document.intro_text }}</div>

        <div class="mb-6">
          <p class="text-xs font-bold uppercase tracking-wider mb-2" :style="{ color: styleVariables.primaryColor }">Détail</p>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[11px]">
              <thead>
                <tr :style="{ backgroundColor: styleVariables.primaryColor }">
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">Type</th>
                  <th class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider">Description</th>
                  <th class="py-1.5 px-2 text-center text-[10px] font-bold text-white uppercase tracking-wider w-12">Qté</th>
                  <th class="py-1.5 px-2 text-right text-[10px] font-bold text-white uppercase tracking-wider">Prix unit. HT</th>
                  <th class="py-1.5 px-2 text-center text-[10px] font-bold text-white uppercase tracking-wider w-12">TVA</th>
                  <th class="py-1.5 px-2 text-right text-[10px] font-bold text-white uppercase tracking-wider">Total HT</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, i) in document.items"
                  :key="i"
                  :class="i % 2 === 1 ? 'bg-gray-50' : 'bg-white'"
                  :style="{ borderBottom: styleVariables.tableLineStyle === 'none' ? 'none' : styleVariables.tableLineStyle === 'bold' ? '2px solid #e5e7eb' : styleVariables.tableLineStyle === 'dashed' ? '1px dashed #d1d5db' : '1px solid #e5e7eb' }"
                >
                  <td class="py-1.5 px-2 text-gray-700">{{ item.product_type || 'Service' }}</td>
                  <td class="py-1.5 px-2 text-gray-700">{{ item.description }}</td>
                  <td class="py-1.5 px-2 text-center text-gray-600">{{ item.quantity }}</td>
                  <td class="py-1.5 px-2 text-right text-gray-700">{{ fmt(item.unit_price) }}</td>
                  <td class="py-1.5 px-2 text-center text-gray-600">{{ item.tax_rate }}%</td>
                  <td class="py-1.5 px-2 text-right font-semibold text-gray-800">{{ fmt(item.total_ht) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end mb-6">
          <div class="w-2/3 sm:w-1/2 space-y-0.5 text-[11px]">
            <div class="flex justify-between">
              <span class="font-semibold" :style="{ color: styleVariables.primaryColor }">Total HT</span>
              <span class="font-semibold text-gray-800">{{ fmt(document.total_ht) }}</span>
            </div>
            <div v-if="document.global_discount_amount > 0" class="flex justify-between">
              <span class="font-semibold text-red-500">Remise ({{ document.global_discount_value }}%)</span>
              <span class="font-semibold text-red-500">- {{ fmt(document.global_discount_amount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="font-semibold" :style="{ color: styleVariables.primaryColor }">TVA</span>
              <span class="font-semibold text-gray-800">{{ fmt(document.total_tva) }}</span>
            </div>
            <div class="flex justify-between items-center pt-1 border-t-2" :style="{ borderColor: styleVariables.primaryColor }">
              <span class="text-sm font-bold" :style="{ color: styleVariables.primaryColor }">Total TTC</span>
              <span class="text-base font-bold" :style="{ color: styleVariables.primaryColor }">{{ fmt(document.total_ttc) }}</span>
            </div>
          </div>
        </div>

        <div v-if="document.payment_condition || document.payment_mode" class="mb-6">
          <p class="text-xs font-bold uppercase tracking-wider mb-2" :style="{ color: styleVariables.primaryColor }">Conditions</p>
          <div class="text-[11px] text-gray-700 space-y-0.5">
            <p v-if="document.payment_condition"><span class="font-semibold" :style="{ color: styleVariables.primaryColor }">Conditions de règlement :</span> {{ document.payment_condition }}</p>
            <p v-if="document.payment_mode"><span class="font-semibold" :style="{ color: styleVariables.primaryColor }">Mode de règlement :</span> {{ document.payment_mode }}</p>
          </div>
        </div>

        <div v-if="bankAccount" class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-100">
          <p class="text-xs font-bold uppercase tracking-wider mb-2" :style="{ color: styleVariables.primaryColor }">Coordonnées bancaires</p>
          <div class="text-[11px] text-gray-700 space-y-0.5">
            <div class="flex gap-2"><span class="text-gray-500 w-20">Banque</span><span class="font-medium">{{ bankAccount.bank_name }}</span></div>
            <div class="flex gap-2"><span class="text-gray-500 w-20">RIB</span><span class="font-mono font-semibold tracking-wide">{{ bankAccount.rib }}</span></div>
          </div>
        </div>

        <div v-if="document.conclusion_text" class="text-[11px] text-gray-600 mb-5 italic">{{ document.conclusion_text }}</div>

        <div v-if="document.footer_text" class="pt-4 border-t border-gray-200 text-center">
          <p class="text-[10px] text-gray-400">{{ document.footer_text }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";
import axios from "axios";

const route = useRoute();
const authStore = useAuthStore();

const document = ref({ number: null, items: [], customer: null });
const theme = ref({ font_family: "Nunito", primary_color: "#062121", background_pattern: "none", table_border_style: "sharp", table_line_style: "standard" });
const company = ref({});
const bankAccount = ref(null);
const documentType = ref("");
const isLoading = ref(true);

const docLabels = { Quote: "DEVIS", Invoice: "FACTURE", DeliveryNote: "BON DE LIVRAISON", PurchaseOrder: "BON DE COMMANDE", Deposit: "ACOMPTE", CreditNote: "AVOIR" };
const docIcons = { Quote: "fas fa-file-signature", Invoice: "fas fa-file-invoice", DeliveryNote: "fas fa-truck", PurchaseOrder: "fas fa-shopping-cart", Deposit: "fas fa-hand-holding-usd", CreditNote: "fas fa-credit-card" };
const dueDateLabels = { Quote: "Valide jusqu'au", Invoice: "Date d'échéance", DeliveryNote: "Livraison prévue", PurchaseOrder: "Livraison prévue" };

const docLabel = computed(() => docLabels[documentType.value] || "DOCUMENT");
const docIcon = computed(() => docIcons[documentType.value] || "fas fa-file");
const dueDateLabel = computed(() => dueDateLabels[documentType.value] || "Échéance");

const styleVariables = computed(() => ({
  fontFamily: theme.value.font_family || "Nunito",
  primaryColor: theme.value.primary_color || "#062121",
  backgroundPattern: theme.value.background_pattern || "none",
  tableLineStyle: theme.value.table_line_style || "standard",
}));

const fmt = (n) => new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0) + " DH";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", { year: "numeric", month: "short", day: "numeric" });
};

const printDocument = () => {
  window.print();
};

const fetchPreview = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/documents/${route.params.id}/preview`);
    document.value = data.document;
    documentType.value = data.document_type;
    theme.value = data.theme;
    company.value = {
      company_name: data.company?.company_name || "",
      address: data.company?.address || "",
      city: data.company?.city || "",
      country: data.company?.country || "",
      postal_code: data.company?.postal_code || "",
      phone: data.company?.phone || "",
      email: data.company?.email || "",
      ice: data.company?.ice || "",
      if: data.company?.if || "",
      rc: data.company?.rc || "",
      logo_url: data.company?.logo || null,
    };
    bankAccount.value = data.bank_account ? { bank_name: data.bank_account.bank_name, rib: data.bank_account.rib } : null;
  } catch {
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => fetchPreview());
</script>

<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
@media print {
  @page { size: A4; margin: 0; }
  body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>