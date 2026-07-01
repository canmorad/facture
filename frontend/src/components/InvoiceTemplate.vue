<script setup>
import { computed } from "vue";

const props = defineProps({
  document: {
    type: Object,
    default: () => ({
      id: 1,
      number: "FAC-2024-0042",
      type: "invoice",
      status: "sent",
      date: "2024-06-01",
      due_date: "2024-06-30",
      client: {
        name: "Société Exemple SARL",
        address: "123 Boulevard Mohammed V, Casablanca 20000, Maroc",
        ice: "001234567000089",
      },
      items: [
        { designation: "Développement application web", quantity: 1, price: 15000 },
        { designation: "Intégration API paiement", quantity: 1, price: 4500 },
        { designation: "Formation & documentation", quantity: 3, price: 800 },
      ],
      total_ht: 21900,
      tva_rate: 20,
      total_ttc: 26280,
    }),
  },
});

const isInvoice = computed(() => props.document.type === "invoice");
const docLabel = computed(() => (isInvoice.value ? "FACTURE" : "DEVIS"));
const docColor = computed(() => (isInvoice.value ? "#062121" : "#1e3a5f"));

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Intl.DateTimeFormat("fr-MA", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  }).format(new Date(dateStr));
};

const formatAmount = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const lineTotal = (item) => item.quantity * item.price;

const tvaAmount = computed(() =>
  (props.document.total_ht * props.document.tva_rate) / 100
);

const statusLabel = computed(() => {
  const map = {
    draft: { label: "Brouillon", color: "#94a3b8" },
    sent: { label: "Envoyé", color: "#3b82f6" },
    paid: { label: "Payé", color: "#22c55e" },
    overdue: { label: "En retard", color: "#ef4444" },
    cancelled: { label: "Annulé", color: "#f97316" },
  };
  return map[props.document.status] || map["draft"];
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 font-sans print:bg-white">

    <!-- Action Bar (hidden on print) -->
    <div class="print:hidden sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
      <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
            :style="{ backgroundColor: docColor }"
          >
            <i :class="isInvoice ? 'fas fa-file-invoice' : 'fas fa-file-alt'"></i>
          </div>
          <span class="text-sm font-semibold text-slate-700">
            {{ docLabel }} — {{ document.number }}
          </span>
          <span
            class="text-xs font-semibold px-2.5 py-1 rounded-full"
            :style="{ backgroundColor: statusLabel.color + '20', color: statusLabel.color }"
          >
            {{ statusLabel.label }}
          </span>
        </div>
        <button
          @click="window.print()"
          class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-95"
          :style="{ backgroundColor: '#062121' }"
        >
          <i class="fas fa-print text-xs"></i>
          Imprimer / PDF
        </button>
      </div>
    </div>

    <!-- A4 Document -->
    <div class="max-w-4xl mx-auto my-8 print:my-0 print:max-w-none print:mx-0 print:shadow-none">
      <div
        class="bg-white shadow-xl print:shadow-none"
        style="min-height: 297mm; padding: 14mm 16mm;"
      >

        <!-- ───── HEADER ───── -->
        <header class="flex items-start justify-between mb-10">

          <!-- Left: Logo + Company -->
          <div class="flex flex-col gap-1">
            <div
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl mb-3"
              :style="{ backgroundColor: '#062121' }"
            >
              <i class="fas fa-bolt text-[#C5F82A] text-lg"></i>
              <span class="text-white font-black text-xl tracking-tight">Facturex</span>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed max-w-[220px]">
              123 Rue de la Liberté, Bureau 4<br />
              Casablanca 20000, Maroc<br />
              contact@facturex.ma · +212 6 00 00 00 00
            </p>
            <p class="text-xs text-slate-400 mt-1">IF : 12345678 &nbsp;|&nbsp; RC : 98765 &nbsp;|&nbsp; ICE : 002345678000099</p>
          </div>

          <!-- Right: Document Type + Meta -->
          <div class="text-right">
            <div class="flex items-center justify-end gap-3 mb-3">
              <span
                class="text-3xl font-black tracking-widest"
                :style="{ color: '#062121' }"
              >
                {{ docLabel }}
              </span>
              <span
                v-if="!isInvoice"
                class="text-xs font-bold px-2 py-1 rounded-md border"
                style="color: #1e3a5f; border-color: #1e3a5f;"
              >
                DEVIS
              </span>
            </div>

            <div class="text-sm text-slate-600 space-y-1">
              <div class="flex items-center justify-end gap-3">
                <span class="text-slate-400 text-xs uppercase tracking-widest">N°</span>
                <span class="font-bold text-slate-800">{{ document.number }}</span>
              </div>
              <div class="flex items-center justify-end gap-3">
                <span class="text-slate-400 text-xs uppercase tracking-widest">Date</span>
                <span class="font-medium">{{ formatDate(document.date) }}</span>
              </div>
              <div class="flex items-center justify-end gap-3" v-if="document.due_date">
                <span class="text-slate-400 text-xs uppercase tracking-widest">
                  {{ isInvoice ? "Échéance" : "Validité" }}
                </span>
                <span class="font-medium">{{ formatDate(document.due_date) }}</span>
              </div>
            </div>
          </div>
        </header>

        <!-- Separator -->
        <div class="h-px w-full bg-slate-200 mb-8"></div>

        <!-- ───── PARTIES ───── -->
        <div class="grid grid-cols-2 gap-8 mb-10">

          <!-- Émetteur -->
          <div class="bg-slate-50 rounded-xl p-5 border border-slate-100">
            <p class="text-[10px] font-bold uppercase tracking-[2px] text-slate-400 mb-3">
              Émetteur
            </p>
            <p class="font-bold text-slate-800 text-sm mb-1">Facturex SARL</p>
            <p class="text-xs text-slate-500 leading-relaxed">
              123 Rue de la Liberté, Bureau 4<br />
              Casablanca 20000, Maroc
            </p>
            <div class="mt-3 pt-3 border-t border-slate-200">
              <p class="text-[10px] text-slate-400 uppercase tracking-widest">ICE</p>
              <p class="text-xs font-mono font-semibold text-slate-600">002345678000099</p>
            </div>
          </div>

          <!-- Client -->
          <div
            class="rounded-xl p-5 border-2"
            :style="{ borderColor: '#062121' + '20', backgroundColor: '#062121' + '05' }"
          >
            <p class="text-[10px] font-bold uppercase tracking-[2px] mb-3" :style="{ color: '#062121' }">
              Facturé à
            </p>
            <p class="font-bold text-slate-800 text-sm mb-1">{{ document.client.name }}</p>
            <p class="text-xs text-slate-500 leading-relaxed">{{ document.client.address }}</p>
            <div class="mt-3 pt-3 border-t border-slate-200" v-if="document.client.ice">
              <p class="text-[10px] text-slate-400 uppercase tracking-widest">ICE</p>
              <p class="text-xs font-mono font-semibold text-slate-600">{{ document.client.ice }}</p>
            </div>
          </div>
        </div>

        <!-- ───── ITEMS TABLE ───── -->
        <div class="mb-8">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr :style="{ backgroundColor: '#062121' }">
                <th class="text-left px-4 py-3 text-white font-semibold text-xs uppercase tracking-wider rounded-tl-lg w-[50%]">
                  Désignation
                </th>
                <th class="text-center px-4 py-3 text-white font-semibold text-xs uppercase tracking-wider w-[12%]">
                  Qté
                </th>
                <th class="text-right px-4 py-3 text-white font-semibold text-xs uppercase tracking-wider w-[20%]">
                  Prix Unitaire
                </th>
                <th class="text-right px-4 py-3 text-white font-semibold text-xs uppercase tracking-wider rounded-tr-lg w-[18%]">
                  Total HT
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, index) in document.items"
                :key="index"
                :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50/60'"
                class="border-b border-slate-100 transition-colors"
              >
                <td class="px-4 py-3.5 text-slate-700 font-medium">
                  {{ item.designation }}
                </td>
                <td class="px-4 py-3.5 text-center text-slate-600">
                  {{ item.quantity }}
                </td>
                <td class="px-4 py-3.5 text-right text-slate-600 font-mono">
                  {{ formatAmount(item.price) }} DH
                </td>
                <td class="px-4 py-3.5 text-right font-semibold text-slate-800 font-mono">
                  {{ formatAmount(lineTotal(item)) }} DH
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ───── TOTALS ───── -->
        <div class="flex justify-end mb-10">
          <div class="w-72">
            <div class="space-y-2">
              <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-sm text-slate-500">Total HT</span>
                <span class="text-sm font-semibold text-slate-700 font-mono">
                  {{ formatAmount(document.total_ht) }} DH
                </span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-slate-100">
                <span class="text-sm text-slate-500">TVA ({{ document.tva_rate }}%)</span>
                <span class="text-sm font-semibold text-slate-700 font-mono">
                  {{ formatAmount(tvaAmount) }} DH
                </span>
              </div>
              <div
                class="flex justify-between items-center px-4 py-3.5 rounded-xl mt-1"
                :style="{ backgroundColor: '#062121' }"
              >
                <span class="text-white font-bold text-sm uppercase tracking-wide">Total TTC</span>
                <span class="text-white font-black text-lg font-mono">
                  {{ formatAmount(document.total_ttc) }} DH
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ───── FOOTER ───── -->
        <div class="mt-auto pt-6 border-t border-slate-200">
          <div class="grid grid-cols-2 gap-8">

            <!-- RIB -->
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[2px] text-slate-400 mb-2">
                Coordonnées bancaires
              </p>
              <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 text-xs text-slate-600 space-y-1">
                <div class="flex gap-2">
                  <span class="text-slate-400 w-16">Banque</span>
                  <span class="font-medium">Attijariwafa Bank</span>
                </div>
                <div class="flex gap-2">
                  <span class="text-slate-400 w-16">RIB</span>
                  <span class="font-mono font-semibold tracking-wide">007 123 0001234567891234</span>
                </div>
                <div class="flex gap-2">
                  <span class="text-slate-400 w-16">IBAN</span>
                  <span class="font-mono">MA64 007 123 0001234567891234</span>
                </div>
              </div>
            </div>

            <!-- Note -->
            <div class="flex flex-col justify-between">
              <div>
                <p class="text-[10px] font-bold uppercase tracking-[2px] text-slate-400 mb-2">
                  Conditions & Notes
                </p>
                <p class="text-xs text-slate-500 leading-relaxed">
                  Paiement à réception de la facture. Passé ce délai, une pénalité de retard
                  de 1,5% par mois sera appliquée.
                </p>
              </div>
              <p
                class="text-sm font-semibold mt-4"
                :style="{ color: '#062121' }"
              >
                Merci pour votre confiance ! 🙏
              </p>
            </div>
          </div>

          <!-- Bottom line -->
          <div class="mt-6 pt-4 border-t border-slate-100 text-center">
            <p class="text-[10px] text-slate-300 tracking-widest uppercase">
              Facturex SARL &nbsp;·&nbsp; IF 12345678 &nbsp;·&nbsp; RC 98765 &nbsp;·&nbsp; ICE 002345678000099 &nbsp;·&nbsp; contact@facturex.ma
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style>
@media print {
  @page {
    size: A4;
    margin: 0;
  }

  body {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>