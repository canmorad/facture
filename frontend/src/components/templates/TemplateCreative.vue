<script setup>
defineProps({
  invoice: { type: Object, required: true },
  companySettings: { type: Object, default: null },
});

const fmt = (n) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n) + " MAD";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const lineTotal = (item) =>
  Math.round((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) * 100) / 100;

const tvaAmount = (inv) => Math.round(inv.total_ht * inv.tva_rate) / 100;
</script>

<template>
  <div class="template-creative bg-white" style="min-height: 297mm; padding: 12mm 15mm;">
    
    <!-- En-tête avec logo et infos entreprise -->
    <div class="flex justify-between items-start mb-6">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-2">
          <div v-if="companySettings?.logo" class="flex-shrink-0">
            <img :src="companySettings.logo" alt="Logo" class="h-12 w-auto object-contain" />
          </div>
          <h1 class="text-xl font-bold text-gray-800">{{ companySettings?.company_name || 'Votre Entreprise' }}</h1>
        </div>
        <div class="text-xs text-gray-500 space-y-0.5">
          <p>{{ companySettings?.address }}</p>
          <p>{{ companySettings?.city }}, {{ companySettings?.country }}</p>
          <p>Tél: {{ companySettings?.phone }} | Email: {{ companySettings?.email }}</p>
        </div>
      </div>
      <div class="text-right">
        <div class="text-2xl font-bold text-[#062121] uppercase tracking-wide">{{ invoice.type === 'invoice' ? 'FACTURE' : 'DEVIS' }}</div>
        <div class="text-lg font-semibold text-gray-600 mt-1">#{{ invoice.number }}</div>
      </div>
    </div>

    <!-- Ligne de séparation -->
    <div class="border-b border-gray-200 mb-6"></div>

    <!-- Section BILL TO + INVOICE INFO -->
    <div class="flex justify-between items-start mb-8">
      <!-- Bill To -->
      <div class="flex-1">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">BILL TO</h3>
        <p class="font-bold text-gray-800 text-base">{{ invoice.client?.name }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.address }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.city }}, {{ invoice.client?.country }}</p>
        <p class="text-sm text-gray-600">Tél: {{ invoice.client?.phone }}</p>
        <p class="text-sm text-gray-600">Email: {{ invoice.client?.email }}</p>
      </div>

      <!-- Invoice Info -->
      <div class="text-right">
        <div class="mb-3">
          <p class="text-xs text-gray-400">DATE</p>
          <p class="text-base font-semibold">{{ fmtDate(invoice.date) }}</p>
        </div>
        <div class="mb-3">
          <p class="text-xs text-gray-400">{{ invoice.type === 'invoice' ? 'DUE DATE' : 'VALID UNTIL' }}</p>
          <p class="text-base font-semibold">{{ invoice.due_date ? fmtDate(invoice.due_date) : 'À réception' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400">BALANCE DUE</p>
          <p class="text-xl font-black text-[#062121]">{{ fmt(invoice.total_ttc) }}</p>
        </div>
      </div>
    </div>

    <!-- Ligne de séparation -->
    <div class="border-b border-gray-200 mb-6"></div>

    <!-- Tableau des articles -->
    <div class="overflow-x-auto mb-8">
      <table class="w-full border-collapse">
        <thead>
          <tr class="border-b-2 border-gray-300">
            <th class="py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">DESCRIPTION</th>
            <th class="py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-24">PRIX UNIT.</th>
            <th class="py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-20">QTÉ</th>
            <th class="py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider w-32">TOTAL HT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in invoice.items" :key="i" class="border-b border-gray-100">
            <td class="py-3 text-sm text-gray-700">
              {{ item.designation }}
              <p v-if="item.description" class="text-xs text-gray-400 mt-0.5">{{ item.description }}</p>
            </td>
            <td class="py-3 text-sm text-center text-gray-600">{{ fmt(item.price) }}</td>
            <td class="py-3 text-sm text-center text-gray-600">{{ item.quantity }}</td>
            <td class="py-3 text-sm text-right font-semibold text-gray-800">{{ fmt(lineTotal(item)) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Section Totaux -->
    <div class="flex justify-end mb-8">
      <div class="w-80">
        <div class="space-y-2">
          <div class="flex justify-between py-1 text-sm">
            <span class="text-gray-500">Total HT</span>
            <span class="font-semibold">{{ fmt(invoice.total_ht) }}</span>
          </div>
          <div class="flex justify-between py-1 text-sm">
            <span class="text-gray-500">TVA ({{ invoice.tva_rate }}%)</span>
            <span class="font-semibold">{{ fmt(tvaAmount(invoice)) }}</span>
          </div>
          <div class="flex justify-between py-1 text-sm border-t border-gray-200 pt-2">
            <span class="font-bold text-gray-800">Total TTC</span>
            <span class="font-bold text-gray-800">{{ fmt(invoice.total_ttc) }}</span>
          </div>
          <div class="flex justify-between py-2 mt-2 rounded-lg px-4" style="background: #C5F82A">
            <span class="font-bold text-[#062121]">Solde dû</span>
            <span class="font-black text-[#062121] text-lg">{{ fmt(invoice.total_ttc) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature -->
    <div v-if="companySettings?.signature" class="mb-6">
      <p class="text-xs text-gray-400 mb-1">Signature</p>
      <img :src="companySettings.signature" alt="Signature" class="h-12 object-contain" />
    </div>

    <!-- Footer -->
    <div class="border-t border-gray-200 pt-4 text-center">
      <p class="text-sm text-gray-500">Merci pour votre confiance</p>
      <p class="text-xs text-gray-400 mt-1">Paiement à réception · Pénalité de retard 1,5%/mois</p>
      <p class="text-xs text-gray-400 mt-2">{{ companySettings?.company_name }} · IF {{ companySettings?.if }} · RC {{ companySettings?.rc }} · ICE {{ companySettings?.ice }}</p>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .template-creative { padding: 8mm 10mm !important; }
  @page { size: A4; margin: 0; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>