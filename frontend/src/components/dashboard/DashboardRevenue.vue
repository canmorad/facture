<script setup>
import { computed } from "vue";
import { Line } from "vue-chartjs";
import {
  Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler,
} from "chart.js";

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
  revenueByMonth: { type: Array, required: true },
  expensesByMonth: { type: Array, required: true },
  topCustomers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const formatCurrencyShort = (amount) => {
  if (amount >= 1000000) return (amount / 1000000).toFixed(1) + "M DH";
  if (amount >= 1000) return (amount / 1000).toFixed(0) + "k DH";
  return amount + " DH";
};

const chartData = computed(() => ({
  labels: props.revenueByMonth.map((m) => m.label),
  datasets: [
    {
      label: "Chiffre d'affaires",
      data: props.revenueByMonth.map((m) => m.total),
      borderColor: "#10B981",
      backgroundColor: "rgba(16, 185, 129, 0.1)",
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: "#10B981",
    },
    {
      label: "Dépenses",
      data: props.expensesByMonth.map((m) => m.total),
      borderColor: "#F97316",
      backgroundColor: "rgba(249, 115, 22, 0.1)",
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: "#F97316",
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: "index" },
  scales: {
    y: {
      ticks: { callback: (v) => formatCurrencyShort(v) },
      grid: { color: "rgba(0,0,0,0.05)" },
    },
    x: { grid: { display: false } },
  },
  plugins: {
    legend: { position: "bottom", labels: { usePointStyle: true, padding: 20 } },
    tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ": " + ctx.parsed.y.toLocaleString("fr-MA") + " DH" } },
  },
};
</script>

<template>
  <div v-if="loading" class="text-center py-12">
    <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
  </div>

  <div v-else class="space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
      <h3 class="text-sm font-semibold text-[#062121] mb-4">Évolution sur 12 mois</h3>
      <div style="height: 300px">
        <Line :data="chartData" :options="chartOptions" />
      </div>
    </div>

    <div v-if="topCustomers.length > 0" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
      <h3 class="text-sm font-semibold text-[#062121] mb-4">Top 5 clients</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">CA</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Documents</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="c in topCustomers" :key="c.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="text-sm font-medium text-gray-900">{{ c.name }}</div>
                <div class="text-xs text-gray-400" v-if="c.city">{{ c.city }}</div>
              </td>
              <td class="px-4 py-3 text-right text-sm font-bold text-[#062121]">{{ c.total_revenue.toLocaleString("fr-MA") }} DH</td>
              <td class="px-4 py-3 text-right text-sm text-gray-500">{{ c.document_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
