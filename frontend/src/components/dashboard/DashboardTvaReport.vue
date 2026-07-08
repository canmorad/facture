<script setup>
import { ref, watch } from "vue";
import axios from "axios";

const props = defineProps({ loading: { type: Boolean, default: false } });

const tvaPeriod = ref("all");
const tvaReport = ref(null);
const tvaLoading = ref(false);

const fetchTvaReport = async () => {
  tvaLoading.value = true;
  try {
    const { data } = await axios.get("/api/dashboard/tva-report", {
      params: { period: tvaPeriod.value === "all" ? undefined : tvaPeriod.value },
    });
    tvaReport.value = data;
  } catch {
    tvaReport.value = null;
  } finally {
    tvaLoading.value = false;
  }
};

watch(tvaPeriod, fetchTvaReport, { immediate: true });

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + " DH";
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button @click="tvaPeriod = 'all'" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition-all', tvaPeriod === 'all' ? 'bg-[#C5F82A] text-[#062121]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Global</button>
      <button @click="tvaPeriod = 'month'" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition-all', tvaPeriod === 'month' ? 'bg-[#C5F82A] text-[#062121]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Ce mois</button>
      <button @click="tvaPeriod = 'quarter'" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition-all', tvaPeriod === 'quarter' ? 'bg-[#C5F82A] text-[#062121]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Ce trimestre</button>
    </div>

    <div v-if="tvaLoading" class="text-center py-12">
      <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <div v-else-if="!tvaReport || tvaReport.document_count === 0" class="text-center py-12">
      <i class="fas fa-file-invoice text-5xl text-gray-300 mb-4 block"></i>
      <p class="text-gray-500">Aucun document finalisé pour le calcul de la TVA.</p>
    </div>

    <div v-else>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200 p-5">
          <span class="text-sm text-blue-600 font-medium">Total HT</span>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(tvaReport.total_ht) }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl border border-amber-200 p-5">
          <span class="text-sm text-amber-600 font-medium">Total TVA collectée</span>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(tvaReport.total_tva) }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 p-5">
          <span class="text-sm text-green-600 font-medium">Total TTC</span>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(tvaReport.total_ttc) }}</p>
        </div>
      </div>

      <div class="text-xs text-gray-400 mb-3">{{ tvaReport.document_count }} document(s) finalisé(s)</div>

      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Taux TVA</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total HT</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">TVA collectée</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Nb lignes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(rate, index) in tvaReport.rates" :key="index" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex px-3 py-1 text-sm font-bold rounded-full bg-indigo-100 text-indigo-700">{{ rate.rate }}%</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <span class="text-sm font-medium text-gray-700">{{ formatCurrency(rate.total_ht) }}</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <span class="text-sm font-bold text-amber-700">{{ formatCurrency(rate.total_tva) }}</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <span class="text-sm font-medium text-gray-700">{{ formatCurrency(rate.total_ttc) }}</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <span class="text-sm text-gray-500">{{ rate.count }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-gray-300 bg-gray-50">
              <td class="px-4 py-3 text-sm font-bold text-[#062121]">TOTAL</td>
              <td class="px-4 py-3 text-right text-sm font-bold text-[#062121]">{{ formatCurrency(tvaReport.total_ht) }}</td>
              <td class="px-4 py-3 text-right text-sm font-bold text-amber-700">{{ formatCurrency(tvaReport.total_tva) }}</td>
              <td class="px-4 py-3 text-right text-sm font-bold text-[#062121]">{{ formatCurrency(tvaReport.total_ttc) }}</td>
              <td class="px-4 py-3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
