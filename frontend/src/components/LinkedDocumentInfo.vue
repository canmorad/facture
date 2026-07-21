<template>
  <div v-if="document">
    <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
      <i class="fas fa-link text-gray-400"></i>
      Document lié
    </h3>
    <div class="text-sm text-gray-700 space-y-1.5">
      <div class="flex gap-3">
        <span class="text-gray-500 font-medium w-28">Document</span>
        <span class="font-semibold text-gray-800">{{ documentTypeLabel }} : #{{ document.number || (document.documentable?.status === 'DRAFT' ? 'Brouillon' : '—') }}</span>
      </div>
      <div class="flex gap-3">
        <span class="text-gray-500 font-medium w-28">Client</span>
        <span class="font-semibold text-gray-800">{{ customerName }}</span>
      </div>
      <div class="flex gap-3">
        <span class="text-gray-500 font-medium w-28">Total TTC</span>
        <span class="font-semibold text-gray-800">{{ formatAmount(document.total_ttc) }} DH</span>
      </div>
      <div class="flex gap-3">
        <span class="text-gray-500 font-medium w-28">Reste à payer</span>
        <span class="font-bold text-[#062121]">{{ formatAmount(balanceData.remaining_total_ttc || balanceData.remaining_balance || 0) }} DH</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  document: {
    type: Object,
    default: null,
  },
  documentType: {
    type: String,
    default: 'devis', // 'devis' or 'proforma'
    validator: (value) => ['devis', 'proforma', 'quote', 'proforma'].includes(value),
  },
  balanceData: {
    type: Object,
    default: () => ({
      quote_total_ttc: 0,
      deposited_total_ttc: 0,
      remaining_balance: 0,
      remaining_total_ttc: 0,
    }),
  },
});

const documentTypeLabel = computed(() => {
  switch (props.documentType) {
    case 'devis':
    case 'quote':
      return 'Devis';
    case 'proforma':
      return 'Proforma';
    default:
      return 'Document';
  }
});

const customerName = computed(() => {
  if (!props.document?.customer) return '—';

  const customer = props.document.customer;

  // Check if customer has customerable relationship (B2B/B2C structure)
  if (customer.customerable) {
    if (customer.type === 'b2b') {
      return customer.customerable.legal_name || '—';
    } else {
      return customer.customerable.name || '—';
    }
  }

  // Fallback to direct name property
  return customer.name || '—';
});

const formatAmount = (value) => {
  if (value === null || value === undefined) return '0.00';
  const num = parseFloat(value);
  if (isNaN(num)) return '0.00';
  return num.toFixed(2);
};
</script>
