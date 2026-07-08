<!-- resources/js/views/deposits/CreateDeposit.vue (modifié) -->
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
              <i class="fas fa-file-invoice-dollar text-[#062121]"></i>
              Nouvelle facture d'acompte
            </button>
          </div>

          <div v-if="isLoading || isFetchingQuote" class="text-center py-12">
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

            <!-- Document lié -->
            <div>
              <InputLabel for="quote_id" value="Document lié *" />
              <CustomSelect
                id="quote_id"
                v-model="form.quote_id"
                :options="
                  lookupData.quotes.map((q) => ({
                    label: `${q.document.number ? '#' + q.document.number : 'Brouillon'} - ${q.document.customer?.name || 'Client'} (${q.document.total_ttc} DH)`,
                    value: q.id,
                  }))
                "
                label-key="label"
                value-key="value"
                placeholder="Sélectionner un devis"
              />
              <InputError class="mt-2" :message="errors.quote_id" />
            </div>

            <!-- Solde restant -->
            <div
              v-if="form.quote_id"
              class="bg-white rounded-xl border border-gray-200 p-4 space-y-2"
            >
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Total du devis (TTC)</span>
                <span class="font-semibold"
                  >{{ balanceData.quote_total_ttc.toFixed(2) }} DH</span
                >
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Acomptes déjà versés (TTC)</span>
                <span class="font-semibold text-orange-600"
                  >{{ balanceData.deposited_total_ttc.toFixed(2) }} DH</span
                >
              </div>
              <div
                class="flex justify-between text-sm border-t border-gray-200 pt-2"
              >
                <span class="text-gray-700 font-bold"
                  >Solde restant disponible (TTC)</span
                >
                <span class="font-bold text-[#062121]"
                  >{{ balanceData.remaining_balance.toFixed(2) }} DH</span
                >
              </div>
            </div>

            <!-- Montant -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <InputLabel for="input_type" value="Type de montant" />
                <div class="flex gap-4 mt-2">
                  <label class="inline-flex items-center gap-2 cursor-pointer">
                    <CustomRadio
                      v-model="form.input_type"
                      value="percentage"
                      name="input_type"
                    />
                    <span class="text-sm text-gray-700">Pourcentage (%)</span>
                  </label>
                  <label class="inline-flex items-center gap-2 cursor-pointer">
                    <CustomRadio
                      v-model="form.input_type"
                      value="fixed"
                      name="input_type"
                    />
                    <span class="text-sm text-gray-700">Montant fixe (DH)</span>
                  </label>
                </div>
                <InputError class="mt-2" :message="errors.input_type" />
              </div>
              <div>
                <InputLabel for="input_value" value="Montant à payer" />
                <TextInput
                  id="input_value"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.input_value"
                  placeholder="0.00"
                />
                <InputError class="mt-2" :message="errors.input_value" />
              </div>
            </div>

            <!-- Date d'échéance et TVA -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                 <InputLabel for="due_date" value="Date d'échéance" />
                 <TextInput
                   id="due_date"
                   type="date"
                   v-model="form.due_date"
                 />
                <InputError class="mt-2" :message="errors.due_date" />
              </div>
              <div>
                <InputLabel for="tax_rate" value="TVA (%)" />
                <DropdownSelect
                  id="tax_rate"
                  v-model="form.tax_rate"
                  :options="
                    lookupData.tax_rates.map((t) => ({
                      label: `${t.libelle} (${t.rate}%)`,
                      value: t.rate,
                    }))
                  "
                  label-key="label"
                  value-key="value"
                  placeholder="Sélectionner ou saisir un taux..."
                />
                <InputError class="mt-2" :message="errors.tax_rate" />
              </div>
            </div>

            <!-- Description -->
            <div>
              <InputLabel
                for="deposit_description"
                value="Description de l'acompte"
              />
              <TextareaInput
                id="deposit_description"
                v-model="form.deposit_description"
                rows="2"
                placeholder="Description de l'acompte..."
              />
              <InputError class="mt-2" :message="errors.deposit_description" />
            </div>

            <!-- Totaux -->
            <div class="flex justify-end">
              <div class="w-full md:w-1/2">
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100"
                  >
                    <span class="text-sm text-gray-500">Total HT</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ totalHt.toFixed(2) }} DH</span
                    >
                  </div>
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
                  >
                    <span class="text-sm text-gray-500">TVA</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ totalTva.toFixed(2) }} DH</span
                    >
                  </div>
                  <div class="px-5 py-4 flex justify-between bg-gray-50">
                    <span
                      class="text-sm font-bold text-[#062121] uppercase tracking-wide"
                      >Total TTC</span
                    >
                    <span class="text-lg font-black text-[#062121] font-mono"
                      >{{ depositTtc.toFixed(2) }} DH</span
                    >
                  </div>
                  <div
                    v-if="
                      !isDepositValid && form.quote_id && form.input_value > 0
                    "
                    class="px-5 py-2 bg-red-50 border-t border-red-200"
                  >
                    <p class="text-sm text-red-600">
                      Le montant dépasse le solde restant disponible.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Règlement -->
            <div class="space-y-6">
              <h3
                class="text-sm font-bold text-[#062121] uppercase tracking-wider border-b border-gray-200 pb-2"
              >
                Règlement
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel
                    for="payment_condition"
                    value="Conditions de règlement"
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
                  <InputError
                    class="mt-2"
                    :message="errors.payment_condition"
                  />
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
                  <InputError
                    class="mt-2"
                    :message="errors.late_fee_interest"
                  />
                </div>
              </div>
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
                <InputError class="mt-2" :message="errors.bank_account_id" />
              </div>
            </div>

            <!-- Textes -->
            <div class="space-y-6">
              <h3
                class="text-sm font-bold text-[#062121] uppercase tracking-wider border-b border-gray-200 pb-2"
              >
                Textes affichés sur le document
              </h3>
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <InputLabel
                    for="intro_text"
                    value="Texte d'introduction (visible sur la facture d'acompte)"
                  />
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
                    value="Texte de conclusion (visible sur la facture d'acompte)"
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
                  <InputLabel
                    for="footer_text"
                    value="Pied de page (visible sur la facture d'acompte)"
                  />
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

            <!-- Boutons -->
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
                :disabled="isSaving || !canSubmit"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fas fa-save"></i> Créer la facture d'acompte
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

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
import DropdownSelect from "@/components/DropdownSelect.vue";
import CustomRadio from "@/components/CustomRadio.vue";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isLoading = ref(false);
const isSaving = ref(false);
const isFetchingBalance = ref(false);
const isFetchingQuote = ref(false);

const lookupData = ref({
  quotes: [],
  tax_rates: [],
  bank_accounts: [],
  payment_conditions: [],
  payment_modes: [],
  late_fee_interests: [],
  defaults: {},
});

const balanceData = ref({
  quote_total_ttc: 0,
  deposited_total_ttc: 0,
  remaining_balance: 0,
});

const selectedQuote = ref(null);
const form = reactive({
  quote_id: null,
  input_type: "percentage",
  input_value: 0,
  due_date: "",
  tax_rate: 20,
  deposit_description: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  bank_account_id: null,
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
});

const errors = reactive({
  quote_id: "",
  input_type: "",
  input_value: "",
  due_date: "",
  tax_rate: "",
  deposit_description: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  bank_account_id: "",
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
  server: "",
});

const totalHt = computed(() => {
  const ttc = depositTtc.value;
  const rate = parseFloat(form.tax_rate) || 0;
  if (rate === 0) return ttc;
  return ttc / (1 + rate / 100);
});

const totalTva = computed(() => {
  return depositTtc.value - totalHt.value;
});

const depositTtc = computed(() => {
  if (!form.quote_id) return 0;
  const value = parseFloat(form.input_value) || 0;
  if (form.input_type === "percentage") {
    return balanceData.value.quote_total_ttc * (value / 100);
  }
  return value;
});

const isDepositValid = computed(() => {
  return (
    depositTtc.value > 0 &&
    depositTtc.value <= balanceData.value.remaining_balance
  );
});

const canSubmit = computed(() => {
  return (
    form.quote_id &&
    form.input_value > 0 &&
    isDepositValid.value
  );
});

const fetchLookups = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/deposits/create");
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
    if (defaultTaxRate) form.tax_rate = defaultTaxRate.rate;
  } catch (err) {
    error("Erreur", "Impossible de charger les données.");
  } finally {
    isLoading.value = false;
  }
};

const fetchBalance = async (quoteId) => {
  if (!quoteId) {
    balanceData.value = {
      quote_total_ttc: 0,
      deposited_total_ttc: 0,
      remaining_balance: 0,
    };
    return;
  }

  isFetchingBalance.value = true;
  try {
    const { data } = await axios.get(`/api/deposits/remaining-balance/${quoteId}`);
    balanceData.value = data;
  } catch (err) {
    const message =
      err.response?.data?.error || "Impossible de récupérer le solde restant.";
    error("Erreur", message);
  } finally {
    isFetchingBalance.value = false;
  }
};

const fetchQuoteDetails = async (quoteId) => {
  if (!quoteId) return;

  isFetchingQuote.value = true;
  try {
    const { data } = await axios.get(`/api/quotes/${quoteId}`);
    selectedQuote.value = data;
    form.quote_id = data.id;
    form.tax_rate = data.document?.items?.[0]?.tax_rate || data.tax_rate || 20;
    await fetchBalance(quoteId);
  } catch (err) {
    const message =
      err.response?.data?.error ||
      "Impossible de charger les détails du devis.";
    error("Erreur", message);
  } finally {
    isFetchingQuote.value = false;
  }
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  if (!form.quote_id) {
    errors.quote_id = "Veuillez sélectionner un devis.";
    return;
  }
  if (!form.input_value || form.input_value <= 0) {
    errors.input_value = "Veuillez saisir un montant valide.";
    return;
  }
  if (!isDepositValid.value) {
    errors.input_value = "Le montant dépasse le solde restant disponible.";
    return;
  }

  const confirmed = await confirm(
    "Enregistrer l'acompte",
    "L'acompte sera enregistré en tant que brouillon. Vous pourrez le finaliser ultérieurement.",
  );
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    const payload = { ...form };
    const response = await axios.post("/api/deposits", payload);
    success(
      "Acompte enregistré",
      "L'acompte a été enregistré en tant que brouillon.",
    );
    router.push("/deposits");
  } catch (err) {
    console.error("Erreur complète :", err);
  console.error("Status :", err.response?.status);
  console.error("Data :", err.response?.data);
  console.error("Message :", err.message);
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((key) => {
        if (key in errors) errors[key] = e[key][0];
      });
      if (err.response.data.error) {
        errors.server = err.response.data.error;
      }
    } else {
      errors.server =
        "Une erreur est survenue lors de l'enregistrement de l'acompte.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

watch(
  () => form.quote_id,
  (newVal) => {
    if (newVal) {
      fetchBalance(newVal);
      const quote = lookupData.value.quotes.find((q) => q.id === newVal);
      if (quote) {
        form.tax_rate = quote.document?.items?.[0]?.tax_rate || 20;
      }
    } else {
      form.tax_rate = 20;
      balanceData.value = {
        quote_total_ttc: 0,
        deposited_total_ttc: 0,
        remaining_balance: 0,
      };
    }
    form.input_value = 0;
  },
);

watch(
  () => form.input_type,
  () => {
    form.input_value = 0;
  },
);

onMounted(async () => {
  await fetchLookups();
  const quoteId = route.query.quote_id;
  if (quoteId) {
    form.quote_id = parseInt(quoteId);
  }
});
</script>

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
