<!-- src/views/credit-notes/CreateCreditNote.vue -->
<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import TextInput from "@/components/TextInput.vue";
import TextareaInput from "@/components/TextareaInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import DocumentLineItem from "@/components/DocumentLineItem.vue";
import DocumentTotals from "@/components/DocumentTotals.vue";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const isLoading = ref(false);
const isSaving = ref(false);
const isEditMode = computed(() => !!route.params.id);

const lookupData = ref({
  products: [],
  tax_rates: [],
  product_types: [],
  bank_accounts: [],
  customers: [],
  payment_conditions: [],
  payment_modes: [],
  late_fee_interests: [],
  invoices: [],
  defaults: {},
});

const defaultTaxRateValue = ref(20);
const defaultProductTypeId = ref("");

const form = reactive({
  customer_id: null,
  bank_account_id: null,
  invoice_id: null,
  date: "",
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
  items: [],
  global_discount_type: "percentage",
  global_discount_value: 0,
});

const errors = reactive({
  customer_id: "",
  invoice_id: "",
  date: "",
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
    const { data } = await axios.get("/api/credit-notes/create");
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

    if (data.tax_rates && data.tax_rates.length > 0) {
      const defaultTax = data.tax_rates.find((tr) => tr.is_default === true);
      defaultTaxRateValue.value = defaultTax ? defaultTax.rate : 20;
    }

    if (data.product_types && data.product_types.length > 0) {
      const defaultProductType = data.product_types.find(
        (pt) => pt.is_default === true,
      );
      defaultProductTypeId.value = defaultProductType
        ? defaultProductType.id
        : data.product_types[0].id;
    }
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de charger les données.",
    );
  } finally {
    isLoading.value = false;
  }
};

const fetchCreditNote = async (id) => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/credit-notes/${id}`);

    form.customer_id = data.customer_id;
    form.invoice_id = data.invoice_id;
    form.date = data.date;
    form.notes = data.notes || "";
    form.terms = data.terms || "";
    form.intro_text = data.intro_text || "";
    form.footer_text = data.footer_text || "";
    form.conclusion_text = data.conclusion_text || "";
    form.items = data.items || [];

    if (data.global_discount) {
      form.global_discount_type = data.global_discount.type || "percentage";
      form.global_discount_value = data.global_discount.value || 0;
    }
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de charger l'avoir.",
    );
    router.push({ name: "credit-note.index" });
  } finally {
    isLoading.value = false;
  }
};

const addItem = () => {
  form.items.push({
    id: Date.now(),
    product_id: null,
    label: "",
    description: "",
    quantity: 1,
    unit_price: 0,
    tax_rate: defaultTaxRateValue.value,
    product_type_id: defaultProductTypeId.value,
    discount_type: "percentage",
    discount_value: 0,
  });
};

const removeItem = (id) => {
  const index = form.items.findIndex((item) => item.id === id);
  if (index > -1) {
    form.items.splice(index, 1);
  }
};

const updateItem = (id, field, value) => {
  const index = form.items.findIndex((item) => item.id === id);
  if (index > -1) {
    form.items[index][field] = value;
  }
};

const subtotal = computed(() => {
  return form.items.reduce((sum, item) => {
    const discount = item.discount_type === "percentage"
      ? item.unit_price * item.quantity * (item.discount_value / 100)
      : item.discount_value;
    return sum + item.unit_price * item.quantity - discount;
  }, 0);
});

const totalTax = computed(() => {
  return form.items.reduce((sum, item) => {
    const discount = item.discount_type === "percentage"
      ? item.unit_price * item.quantity * (item.discount_value / 100)
      : item.discount_value;
    const base = item.unit_price * item.quantity - discount;
    return sum + base * (item.tax_rate / 100);
  }, 0);
});

const globalDiscountAmount = computed(() => {
  if (form.global_discount_type === "percentage") {
    return subtotal.value * (form.global_discount_value / 100);
  }
  return form.global_discount_value;
});

const totalTtc = computed(() => {
  return subtotal.value + totalTax.value - globalDiscountAmount.value;
});

const validate = () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));

  if (!form.customer_id) {
    errors.customer_id = "Le client est requis.";
    return false;
  }

  if (!form.date) {
    errors.date = "La date est requise.";
    return false;
  }

  if (form.items.length === 0) {
    errors.items = "Au moins un article est requis.";
    return false;
  }

  for (let i = 0; i < form.items.length; i++) {
    const item = form.items[i];
    if (!item.label || item.label.trim() === "") {
      errors.items = `L'article ${i + 1} doit avoir une désignation.`;
      return false;
    }
    if (item.quantity <= 0) {
      errors.items = `L'article ${i + 1} doit avoir une quantité positive.`;
      return false;
    }
    if (item.unit_price < 0) {
      errors.items = `L'article ${i + 1} ne peut pas avoir un prix négatif.`;
      return false;
    }
  }

  return true;
};

const saveCreditNote = async (finalize = false) => {
  if (!validate()) return;

  isSaving.value = true;
  try {
    const payload = {
      customer_id: form.customer_id,
      invoice_id: form.invoice_id,
      date: form.date,
      notes: form.notes,
      terms: form.terms,
      intro_text: form.intro_text,
      footer_text: form.footer_text,
      conclusion_text: form.conclusion_text,
      items: form.items.map((item) => ({
        product_id: item.product_id,
        label: item.label,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate: item.tax_rate,
        product_type_id: item.product_type_id,
        discount_type: item.discount_type,
        discount_value: item.discount_value,
      })),
      global_discount: {
        type: form.global_discount_type,
        value: form.global_discount_value,
      },
    };

    let response;
    if (isEditMode.value) {
      response = await axios.put(`/api/credit-notes/${route.params.id}`, payload);
    } else {
      response = await axios.post("/api/credit-notes", payload);
    }

    if (finalize) {
      await axios.put(`/api/credit-notes/${response.data.id}/finalize`);
      success(
        "Créé et finalisé !",
        `L'avoir ${response.data.number} a été créé et finalisé.`,
      );
    } else {
      success(
        "Enregistré !",
        isEditMode.value
          ? "L'avoir a été mis à jour."
          : "L'avoir a été enregistré en brouillon.",
      );
    }

    if (isEditMode.value) {
      await fetchCreditNote(route.params.id);
    } else {
      router.push({ name: "credit-note.edit", params: { id: response.data.id } });
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors;
      Object.keys(serverErrors).forEach((key) => {
        if (errors.hasOwnProperty(key)) {
          errors[key] = serverErrors[key][0];
        }
      });
    } else {
      errors.server =
        err.response?.data?.message || "Une erreur est survenue.";
    }
  } finally {
    isSaving.value = false;
  }
};

const saveAndFinalize = () => saveCreditNote(true);
const saveDraft = () => saveCreditNote(false);

const cancelEdit = async () => {
  if (!isEditMode.value) {
    const result = await confirm(
      "Quitter sans enregistrer",
      "Les données non enregistrées seront perdues. Continuer ?",
    );
    if (!result.isConfirmed) return;
  }
  router.push({ name: "credit-note.index" });
};

onMounted(async () => {
  await fetchLookups();
  if (isEditMode.value) {
    await fetchCreditNote(route.params.id);
  } else {
    addItem();
  }
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <!-- Header -->
          <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-xl font-bold text-[#062121]">
                  {{ isEditMode ? "Modifier l'Avoir" : "Nouvel Avoir" }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                  {{
                    isEditMode
                      ? "Modifiez les informations de l'avoir"
                      : "Créez un nouvel avoir pour votre client"
                  }}
                </p>
              </div>
              <button
                @click="cancelEdit"
                class="text-gray-500 hover:text-gray-700 transition-colors"
              >
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>
          </div>

          <!-- Content -->
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
            <p class="mt-2 text-gray-500">Chargement...</p>
          </div>

          <div v-else class="p-6 lg:p-8">
            <!-- Server Error -->
            <div
              v-if="errors.server"
              class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg"
            >
              <p class="text-sm text-red-600">{{ errors.server }}</p>
            </div>

            <!-- Customer & Invoice Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <InputLabel value="Client *" />
                <CustomSelect
                  v-model="form.customer_id"
                  :options="
                    lookupData.customers.map((c) => ({
                      value: c.id,
                      label:
                        c.type === 'b2b'
                          ? c.customerable.legal_name
                          : c.customerable.name,
                    }))
                  "
                  placeholder="Sélectionner un client"
                  class="mt-1"
                />
                <InputError :message="errors.customer_id" class="mt-1" />
              </div>

              <div>
                <InputLabel value="Facture associée (optionnel)" />
                <CustomSelect
                  v-model="form.invoice_id"
                  :options="
                    lookupData.invoices.map((inv) => ({
                      value: inv.id,
                      label: `Facture ${inv.number}`,
                    }))
                  "
                  placeholder="Sélectionner une facture"
                  class="mt-1"
                />
                <InputError :message="errors.invoice_id" class="mt-1" />
              </div>
            </div>

            <!-- Date -->
            <div class="mb-6">
              <InputLabel value="Date de l'avoir *" />
              <TextInput
                v-model="form.date"
                type="date"
                class="mt-1"
              />
              <InputError :message="errors.date" class="mt-1" />
            </div>

            <!-- Items Section -->
            <div class="mb-6">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-[#062121]">Articles</h3>
                <button
                  @click="addItem"
                  type="button"
                  class="text-sm text-[#062121] hover:text-[#C5F82A] flex items-center gap-1"
                >
                  <i class="fas fa-plus"></i> Ajouter un article
                </button>
              </div>

              <div
                v-if="form.items.length === 0"
                class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300"
              >
                <p class="text-gray-500 text-sm">
                  Aucun article. Cliquez sur "Ajouter un article".
                </p>
              </div>

              <div v-else class="space-y-3">
                <DocumentLineItem
                  v-for="item in form.items"
                  :key="item.id"
                  :item="item"
                  :products="lookupData.products"
                  :taxRates="lookupData.tax_rates"
                  :productTypes="lookupData.product_types"
                  @update="(field, value) => updateItem(item.id, field, value)"
                  @remove="removeItem(item.id)"
                />
              </div>

              <InputError :message="errors.items" class="mt-1" />
            </div>

            <!-- Totals -->
            <DocumentTotals
              :subtotal="subtotal"
              :totalTax="totalTax"
              :globalDiscountAmount="globalDiscountAmount"
              :totalTtc="totalTtc"
            />

            <!-- Text Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
              <div>
                <InputLabel value="Texte d'introduction" />
                <TextareaInput
                  v-model="form.intro_text"
                  rows="3"
                  class="mt-1"
                  placeholder="Texte qui apparaîtra en haut du document..."
                />
                <InputError :message="errors.intro_text" class="mt-1" />
              </div>

              <div>
                <InputLabel value="Notes internes" />
                <TextareaInput
                  v-model="form.notes"
                  rows="3"
                  class="mt-1"
                  placeholder="Notes internes (n'apparaîtront pas sur le document)..."
                />
                <InputError :message="errors.notes" class="mt-1" />
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
              <button
                @click="saveDraft"
                :disabled="isSaving"
                class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i v-if="isSaving" class="fas fa-spinner fa-spin mr-2"></i>
                {{ isSaving ? "Enregistrement..." : "Enregistrer brouillon" }}
              </button>
              <button
                @click="saveAndFinalize"
                :disabled="isSaving"
                class="px-6 py-2.5 bg-[#C5F82A] text-[#062121] rounded-lg text-sm font-bold hover:bg-[#B5E81A] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i v-if="isSaving" class="fas fa-spinner fa-spin mr-2"></i>
                {{ isSaving ? "Finalisation..." : "Finaliser" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
