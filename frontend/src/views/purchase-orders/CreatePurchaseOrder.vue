<script setup>
import { ref, reactive, computed, onMounted, watch } from "vue";
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
import LinkedDocumentInfo from "@/components/LinkedDocumentInfo.vue";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const isLoading = ref(false);
const isSaving = ref(false);
const isFromQuote = ref(false);
const isEditMode = computed(() => !!route.params.id);

const linkedDocument = ref(null);
const documentType = ref(null);

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
  expected_date: "",
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
  parent_document_id: null,
});

const errors = reactive({
  customer_id: "",
  date: "",
  expected_date: "",
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
    const { data } = await axios.get("/api/purchase-orders/create");
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

const fetchDocument = async (documentId) => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/purchase-orders/${documentId}`);
    const doc = data.document || data;
    if (!doc) {
      error("Erreur", "Document introuvable.");
      router.push("/purchase-orders");
      return;
    }

    // Populate form with document data
    form.customer_id = doc.customer_id;
    form.bank_account_id = doc.bank_account_id;
    form.date = doc.date || new Date().toISOString().split("T")[0];
    form.expected_date = doc.expected_date || "";
    form.payment_condition = doc.payment_condition || "";
    form.payment_mode = doc.payment_mode || "";
    form.late_fee_interest = doc.late_fee_interest || "";
    form.notes = doc.notes || "";
    form.terms = doc.terms || "";
    form.intro_text = doc.intro_text || "";
    form.footer_text = doc.footer_text || "";
    form.conclusion_text = doc.conclusion_text || "";
    form.global_discount_type = doc.global_discount_type || "percentage";
    form.global_discount_value = doc.global_discount_value || 0;

    // Populate items
    form.items = (doc.items || []).map((item) => ({
      product_id: item.product_id,
      product_type: item.product_type,
      designation: item.designation || item.description || "",
      quantity: item.quantity,
      unit_price: item.unit_price,
      tax_rate: item.tax_rate,
      discount_type: item.discount_type || "percentage",
      discount_value: item.discount_value || 0,
    }));
  } catch (err) {
    if (err.response?.status === 404) {
      error("Document introuvable", "Le document demandé n'existe pas ou a été supprimé.");
    } else {
      error("Erreur", "Impossible de charger les données du document.");
    }
    router.push("/purchase-orders");
  } finally {
    isLoading.value = false;
  }
};

const loadQuoteData = async (quoteId) => {
  try {
    const { data } = await axios.get(`/api/quotes/${quoteId}`);
    const doc = data.document;
    if (!doc) return;

    isFromQuote.value = true;
    linkedDocument.value = doc;
    documentType.value = 'quote';

    form.customer_id = doc.customer_id;
    form.bank_account_id = doc.bank_account_id;
    form.parent_document_id = doc.id;
    form.payment_condition = doc.payment_condition || form.payment_condition;
    form.payment_mode = doc.payment_mode || form.payment_mode;
    form.late_fee_interest = doc.late_fee_interest || form.late_fee_interest;
    form.notes = doc.notes || "";
    form.terms = doc.terms || "";
    form.intro_text = doc.intro_text || form.intro_text;
    form.footer_text = doc.footer_text || form.footer_text;
    form.conclusion_text = doc.conclusion_text || form.conclusion_text;
    form.global_discount_type = doc.global_discount_type || "percentage";
    form.global_discount_value = doc.global_discount_value || 0;

    form.items = (doc.items || []).map((item) => ({
      product_id: item.product_id,
      product_type: item.product_type,
      designation: item.designation || item.description || "",
      quantity: item.quantity,
      unit_price: item.unit_price,
      tax_rate: item.tax_rate,
      discount_type: item.discount_type || "percentage",
      discount_value: item.discount_value || 0,
    }));
  } catch (err) {
    if (err.response?.status === 404) {
      error("Devis introuvable", "Le devis demandé n'existe pas ou a été supprimé.");
      router.push("/quotes");
    } else {
      error("Erreur", "Impossible de charger les données du devis.");
    }
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
});

const addLine = () => {
  if (isFromQuote.value) return;
  form.items.push(createItem());
};

const removeLine = (index) => {
  if (isFromQuote.value) return;
  if (form.items.length > 1) form.items.splice(index, 1);
};

const handleSelectProduct = ({ index, product }) => {
  if (isFromQuote.value) return;

  const currentItem = form.items[index];

  let productTypeId = "";
  if (product.category_id) {
    const categoryExists = lookupData.value.product_types.some(
      (pt) => String(pt.id) === String(product.category_id)
    );
    if (categoryExists) {
      productTypeId = String(product.category_id);
    }
  }

  const updatedItem = {
    ...currentItem,
    product_id: product.id,
    designation: product.name,
    unit_price: product.price,
    product_type: productTypeId,
  };
  form.items[index] = updatedItem;
};

const handleSelectTaxRate = ({ index, taxRate }) => {
  if (isFromQuote.value) return;
  const item = form.items[index];
  item.tax_rate = taxRate.rate;
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

const validateForm = () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  if (!form.customer_id) {
    errors.customer_id = "Veuillez sélectionner un client.";
    return false;
  }
  if (form.items.some((i) => !i.designation.trim())) {
    errors.items = "Toutes les lignes doivent avoir une désignation.";
    return false;
  }
  return true;
};

const submit = async () => {
  if (!validateForm()) return;

  const confirmed = await confirm(
    isEditMode.value ? "Mettre à jour la commande" : "Enregistrer la commande",
    isEditMode.value
      ? "Les modifications seront enregistrées."
      : "La commande sera enregistrée en tant que brouillon. Vous pourrez la finaliser ultérieurement.",
  );
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    const payload = { ...form, parent_document_id: form.parent_document_id || undefined };

    if (isEditMode.value) {
      await axios.put(`/api/purchase-orders/${route.params.id}`, payload);
      success("Commande mise à jour", "La commande a été mise à jour avec succès.");
    } else {
      await axios.post("/api/purchase-orders", payload);
      success("Commande enregistrée", "La commande a été enregistrée en tant que brouillon.");
    }
    router.push("/purchase-orders");
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((key) => {
        if (key in errors) errors[key] = e[key][0];
      });
    } else {
      errors.server =
        "Une erreur est survenue lors de l'enregistrement de la commande.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

const saveAndFinalize = async () => {
  if (!validateForm()) return;

  const confirmed = await confirm(
    isEditMode.value ? "Finaliser les modifications" : "Finaliser la commande",
    isEditMode.value
      ? "La commande sera finalisée avec les modifications."
      : "La commande sera finalisée et un numéro lui sera attribué.",
  );
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    const payload = { ...form, parent_document_id: form.parent_document_id || undefined };

    if (isEditMode.value) {
      await axios.put(`/api/purchase-orders/${route.params.id}`, payload);
      await axios.put(`/api/purchase-orders/${route.params.id}/finalize`);
      success("Finalisé !", "La commande a été mise à jour et finalisée.");
    } else {
      const response = await axios.post("/api/purchase-orders", payload);
      const createdDoc = response.data;
      await axios.put(`/api/purchase-orders/${createdDoc.id}/finalize`);
      success("Finalisé !", `La commande ${createdDoc.number} a été finalisée.`);
    }
    router.push("/purchase-orders");
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((key) => {
        if (key in errors) errors[key] = e[key][0];
      });
    } else {
      errors.server = "Une erreur est survenue.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

const loadLinkedDocument = async () => {
  const quoteId = route.query.quote_id;
  console.log('[CreatePurchaseOrder] quoteId:', quoteId);

  if (quoteId) {
    await loadQuoteData(parseInt(quoteId));
  }
};

onMounted(async () => {
  await fetchLookups();

  // Load document data if in edit mode
  if (isEditMode.value) {
    await fetchDocument(route.params.id);
  } else {
    await loadLinkedDocument();
  }
});

watch(() => route.query, async () => {
  if (!isLoading.value) {
    await loadLinkedDocument();
  }
}, { immediate: false });
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
              <i class="fas fa-file-contract"></i>
              {{ isEditMode ? "Modifier la Commande" : "Créer une Commande" }}
            </button>
          </div>

          <div v-if="isLoading" class="text-center py-12">
            <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-2 text-gray-500">Chargement du formulaire...</p>
          </div>

          <form v-else @submit.prevent="submit" class="p-6 lg:p-8 space-y-8">
            <InputError class="mt-2" :message="errors.server" />

            <!-- Document lié -->
            <LinkedDocumentInfo
              v-if="linkedDocument"
              :document="linkedDocument"
              :document-type="documentType"
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="customer_id" value="Client *" />
                <CustomSelect
                  id="customer_id"
                  v-model="form.customer_id"
                  :options="
                    lookupData.customers.length
                      ? lookupData.customers.map((c) => ({
                          label: c.customerable?.name || c.customerable?.legal_name || 'Client',
                          value: c.id,
                        }))
                      : [{ label: 'Aucun client', value: null }]
                  "
                  label-key="label"
                  value-key="value"
                  :placeholder="lookupData.customers.length ? 'Sélectionner un client' : 'Aucun client'"
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
                <InputLabel for="expected_date" value="Date de livraison prévue" />
                <TextInput id="expected_date" type="date" v-model="form.expected_date" />
                <InputError class="mt-2" :message="errors.expected_date" />
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">
                  Lignes de la commande
                </h3>
                <button
                  v-if="!isFromQuote"
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
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[18%]">Produit</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">Type</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]">Qté</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]">P.U. HT</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]">TVA %</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]">Réduction</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]">Total HT</th>
                      <th class="px-4 py-3 w-[5%]"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <DocumentLineItem
                      v-for="(item, index) in form.items"
                      :key="index"
                      :item="item"
                      :index="index"
                      :products="lookupData.products"
                      :product-types="lookupData.product_types"
                      :tax-rates="lookupData.tax_rates"
                      :disabled="isFromQuote"
                      @update:item="(newItem) => form.items[index] = newItem"
                      @select-product="handleSelectProduct"
                      @select-tax-rate="handleSelectTaxRate"
                      @update:product-type="(value) => form.items[index].product_type = value"
                      @remove-line="removeLine"
                    />
                  </tbody>
                </table>
              </div>
            </div>

            <DocumentTotals
              :totals="totals"
              :global-discount-type="form.global_discount_type"
              :global-discount-value="form.global_discount_value"
              @update:global-discount-type="form.global_discount_type = $event"
              @update:global-discount-value="form.global_discount_value = $event"
            />

            <div class="space-y-6">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">Règlement</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="payment_condition" value="Condition de règlement" />
                  <CustomSelect id="payment_condition" v-model="form.payment_condition" :options="lookupData.payment_conditions.map((pc) => ({ label: pc.label, value: pc.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                  <InputError class="mt-2" :message="errors.payment_condition" />
                </div>
                <div>
                  <InputLabel for="payment_mode" value="Mode de règlement" />
                  <CustomSelect id="payment_mode" v-model="form.payment_mode" :options="lookupData.payment_modes.map((pm) => ({ label: pm.label, value: pm.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                  <InputError class="mt-2" :message="errors.payment_mode" />
                </div>
                <div>
                  <InputLabel for="late_fee_interest" value="Intérêts de retard" />
                  <CustomSelect id="late_fee_interest" v-model="form.late_fee_interest" :options="lookupData.late_fee_interests.map((lfi) => ({ label: lfi.label, value: lfi.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                  <InputError class="mt-2" :message="errors.late_fee_interest" />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="bank_account_id" value="Compte bancaire (RIB)" />
                  <CustomSelect id="bank_account_id" v-model="form.bank_account_id" :options="[{ label: 'Aucun RIB', value: null }, ...lookupData.bank_accounts.map((b) => ({ label: `${b.label} (${b.bank_name})`, value: b.id }))]" label-key="label" value-key="value" placeholder="Sélectionner un compte" />
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">Textes affichés sur le document</h3>
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <InputLabel for="intro_text" value="Texte d'introduction" />
                  <TextareaInput id="intro_text" v-model="form.intro_text" rows="3" placeholder="Texte d'introduction..." />
                  <InputError class="mt-2" :message="errors.intro_text" />
                </div>
                <div>
                  <InputLabel for="conclusion_text" value="Texte de conclusion" />
                  <TextareaInput id="conclusion_text" v-model="form.conclusion_text" rows="3" placeholder="Texte de conclusion..." />
                  <InputError class="mt-2" :message="errors.conclusion_text" />
                </div>
                <div>
                  <InputLabel for="footer_text" value="Pied de page" />
                  <TextareaInput id="footer_text" v-model="form.footer_text" rows="3" placeholder="Pied de page..." />
                  <InputError class="mt-2" :message="errors.footer_text" />
                </div>
                <div>
                  <InputLabel for="terms" value="Conditions générales" />
                  <TextareaInput id="terms" v-model="form.terms" rows="3" placeholder="Conditions générales..." />
                  <InputError class="mt-2" :message="errors.terms" />
                </div>
                <div>
                  <InputLabel for="notes" value="Notes" />
                  <TextareaInput id="notes" v-model="form.notes" rows="2" placeholder="Notes..." />
                  <InputError class="mt-2" :message="errors.notes" />
                </div>
              </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-6 border-t border-gray-100">
              <button type="button" @click="router.back()" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all">Annuler</button>
              <button type="button" @click="saveAndFinalize" :disabled="isSaving" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold bg-[#C5F82A] text-[#062121] hover:opacity-90 transition-all disabled:opacity-50">
                <i class="fas fa-check-circle"></i> Enregistrer & Finaliser
              </button>
              <button type="submit" :disabled="isSaving" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50">
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
