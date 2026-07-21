<script setup>
import { ref, computed } from "vue";
import BaseInputNumber from "./BaseInputNumber.vue";
import CustomSelect from "./CustomSelect.vue";
import ProductSearchInput from "./ProductSearchInput.vue";
import TaxRateInput from "./TaxRateInput.vue";
import DiscountInput from "./DiscountInput.vue";

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    default: () => [],
  },
  productTypes: {
    type: Array,
    default: () => [],
  },
  taxRates: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  index: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits([
  "update:item",
  "select-product",
  "select-tax-rate",
  "update:product-type",
  "remove-line",
]);

const item = computed({
  get: () => props.item,
  set: (val) => emit("update:item", val),
});

const selectProduct = (product) => {
  emit("select-product", { index: props.index, product });
};

const selectTaxRate = (taxRate) => {
  emit("select-tax-rate", { index: props.index, taxRate });
};

const lineTotalHt = computed(() => {
  const subtotal =
    (parseFloat(item.value.quantity) || 0) * (parseFloat(item.value.unit_price) || 0);
  let discount = 0;
  if (item.value.discount_type && item.value.discount_value > 0) {
    if (item.value.discount_type === "percentage") {
      discount = subtotal * (item.value.discount_value / 100);
    } else {
      discount = item.value.discount_value;
    }
  }
  return subtotal - discount;
});

const productTypeName = computed(() => {
  if (!props.item.product_type || !props.productTypes.length) return "";
  const type = props.productTypes.find(
    (pt) => String(pt.id) === String(props.item.product_type)
  );
  return type ? type.name : "";
});

const productTypeOptions = computed(() => {
  if (!props.productTypes.length) return [];
  return props.productTypes.map((pt) => ({
    label: pt.name,
    value: String(pt.id),
  }));
});

const fmt = (n) => {
  if (isNaN(n) || !isFinite(n)) return "0.00";
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
};
</script>

<template>
  <tr :class="index % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'">
    <!-- Produit -->
    <td class="px-4 py-3 relative z-10">
      <ProductSearchInput
        v-model="item.designation"
        :products="products"
        :disabled="disabled"
        @select-product="selectProduct"
      />
    </td>

    <!-- Type -->
    <td class="px-4 py-3 relative z-10">
      <CustomSelect
        :model-value="props.item.product_type"
        @update:model-value="$emit('update:product-type', $event)"
        :options="productTypeOptions"
        label-key="label"
        value-key="value"
        placeholder="Type"
        :disabled="disabled"
      />
    </td>

    <!-- Qté -->
    <td class="px-4 py-3">
      <BaseInputNumber
        v-model="item.quantity"
        :min="0.01"
        :step="0.01"
        :disabled="disabled"
      />
    </td>

    <!-- P.U. HT -->
    <td class="px-4 py-3">
      <BaseInputNumber
        v-model="item.unit_price"
        :min="0"
        :step="0.01"
        :disabled="disabled"
      />
    </td>

    <!-- TVA % -->
    <td class="px-4 py-3 relative z-10">
      <TaxRateInput
        v-model="item.tax_rate"
        :tax-rates="taxRates"
        :disabled="disabled"
        @select-tax-rate="selectTaxRate"
      />
    </td>

    <!-- Réduction -->
    <td class="px-4 py-3">
      <DiscountInput
        v-model="item.discount_value"
        v-model:discount-type="item.discount_type"
        :disabled="disabled"
      />
    </td>

    <!-- Total HT -->
    <td class="px-4 py-3 text-center">
      <span class="text-sm font-semibold text-[#062121] font-mono">
        {{ fmt(lineTotalHt) }}
      </span>
    </td>

    <!-- Actions -->
    <td class="px-4 py-3 text-center">
      <button
        type="button"
        @click="$emit('remove-line', index)"
        :disabled="disabled"
        class="text-red-400 hover:text-red-600 transition-colors disabled:opacity-20 disabled:cursor-not-allowed"
      >
        <i class="fas fa-times text-xs"></i>
      </button>
    </td>
  </tr>
</template>
