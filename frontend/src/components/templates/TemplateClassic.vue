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
  <div class="template-classic bg-white font-sans text-gray-800" style="min-height: 297mm; padding: 15mm 18mm;">
    
    <!-- Header avec logo et infos entreprise -->
    <div class="flex justify-between items-start mb-8">
      <div class="flex-1">
        <!-- Logo -->
        <div v-if="companySettings?.logo" class="mb-4">
          <img :src="companySettings.logo" alt="Company Logo" class="h-16 w-auto object-contain" />
        </div>
        <h1 class="text-3xl font-black text-[#062121] mb-2">{{ companySettings?.company_name || 'Votre Entreprise' }}</h1>
        <div class="text-sm text-gray-600 space-y-0.5">
          <p>{{ companySettings?.address }}</p>
          <p>{{ companySettings?.city }}, {{ companySettings?.country }} {{ companySettings?.postal_code }}</p>
          <p>{{ companySettings?.phone }} | {{ companySettings?.email }}</p>
          <div class="flex gap-4 mt-2 text-xs text-gray-500">
            <span v-if="companySettings?.ice">ICE: {{ companySettings.ice }}</span>
            <span v-if="companySettings?.if">IF: {{ companySettings.if }}</span>
            <span v-if="companySettings?.rc">RC: {{ companySettings.rc }}</span>
          </div>
        </div>
      </div>
      <div class="text-right">
        <div class="text-4xl font-black text-[#062121] uppercase tracking-wider mb-2">{{ invoice.type === 'invoice' ? 'FACTURE' : 'DEVIS' }}</div>
        <div class="text-2xl font-bold text-gray-700">#{{ invoice.number }}</div>
      </div>
    </div>

    <!-- Bill To et Dates -->
    <div class="grid grid-cols-2 gap-8 mb-10 pb-6 border-b-2 border-gray-200">
      <div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Facturé à</p>
        <p class="font-bold text-gray-800 text-lg">{{ invoice.client?.name }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.address }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.city }}, {{ invoice.client?.country }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.email }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.phone }}</p>
      </div>
      <div class="text-right">
        <div class="mb-3">
          <p class="text-xs text-gray-500">Date d'émission</p>
          <p class="text-base font-semibold">{{ fmtDate(invoice.date) }}</p>
        </div>
        <div v-if="invoice.due_date" class="mb-3">
          <p class="text-xs text-gray-500">{{ invoice.type === 'invoice' ? "Date d'échéance" : 'Validité' }}</p>
          <p class="text-base font-semibold">{{ fmtDate(invoice.due_date) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Statut</p>
          <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold mt-1" 
            :class="invoice.status === 'paid' ? 'bg-green-100 text-green-700' :
                     invoice.status === 'sent' ? 'bg-blue-100 text-blue-700' :
                     invoice.status === 'overdue' ? 'bg-red-100 text-red-700' :
                     'bg-gray-100 text-gray-600'">
            {{ invoice.status === 'paid' ? 'Payée' :
               invoice.status === 'sent' ? 'Envoyée' :
               invoice.status === 'overdue' ? 'En retard' : 'Brouillon' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Tableau des articles -->
    <table class="w-full mb-8 border-collapse">
      <thead>
        <tr class="border-b-2 border-[#062121]">
          <th class="py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Description</th>
          <th class="py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-20">Qté</th>
          <th class="py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider w-32">Prix unitaire</th>
          <th class="py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider w-32">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, i) in invoice.items" :key="i" class="border-b border-gray-100">
          <td class="py-4 text-sm text-gray-700">
            {{ item.designation }}
            <p v-if="item.description" class="text-xs text-gray-400 mt-1">{{ item.description }}</p>
          </td>
          <td class="py-4 text-sm text-center text-gray-600">{{ item.quantity }}</td>
          <td class="py-4 text-sm text-right text-gray-600">{{ fmt(item.price) }}</td>
          <td class="py-4 text-sm text-right font-semibold text-gray-800">{{ fmt(lineTotal(item)) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Totaux et informations de paiement -->
    <div class="grid grid-cols-2 gap-8 mb-8">
      <div>
        <div v-if="companySettings?.signature" class="mb-6">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Signature</p>
          <img :src="companySettings.signature" alt="Signature" class="h-16 object-contain border rounded-lg p-1 bg-white" />
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Instructions de paiement</p>
          <p class="text-sm text-gray-600">Tous les moyens de paiement sont acceptés. Virement bancaire, chèque ou espèces.</p>
          <p v-if="companySettings?.rib" class="text-sm text-gray-600 mt-2">
            <span class="font-semibold">RIB:</span> {{ companySettings.rib }}
          </p>
        </div>
      </div>
      
      <div>
        <div class="bg-gray-50 rounded-lg p-5 space-y-3">
          <div class="flex justify-between py-2 text-sm">
            <span class="text-gray-500">Sous-total HT</span>
            <span class="font-semibold">{{ fmt(invoice.total_ht) }}</span>
          </div>
          <div class="flex justify-between py-2 text-sm border-b border-gray-200">
            <span class="text-gray-500">TVA ({{ invoice.tva_rate }}%)</span>
            <span class="font-semibold">{{ fmt(tvaAmount(invoice)) }}</span>
          </div>
          <div class="flex justify-between items-center pt-3">
            <span class="text-lg font-bold text-[#062121] uppercase">Total TTC</span>
            <span class="text-2xl font-black text-[#062121]">{{ fmt(invoice.total_ttc) }}</span>
          </div>
          <div class="flex justify-between pt-3 mt-2 border-t-2 border-[#062121]">
            <span class="text-sm font-bold text-gray-700 uppercase">Solde dû</span>
            <span class="text-xl font-black text-[#062121]">{{ fmt(invoice.total_ttc) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="pt-6 border-t border-gray-200 text-center">
      <p class="text-xs text-gray-400">Merci pour votre confiance !</p>
      <p class="text-xs text-gray-400 mt-1">Ce document fait office de facture originale.</p>
    </footer>
  </div>
</template>

<style scoped>
@media print {
  .template-classic { padding: 10mm 12mm !important; }
  @page { size: A4; margin: 0; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>