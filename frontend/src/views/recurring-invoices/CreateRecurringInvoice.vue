<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";
import { success, error, confirm } from "@/helpers/notifications";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import TextInput from "@/components/TextInput.vue";
import TextareaInput from "@/components/TextareaInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import DropdownSelect from "@/components/DropdownSelect.vue";

const router = useRouter();
const route = useRoute();
const isEdit = computed(() => !!route.params.id);
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

const form = reactive({
  customer_id: null,
  bank_account_id: null,
  date: new Date().toISOString().split("T")[0],
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
  frequency: "monthly",
  start_date: new Date().toISOString().split("T")[0],
  end_date: "",
  next_run_date: new Date().toISOString().split("T")[0],
  status: "active",
});

const errors = reactive({
  customer_id: "",
  date: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  items: "",
  global_discount_type: "",
  global_discount_value: "",
  frequency: "",
  start_date: "",
  end_date: "",
  next_run_date: "",
  server: "",
});

const frequencies = [
  { value: "weekly", label: "Hebdomadaire" },
  { value: "monthly", label: "Mensuel" },
  { value: "quarterly", label: "Trimestriel" },
  { value: "yearly", label: "Annuel" },
];

const productOptions = computed(() =>
  lookupData.value.products.map((p) => ({ label: p.name, value: p.id, price: p.price, categoryId: p.product_category_id }))
);

const taxOptions = computed(() =>
  lookupData.value.tax_rates.map((t) => ({
    label: t.libelle ? `${t.libelle} (${t.rate}%)` : `${t.rate}%`,
    value: t.id,
    rate: t.rate,
  }))
);

const fetchLookups = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/invoices/create");
    lookupData.value = data;
    form.intro_text = data.defaults.intro_text || "";
    form.footer_text = data.defaults.footer_text || "";
    form.terms = data.defaults.terms || "";
    form.conclusion_text = data.defaults.conclusion_text || "";

    const defaultPaymentCondition = data.payment_conditions.find((pc) => pc.is_default === true);
    if (defaultPaymentCondition) form.payment_condition = defaultPaymentCondition.label;

    const defaultPaymentMode = data.payment_modes.find((pm) => pm.is_default === true);
    if (defaultPaymentMode) form.payment_mode = defaultPaymentMode.label;

    const defaultLateFeeInterest = data.late_fee_interests.find((lfi) => lfi.is_default === true);
    if (defaultLateFeeInterest) form.late_fee_interest = defaultLateFeeInterest.label;

    const defaultBankAccount = data.bank_accounts.find((ba) => ba.is_default === true);
    if (defaultBankAccount) form.bank_account_id = defaultBankAccount.id;

    const defaultTaxRate = data.tax_rates.find((tr) => tr.is_default === true);
    if (defaultTaxRate) defaultTaxRateValue.value = defaultTaxRate.rate;

    if (form.items.length === 0) {
      form.items.push(createItem());
    }
  } catch {
    error("Erreur", "Impossible de charger les données.");
  } finally {
    isLoading.value = false;
  }
};

const fetchRecurring = async () => {
  if (!isEdit.value) return;
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/recurring-invoices/${route.params.id}`);
    const doc = data.template_document;
    form.customer_id = doc?.customer_id || null;
    form.bank_account_id = doc?.bank_account_id || null;
    form.date = doc?.created_at?.split("T")[0] || "";
    form.payment_condition = doc?.payment_condition || "";
    form.payment_mode = doc?.payment_mode || "";
    form.late_fee_interest = doc?.late_fee_interest || "";
    form.items = (doc?.items || []).map((i) => ({
      product_id: i.product_id,
      designation: i.description,
      quantity: parseFloat(i.quantity) || 1,
      unit_price: parseFloat(i.unit_price) || 0,
      tax_rate: parseFloat(i.tax_rate) || defaultTaxRateValue.value,
      discount_type: i.discount_type || "percentage",
      discount_value: parseFloat(i.discount_value) || 0,
    }));
    form.notes = doc?.notes || "";
    form.terms = doc?.terms || "";
    form.intro_text = doc?.intro_text || "";
    form.footer_text = doc?.footer_text || "";
    form.conclusion_text = doc?.conclusion_text || "";
    form.global_discount_type = doc?.global_discount_type || "percentage";
    form.global_discount_value = parseFloat(doc?.global_discount_value) || 0;
    form.frequency = data.frequency;
    form.start_date = data.start_date;
    form.end_date = data.end_date || "";
    form.next_run_date = data.next_run_date;
    form.status = data.status;
  } catch (err) {
    error("Erreur", "Impossible de charger le modèle récurrent.");
    router.push({ name: "recurring-invoice.index" });
  } finally {
    isLoading.value = false;
  }
};

const createItem = () => ({
  product_id: null,
  designation: "",
  quantity: 1,
  unit_price: 0,
  tax_rate: defaultTaxRateValue.value,
  discount_type: "percentage",
  discount_value: 0,
});

const addLine = () => form.items.push(createItem());
const removeLine = (index) => {
  if (form.items.length > 1) form.items.splice(index, 1);
};

const selectProduct = (index, productId) => {
  const product = lookupData.value.products.find((p) => p.id === productId);
  if (!product) return;
  const item = form.items[index];
  item.product_id = product.id;
  item.designation = product.name;
  item.unit_price = product.price;
};

const selectTaxRate = (index, taxId) => {
  const tax = lookupData.value.tax_rates.find((t) => t.id === taxId);
  if (!tax) return;
  form.items[index].tax_rate = tax.rate;
};

const lineTotalHt = (item) => {
  const subtotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
  let discount = 0;
  if (item.discount_type && item.discount_value > 0) {
    discount = item.discount_type === "percentage" ? subtotal * (item.discount_value / 100) : item.discount_value;
  }
  return subtotal - discount;
};

const lineTotalTva = (item) => (lineTotalHt(item) * (parseFloat(item.tax_rate) || 0)) / 100;

const totals = computed(() => {
  let ht = 0, tva = 0;
  form.items.forEach((item) => {
    ht += lineTotalHt(item);
    tva += lineTotalTva(item);
  });
  let globalDiscount = 0;
  if (form.global_discount_type && form.global_discount_value > 0) {
    globalDiscount = form.global_discount_type === "percentage" ? ht * (form.global_discount_value / 100) : form.global_discount_value;
  }
  const htAfterDiscount = ht - globalDiscount;
  const tvaAfterDiscount = ht > 0 ? tva * (htAfterDiscount / ht) : 0;
  return { ht, tva, globalDiscount, htAfterDiscount, tvaAfterDiscount, ttc: htAfterDiscount + tvaAfterDiscount };
});

const fmt = (n) => {
  if (isNaN(n) || !isFinite(n)) return "0.00";
  return new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
};

const getTaxOptionByRate = (rate) => {
  return taxOptions.value.find((t) => t.rate === rate)?.value || null;
};

const getProductOptionById = (id) => {
  return productOptions.value.find((p) => p.value === id)?.value || null;
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  if (!form.customer_id) { errors.customer_id = "Veuillez sélectionner un client."; return; }
  if (form.items.some((i) => !i.designation.trim())) { errors.items = "Toutes les lignes doivent avoir une désignation."; return; }

  const msg = isEdit.value ? "Confirmer la mise à jour du modèle récurrent ?" : "Le modèle sera enregistré. Il générera automatiquement des factures finalisées et envoyées par email.";
  const confirmed = await confirm(isEdit.value ? "Mettre à jour" : "Enregistrer le modèle", msg);
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    if (isEdit.value) {
      await axios.put(`/api/recurring-invoices/${route.params.id}`, { ...form });
      success("Mis à jour", "Le modèle récurrent a été mis à jour.");
    } else {
      await axios.post("/api/recurring-invoices", { ...form });
      success("Créé", "Le modèle récurrent a été créé. Les factures seront générées automatiquement.");
    }
    router.push("/recurring-invoices");
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((key) => { if (key in errors) errors[key] = e[key][0]; });
    } else {
      errors.server = err.response?.data?.message || "Une erreur est survenue.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

onMounted(async () => {
  await fetchLookups();
  if (isEdit.value) await fetchRecurring();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <button class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]">
              <i class="fas fa-rotate"></i>
              {{ isEdit ? "Modifier le modèle récurrent" : "Nouveau modèle récurrent" }}
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="customer_id" value="Client *" />
                <CustomSelect id="customer_id" v-model="form.customer_id" :options="lookupData.customers.length ? lookupData.customers.map((c) => ({ label: c.customerable?.name || c.customerable?.legal_name || 'Client', value: c.id })) : [{ label: 'Aucun client', value: null }]" label-key="label" value-key="value" :placeholder="lookupData.customers.length ? 'Sélectionner un client' : 'Aucun client'" :disabled="!lookupData.customers.length" />
                <InputError class="mt-2" :message="errors.customer_id" />
              </div>
              <div>
                <InputLabel for="date" value="Date d'émission *" />
                <TextInput id="date" type="date" v-model="form.date" required />
                <InputError class="mt-2" :message="errors.date" />
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">Lignes de facturation</h3>
                <button type="button" @click="addLine" class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors">
                  <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
                </button>
              </div>
              <InputError class="mb-3" :message="errors.items" />

              <div class="overflow-x-auto rounded-xl border border-gray-200" style="overflow: visible !important;">
                <table class="min-w-full" style="overflow: visible !important;">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[25%]">Produit</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]">Qté</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]">P.U. HT</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]">TVA</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]">Réduction</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[13%]">Total HT</th>
                      <th class="px-4 py-3 w-[5%]"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-for="(item, index) in form.items" :key="index" :class="index % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'">
                      <td class="px-4 py-3 relative">
                        <div class="relative">
                          <DropdownSelect
                            :model-value="item.designation"
                            :options="productOptions"
                            label-key="label"
                            value-key="label"
                            placeholder="— Saisie libre —"
                            @update:model-value="(val) => { const prod = lookupData.products.find(p => p.name === val); if (prod) selectProduct(index, prod.id); }"
                          />
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <input type="number" v-model.number="item.quantity" min="0.01" step="0.01" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none" />
                      </td>
                      <td class="px-4 py-3">
                        <input type="number" v-model.number="item.unit_price" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none" />
                      </td>
                      <td class="px-4 py-3">
                        <DropdownSelect
                          :model-value="getTaxOptionByRate(item.tax_rate)"
                          :options="taxOptions"
                          label-key="label"
                          value-key="value"
                          placeholder="TVA"
                          @update:model-value="(val) => selectTaxRate(index, val)"
                        />
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                          <input type="number" v-model.number="item.discount_value" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none" placeholder="0.00" />
                          <CustomSelect v-model="item.discount_type" :options="[{ label: '%', value: 'percentage' }, { label: 'DH', value: 'fixed' }]" label-key="label" value-key="value" placeholder="Type" class="w-16" />
                        </div>
                      </td>
                      <td class="px-4 py-3 text-center">
                        <div class="text-sm font-semibold text-[#062121] whitespace-nowrap">{{ fmt(lineTotalHt(item)) }} DH</div>
                      </td>
                      <td class="px-4 py-3 text-center">
                        <button type="button" @click="removeLine(index)" :disabled="form.items.length <= 1" :class="form.items.length <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700'">
                          <i class="fas fa-trash text-sm"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="global_discount_type" value="Réduction globale" />
                <div class="flex gap-2">
                  <CustomSelect v-model="form.global_discount_type" :options="[{ label: '%', value: 'percentage' }, { label: 'DH', value: 'fixed' }]" label-key="label" value-key="value" placeholder="Type" />
                  <input type="number" v-model.number="form.global_discount_value" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-[#C5F82A] focus:border-[#C5F82A] outline-none" placeholder="0.00" />
                </div>
              </div>
              <div>
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100"><span class="text-sm text-gray-500">Total HT</span><span class="text-sm font-semibold text-gray-800 font-mono">{{ fmt(totals.htAfterDiscount) }} DH</span></div>
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"><span class="text-sm text-gray-500">TVA</span><span class="text-sm font-semibold text-gray-800 font-mono">{{ fmt(totals.tvaAfterDiscount) }} DH</span></div>
                  <div class="px-5 py-4 flex justify-between bg-gray-50"><span class="text-sm font-bold text-[#062121] uppercase tracking-wide">Total TTC</span><span class="text-lg font-black text-[#062121] font-mono">{{ fmt(totals.ttc) }} DH</span></div>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider border-b border-gray-200 pb-2">Règlement</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="payment_condition" value="Conditions de règlement" />
                  <CustomSelect id="payment_condition" v-model="form.payment_condition" :options="lookupData.payment_conditions.map((pc) => ({ label: pc.label, value: pc.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                </div>
                <div>
                  <InputLabel for="payment_mode" value="Mode de règlement" />
                  <CustomSelect id="payment_mode" v-model="form.payment_mode" :options="lookupData.payment_modes.map((pm) => ({ label: pm.label, value: pm.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                </div>
                <div>
                  <InputLabel for="late_fee_interest" value="Intérêts de retard" />
                  <CustomSelect id="late_fee_interest" v-model="form.late_fee_interest" :options="lookupData.late_fee_interests.map((lfi) => ({ label: lfi.label, value: lfi.label }))" label-key="label" value-key="value" placeholder="Sélectionner" />
                </div>
              </div>
              <div>
                <InputLabel for="bank_account_id" value="Compte bancaire (RIB)" />
                <CustomSelect id="bank_account_id" v-model="form.bank_account_id" :options="[{ label: 'Aucun RIB', value: null }, ...lookupData.bank_accounts.map((b) => ({ label: `${b.label} (${b.bank_name})`, value: b.id }))]" label-key="label" value-key="value" placeholder="Sélectionner un compte" />
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider border-b border-gray-200 pb-2">Textes affichés sur le document</h3>
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <InputLabel for="intro_text" value="Texte d'introduction" />
                  <TextareaInput id="intro_text" v-model="form.intro_text" rows="3" placeholder="Texte d'introduction..." />
                </div>
                <div>
                  <InputLabel for="conclusion_text" value="Texte de conclusion" />
                  <TextareaInput id="conclusion_text" v-model="form.conclusion_text" rows="3" placeholder="Texte de conclusion..." />
                </div>
                <div>
                  <InputLabel for="footer_text" value="Pied de page" />
                  <TextareaInput id="footer_text" v-model="form.footer_text" rows="3" placeholder="Pied de page..." />
                </div>
                <div>
                  <InputLabel for="terms" value="Conditions générales" />
                  <TextareaInput id="terms" v-model="form.terms" rows="3" placeholder="Conditions générales..." />
                </div>
                <div>
                  <InputLabel for="notes" value="Notes" />
                  <TextareaInput id="notes" v-model="form.notes" rows="2" placeholder="Notes..." />
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider border-b border-gray-200 pb-2">Paramètres de récurrence</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="frequency" value="Fréquence *" />
                  <CustomSelect id="frequency" v-model="form.frequency" :options="frequencies" label-key="label" value-key="value" placeholder="Sélectionner" />
                  <InputError class="mt-2" :message="errors.frequency" />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="start_date" value="Date de début *" />
                  <TextInput id="start_date" type="date" v-model="form.start_date" required />
                  <InputError class="mt-2" :message="errors.start_date" />
                </div>
                <div>
                  <InputLabel for="end_date" value="Date de fin (optionnelle)" />
                  <TextInput id="end_date" type="date" v-model="form.end_date" />
                  <InputError class="mt-2" :message="errors.end_date" />
                </div>
              </div>
              <div>
                <InputLabel for="next_run_date" value="Prochaine exécution *" />
                <TextInput id="next_run_date" type="date" v-model="form.next_run_date" required />
                <InputError class="mt-2" :message="errors.next_run_date" />
              </div>
              <div v-if="isEdit">
                <InputLabel for="status" value="Statut" />
                <CustomSelect id="status" v-model="form.status" :options="[{ label: 'Actif', value: 'active' }, { label: 'En pause', value: 'paused' }, { label: 'Terminé', value: 'completed' }]" label-key="label" value-key="value" />
              </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-6 border-t border-gray-100">
              <button type="button" @click="router.push('/recurring-invoices')" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all">Annuler</button>
              <button type="submit" :disabled="isSaving" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-save"></i> {{ isEdit ? "Mettre à jour" : "Créer le modèle" }}
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
table, tbody, tr, td {
  overflow: visible !important;
}
</style>