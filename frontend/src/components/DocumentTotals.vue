<script setup>
import { computed } from "vue";
import BaseInputNumber from "./BaseInputNumber.vue";
import CustomSelect from "./CustomSelect.vue";

const props = defineProps({
  totals: {
    type: Object,
    required: true,
  },
  globalDiscountType: {
    type: String,
    default: "percentage",
  },
  globalDiscountValue: {
    type: Number,
    default: 0,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:globalDiscountType", "update:globalDiscountValue"]);

const fmt = (n) => {
  if (isNaN(n) || !isFinite(n)) return "0.00";
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
};

const discountTypes = [
  { label: "%", value: "percentage" },
  { label: "DH", value: "fixed" },
];
</script>

<template>
  <div class="flex justify-end">
    <div class="w-full md:w-1/2">
      <div class="rounded-xl border border-gray-200 overflow-hidden">
        <!-- Total HT -->
        <div class="px-5 py-3 flex justify-between border-b border-gray-100">
          <span class="text-sm text-gray-500">Total HT</span>
          <span class="text-sm font-semibold text-gray-800 font-mono">
            {{ fmt(totals.ht) }} DH
          </span>
        </div>

        <!-- Remise générale -->
        <div class="px-5 py-3 flex justify-between items-center border-b border-gray-100">
          <span class="text-sm text-gray-500">Remise générale</span>
          <div class="flex items-center gap-2">
            <BaseInputNumber
              :model-value="globalDiscountValue"
              @update:model-value="$emit('update:globalDiscountValue', $event)"
              :min="0"
              :step="0.01"
              placeholder="0.00"
              :disabled="disabled"
              class="w-20"
            />
            <CustomSelect
              :model-value="globalDiscountType"
              @update:model-value="$emit('update:globalDiscountType', $event)"
              :options="discountTypes"
              label-key="label"
              value-key="value"
              placeholder="Type"
              container-class="w-20"
              :disabled="disabled"
            />
            <span class="text-sm font-semibold text-red-600 whitespace-nowrap">
              - {{ fmt(totals.globalDiscount) }}
            </span>
          </div>
        </div>

        <!-- Total HT après remise -->
        <div
          class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
        >
          <span class="text-sm text-gray-500">Total HT après remise</span>
          <span class="text-sm font-semibold text-gray-800 font-mono">
            {{ fmt(totals.htAfterDiscount) }} DH
          </span>
        </div>

        <!-- TVA -->
        <div
          class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
        >
          <span class="text-sm text-gray-500">TVA</span>
          <span class="text-sm font-semibold text-gray-800 font-mono">
            {{ fmt(totals.tvaAfterDiscount) }} DH
          </span>
        </div>

        <!-- Total TTC -->
        <div class="px-5 py-4 flex justify-between bg-gray-50">
          <span class="text-sm font-bold text-[#062121] uppercase tracking-wide">
            Total TTC
          </span>
          <span class="text-lg font-black text-[#062121] font-mono">
            {{ fmt(totals.ttc) }} DH
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
