<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import TextInput from "@/components/TextInput.vue";
import TextareaInput from "@/components/TextareaInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";

const router = useRouter();
const authStore = useAuthStore();
const isLoading = ref(false);
const isSaving = ref(false);

const lookupData = ref({
  products: [],
  tax_rates: [],
  product_types: [],
  bank_accounts: [],
  customers: [],
  payment_conditions: [],
  payment_modes: [],
  late_fee_interests: [],
  defaults: {},
});

const defaultTaxRateValue = ref(20);
const defaultProductTypeId = ref("");

const form = reactive({
  customer_id: null,
  bank_account_id: null,
  date: new Date().toISOString().split("T")[0],
  valid_until: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  items: [],
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
  global_discount_type: "percentage",
  global_discount_value: 0,
});

const errors = reactive({
  customer_id: "",
  date: "",
  valid_until: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  items: "",
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
  global_discount_type: "",
  global_discount_value: "",
  server: "",
});

const fetchLookups = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const { data } = await axios.get("/api/quote/create", { params });
    lookupData.value = data;
    form.intro_text = data.defaults.intro_text || "";
    form.footer_text = data.defaults.footer_text || "";
    form.terms = data.defaults.terms || "";
    form.conclusion_text = data.defaults.conclusion_text || "";

    const defaultPaymentCondition = data.payment_conditions.find(
      (pc) => pc.is_default === true,
    );
    if (defaultPaymentCondition)
      form.payment_condition = defaultPaymentCondition.label;

    const defaultPaymentMode = data.payment_modes.find(
      (pm) => pm.is_default === true,
    );
    if (defaultPaymentMode) form.payment_mode = defaultPaymentMode.label;

    const defaultLateFeeInterest = data.late_fee_interests.find(
      (lfi) => lfi.is_default === true,
    );
    if (defaultLateFeeInterest)
      form.late_fee_interest = defaultLateFeeInterest.label;

    const defaultBankAccount = data.bank_accounts.find(
      (ba) => ba.is_default === true,
    );
    if (defaultBankAccount) form.bank_account_id = defaultBankAccount.id;

    const defaultTaxRate = data.tax_rates.find((tr) => tr.is_default === true);
    if (defaultTaxRate) defaultTaxRateValue.value = defaultTaxRate.rate;

    const defaultProductType = data.product_types.find(
      (pt) => pt.is_default === true,
    );
    if (defaultProductType)
      defaultProductTypeId.value = String(defaultProductType.id);

    if (form.items.length === 0) {
      form.items.push(createItem());
    }
  } catch {
    error("Erreur", "Impossible de charger les données.");
  } finally {
    isLoading.value = false;
  }
};

const createItem = () => ({
  product_id: null,
  product_type: defaultProductTypeId.value,
  designation: "",
  quantity: 1,
  unit_price: 0,
  tax_rate: defaultTaxRateValue.value,
  discount_type: "percentage",
  discount_value: 0,
  showProductDropdown: false,
  showTaxDropdown: false,
  productSearch: "",
  taxSearch: "",
});

const addLine = () => form.items.push(createItem());
const removeLine = (index) => {
  if (form.items.length > 1) form.items.splice(index, 1);
};

const toggleProductDropdown = (index) => {
  const item = form.items[index];
  item.showProductDropdown = !item.showProductDropdown;
  if (item.showProductDropdown) {
    item.productSearch = item.designation || "";
  } else {
    item.productSearch = "";
  }
};

const toggleTaxDropdown = (index) => {
  const item = form.items[index];
  item.showTaxDropdown = !item.showTaxDropdown;
  if (item.showTaxDropdown) {
    item.taxSearch = String(item.tax_rate || "");
  } else {
    item.taxSearch = "";
  }
};

const selectProduct = (index, product) => {
  const item = form.items[index];
  item.product_id = product.id;
  item.designation = product.name;
  item.unit_price = product.price;
  item.product_type = product.product_category_id
    ? String(product.product_category_id)
    : "";
  item.showProductDropdown = false;
  item.productSearch = "";
};

const selectTaxRate = (index, taxRate) => {
  const item = form.items[index];
  item.tax_rate = taxRate.rate;
  item.showTaxDropdown = false;
  item.taxSearch = "";
};

const filterProducts = (index) => {
  const item = form.items[index];
  const query = item.productSearch.toLowerCase().trim();
  if (!query) return lookupData.value.products;
  return lookupData.value.products.filter((p) =>
    p.name.toLowerCase().includes(query),
  );
};

const filterTaxRates = (index) => {
  const item = form.items[index];
  const query = item.taxSearch.toLowerCase().trim();
  if (!query) return lookupData.value.tax_rates;
  return lookupData.value.tax_rates.filter(
    (t) =>
      String(t.rate).includes(query) || t.libelle.toLowerCase().includes(query),
  );
};

const lineTotalHt = (item) => {
  const subtotal =
    (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
  let discount = 0;
  if (item.discount_type && item.discount_value > 0) {
    if (item.discount_type === "percentage") {
      discount = subtotal * (item.discount_value / 100);
    } else {
      discount = item.discount_value;
    }
  }
  return subtotal - discount;
};

const lineTotalTva = (item) => {
  return (lineTotalHt(item) * (parseFloat(item.tax_rate) || 0)) / 100;
};

const totals = computed(() => {
  let ht = 0,
    tva = 0;
  form.items.forEach((item) => {
    ht += lineTotalHt(item);
    tva += lineTotalTva(item);
  });
  let globalDiscount = 0;
  if (form.global_discount_type && form.global_discount_value > 0) {
    if (form.global_discount_type === "percentage") {
      globalDiscount = ht * (form.global_discount_value / 100);
    } else {
      globalDiscount = form.global_discount_value;
    }
  }
  const htAfterDiscount = ht - globalDiscount;
  let tvaAfterDiscount = 0;
  if (ht > 0) {
    tvaAfterDiscount = tva * (htAfterDiscount / ht);
  } else {
    tvaAfterDiscount = 0;
  }
  return {
    ht,
    tva,
    globalDiscount,
    htAfterDiscount,
    tvaAfterDiscount,
    ttc: htAfterDiscount + tvaAfterDiscount,
  };
});

const fmt = (n) => {
  if (isNaN(n) || !isFinite(n)) return "0.00";
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  if (!form.customer_id) {
    errors.customer_id = "Veuillez sélectionner un client.";
    return;
  }
  if (form.items.some((i) => !i.designation.trim())) {
    errors.items = "Toutes les lignes doivent avoir une désignation.";
    return;
  }

  const confirmed = await confirm(
    "Enregistrer le devis",
    "Le devis sera enregistré en tant que brouillon. Vous pourrez le finaliser ultérieurement."
  );
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const payload = { ...form };
    if (companyId) {
      payload.company_id = companyId;
    }
    await axios.post("/api/quotes", payload);
    success("Devis enregistré", "Le devis a été enregistré en tant que brouillon.");
    router.push("/quote");
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((key) => {
        if (key in errors) errors[key] = e[key][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement du devis.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => {
  fetchLookups();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <button
              class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]"
            >
              <i class="fas fa-file-alt"></i>
              Créer un Devis
            </button>
          </div>

          <div v-if="isLoading" class="text-center py-12">
            <svg
              class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
              />
            </svg>
            <p class="mt-2 text-gray-500">Chargement du formulaire...</p>
          </div>

          <form v-else @submit.prevent="submit" class="p-6 lg:p-8 space-y-8">
            <InputError class="mt-2" :message="errors.server" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="customer_id" value="Client *" />
                <CustomSelect
                  id="customer_id"
                  v-model="form.customer_id"
                  :options="
                    lookupData.customers.length
                      ? lookupData.customers.map((c) => ({
                          label:
                            c.customerable?.name ||
                            c.customerable?.legal_name ||
                            'Client',
                          value: c.id,
                        }))
                      : [{ label: 'Aucun client', value: null }]
                  "
                  label-key="label"
                  value-key="value"
                  :placeholder="
                    lookupData.customers.length
                      ? 'Sélectionner un client'
                      : 'Aucun client'
                  "
                  :disabled="!lookupData.customers.length"
                />
                <InputError class="mt-2" :message="errors.customer_id" />
              </div>
              <div>
                <InputLabel for="date" value="Date d'émission *" />
                <TextInput id="date" type="date" v-model="form.date" required />
                <InputError class="mt-2" :message="errors.date" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="valid_until" value="Date de validité" />
                <TextInput
                  id="valid_until"
                  type="date"
                  v-model="form.valid_until"
                />
                <InputError class="mt-2" :message="errors.valid_until" />
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-3">
                <h3
                  class="text-sm font-bold text-[#062121] uppercase tracking-wider"
                >
                  Lignes du devis
                </h3>
                <button
                  type="button"
                  @click="addLine"
                  class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
                >
                  <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
                </button>
              </div>
              <InputError class="mb-3" :message="errors.items" />

              <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th
                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[22%]"
                      >
                        Produit
                      </th>
                      <th
                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]"
                      >
                        Qté
                      </th>
                      <th
                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]"
                      >
                        P.U. HT
                      </th>
                      <th
                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]"
                      >
                        TVA %
                      </th>
                      <th
                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]"
                      >
                        Réduction
                      </th>
                      <th
                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]"
                      >
                        Total HT
                      </th>
                      <th class="px-4 py-3 w-[5%]"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="(item, index) in form.items"
                      :key="index"
                      :class="index % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'"
                    >
                      <td class="px-4 py-3 relative z-10">
                        <div class="relative">
                          <input
                            type="text"
                            v-model="item.designation"
                            @focus="toggleProductDropdown(index)"
                            @blur="
                              setTimeout(() => {
                                item.showProductDropdown = false;
                              }, 200)
                            "
                            placeholder="— Saisie libre —"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                          />
                          <div
                            v-if="item.showProductDropdown"
                            class="absolute left-0 z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                          >
                            <div class="p-2 border-b border-gray-100">
                              <input
                                type="text"
                                v-model="item.productSearch"
                                @input="filterProducts(index)"
                                placeholder="Rechercher un produit..."
                                class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:border-[#C5F82A]"
                              />
                            </div>
                            <div
                              v-for="product in filterProducts(index)"
                              :key="product.id"
                              @mousedown.prevent="selectProduct(index, product)"
                              class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center justify-between"
                            >
                              <span>{{ product.name }}</span>
                              <span class="text-xs text-gray-400 font-mono"
                                >{{ product.price }} DH</span
                              >
                            </div>
                            <div
                              v-if="filterProducts(index).length === 0"
                              class="px-4 py-2 text-sm text-gray-500 italic text-center"
                            >
                              Aucun produit trouvé
                            </div>
                          </div>
                        </div>
                      </td>

                      <td class="px-4 py-3">
                        <input
                          type="number"
                          v-model.number="item.quantity"
                          min="0.01"
                          step="0.01"
                          class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                        />
                      </td>

                      <td class="px-4 py-3">
                        <input
                          type="number"
                          v-model.number="item.unit_price"
                          min="0"
                          step="0.01"
                          class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                        />
                      </td>

                      <td class="px-4 py-3 relative z-10">
                        <div class="relative">
                          <input
                            type="number"
                            v-model.number="item.tax_rate"
                            @focus="toggleTaxDropdown(index)"
                            @blur="
                              setTimeout(() => {
                                item.showTaxDropdown = false;
                              }, 200)
                            "
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                            placeholder="%"
                          />
                          <div
                            v-if="item.showTaxDropdown"
                            class="absolute left-0 z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                          >
                            <div class="p-2 border-b border-gray-100">
                              <input
                                type="text"
                                v-model="item.taxSearch"
                                @input="filterTaxRates(index)"
                                placeholder="Rechercher un taux..."
                                class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:border-[#C5F82A]"
                              />
                            </div>
                            <div
                              v-for="tax in filterTaxRates(index)"
                              :key="tax.id"
                              @mousedown.prevent="selectTaxRate(index, tax)"
                              class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center justify-between"
                            >
                              <span>{{ tax.libelle }}</span>
                              <span class="text-xs text-gray-500 font-mono"
                                >{{ tax.rate }}%</span
                              >
                            </div>
                            <div
                              v-if="filterTaxRates(index).length === 0"
                              class="px-4 py-2 text-sm text-gray-500 italic text-center"
                            >
                              Aucun taux trouvé
                            </div>
                          </div>
                        </div>
                      </td>

                      <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                          <input
                            type="number"
                            v-model.number="item.discount_value"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                            placeholder="0.00"
                          />
                          <CustomSelect
                            v-model="item.discount_type"
                            :options="[
                              { label: '%', value: 'percentage' },
                              { label: 'DH', value: 'fixed' },
                            ]"
                            label-key="label"
                            value-key="value"
                            placeholder="Type"
                            class="w-16"
                          />
                        </div>
                      </td>

                      <td class="px-4 py-3 text-center">
                        <span
                          class="text-sm font-semibold text-[#062121] font-mono"
                        >
                          {{ fmt(lineTotalHt(item)) }}
                        </span>
                      </td>

                      <td class="px-4 py-3 text-center">
                        <button
                          type="button"
                          @click="removeLine(index)"
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

            <div class="flex justify-end">
              <div class="w-full md:w-1/2">
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100"
                  >
                    <span class="text-sm text-gray-500">Total HT</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ fmt(totals.ht) }} DH</span
                    >
                  </div>
                  <div
                    class="px-5 py-3 flex justify-between items-center border-b border-gray-100"
                  >
                    <span class="text-sm text-gray-500">Remise générale</span>
                    <div class="flex items-center gap-2">
                      <input
                        type="number"
                        v-model.number="form.global_discount_value"
                        min="0"
                        step="0.01"
                        class="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                        placeholder="0.00"
                      />
                      <CustomSelect
                        v-model="form.global_discount_type"
                        :options="[
                          { label: '%', value: 'percentage' },
                          { label: 'DH', value: 'fixed' },
                        ]"
                        label-key="label"
                        value-key="value"
                        placeholder="Type"
                        class="w-16"
                      />
                      <span
                        class="text-sm font-semibold text-red-600 whitespace-nowrap"
                      >
                        - {{ fmt(totals.globalDiscount) }}
                      </span>
                    </div>
                  </div>
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
                  >
                    <span class="text-sm text-gray-500"
                      >Total HT après remise</span
                    >
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ fmt(totals.htAfterDiscount) }} DH</span
                    >
                  </div>
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
                  >
                    <span class="text-sm text-gray-500">TVA</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ fmt(totals.tvaAfterDiscount) }} DH</span
                    >
                  </div>
                  <div class="px-5 py-4 flex justify-between bg-gray-50">
                    <span
                      class="text-sm font-bold text-[#062121] uppercase tracking-wide"
                      >Total TTC</span
                    >
                    <span class="text-lg font-black text-[#062121] font-mono"
                      >{{ fmt(totals.ttc) }} DH</span
                    >
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <h3
                class="text-sm font-bold text-[#062121] uppercase tracking-wider"
              >
                Règlement
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel
                    for="payment_condition"
                    value="Condition de règlement"
                  />
                  <CustomSelect
                    id="payment_condition"
                    v-model="form.payment_condition"
                    :options="
                      lookupData.payment_conditions.map((pc) => ({
                        label: pc.label,
                        value: pc.label,
                      }))
                    "
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner"
                  />
                  <InputError class="mt-2" :message="errors.payment_condition" />
                </div>
                <div>
                  <InputLabel for="payment_mode" value="Mode de règlement" />
                  <CustomSelect
                    id="payment_mode"
                    v-model="form.payment_mode"
                    :options="
                      lookupData.payment_modes.map((pm) => ({
                        label: pm.label,
                        value: pm.label,
                      }))
                    "
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner"
                  />
                  <InputError class="mt-2" :message="errors.payment_mode" />
                </div>
                <div>
                  <InputLabel
                    for="late_fee_interest"
                    value="Intérêts de retard"
                  />
                  <CustomSelect
                    id="late_fee_interest"
                    v-model="form.late_fee_interest"
                    :options="
                      lookupData.late_fee_interests.map((lfi) => ({
                        label: lfi.label,
                        value: lfi.label,
                      }))
                    "
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner"
                  />
                  <InputError class="mt-2" :message="errors.late_fee_interest" />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel
                    for="bank_account_id"
                    value="Compte bancaire (RIB)"
                  />
                  <CustomSelect
                    id="bank_account_id"
                    v-model="form.bank_account_id"
                    :options="[
                      { label: 'Aucun RIB', value: null },
                      ...lookupData.bank_accounts.map((b) => ({
                        label: `${b.label} (${b.bank_name})`,
                        value: b.id,
                      })),
                    ]"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner un compte"
                  />
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <h3
                class="text-sm font-bold text-[#062121] uppercase tracking-wider"
              >
                Textes affichés sur le document
              </h3>
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <InputLabel for="intro_text" value="Texte d'introduction" />
                  <TextareaInput
                    id="intro_text"
                    v-model="form.intro_text"
                    rows="3"
                    placeholder="Texte d'introduction..."
                  />
                  <InputError class="mt-2" :message="errors.intro_text" />
                </div>
                <div>
                  <InputLabel
                    for="conclusion_text"
                    value="Texte de conclusion"
                  />
                  <TextareaInput
                    id="conclusion_text"
                    v-model="form.conclusion_text"
                    rows="3"
                    placeholder="Texte de conclusion..."
                  />
                  <InputError class="mt-2" :message="errors.conclusion_text" />
                </div>
                <div>
                  <InputLabel for="footer_text" value="Pied de page" />
                  <TextareaInput
                    id="footer_text"
                    v-model="form.footer_text"
                    rows="3"
                    placeholder="Pied de page..."
                  />
                  <InputError class="mt-2" :message="errors.footer_text" />
                </div>
                <div>
                  <InputLabel for="terms" value="Conditions générales" />
                  <TextareaInput
                    id="terms"
                    v-model="form.terms"
                    rows="3"
                    placeholder="Conditions générales..."
                  />
                  <InputError class="mt-2" :message="errors.terms" />
                </div>
                <div>
                  <InputLabel for="notes" value="Notes" />
                  <TextareaInput
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    placeholder="Notes..."
                  />
                  <InputError class="mt-2" :message="errors.notes" />
                </div>
              </div>
            </div>

            <div
              class="flex flex-wrap justify-end gap-3 pt-6 border-t border-gray-100"
            >
              <button
                type="button"
                @click="router.back()"
                class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all"
              >
                Annuler
              </button>
              <button
                type="submit"
                :disabled="isSaving"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50"
              >
                <i class="fas fa-save"></i> Enregistrer
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.overflow-x-auto {
  overflow: visible !important;
}
table,
tbody,
tr,
td {
  overflow: visible !important;
}
</style>