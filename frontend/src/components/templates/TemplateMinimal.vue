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
  <div class="template-minimal bg-white font-sans text-gray-800" style="min-height: 297mm; padding: 15mm 18mm;">
    
    <!-- En-tête minimal -->
    <div class="flex justify-between items-start mb-12 pb-6 border-b border-gray-300">
      <div>
        <!-- Logo -->
        <div v-if="companySettings?.logo" class="mb-3">
          <img :src="companySettings.logo" alt="Company Logo" class="h-12 w-auto object-contain" />
        </div>
        <h1 class="text-2xl font-light text-gray-700 mb-1">{{ companySettings?.company_name || 'Votre Entreprise' }}</h1>
        <div class="text-xs text-gray-400 space-y-0.5">
          <p>{{ companySettings?.address }}, {{ companySettings?.city }}</p>
          <p>{{ companySettings?.phone }} | {{ companySettings?.email }}</p>
        </div>
      </div>
      <div class="text-right">
        <div class="text-lg font-light text-gray-500 mb-1">{{ invoice.type === 'invoice' ? 'INVOICE' : 'ESTIMATE' }}</div>
        <div class="text-2xl font-bold text-gray-800">#{{ invoice.number }}</div>
      </div>
    </div>

    <!-- Client et dates -->
    <div class="grid grid-cols-2 gap-12 mb-12">
      <div>
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Bill To</p>
        <p class="font-semibold text-gray-800">{{ invoice.client?.name }}</p>
        <p class="text-sm text-gray-500">{{ invoice.client?.address }}</p>
        <p class="text-sm text-gray-500">{{ invoice.client?.city }}, {{ invoice.client?.country }}</p>
        <p class="text-sm text-gray-500">{{ invoice.client?.email }}</p>
      </div>
      <div class="text-right space-y-2">
        <div>
          <p class="text-xs text-gray-400">Issue Date</p>
          <p class="text-sm font-medium">{{ fmtDate(invoice.date) }}</p>
        </div>
        <div v-if="invoice.due_date">
          <p class="text-xs text-gray-400">{{ invoice.type === 'invoice' ? 'Due Date' : 'Valid Until' }}</p>
          <p class="text-sm font-medium">{{ fmtDate(invoice.due_date) }}</p>
        </div>
      </div>
    </div>

    <!-- Tableau -->
    <table class="w-full mb-10">
      <thead>
        <tr class="border-b border-gray-300">
          <th class="py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
          <th class="py-3 text-center text-xs font-medium text-gray-500 uppercase w-20">Qty</th>
          <th class="py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Unit Price</th>
          <th class="py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, i) in invoice.items" :key="i" class="border-b border-gray-100">
          <td class="py-4 text-sm text-gray-700">{{ item.designation }}</td>
          <td class="py-4 text-sm text-center text-gray-600">{{ item.quantity }}</td>
          <td class="py-4 text-sm text-right text-gray-600">{{ fmt(item.price) }}</td>
          <td class="py-4 text-sm text-right font-medium text-gray-800">{{ fmt(lineTotal(item)) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Signature -->
    <div class="flex justify-start mb-8" v-if="companySettings?.signature">
      <div>
        <p class="text-xs text-gray-400 mb-1">Signature</p>
        <img :src="companySettings.signature" alt="Signature" class="h-14 object-contain border rounded p-1 bg-white" />
      </div>
    </div>

    <!-- Totaux -->
    <div class="flex justify-end mb-10">
      <div class="w-80">
        <div class="flex justify-between py-2 text-sm">
          <span class="text-gray-500">Subtotal</span>
          <span>{{ fmt(invoice.total_ht) }}</span>
        </div>
        <div class="flex justify-between py-2 text-sm text-gray-500 border-b border-gray-200">
          <span>Tax ({{ invoice.tva_rate }}%)</span>
          <span>{{ fmt(tvaAmount(invoice)) }}</span>
        </div>
        <div class="flex justify-between py-3 mt-1">
          <span class="text-base font-bold text-gray-800">Total</span>
          <span class="text-xl font-bold text-gray-800">{{ fmt(invoice.total_ttc) }}</span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t border-gray-200 pt-6 text-center">
      <p class="text-xs text-gray-400">Thank you for your business!</p>
      <p class="text-xs text-gray-400 mt-1">{{ companySettings?.company_name }} · {{ companySettings?.if }} · {{ companySettings?.rc }}</p>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .template-minimal { padding: 10mm 12mm !important; }
  @page { size: A4; margin: 0; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>