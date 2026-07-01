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
    month: "long",
    day: "numeric",
  });
};

const lineTotal = (item) =>
  Math.round((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) * 100) / 100;

const tvaAmount = (inv) => Math.round(inv.total_ht * inv.tva_rate) / 100;
</script>

<template>
  <div class="template-modern bg-white" style="min-height: 297mm; padding: 15mm 18mm; font-family: 'Inter', sans-serif;">
    
    <!-- En-tête avec bordure et logo -->
    <div class="flex justify-between items-start pb-6 mb-8 border-b-2 border-[#062121]">
      <div class="flex gap-6">
        <!-- Logo -->
        <div v-if="companySettings?.logo" class="flex-shrink-0">
          <img :src="companySettings.logo" alt="Logo" class="h-16 w-auto object-contain" />
        </div>
        <div>
          <h1 class="text-3xl font-bold text-[#062121] mb-2">{{ companySettings?.company_name || 'Lindsey Webster LLC' }}</h1>
          <div class="text-sm text-gray-600 leading-relaxed">
            <p>{{ companySettings?.legal_name || 'Mike Clay Landscaping' }}</p>
            <p>{{ companySettings?.address || 'Aut autem dicta id i' }}</p>
            <p>{{ companySettings?.city || 'Similique deserunt q' }}</p>
            <p>{{ companySettings?.phone || '+1 (638) 846-7748' }}</p>
            <p>{{ companySettings?.email || 'laqixegov@mailinator.com' }}</p>
          </div>
        </div>
      </div>
      <div class="text-right">
        <div class="text-3xl font-bold text-[#062121] uppercase tracking-wide">{{ invoice.type === 'invoice' ? 'FACTURE' : 'DEVIS' }}</div>
        <div class="text-xl font-semibold text-gray-500 mt-2">#{{ invoice.number }}</div>
      </div>
    </div>

    <!-- Section BILL TO et informations -->
    <div class="grid grid-cols-2 gap-12 mb-10">
      <div>
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">BILL TO</h3>
        <p class="font-bold text-gray-800 text-lg mb-1">{{ invoice.client?.name || 'Aurelia Hebert' }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.address || 'Tenetur laborum Exe' }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.phone || '+1 (809) 836-9205' }}</p>
        <p class="text-sm text-gray-600">{{ invoice.client?.email || 'vawokecap@mailinator.com' }}</p>
      </div>
      <div class="text-right space-y-4">
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider">DATE D'ÉMISSION</p>
          <p class="text-base font-semibold text-gray-800">{{ fmtDate(invoice.date) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider">DATE D'ÉCHÉANCE</p>
          <p class="text-base font-semibold text-gray-800">{{ invoice.due_date ? fmtDate(invoice.due_date) : 'À réception' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider">SOLDE DÛ</p>
          <p class="text-2xl font-bold text-[#062121]">{{ fmt(invoice.total_ttc) }}</p>
        </div>
      </div>
    </div>

    <!-- Tableau des articles -->
    <div class="mb-10">
      <table class="w-full">
        <thead>
          <tr class="bg-[#062121] text-white">
            <th class="py-3 px-4 text-left text-sm font-semibold rounded-tl-lg">DESCRIPTION</th>
            <th class="py-3 px-4 text-center text-sm font-semibold w-28">PRIX UNIT.</th>
            <th class="py-3 px-4 text-center text-sm font-semibold w-20">QTÉ</th>
            <th class="py-3 px-4 text-right text-sm font-semibold w-32 rounded-tr-lg">TOTAL HT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in invoice.items" :key="i" class="border-b border-gray-100 hover:bg-gray-50">
            <td class="py-4 px-4 text-sm text-gray-700">
              {{ item.designation }}
              <p v-if="item.description" class="text-xs text-gray-400 mt-1">{{ item.description }}</p>
            </td>
            <td class="py-4 px-4 text-sm text-center text-gray-600">{{ fmt(item.price) }}</td>
            <td class="py-4 px-4 text-sm text-center text-gray-600">{{ item.quantity }}</td>
            <td class="py-4 px-4 text-sm text-right font-semibold text-gray-800">{{ fmt(lineTotal(item)) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Section Totaux -->
    <div class="flex justify-end mb-10">
      <div class="w-80">
        <div class="bg-gray-50 rounded-lg p-5">
          <div class="flex justify-between py-2 text-sm">
            <span class="text-gray-500">Total HT</span>
            <span class="font-semibold">{{ fmt(invoice.total_ht) }}</span>
          </div>
          <div class="flex justify-between py-2 text-sm border-b border-gray-200">
            <span class="text-gray-500">TVA ({{ invoice.tva_rate }}%)</span>
            <span class="font-semibold">{{ fmt(tvaAmount(invoice)) }}</span>
          </div>
          <div class="flex justify-between py-3 mt-2">
            <span class="text-lg font-bold text-[#062121]">Total TTC</span>
            <span class="text-xl font-black text-[#062121]">{{ fmt(invoice.total_ttc) }}</span>
          </div>
          <div class="flex justify-between pt-3 mt-2 border-t-2 border-[#C5F82A]">
            <span class="text-sm font-bold text-gray-700">Solde dû</span>
            <span class="text-lg font-black text-[#062121]">{{ fmt(invoice.total_ttc) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature -->
    <div v-if="companySettings?.signature" class="mb-8">
      <p class="text-xs text-gray-400 mb-2">Signature électronique</p>
      <img :src="companySettings.signature" alt="Signature" class="h-14 object-contain" />
    </div>

    <!-- Footer -->
    <div class="border-t border-gray-200 pt-6 text-center">
      <p class="text-sm text-gray-500 font-medium">Merci pour votre confiance</p>
      <p class="text-xs text-gray-400 mt-2">
        {{ companySettings?.company_name }} · IF {{ companySettings?.if }} · RC {{ companySettings?.rc }} · ICE {{ companySettings?.ice }}
      </p>
      <p class="text-xs text-gray-400 mt-1">Paiement à réception · Pénalité de retard : 1,5% par mois</p>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .template-modern { 
    padding: 10mm 12mm !important; 
  }
  @page { 
    size: A4; 
    margin: 0; 
  }
  * { 
    -webkit-print-color-adjust: exact !important; 
    print-color-adjust: exact !important; 
  }
  .bg-\[#062121\] {
    background-color: #062121 !important;
  }
}
</style>