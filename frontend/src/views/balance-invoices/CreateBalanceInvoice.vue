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
              {{ isEditMode ? "Modifier la facture de solde" : "Nouvelle facture de solde" }}
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
            <LinkedDocumentInfo
              v-if="devis"
              :document="devis"
              document-type="devis"
              :balance-data="balanceDataWithAlias"
            />

            <!-- Sélection du devis si non fourni -->
            <div v-if="!devis">
              <InputLabel for="quote_id" value="Devis" />
              <CustomSelect
                id="quote_id"
                v-model="form.quote_id"
                :options="
                  lookupData.quotes.map((q) => ({
                    label: `Devis ${q.document?.number || 'N/A'} - ${q.document?.customer?.name || 'Client inconnu'} (${q.document?.total_ttc || 0} DH)`,
                    value: q.id,
                  }))
                "
                label-key="label"
                value-key="value"
                placeholder="Sélectionner un devis signé"
              />
              <InputError class="mt-2" :message="errors.quote_id" />
            </div>

            <!-- Données de solde -->
            <div v-if="balanceData" class="rounded-xl border border-gray-100/60 bg-white/40 p-4 backdrop-blur-sm">
              <h3 class="text-sm font-bold text-[#062121] mb-3">Récapitulatif du solde</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                  <span class="text-xs text-gray-500">Total Devis TTC</span>
                  <p class="text-sm font-semibold text-gray-800">{{ formatCurrency(balanceData.quote_total_ttc) }}</p>
                </div>
                <div>
                  <span class="text-xs text-gray-500">Acomptes déduits</span>
                  <p class="text-sm font-semibold text-red-600">{{ formatCurrency(balanceData.deposited_total_ttc) }}</p>
                </div>
                <div>
                  <span class="text-xs text-gray-500">Solde déjà facturé</span>
                  <p class="text-sm font-semibold text-orange-600">{{ formatCurrency(balanceData.balance_invoiced_total_ttc || 0) }}</p>
                </div>
                <div>
                  <span class="text-xs text-gray-500">Solde restant</span>
                  <p class="text-sm font-bold text-green-600">{{ formatCurrency(balanceData.remaining_balance) }}</p>
                </div>
              </div>
              <div v-if="balanceData.deposit_details && balanceData.deposit_details.length > 0" class="mt-3 pt-3 border-t border-gray-200/60">
                <span class="text-xs text-gray-500">Acomptes appliqués :</span>
                <div class="flex flex-wrap gap-2 mt-2">
                  <span
                    v-for="deposit in balanceData.deposit_details"
                    :key="deposit.id"
                    class="text-xs bg-blue-100/80 text-blue-700 px-2 py-1 rounded"
                  >
                    {{ deposit.number }} ({{ formatCurrency(deposit.amount) }})
                  </span>
                </div>
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
                      value="full"
                      name="input_type"
                    />
                    <span class="text-sm text-gray-700">Solde complet</span>
                  </label>
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
              <div v-if="form.input_type !== 'full'">
                <InputLabel for="input_value" value="Montant à facturer" />
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
                <TextInput id="due_date" type="date" v-model="form.due_date" />
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
                for="balance_description"
                value="Description de la facture de solde"
              />
              <TextareaInput
                id="balance_description"
                v-model="form.balance_description"
                rows="2"
                placeholder="Description de la facture de solde..."
              />
              <InputError class="mt-2" :message="errors.balance_description" />
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
                      >{{ balanceTtc.toFixed(2) }} DH</span
                    >
                  </div>
                  <div
                    v-if="
                      !isBalanceValid && form.quote_id && balanceTtc > 0
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
                    value="Texte d'introduction"
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
                  <InputLabel
                    for="footer_text"
                    value="Pied de page"
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
                type="button"
                @click="submitAsDraft"
                :disabled="isSaving || !canSubmit"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-gray-600 hover:bg-gray-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fas fa-save"></i> Enregistrer comme brouillon
              </button>
              <button
                type="button"
                @click="submitAndFinalize"
                :disabled="isSaving || !canSubmit"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fas fa-check"></i> Enregistrer & Finaliser
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
import LinkedDocumentInfo from "@/components/LinkedDocumentInfo.vue";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isLoading = ref(false);
const isSaving = ref(false);
const isFetchingBalance = ref(false);
const isFetchingQuote = ref(false);
const isEditMode = computed(() => !!route.params.id);

const lookupData = ref({
  quotes: [],
  tax_rates: [],
  bank_accounts: [],
  payment_conditions: [],
  payment_modes: [],
  late_fee_interests: [],
  defaults: {},
});

const balanceData = ref(null);

// Alias for remaining_balance as remaining_total_ttc (per template requirement)
const balanceDataWithAlias = computed(() => ({
  ...balanceData.value,
  remaining_total_ttc: balanceData.value?.remaining_balance || 0,
}));

const selectedQuote = ref(null);

const linkedQuote = computed(() => {
  if (form.quote_id) {
    return lookupData.value.quotes.find((q) => q.id === form.quote_id) || selectedQuote.value;
  }
  return selectedQuote.value;
});

// Simplified access to devis data for template
const devis = computed(() => {
  return linkedQuote.value?.document || null;
});

const form = reactive({
  quote_id: null,
  input_type: "full",
  input_value: 0,
  due_date: "",
  tax_rate: 20,
  balance_description: "",
  payment_condition: "",
  payment_mode: "",
  late_fee_interest: "",
  bank_account_id: null,
  notes: "",
  terms: "",
  intro_text: "",
  footer_text: "",
  conclusion_text: "",
  status: "DRAFT",
});

const errors = reactive({
  quote_id: "",
  input_type: "",
  input_value: "",
  due_date: "",
  tax_rate: "",
  balance_description: "",
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
  const ttc = balanceTtc.value;
  const rate = parseFloat(form.tax_rate) || 0;
  if (rate === 0) return ttc;
  return ttc / (1 + rate / 100);
});

const totalTva = computed(() => {
  return balanceTtc.value - totalHt.value;
});

const balanceTtc = computed(() => {
  if (!form.quote_id || !balanceData.value) return 0;
  const value = parseFloat(form.input_value) || 0;

  if (form.input_type === "full") {
    return balanceData.value.remaining_balance;
  }

  if (form.input_type === "percentage") {
    return balanceData.value.remaining_balance * (value / 100);
  }

  return Math.min(value, balanceData.value.remaining_balance);
});

const isBalanceValid = computed(() => {
  if (!balanceData.value) return false;
  return (
    balanceTtc.value > 0 &&
    balanceTtc.value <= balanceData.value.remaining_balance
  );
});

const canSubmit = computed(() => {
  return form.quote_id && isBalanceValid.value;
});

const fetchLookups = async (quoteId = null) => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/balance-invoices/create", {
      params: { quote_id: quoteId },
    });
    lookupData.value = data;
    form.intro_text = data.defaults.intro_text || "";
    form.footer_text = data.defaults.footer_text || "";
    form.terms = data.defaults.terms || "";
    form.conclusion_text = data.defaults.conclusion_text || "";

    if (data.balance_data) {
      balanceData.value = data.balance_data;
    }

    if (data.selected_quote) {
      selectedQuote.value = data.selected_quote;
      form.quote_id = data.selected_quote.id;
    }

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

const fetchDocument = async (documentId) => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/balance-invoices/${documentId}`);
    const doc = data.document || data;
    if (!doc) {
      error("Erreur", "Document introuvable.");
      router.push("/balance-invoices");
      return;
    }

    // Populate form with document data
    form.quote_id = doc.quote_id;
    form.input_type = doc.input_type || "full";
    form.input_value = doc.input_value || 0;
    form.due_date = doc.due_date || "";
    form.tax_rate = doc.tax_rate || 20;
    form.balance_description = doc.balance_description || "";
    form.payment_condition = doc.payment_condition || "";
    form.payment_mode = doc.payment_mode || "";
    form.late_fee_interest = doc.late_fee_interest || "";
    form.bank_account_id = doc.bank_account_id || null;
    form.notes = doc.notes || "";
    form.terms = doc.terms || "";
    form.intro_text = doc.intro_text || "";
    form.footer_text = doc.footer_text || "";
    form.conclusion_text = doc.conclusion_text || "";
    form.status = doc.status || "DRAFT";

    // Fetch balance if linked to a quote
    if (doc.quote_id) {
      await fetchBalance(doc.quote_id);
    }
  } catch (err) {
    if (err.response?.status === 404) {
      error("Document introuvable", "Le document demandé n'existe pas ou a été supprimé.");
    } else {
      error("Erreur", "Impossible de charger les données du document.");
    }
    router.push("/balance-invoices");
  } finally {
    isLoading.value = false;
  }
};

const fetchBalance = async (quoteId) => {
  if (!quoteId) {
    balanceData.value = null;
    return;
  }

  isFetchingBalance.value = true;
  try {
    const { data } = await axios.get(
      `/api/balance-invoices/balance-data/${quoteId}`,
    );
    balanceData.value = data;
  } catch (err) {
    const message =
      err.response?.data?.error || "Impossible de récupérer le solde restant.";
    error("Erreur", message);
  } finally {
    isFetchingBalance.value = false;
  }
};

const submit = async (status = "DRAFT") => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  if (!form.quote_id) {
    errors.quote_id = "Veuillez sélectionner un devis.";
    return;
  }
  if (!isBalanceValid.value) {
    errors.input_value = "Le montant dépasse le solde restant disponible.";
    return;
  }

  const isFinalize = status === "FINALIZED";
  const confirmed = await confirm(
    isEditMode.value
      ? (isFinalize ? "Finaliser les modifications" : "Mettre à jour la facture de solde")
      : (isFinalize ? "Finaliser la facture de solde" : "Enregistrer la facture de solde"),
    isEditMode.value
      ? (isFinalize
        ? "La facture sera finalisée avec les modifications."
        : "Les modifications seront enregistrées.")
      : (isFinalize
        ? "La facture sera finalisée et le numéro sera généré automatiquement."
        : "La facture sera enregistrée en tant que brouillon."),
  );
  if (!confirmed.isConfirmed) return;

  isSaving.value = true;
  try {
    const payload = { ...form, status };

    if (isEditMode.value) {
      await axios.put(`/api/balance-invoices/${route.params.id}`, payload);
      if (isFinalize) {
        await axios.put(`/api/balance-invoices/${route.params.id}/finalize`);
      }
      success(isFinalize ? "Facture finalisée" : "Facture mise à jour", "La facture de solde a été mise à jour avec succès.");
    } else {
      const response = await axios.post("/api/balance-invoices", payload);
      if (isFinalize) {
        await axios.put(`/api/balance-invoices/${response.data.id}/finalize`);
      }
      success(
        isFinalize ? "Facture finalisée" : "Facture enregistrée",
        isFinalize
          ? `La facture de solde ${response.data.number} a été créée et finalisée.`
          : "La facture de solde a été enregistrée en tant que brouillon.",
      );
    }
    router.push("/balance-invoices");
  } catch (err) {
    console.error("Erreur complète :", err);
    console.error("Status :", err.response?.status);
    console.error("Data :", err.response?.data);
    console.error("Message :", err.message);
    if (err.response?.status === 422) {
      const e = err.response.data?.errors;
      if (e && typeof e === 'object') {
        Object.keys(e).forEach((key) => {
          if (key in errors) errors[key] = Array.isArray(e[key]) ? e[key][0] : e[key];
        });
      }
      if (err.response.data?.error) {
        errors.server = err.response.data.error;
      } else if (err.response.data?.message) {
        errors.server = err.response.data.message;
      }
    } else if (err.response?.status === 500) {
      errors.server = err.response.data?.message || "Une erreur interne est survenue.";
      error("Erreur", errors.server);
    } else {
      errors.server =
        "Une erreur est survenue lors de l'enregistrement de la facture de solde.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

const submitAsDraft = () => submit("DRAFT");
const submitAndFinalize = () => submit("FINALIZED");

watch(
  () => form.quote_id,
  (newVal) => {
    if (newVal) {
      fetchBalance(newVal);
      const quote = lookupData.value.quotes.find((q) => q.id === newVal);
      if (quote) {
        selectedQuote.value = quote;
      }
    } else {
      selectedQuote.value = null;
      balanceData.value = null;
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

const loadLinkedDocument = async () => {
  const quoteId = route.query.quote_id;

  if (quoteId) {
    await fetchLookups(parseInt(quoteId));
  } else {
    await fetchLookups();
  }
};

onMounted(async () => {
  // Load document data if in edit mode
  if (isEditMode.value) {
    await fetchLookups(); // Still need lookups for dropdowns
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

const formatCurrency = (amount) => {
  if (!amount && amount !== 0) return "0.00 DH";
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";
};
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
