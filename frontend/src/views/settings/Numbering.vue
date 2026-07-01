<!-- resources/js/views/settings/Numbering.vue -->
<script setup>
import { reactive, ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../stores/auth";
import SettingsLayout from "../../layouts/SettingsLayout.vue";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import TextInput from "../../components/TextInput.vue";
import CustomSelect from "../../components/CustomSelect.vue";
import axios from "axios";
import { success, error, validation, showWelcomeModal } from "../../helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const isLoading = ref(false);
const isSubmitting = ref(false);
const welcomeShown = ref(false);
const hasDocuments = ref(false);

const formatOptions = [
  { label: "<doc><aa><cmp>", value: "<doc><aa><cmp>" },
  { label: "<cmp><aaaa><doc>", value: "<cmp><aaaa><doc>" },
  { label: "<doc><aaaa><cmp>", value: "<doc><aaaa><cmp>" },
  { label: "<aa><doc><cmp>", value: "<aa><doc><cmp>" },
  { label: "<aaaa><doc><cmp>", value: "<aaaa><doc><cmp>" },
  { label: "<doc><aa><cmp> (par défaut)", value: "<doc><aa><cmp>" },
];

const resetPeriodOptions = [
  { label: "Jamais", value: "never" },
  { label: "Tous les ans", value: "yearly" },
  { label: "Tous les mois", value: "monthly" },
];

const counterFields = [
  { key: 'start_from_invoice', label: 'Départ Factures' },
  { key: 'start_from_quote', label: 'Départ Devis' },
  { key: 'start_from_credit_note', label: 'Départ Avoirs' },
  { key: 'start_from_deposit_invoice', label: 'Départ Factures d\'acompte' },
  { key: 'start_from_deposit_credit_note', label: 'Départ Avoir d\'acompte' },
  { key: 'start_from_balance_invoice', label: 'Départ Facture de solde' },
  { key: 'start_from_delivery_note', label: 'Départ Bons de livraison' },
  { key: 'start_from_purchase_order', label: 'Départ Bons de commande' },
];

const form = reactive({
  id: null,
  format: "<doc><aa><cmp>",
  min_size: 5,
  reset_period: "yearly",
  start_from_invoice: 1,
  start_from_quote: 1,
  start_from_credit_note: 1,
  start_from_deposit_invoice: 1,
  start_from_deposit_credit_note: 1,
  start_from_balance_invoice: 1,
  start_from_delivery_note: 1,
  start_from_purchase_order: 1,
});

const originalStartValues = ref({
  start_from_invoice: 1,
  start_from_quote: 1,
  start_from_credit_note: 1,
  start_from_deposit_invoice: 1,
  start_from_deposit_credit_note: 1,
  start_from_balance_invoice: 1,
  start_from_delivery_note: 1,
  start_from_purchase_order: 1,
});

const currentValues = ref({
  current_invoice: 1,
  current_quote: 1,
  current_credit_note: 1,
  current_deposit_invoice: 1,
  current_deposit_credit_note: 1,
  current_balance_invoice: 1,
  current_delivery_note: 1,
  current_purchase_order: 1,
});

const errors = reactive({});

const currentDate = new Date();
const currentYear = currentDate.getFullYear();
const currentYearShort = String(currentDate.getFullYear()).slice(-2);
const currentMonth = String(currentDate.getMonth() + 1).padStart(2, "0");
const currentMonthShort = String(currentDate.getMonth() + 1);
const currentDay = String(currentDate.getDate()).padStart(2, "0");
const currentDayShort = String(currentDate.getDate());

const previewFormat = computed(() => {
  const doc = "FACT";
  const year = String(currentDate.getFullYear());
  const yearShort = year.slice(-2);
  const month = String(currentDate.getMonth() + 1).padStart(2, "0");
  const monthShort = String(currentDate.getMonth() + 1);
  const day = String(currentDate.getDate()).padStart(2, "0");
  const dayShort = String(currentDate.getDate());
  const counter = String(form.start_from_invoice).padStart(Number(form.min_size) || 5, "0");

  return form.format
    .replace(/<doc>/g, doc)
    .replace(/<aaaa>/g, year)
    .replace(/<aa>/g, yearShort)
    .replace(/<mm>/g, month)
    .replace(/<m>/g, monthShort)
    .replace(/<jj>/g, day)
    .replace(/<j>/g, dayShort)
    .replace(/<cmp>/g, counter);
});

const paddedCounter = computed(() => {
  return String(form.start_from_invoice).padStart(Number(form.min_size) || 5, "0");
});

const fetchConfig = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get("/api/numbering-serie", {
      params: { company_id: companyId },
    });

    hasDocuments.value = data.has_documents || false;

    if (data.exists && data.data) {
      const serverData = data.data;
      Object.assign(form, serverData);

      originalStartValues.value = {
        start_from_invoice: serverData.start_from_invoice,
        start_from_quote: serverData.start_from_quote,
        start_from_credit_note: serverData.start_from_credit_note,
        start_from_deposit_invoice: serverData.start_from_deposit_invoice,
        start_from_deposit_credit_note: serverData.start_from_deposit_credit_note,
        start_from_balance_invoice: serverData.start_from_balance_invoice,
        start_from_delivery_note: serverData.start_from_delivery_note,
        start_from_purchase_order: serverData.start_from_purchase_order,
      };

      currentValues.value = {
        current_invoice: serverData.current_invoice,
        current_quote: serverData.current_quote,
        current_credit_note: serverData.current_credit_note,
        current_deposit_invoice: serverData.current_deposit_invoice,
        current_deposit_credit_note: serverData.current_deposit_credit_note,
        current_balance_invoice: serverData.current_balance_invoice,
        current_delivery_note: serverData.current_delivery_note,
        current_purchase_order: serverData.current_purchase_order,
      };

      authStore.updateHasNumbering(true);
    } else {
      form.id = null;
      const defaults = {
        start_from_invoice: 1,
        start_from_quote: 1,
        start_from_credit_note: 1,
        start_from_deposit_invoice: 1,
        start_from_deposit_credit_note: 1,
        start_from_balance_invoice: 1,
        start_from_delivery_note: 1,
        start_from_purchase_order: 1,
      };
      Object.assign(form, defaults);
      originalStartValues.value = { ...defaults };
      currentValues.value = {
        current_invoice: 1,
        current_quote: 1,
        current_credit_note: 1,
        current_deposit_invoice: 1,
        current_deposit_credit_note: 1,
        current_balance_invoice: 1,
        current_delivery_note: 1,
        current_purchase_order: 1,
      };
      authStore.updateHasNumbering(false);
    }
  } catch {
    error("Erreur", "Impossible de charger la configuration.");
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  fetchConfig();
};

const resetToDefault = () => {
  const defaults = {
    format: "<doc><aa><cmp>",
    min_size: 5,
    reset_period: "yearly",
    start_from_invoice: 1,
    start_from_quote: 1,
    start_from_credit_note: 1,
    start_from_deposit_invoice: 1,
    start_from_deposit_credit_note: 1,
    start_from_balance_invoice: 1,
    start_from_delivery_note: 1,
    start_from_purchase_order: 1,
  };
  Object.assign(form, defaults);
  Object.keys(originalStartValues.value).forEach(key => {
    originalStartValues.value[key] = 1;
  });
  Object.keys(currentValues.value).forEach(key => {
    currentValues.value[key] = 1;
  });
};

const submit = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isSubmitting.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isSubmitting.value = false;
    return;
  }

  const payload = {
    company_id: companyId,
  };

  const formatLocked = hasDocuments.value;
  if (!formatLocked) {
    payload.format = form.format;
    payload.min_size = form.min_size;
    payload.reset_period = form.reset_period;
  }

  const startFields = [
    'start_from_invoice', 'start_from_quote', 'start_from_credit_note',
    'start_from_deposit_invoice', 'start_from_deposit_credit_note', 'start_from_balance_invoice',
    'start_from_delivery_note', 'start_from_purchase_order'
  ];

  startFields.forEach(field => {
    const currentKey = field.replace('start_from_', 'current_');
    const isLocked = originalStartValues.value[field] !== currentValues.value[currentKey];
    if (!isLocked) {
      payload[field] = form[field];
    }
  });

  delete payload.id;

  try {
    let response;
    if (form.id) {
      response = await axios.put("/api/numbering-serie", payload);
    } else {
      response = await axios.post("/api/numbering-serie", payload);
    }
    Object.assign(form, response.data);
    originalStartValues.value = {
      start_from_invoice: response.data.start_from_invoice,
      start_from_quote: response.data.start_from_quote,
      start_from_credit_note: response.data.start_from_credit_note,
      start_from_deposit_invoice: response.data.start_from_deposit_invoice,
      start_from_deposit_credit_note: response.data.start_from_deposit_credit_note,
      start_from_balance_invoice: response.data.start_from_balance_invoice,
      start_from_delivery_note: response.data.start_from_delivery_note,
      start_from_purchase_order: response.data.start_from_purchase_order,
    };
    currentValues.value = {
      current_invoice: response.data.current_invoice,
      current_quote: response.data.current_quote,
      current_credit_note: response.data.current_credit_note,
      current_deposit_invoice: response.data.current_deposit_invoice,
      current_deposit_credit_note: response.data.current_deposit_credit_note,
      current_balance_invoice: response.data.current_balance_invoice,
      current_delivery_note: response.data.current_delivery_note,
      current_purchase_order: response.data.current_purchase_order,
    };
    authStore.updateHasNumbering(true);
    success("Enregistré !", "La configuration a été mise à jour.");
    router.push({ name: "dashboard" });
  } catch (err) {
    if (err.response?.status === 422) {
      const errorData = err.response.data;
      if (errorData.errors) {
        Object.keys(errorData.errors).forEach((k) => {
          if (errors[k] !== undefined) errors[k] = errorData.errors[k][0];
        });
        validation(Object.values(errorData.errors).flat().join("\n"));
      } else if (errorData.error) {
        errors.server = errorData.error;
        error("Erreur", errorData.error);
      } else {
        errors.server = "Une erreur de validation s'est produite.";
        error("Erreur", "Veuillez vérifier les champs saisis.");
      }
    } else {
      error("Erreur", "Impossible de mettre à jour.");
    }
  } finally {
    isSubmitting.value = false;
  }
};

const isFieldDisabled = (fieldKey) => {
  if (['format', 'min_size', 'reset_period'].includes(fieldKey)) {
    return hasDocuments.value;
  }
  const currentKey = fieldKey.replace('start_from_', 'current_');
  return originalStartValues.value[fieldKey] !== currentValues.value[currentKey];
};

onMounted(async () => {
  await fetchConfig();
  if (!authStore.hasNumbering && !welcomeShown.value) {
    welcomeShown.value = true;
    showWelcomeModal(
      'Paramétrez votre numérotation',
      'Choisissez le format de vos numéros de documents et définissez vos compteurs de départ.',
      'C\'est parti'
    );
  }
});
</script>

<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex gap-6">
              <button class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]">
                <i class="fas fa-hashtag"></i>
                Préférences pour la numérotation
              </button>
            </div>
          </div>

          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement...</p>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-8">
              <div class="rounded-xl border border-gray-200/80 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base font-bold text-[#062121] flex items-center gap-2">
                    <i class="fas fa-code text-gray-400"></i> Format
                  </h3>
                  <!-- <span v-if="hasDocuments" class="text-xs text-amber-600 bg-amber-50 px-3 py-1 rounded-full flex items-center gap-1">
                    <i class="fas fa-lock"></i> Modifications verrouillées
                  </span> -->
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <InputLabel for="format" value="Format de la numérotation" />
                    <div class="relative">
                      <CustomSelect
                        id="format"
                        v-model="form.format"
                        :options="formatOptions"
                        label-key="label"
                        value-key="value"
                        placeholder="Choisir un format"
                        :disabled="isFieldDisabled('format')"
                        :class="{ 'cursor-not-allowed opacity-60': isFieldDisabled('format') }"
                      />
                      <i v-if="isFieldDisabled('format')" class="fas fa-lock absolute right-10 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <p v-if="isFieldDisabled('format')" class="text-xs text-gray-500 mt-1">
                      <i class="fas fa-info-circle"></i> Modifiable uniquement avant la création du premier document.
                    </p>
                    <InputError class="mt-2" :message="errors.format" />
                  </div>
                  <div>
                    <InputLabel value="Aperçu du résultat" />
                    <div class="mt-1 px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 text-sm font-mono text-gray-800">
                      {{ previewFormat }}
                    </div>
                  </div>
                  <div>
                    <InputLabel for="min_size" value="Taille minimale du compteur" />
                    <div class="relative">
                      <TextInput
                        id="min_size"
                        type="number"
                        min="1"
                        max="20"
                        class="mt-1 block w-full"
                        v-model="form.min_size"
                        :disabled="isFieldDisabled('min_size')"
                        :class="{ 'cursor-not-allowed opacity-60': isFieldDisabled('min_size') }"
                      />
                      <i v-if="isFieldDisabled('min_size')" class="fas fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <p v-if="isFieldDisabled('min_size')" class="text-xs text-gray-500 mt-1">
                      <i class="fas fa-info-circle"></i> Modifiable uniquement avant la création du premier document.
                    </p>
                    <InputError class="mt-2" :message="errors.min_size" />
                  </div>
                  <div>
                    <InputLabel for="reset_period" value="Réinitialisation du compteur" />
                    <div class="relative">
                      <CustomSelect
                        id="reset_period"
                        v-model="form.reset_period"
                        :options="resetPeriodOptions"
                        label-key="label"
                        value-key="value"
                        placeholder="Choisir une période"
                        :disabled="isFieldDisabled('reset_period')"
                        :class="{ 'cursor-not-allowed opacity-60': isFieldDisabled('reset_period') }"
                      />
                      <i v-if="isFieldDisabled('reset_period')" class="fas fa-lock absolute right-10 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <p v-if="isFieldDisabled('reset_period')" class="text-xs text-gray-500 mt-1">
                      <i class="fas fa-info-circle"></i> Modifiable uniquement avant la création du premier document.
                    </p>
                    <InputError class="mt-2" :message="errors.reset_period" />
                  </div>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200/80 p-6 shadow-sm">
                <h3 class="text-base font-bold text-[#062121] mb-4 flex items-center gap-2">
                  <i class="fas fa-calculator text-gray-400"></i> Compteurs
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div v-for="field in counterFields" :key="field.key">
                    <InputLabel :for="field.key" :value="field.label" />
                    <div class="relative">
                      <TextInput
    :id="field.key"
    type="number"
    min="0"
    class="mt-1"
    v-model="form[field.key]"
    :disabled="isFieldDisabled(field.key)"
/>
                      <i v-if="isFieldDisabled(field.key)" class="fas fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <p v-if="isFieldDisabled(field.key)" class="text-xs text-gray-500 mt-1">
                      <i class="fas fa-info-circle"></i> Verrouillé car le compteur a déjà évolué.
                    </p>
                    <InputError class="mt-2" :message="errors[field.key]" />
                  </div>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200/80 p-6 shadow-sm">
                <h3 class="text-base font-bold text-[#062121] mb-4 flex items-center gap-2">
                  <i class="fas fa-book text-gray-400"></i> Documentation
                </h3>
                <p class="text-sm text-gray-600 mb-4">
                  Vous pouvez personnaliser la numérotation de vos documents (factures, devis, etc.).<br />
                  Seuls les lettres, chiffres et ces caractères spéciaux sont acceptés :
                  <code class="bg-gray-100 px-2 py-0.5 rounded">- _ . #</code>
                </p>
                <div class="overflow-x-auto">
                  <table class="min-w-full border-collapse">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type du document</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Année</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Mois</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Jours</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Compteur incrémental</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;doc&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;aa&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;m&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;j&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;cmp&gt;</code></td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 text-sm text-gray-600">F</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentYearShort }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentMonthShort }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentDayShort }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ paddedCounter }}</td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;doc&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;aaaa&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;mm&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;jj&gt;</code></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><code class="bg-gray-100 px-2 py-0.5 rounded">&lt;cmp&gt;</code></td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 text-sm text-gray-600">F</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentYear }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentMonth }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ currentDay }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ paddedCounter }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="flex justify-between items-center gap-3 pt-6 border-t border-gray-100">
                <button
                  v-if="!form.id"
                  type="button"
                  @click="resetToDefault"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium flex items-center gap-2"
                >
                  <i class="fas fa-undo"></i>
                  Utiliser la numérotation par défaut
                </button>
                <div class="flex gap-3 ml-auto">
                  <button
                    type="button"
                    @click="resetForm"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    Réinitialiser
                  </button>
                  <PrimaryButton :disabled="isSubmitting">
                    <span v-if="isSubmitting">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                      </svg>
                      Enregistrement...
                    </span>
                    <span v-else>Enregistrer</span>
                  </PrimaryButton>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>