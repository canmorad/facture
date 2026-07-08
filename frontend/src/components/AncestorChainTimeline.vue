<script setup>
import { defineProps, defineEmits } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({
  chain: {
    type: Array,
    required: true,
    default: () => [],
  },
});

const emit = defineEmits(["navigate"]);

const router = useRouter();

const typeLabels = {
  Quote: "Devis",
  Invoice: "Facture",
  PurchaseOrder: "Commande",
  DeliveryNote: "Bon de livraison",
  CreditNote: "Avoir",
};

const typeIcons = {
  Quote: "fa-file-invoice",
  Invoice: "fa-file-invoice-dollar",
  PurchaseOrder: "fa-file-contract",
  DeliveryNote: "fa-truck",
  CreditNote: "fa-file-circle-minus",
};

const handleClick = (item) => {
  emit("navigate", item);
};
</script>

<template>
  <div v-if="chain && chain.length > 0" class="ancestor-chain-timeline">
    <div class="flex items-center gap-3 px-4 py-3 bg-white border border-gray-200 rounded-lg">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Chaîne documentaire</span>
      <div class="flex items-center gap-1 flex-wrap">
        <template v-for="(item, index) in chain" :key="item.id">
          <button
            v-if="index > 0"
            class="text-gray-300 cursor-default mx-1"
            disabled
          >
            <i class="fas fa-chevron-right text-xs"></i>
          </button>
          <button
            @click="handleClick(item)"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all duration-200',
              index === chain.length - 1
                ? 'bg-[#062121] text-white shadow-sm'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
            ]"
          >
            <i :class="['fas', typeIcons[item.type] || 'fa-file', 'text-xs']"></i>
            <span>{{ typeLabels[item.type] || item.type }}</span>
            <span v-if="item.number" class="opacity-75">#{{ item.number }}</span>
          </button>
        </template>
      </div>
    </div>
  </div>
</template>