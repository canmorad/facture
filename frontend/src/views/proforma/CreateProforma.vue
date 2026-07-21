<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { useAuthStore } from "@/stores/auth";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import TextInput from "@/components/TextInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import BaseInputNumber from "@/components/BaseInputNumber.vue";
import TaxRateInput from "@/components/TaxRateInput.vue";
import axios from "axios";
import { success, error } from "@/helpers/notifications";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isLoading = ref(false);
const isLoadingCustomers = ref(false);
const isLoadingProducts = ref(false);
const isLoadingTaxRates = ref(false);
const editingId = ref(null);

const customers = ref([]);
const products = ref([]);
const taxRates = ref([]);

const form = reactive({
  customer_id: "",
  validity_date: "",
  payment_condition: "",
  payment_mode: "",
  notes: "",
  intro_text: "",
  conclusion_text: "",
  items: [],
});

const errors = reactive({
  customer_id: "",
  validity_date: "",
  items: "",
  server: "",
});

const makeItem = () => ({
  product_id: "",
  designation: "",
  quantity: 1,
  unit_price: 0,
  tax_rate: defaultTaxRate.value || 20,
  discount_type: "none",
  discount_value: 0,
});

const addItem = () => {
  form.items.push(makeItem());
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const handleProductChange = (index, productId) => {
  const item = form.items[index];
  const product = products.value.find((p) => p.id == productId);
  if (product) {
    item.product_id = product.id;
    item.designation = product.name;
    item.unit_price = product.price;
    item.tax_rate = product.tax_rate || form.items[0].tax_rate || 20;
  } else {
    item.product_id = "";
    item.designation = "";
    item.unit_price = 0;
    item.tax_rate = defaultTaxRate.value || 20;
  }
};

const handleSelectTaxRate = ({ index, taxRate }) => {
  form.items[index].tax_rate = taxRate.rate;
};

const lineTotalBeforeDiscount = (item) => {
  return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
};

const lineDiscount = (item) => {
  const total = lineTotalBeforeDiscount(item);
  if (item.discount_type === "percent") {
    return total * ((parseFloat(item.discount_value) || 0) / 100);
  } else if (item.discount_type === "fixed") {
    return parseFloat(item.discount_value) || 0;
  }
  return 0;
};

const lineTotal = (item) => {
  return lineTotalBeforeDiscount(item) - lineDiscount(item);
};

const totals = computed(() => {
  let totalHt = 0;
  let totalTva = 0;
  let totalDiscount = 0;

  form.items.forEach((item) => {
    const beforeDiscount = lineTotalBeforeDiscount(item);
    const discount = lineDiscount(item);
    const afterDiscount = beforeDiscount - discount;
    const itemTva = afterDiscount * ((item.tax_rate || 0) / 100);

    totalHt += afterDiscount;
    totalTva += itemTva;
    totalDiscount += discount;
  });

  const totalTtc = totalHt + totalTva;

  return {
    ht: totalHt.toFixed(2),
    tva: totalTva.toFixed(2),
    ttc: totalTtc.toFixed(2),
    discount: totalDiscount.toFixed(2),
  };
});

// Fetch functions
const fetchCustomers = async () => {
  isLoadingCustomers.value = true;
  try {
    const { data } = await axios.get("/api/customers");
    customers.value = data;
  } catch {
    error("Erreur", "Impossible de charger les clients.");
  } finally {
    isLoadingCustomers.value = false;
  }
};

const fetchProducts = async () => {
  isLoadingProducts.value = true;
  try {
    const { data } = await axios.get("/api/products");
    products.value = data;
  } catch {
    error("Erreur", "Impossible de charger les produits.");
  } finally {
    isLoadingProducts.value = false;
  }
};

const fetchTaxRates = async () => {
  isLoadingTaxRates.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const { data } = await axios.get("/api/tax-rates", { params });
    taxRates.value = data.filter(t => t.is_actif).map(t => ({
      label: `${t.libelle} (${t.rate}%)`,
      value: t.rate
    }));

    if (taxRates.value.length > 0) {
      const defaultRate = data.find(t => t.is_default && t.is_actif);
      if (defaultRate && form.items.length === 1) {
        form.items[0].tax_rate = defaultRate.rate;
      }
    }
  } catch {
    taxRates.value = [
      { label: "0% — Exonéré", value: 0 },
      { label: "7% — Eau/Pharmaceutiques", value: 7 },
      { label: "10% — Banques/Hôtels", value: 10 },
      { label: "14% — Transport/Électricité", value: 14 },
      { label: "20% — Standard", value: 20 },
    ];
  } finally {
    isLoadingTaxRates.value = false;
  }
};

const resetForm = () => {
  form.customer_id = "";
  form.validity_date = "";
  form.payment_condition = "";
  form.payment_mode = "";
  form.notes = "";
  form.intro_text = "";
  form.conclusion_text = "";
  form.items = [makeItem()];
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingId.value = null;
};

const submitProforma = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  if (form.items.some((i) => !i.designation.trim() || !i.quantity || i.unit_price < 0)) {
    errors.items = "Veuillez remplir correctement toutes les lignes.";
    isLoading.value = false;
    return;
  }

  try {
    const payload = {
      customer_id: form.customer_id,
      validity_date: form.validity_date || null,
      payment_condition: form.payment_condition,
      payment_mode: form.payment_mode,
      notes: form.notes,
      intro_text: form.intro_text,
      conclusion_text: form.conclusion_text,
      items: form.items.map((item) => ({
        product_id: item.product_id,
        designation: item.designation,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate: item.tax_rate,
        discount_type: item.discount_type,
        discount_value: item.discount_value,
      })),
    };

    if (editingId.value) {
      await axios.put(`/api/proformas/${editingId.value}`, payload);
      success("Modifiée !", "La facture proforma a été modifiée avec succès.");
    } else {
      await axios.post("/api/proformas", payload);
      success("Créée !", "La facture proforma a été créée avec succès.");
    }

    resetForm();
    router.push({ name: 'proforma.index' });
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.errors) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
    }
  } finally {
    isLoading.value = false;
  }
};

const loadProforma = async (id) => {
  try {
    const { data } = await axios.get(`/api/proformas/${id}`);
    editingId.value = id;

    form.customer_id = data.document?.customer_id || "";
    form.validity_date = data.validity_date || "";
    form.payment_condition = data.document?.payment_condition || "";
    form.payment_mode = data.document?.payment_mode || "";
    form.notes = data.document?.notes || "";
    form.intro_text = data.document?.intro_text || "";
    form.conclusion_text = data.document?.conclusion_text || "";

    form.items = (data.items || []).map((item) => ({
      product_id: item.product_id || "",
      designation: item.designation || "",
      quantity: item.quantity || 1,
      unit_price: item.unit_price || 0,
      tax_rate: item.tax_rate || 20,
      discount_type: item.discount_type || "none",
      discount_value: item.discount_value || 0,
    }));

    if (form.items.length === 0) {
      form.items = [makeItem()];
    }
  } catch (err) {
    error("Erreur", "Impossible de charger les détails de la facture proforma.");
    router.push({ name: 'proforma.index' });
  }
};

// Format utilities
const formatAmount = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

// Computed options
const customerOptions = computed(() => [
  { label: "-- Sélectionnez un client --", value: "" },
  ...customers.value.map(c => ({ label: c.name, value: c.id }))
]);

const productOptions = computed(() => [
  { label: "-- Choisir un produit --", value: "" },
  ...products.value.map(p => ({ label: p.name, value: p.id }))
]);

const defaultTaxRate = computed(() => {
  if (taxRates.value.length === 0) return 20;
  return taxRates.value[0].value;
});

onMounted(async () => {
  await Promise.all([fetchCustomers(), fetchProducts(), fetchTaxRates()]);

  const id = route.params.id;
  if (id) {
    await loadProforma(id);
  }
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="max-w-5xl mx-auto">
          <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">

            <!-- Header -->
            <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
              <div>
                <h1 class="text-xl font-bold text-[#062121]">
                  {{ editingId ? "Modifier la facture proforma" : "Nouvelle facture proforma" }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                  {{ editingId ? "Modifiez les informations de la facture proforma" : "Créez une nouvelle facture proforma" }}
                </p>
              </div>
              <button
                @click="router.push({ name: 'proforma.index' })"
                class="text-gray-400 hover:text-gray-600 transition-colors"
              >
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="submitProforma" class="p-6 space-y-8">
              <InputError :message="errors.server" />

              <!-- Customer & Dates -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="customer_id" value="Client *" />
                  <CustomSelect
                    id="customer_id"
                    v-model="form.customer_id"
                    :options="customerOptions"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner un client"
                    :use-portal="true"
                  />
                  <InputError class="mt-2" :message="errors.customer_id" />
                </div>

                <div>
                  <InputLabel for="validity_date" value="Date de validité" />
                  <TextInput
                    id="validity_date"
                    type="date"
                    v-model="form.validity_date"
                    class="mt-1"
                  />
                </div>
              </div>

              <!-- Payment Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="payment_condition" value="Condition de paiement" />
                  <TextInput
                    id="payment_condition"
                    v-model="form.payment_condition"
                    placeholder="Ex: 30 jours, 50% à la commande..."
                    class="mt-1"
                  />
                </div>

                <div>
                  <InputLabel for="payment_mode" value="Mode de paiement" />
                  <TextInput
                    id="payment_mode"
                    v-model="form.payment_mode"
                    placeholder="Virement, Chèque, Espèces..."
                    class="mt-1"
                  />
                </div>
              </div>

              <!-- LINE ITEMS -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">Articles</h3>
                  <button
                    type="button"
                    @click="addItem"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
                  >
                    <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
                  </button>
                </div>

                <InputError :message="errors.items" class="mb-3" />

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                  <table class="min-w-full">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Produit</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">P.U. HT</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">TVA %</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">Total HT</th>
                        <th class="px-4 py-3 w-[3%]"></th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                      <tr v-for="(item, idx) in form.items" :key="idx" :class="idx % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'">
                        <td class="px-4 py-3 relative z-10 overflow-visible">
                          <CustomSelect
                            :model-value="item.product_id"
                            :options="productOptions"
                            label-key="label"
                            value-key="value"
                            placeholder="-- Choisir un produit --"
                            @update:model-value="(value) => handleProductChange(idx, value)"
                            :use-portal="true"
                          />
                        </td>

                        <td class="px-4 py-3">
                          <BaseInputNumber
                            v-model="item.quantity"
                            :min="0.01"
                            :step="'0.01'"
                          />
                        </td>

                        <td class="px-4 py-3">
                          <BaseInputNumber
                            v-model="item.unit_price"
                            :min="0"
                            :step="'0.01'"
                          />
                        </td>

                        <td class="px-4 py-3 relative z-10 overflow-visible">
                          <TaxRateInput
                            v-model="item.tax_rate"
                            :tax-rates="taxRates"
                            @select-tax-rate="(taxRate) => handleSelectTaxRate({ index: idx, taxRate })"
                          />
                        </td>

                        <td class="px-4 py-3 text-center">
                          <span class="text-sm font-semibold text-[#062121] font-mono">
                            {{ formatAmount(lineTotal(item)) }} DH
                          </span>
                        </td>

                        <td class="px-4 py-3 text-center">
                          <button
                            type="button"
                            @click="removeItem(idx)"
                            :disabled="form.items.length === 1"
                            class="text-red-400 hover:text-red-600 transition-colors disabled:opacity-20 disabled:cursor-not-allowed"
                          >
                            <i class="fas fa-times text-xs"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- TOTALS -->
              <div class="flex justify-end">
                <div class="w-full md:w-1/2 rounded-xl border border-gray-200 overflow-hidden">
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100">
                    <span class="text-sm text-gray-500">Total HT</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono">{{ formatAmount(totals.ht) }} DH</span>
                  </div>
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50">
                    <span class="text-sm text-gray-500">TVA totale</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono">{{ formatAmount(totals.tva) }} DH</span>
                  </div>
                  <div class="px-5 py-4 flex justify-between bg-gray-50">
                    <span class="text-sm font-bold text-[#062121] uppercase tracking-wide">Total TTC</span>
                    <span class="text-lg font-black text-[#062121] font-mono">{{ formatAmount(totals.ttc) }} DH</span>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div>
                <InputLabel for="notes" value="Notes" />
                <textarea
                  id="notes"
                  v-model="form.notes"
                  rows="3"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#C5F82A] focus:ring-[#C5F82A] text-sm"
                  placeholder="Notes additionnelles..."
                ></textarea>
              </div>

              <!-- FOOTER ACTIONS -->
              <div class="flex flex-wrap justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="router.push({ name: 'proforma.index' })"
                  class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  :disabled="isLoading"
                  class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50"
                >
                  <span v-if="isLoading">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>
                    <i class="fas fa-save"></i> {{ editingId ? "Modifier" : "Enregistrer" }}
                  </span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
