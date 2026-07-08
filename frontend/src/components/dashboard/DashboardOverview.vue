<script setup>
defineProps({
  stats: { type: Object, required: true },
  documentsByType: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + " DH";

const colorMap = {
  blue: "bg-blue-500", emerald: "bg-emerald-500", amber: "bg-amber-500",
  purple: "bg-purple-500", red: "bg-red-500", teal: "bg-teal-500",
};
const bgColorMap = {
  blue: "from-blue-50 to-blue-100 border-blue-200", emerald: "from-emerald-50 to-emerald-100 border-emerald-200",
  amber: "from-amber-50 to-amber-100 border-amber-200", purple: "from-purple-50 to-purple-100 border-purple-200",
  red: "from-red-50 to-red-100 border-red-200", teal: "from-teal-50 to-teal-100 border-teal-200",
};
</script>

<template>
  <div v-if="loading" class="text-center py-12">
    <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
    <p class="mt-2 text-gray-500">Chargement du dashboard...</p>
  </div>

  <div v-else class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-sm text-green-600 font-medium">Chiffre d'affaires</span>
          <i class="fas fa-euro-sign text-green-500 text-lg"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ formatCurrency(stats.total_revenue) }}</p>
      </div>

      <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl border border-orange-200 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-sm text-orange-600 font-medium">Total dépenses</span>
          <i class="fas fa-receipt text-orange-500 text-lg"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ formatCurrency(stats.total_expenses) }}</p>
      </div>

      <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-sm text-blue-600 font-medium">Clients</span>
          <i class="fas fa-users text-blue-500 text-lg"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ stats.active_customers }}</p>
      </div>

      <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-sm text-purple-600 font-medium">Documents</span>
          <i class="fas fa-file-alt text-purple-500 text-lg"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ stats.total_documents }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div
        v-for="doc in documentsByType"
        :key="doc.type"
        :class="['rounded-xl border p-4 shadow-sm bg-gradient-to-br', bgColorMap[doc.color] || 'from-gray-50 to-gray-100 border-gray-200']"
      >
        <div class="flex items-center gap-2 mb-2">
          <i :class="['fas', doc.icon, 'text-lg', 'text-' + doc.color + '-600']"></i>
          <span class="text-xs text-gray-600 truncate">{{ doc.type }}</span>
        </div>
        <p class="text-xl font-bold text-gray-900">{{ doc.count }}</p>
      </div>
    </div>

    <div v-if="stats.pending_documents" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <span class="text-xs text-gray-500">Brouillons</span>
        <p class="text-xl font-bold text-yellow-600">{{ stats.pending_documents.draft }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <span class="text-xs text-gray-500">Envoyées (non payées)</span>
        <p class="text-xl font-bold text-blue-600">{{ stats.pending_documents.sent }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <span class="text-xs text-gray-500">En retard</span>
        <p class="text-xl font-bold text-red-600">{{ stats.pending_documents.overdue }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <span class="text-xs text-gray-500">Montant en attente</span>
        <p class="text-xl font-bold text-[#062121]">{{ formatCurrency(stats.pending_documents.total_pending_amount) }}</p>
      </div>
    </div>
  </div>
</template>
