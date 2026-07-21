<!-- src/components/RegisterPaymentForm.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { paymentApi } from "@/services/paymentApi";
import { success, error } from "@/helpers/notifications";
import TextInput from "@/components/TextInput.vue";
import InputLabel from "@/components/InputLabel.vue";
import InputError from "@/components/InputError.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import CustomSelect from "@/components/CustomSelect.vue";

const props = defineProps({
  invoice: { type: Object, default: null },
  document: { type: Object, default: null },
  documentType: { type: String, default: null },
});

const emit = defineEmits(["payment-registered", "cancel", "open-cash-session"]);

const activeDocument = computed(() => props.invoice || props.document);

const isLoading = ref(false);
const cashRegisters = ref([]);
const activeSessions = ref([]);
const paymentSummary = ref(null);
const creationData = ref(null);

const formData = ref({
  payment_mode: "espece",
  amount: 0,
  payment_date: new Date().toISOString().split("T")[0],
  reference: "",
  notes: "",
  cash_register_id: null,
  // For cheques and LCN
  document_number: "",
  due_date: "",
  drawer_name: "",
  drawer_bank: "",
  drawer_account: "",
  drawer_address: "",
  beneficiary_name: "",
  document_notes: "",
});

const errors = ref({
  amount: "",
  cash_register_id: "",
  cash_session: "",
  document_number: "",
  due_date: "",
  server: "",
});

const paymentModes = [
  { value: "espece", label: "Espèces", icon: "fa-money-bill-wave" },
  { value: "cheque", label: "Chèque", icon: "fa-check" },
  { value: "lcn", label: "LCN (Lettre de Change)", icon: "fa-file-contract" },
  { value: "virement", label: "Virement", icon: "fa-university" },
  { value: "carte", label: "Carte", icon: "fa-credit-card" },
];

const detectedDocumentType = computed(() => {
  if (props.documentType) return props.documentType;
  if (activeDocument.value?.type) {
    const typeMap = {
      'Facture de solde': 'balance-invoice',
      'Facture d\'acompte': 'deposit',
      'Facture standard': 'invoice',
      'deposit': 'deposit',
      'balance_invoice': 'balance-invoice',
    };
    return typeMap[activeDocument.value.type] || 'invoice';
  }
  return 'invoice';
});

const documentTypeLabel = computed(() => {
  const labels = {
    'invoice': 'Facture',
    'deposit': 'Facture d\'acompte',
    'balance-invoice': 'Facture de solde',
  };
  return labels[detectedDocumentType.value] || 'Document';
});

const isDocumentaryPayment = computed(() => {
  return ["cheque", "lcn"].includes(formData.value.payment_mode);
});

const isCashPayment = computed(() => {
  return formData.value.payment_mode === "espece";
});

const remainingAmount = computed(() => {
  return paymentSummary.value?.remaining_amount ?? activeDocument.value?.total_ttc ?? 0;
});

const hasOpenSession = computed(() => {
  return activeSessions.value && activeSessions.value.length > 0;
});

const cashRegisterOptions = computed(() => {
  return Array.isArray(cashRegisters.value)
    ? cashRegisters.value.map((cr) => ({
        value: cr.id,
        label: `${cr.name} (${cr.type})`,
      }))
    : [];
});

const paymentModeOptions = computed(() => {
  return paymentModes.map((mode) => ({
    value: mode.value,
    label: mode.label,
  }));
});

// Formatage monétaire
const fmtMoney = (amount) => {
  if (!amount && amount !== 0) return "0,00";
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

onMounted(async () => {
  if (activeDocument.value && activeDocument.value.id) {
    try {
      const [summaryData, creation] = await Promise.all([
        paymentApi.getDocumentPaymentSummary(activeDocument.value.id),
        paymentApi.getCreationData(),
      ]);
      paymentSummary.value = summaryData;
      creationData.value = creation;
      cashRegisters.value = creation.cash_registers || [];
      activeSessions.value = Object.values(creation.active_sessions || {});
      formData.value.amount = summaryData.remaining_amount;

      if (isCashPayment.value && creation.default_cash_register_id) {
        formData.value.cash_register_id = creation.default_cash_register_id;
      }
    } catch (err) {
      console.error("Error fetching payment data:", err);
      paymentSummary.value = null;
      creationData.value = null;
      formData.value.amount = activeDocument.value.total_ttc || 0;
    }
  }
});

const resetForm = () => {
  formData.value = {
    payment_mode: "espece",
    amount: paymentSummary.value?.remaining_amount || activeDocument.value?.total_ttc || 0,
    payment_date: new Date().toISOString().split("T")[0],
    reference: "",
    notes: "",
    cash_register_id: creationData.value?.default_cash_register_id || null,
    document_number: "",
    due_date: "",
    drawer_name: "",
    drawer_bank: "",
    drawer_account: "",
    drawer_address: "",
    beneficiary_name: "",
    document_notes: "",
  };
  errors.value = {
    amount: "",
    cash_register_id: "",
    cash_session: "",
    document_number: "",
    due_date: "",
    server: "",
  };
};

const validate = () => {
  errors.value = { amount: "", cash_register_id: "", cash_session: "", document_number: "", due_date: "", server: "" };

  if (!formData.value.amount || formData.value.amount <= 0) {
    errors.value.amount = "Le montant doit être supérieur à zéro.";
  }

  if (formData.value.amount > remainingAmount.value) {
    errors.value.amount = `Le montant ne peut pas dépasser le reste à payer (${remainingAmount.value.toFixed(2)} DH).`;
  }

  if (isCashPayment.value) {
    if (!hasOpenSession.value) {
      errors.value.cash_session = "Aucune session de caisse ouverte. Veuillez ouvrir une session avant d'enregistrer un paiement en espèces.";
    }
    if (!formData.value.cash_register_id) {
      errors.value.cash_register_id = "Veuillez sélectionner une caisse.";
    }
  }

  if (isDocumentaryPayment.value) {
    if (!formData.value.document_number) {
      errors.value.document_number = "Le numéro de document est requis.";
    }
    if (!formData.value.due_date) {
      errors.value.due_date = "La date d'échéance est requise.";
    }
  }

  return !errors.value.amount && !errors.value.cash_register_id && !errors.value.cash_session && !errors.value.document_number && !errors.value.due_date;
};

const submitPayment = async () => {
  if (!validate()) return;

  if (!activeDocument.value || !activeDocument.value.id) {
    error("Erreur", "Aucun document sélectionné.");
    return;
  }

  isLoading.value = true;

  try {
    const paymentData = {
      document_id: activeDocument.value.id,
      document_type: detectedDocumentType.value,
      payment_mode: formData.value.payment_mode,
      amount: parseFloat(formData.value.amount),
      payment_date: formData.value.payment_date,
      reference: formData.value.reference || null,
      notes: formData.value.notes || null,
    };

    if (isCashPayment.value) {
      paymentData.cash_register_id = formData.value.cash_register_id;
    }

    if (isDocumentaryPayment.value) {
      paymentData.document_number = formData.value.document_number;
      paymentData.due_date = formData.value.due_date;
      paymentData.drawer_name = formData.value.drawer_name || null;
      paymentData.drawer_bank = formData.value.drawer_bank || null;
      paymentData.drawer_account = formData.value.drawer_account || null;
      paymentData.drawer_address = formData.value.drawer_address || null;
      paymentData.beneficiary_name = formData.value.beneficiary_name || null;
      paymentData.document_notes = formData.value.document_notes || null;
    }

    const payment = await paymentApi.createPayment(paymentData);

    success("Paiement enregistré !", `Le paiement de ${fmtMoney(formData.value.amount)} DH a été enregistré avec succès.`);

    emit("payment-registered", payment);
    resetForm();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'enregistrer le paiement.");
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors;
      Object.keys(serverErrors).forEach((key) => {
        if (errors.value.hasOwnProperty(key)) {
          errors.value[key] = serverErrors[key][0];
        }
      });
    }
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <form @submit.prevent="submitPayment" class="space-y-6">
    <InputError :message="errors.server" />

    <!-- Bloc Document lié - Restructuré -->
    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
      <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
        <i class="fas fa-link text-gray-400"></i>
        Document lié
      </h3>
      <div class="text-sm text-gray-700 space-y-2">
        <div class="flex gap-3">
          <span class="text-gray-500 font-medium w-32">Document</span>
          <span class="text-gray-800 font-semibold">#{{ activeDocument?.number || activeDocument?.id }}</span>
        </div>
        <div class="flex gap-3">
          <span class="text-gray-500 font-medium w-32">Client</span>
          <span class="text-gray-800">
            {{ activeDocument?.customer?.customerable
              ? activeDocument.customer.type === "b2b"
                ? activeDocument.customer.customerable.legal_name
                : activeDocument.customer.customerable.name
              : activeDocument?.customer?.name || '—'
            }}
          </span>
        </div>
        <div class="flex gap-3">
          <span class="text-gray-500 font-medium w-32">Total TTC</span>
          <span class="text-gray-800 font-medium">{{ fmtMoney(activeDocument?.total_ttc) }} DH</span>
        </div>
        <div class="flex gap-3 border-t border-gray-200 pt-2 mt-1">
          <span class="text-gray-500 font-medium w-32">Reste à payer</span>
          <span class="text-red-600 font-bold">{{ fmtMoney(remainingAmount) }} DH</span>
        </div>
      </div>
    </div>

    <!-- Mode de paiement -->
    <div class="mb-6">
      <InputLabel value="Mode de paiement *" />
      <CustomSelect
        v-model="formData.payment_mode"
        :options="paymentModeOptions"
        placeholder="Sélectionner un mode de paiement"
        class="mt-1"
      />
    </div>

    <!-- Alertes -->
    <div
      v-if="isCashPayment && !hasOpenSession"
      class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 mb-6"
    >
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0">
          <i class="fas fa-exclamation-triangle text-yellow-600"></i>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold text-yellow-800">Aucune session de caisse ouverte</p>
          <p class="text-xs text-yellow-700 mt-0.5">
            Les paiements en espèces nécessitent une session de caisse ouverte.
          </p>
        </div>
        <button
          type="button"
          @click="$emit('open-cash-session')"
          class="px-4 py-2 text-sm font-semibold text-yellow-800 bg-yellow-100 hover:bg-yellow-200 rounded-lg transition-all duration-200"
        >
          <i class="fas fa-lock-open mr-1"></i>
          Ouvrir Session
        </button>
      </div>
    </div>

    <div
      v-if="isDocumentaryPayment"
      class="rounded-xl border border-blue-200 bg-blue-50 p-4 mb-6"
    >
      <div class="flex items-start gap-3">
        <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
          <i class="fas fa-info-circle text-blue-600"></i>
        </div>
        <div>
          <p class="text-sm font-medium text-blue-800">Document à encaisser</p>
          <p class="text-xs text-blue-700 mt-0.5">
            Ce document sera ajouté aux "Documents en attente" et pourra être inclus dans une remise bancaire.
          </p>
        </div>
      </div>
    </div>

    <!-- Informations de paiement - Grille 2 colonnes -->
    <div class="mb-6">
      <h4 class="text-base font-bold text-[#062121] mb-4">Informations de paiement</h4>
      <div class="grid grid-cols-2 gap-4">
        <!-- Montant à payer -->
        <div>
          <InputLabel value="Montant à payer *" />
          <TextInput
            v-model.number="formData.amount"
            type="number"
            step="0.01"
            placeholder="0.00"
            class="mt-1"
          />
          <InputError class="mt-1" :message="errors.amount" />
        </div>

        <!-- Date de paiement -->
        <div>
          <InputLabel value="Date de paiement *" />
          <TextInput
            v-model="formData.payment_date"
            type="date"
            class="mt-1"
          />
        </div>

        <!-- Référence -->
        <div>
          <InputLabel value="Référence" />
          <TextInput
            v-model="formData.reference"
            type="text"
            placeholder="Numéro de référence"
            class="mt-1"
          />
        </div>

        <!-- Caisse (pour espèces) -->
        <div v-if="isCashPayment">
          <InputLabel value="Caisse *" />
          <CustomSelect
            v-model="formData.cash_register_id"
            :options="cashRegisterOptions"
            placeholder="Sélectionner une caisse"
            class="mt-1"
          />
          <InputError class="mt-1" :message="errors.cash_register_id || errors.cash_session" />
        </div>

        <!-- Placeholder pour garder le grid aligné -->
        <div v-if="!isCashPayment && !isDocumentaryPayment"></div>
      </div>
    </div>

    <!-- Informations documentaires (chèque/LCN) -->
    <div v-if="isDocumentaryPayment" class="mb-6">
      <h4 class="text-base font-bold text-[#062121] mb-4">Informations du document</h4>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <InputLabel value="Numéro du document *" />
          <TextInput
            v-model="formData.document_number"
            type="text"
            placeholder="N° chèque/LCN"
            class="mt-1"
          />
          <InputError class="mt-1" :message="errors.document_number" />
        </div>

        <div>
          <InputLabel value="Date d'échéance *" />
          <TextInput
            v-model="formData.due_date"
            type="date"
            class="mt-1"
          />
          <InputError class="mt-1" :message="errors.due_date" />
        </div>

        <div>
          <InputLabel value="Tireur (Nom)" />
          <TextInput
            v-model="formData.drawer_name"
            type="text"
            placeholder="Nom du tireur"
            class="mt-1"
          />
        </div>

        <div>
          <InputLabel value="Banque du tireur" />
          <TextInput
            v-model="formData.drawer_bank"
            type="text"
            placeholder="Nom de la banque"
            class="mt-1"
          />
        </div>

        <div>
          <InputLabel value="Numéro de compte" />
          <TextInput
            v-model="formData.drawer_account"
            type="text"
            placeholder="Numéro de compte"
            class="mt-1"
          />
        </div>

        <div>
          <InputLabel value="Bénéficiaire" />
          <TextInput
            v-model="formData.beneficiary_name"
            type="text"
            placeholder="Nom du bénéficiaire"
            class="mt-1"
          />
        </div>

        <div class="col-span-2">
          <InputLabel value="Adresse du tireur" />
          <TextInput
            v-model="formData.drawer_address"
            type="text"
            placeholder="Adresse complète"
            class="mt-1"
          />
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="mb-6">
      <InputLabel value="Notes" />
      <textarea
        v-model="formData.notes"
        rows="3"
        placeholder="Ajouter des notes additionnelles..."
        class="mt-1 block w-full p-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] text-sm transition-all duration-300 outline-none focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
      ></textarea>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
      <button
        type="button"
        @click="$emit('cancel')"
        class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-semibold text-sm transition-all"
      >
        Annuler
      </button>
      <PrimaryButton type="submit" :disabled="isLoading" class="!px-6 !py-2.5">
        <span v-if="isLoading" class="flex items-center gap-2">
          <i class="fas fa-spinner fa-spin"></i>
          Enregistrement...
        </span>
        <span v-else class="flex items-center gap-2">
          <i class="fas fa-check"></i>
          Enregistrer le paiement
        </span>
      </PrimaryButton>
    </div>
  </form>
</template>
