<!-- components/ThemePreview.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
  invoice: { type: Object, required: true },
  companySettings: { type: Object, default: null },
  theme: { type: Object, default: () => ({}) },
});

const fmt = (n) =>
  new Intl.NumberFormat('fr-MA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n) + ' DH';

const fmtDate = (d) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-MA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const lineTotal = (item) =>
  Math.round((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) * 100) / 100;

const tvaAmount = (inv) => Math.round(inv.total_ht * inv.tva_rate) / 100;

const styleVariables = computed(() => {
  const t = props.theme || {};
  return {
    fontFamily: t.font_family || 'Nunito',
    primaryColor: t.primary_color || '#062121',
    backgroundPattern: t.background_pattern || 'none',
    tableBorderStyle: t.table_border_style || 'sharp',
    tableLineStyle: t.table_line_style || 'standard',
  };
});
</script>

<template>
  <div
    class="bg-white font-sans text-gray-800"
    style="min-height: 500px; padding: 10mm 12mm;"
    :style="{
      fontFamily: styleVariables.fontFamily,
      backgroundColor: styleVariables.backgroundPattern === 'none' ? 'white' : '#f8fafc',
      backgroundImage: styleVariables.backgroundPattern === 'dots' ? 'radial-gradient(circle, #e2e8f0 1px, transparent 1px)' : 
                       styleVariables.backgroundPattern === 'lines' ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)' :
                       styleVariables.backgroundPattern === 'grid' ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px), repeating-linear-gradient(90deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)' :
                       'none',
      backgroundSize: styleVariables.backgroundPattern === 'dots' ? '20px 20px' : '20px 20px',
    }"
  >
    <!-- Header -->
    <div class="flex justify-between items-start border-b-2 pb-4 mb-5" :style="{ borderColor: styleVariables.primaryColor }">
      <div class="flex-1">
        <div v-if="companySettings?.logo" class="mb-3">
          <img :src="companySettings.logo" alt="Logo" class="h-12 w-auto object-contain" />
        </div>
        <h1 class="text-xl font-bold" :style="{ color: styleVariables.primaryColor }">
          {{ companySettings?.company_name || 'Votre Entreprise' }}
        </h1>
        <div class="text-xs text-gray-600 space-y-0.5 mt-1">
          <p>{{ companySettings?.address }}</p>
          <p>{{ companySettings?.city }}, {{ companySettings?.country }} {{ companySettings?.postal_code }}</p>
          <p>{{ companySettings?.phone }} | {{ companySettings?.email }}</p>
          <div class="flex flex-wrap gap-3 mt-1 text-[10px] text-gray-500">
            <span v-if="companySettings?.ice">ICE: {{ companySettings.ice }}</span>
            <span v-if="companySettings?.if">IF: {{ companySettings.if }}</span>
            <span v-if="companySettings?.rc">RC: {{ companySettings.rc }}</span>
          </div>
        </div>
      </div>
      <div class="text-right">
        <div class="text-2xl font-black uppercase" :style="{ color: styleVariables.primaryColor }">
          {{ invoice.type === 'invoice' ? 'FACTURE' : 'DEVIS' }}
        </div>
        <div class="text-lg font-bold text-gray-700">#{{ invoice.number }}</div>
      </div>
    </div>

    <!-- Émetteur / Client -->
    <div class="grid grid-cols-2 gap-6 mb-6">
      <div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Émetteur</p>
        <div class="space-y-1 text-sm text-gray-700">
          <p><span class="font-semibold">Société :</span> {{ companySettings?.company_name || 'Votre Entreprise' }}</p>
          <p><span class="font-semibold">Votre contact :</span> {{ companySettings?.contact || 'Admin' }}</p>
          <p><span class="font-semibold">Adresse :</span> {{ companySettings?.address }}</p>
          <p><span class="font-semibold">Numéro d'entreprise :</span> {{ companySettings?.ice || '—' }}</p>
          <p><span class="font-semibold">Adresse email :</span> {{ companySettings?.email }}</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Facturé à</p>
        <div class="space-y-1 text-sm text-gray-700">
          <p class="font-semibold">{{ invoice.client?.name }}</p>
          <p>{{ invoice.client?.address }}</p>
          <p>{{ invoice.client?.city }}, {{ invoice.client?.country }}</p>
          <p>{{ invoice.client?.email }}</p>
          <p>{{ invoice.client?.phone }}</p>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-200">
          <p class="text-xs text-gray-500">Date d'émission</p>
          <p class="text-sm font-semibold">{{ fmtDate(invoice.date) }}</p>
          <p v-if="invoice.due_date" class="text-xs text-gray-500 mt-1">Date d'échéance</p>
          <p v-if="invoice.due_date" class="text-sm font-semibold">{{ fmtDate(invoice.due_date) }}</p>
        </div>
      </div>
    </div>

    <!-- Tableau Détail -->
    <div class="mb-6">
      <p class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-3">Détail</p>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm" :style="{ borderCollapse: 'collapse' }">
          <thead>
            <tr :style="{ borderBottom: `2px solid ${styleVariables.primaryColor}` }">
              <th class="py-2 text-left text-[11px] font-bold text-gray-700 uppercase tracking-wider">Type</th>
              <th class="py-2 text-left text-[11px] font-bold text-gray-700 uppercase tracking-wider">Description</th>
              <th class="py-2 text-right text-[11px] font-bold text-gray-700 uppercase tracking-wider">Prix unitaire HT</th>
              <th class="py-2 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wider w-16">Quantité</th>
              <th class="py-2 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wider w-16">TVA</th>
              <th class="py-2 text-right text-[11px] font-bold text-gray-700 uppercase tracking-wider">Total HT</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, i) in invoice.items"
              :key="i"
              :style="{
                borderBottom: styleVariables.tableLineStyle === 'none' ? 'none' :
                             styleVariables.tableLineStyle === 'bold' ? '2px solid #e5e7eb' :
                             styleVariables.tableLineStyle === 'dashed' ? '1px dashed #d1d5db' :
                             '1px solid #e5e7eb'
              }"
            >
              <td class="py-3 text-sm text-gray-600">{{ item.type || 'Service' }}</td>
              <td class="py-3 text-sm text-gray-700">{{ item.designation }}</td>
              <td class="py-3 text-sm text-right text-gray-600">{{ fmt(item.price) }}</td>
              <td class="py-3 text-sm text-center text-gray-600">{{ item.quantity }}</td>
              <td class="py-3 text-sm text-center text-gray-600">{{ invoice.tva_rate }}%</td>
              <td class="py-3 text-sm text-right font-semibold text-gray-800">{{ fmt(lineTotal(item)) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Totaux -->
    <div class="flex justify-end mb-6">
      <div class="w-2/3 sm:w-1/2">
        <div class="bg-gray-50 rounded-lg p-4 space-y-2">
          <div class="flex justify-between py-1 text-sm">
            <span class="text-gray-500">Sous-total HT</span>
            <span class="font-semibold">{{ fmt(invoice.total_ht) }}</span>
          </div>
          <div class="flex justify-between py-1 text-sm border-b border-gray-200">
            <span class="text-gray-500">TVA ({{ invoice.tva_rate }}%)</span>
            <span class="font-semibold">{{ fmt(tvaAmount(invoice)) }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-base font-bold" :style="{ color: styleVariables.primaryColor }">Total TTC</span>
            <span class="text-xl font-black" :style="{ color: styleVariables.primaryColor }">{{ fmt(invoice.total_ttc) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Conditions -->
    <div class="mb-6 pt-4 border-t border-gray-200">
      <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Conditions</p>
      <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
        <div>
          <span class="font-semibold">Conditions de règlement :</span>
          <span>{{ companySettings?.payment_condition || '45 jours fin de mois' }}</span>
        </div>
        <div>
          <span class="font-semibold">Mode de règlement :</span>
          <span>{{ companySettings?.payment_mode || 'Virement bancaire' }}</span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="pt-4 border-t-2 border-gray-200 text-center">
      <p class="text-sm font-bold" :style="{ color: styleVariables.primaryColor }">
        Facture {{ invoice.number }}
      </p>
      <p class="text-[10px] text-gray-400 mt-1">Page 1 sur 1</p>
    </div>
  </div>
</template>

<style scoped>
/* All styles are dynamic via inline styles */
</style>