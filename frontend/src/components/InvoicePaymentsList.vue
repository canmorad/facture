<!-- src/components/InvoicePaymentsList.vue -->
<script setup>
import { ref, computed, onMounted } from "vue";
import { paymentApi } from "@/services/paymentApi";
import { confirm, success, error } from "@/helpers/notifications";

const props = defineProps({
  invoiceId: {
    type: [Number, String],
    required: true,
  },
  invoiceTotal: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(["payment-cancelled"]);

const isLoading = ref(false);
const payments = ref([]);
const paymentSummary = ref(null);

const totalPaid = computed(() => {
  return paymentSummary.value?.total_paid || 0;
});

const remainingAmount = computed(() => {
  return paymentSummary.value?.remaining_amount || props.invoiceTotal;
});

const paymentPercentage = computed(() => {
  return paymentSummary.value?.payment_percentage || 0;
});

const isFullyPaid = computed(() => {
  return paymentSummary.value?.is_fully_paid || false;
});

const fetchPayments = async () => {
  isLoading.value = true;
  try {
    const [paymentsData, summaryData] = await Promise.all([
      paymentApi.getInvoicePayments(props.invoiceId),
      paymentApi.getInvoicePaymentSummary(props.invoiceId),
    ]);
    payments.value = paymentsData;
    paymentSummary.value = summaryData;
  } catch (err) {
    console.error("Error fetching payments:", err);
  } finally {
    isLoading.value = false;
  }
};

const cancelPayment = async (paymentId) => {
  const payment = payments.value.find((p) => p.id === paymentId);
  if (!payment) return;

  const result = await confirm(
    "Annuler le paiement",
    `Annuler le paiement de ${formatCurrency(payment.amount)} ? Cette action est irréversible.`
  );

  if (!result.isConfirmed) return;

  try {
    await paymentApi.cancelPayment(paymentId);
    success("Annulé !", "Le paiement a été annulé avec succès.");
    emit("payment-cancelled");
    await fetchPayments();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'annuler le paiement."
    );
  }
};

const getPaymentModeIcon = (mode) => {
  const icons = {
    espece: "fa-money-bill-wave",
    cheque: "fa-check",
    lcn: "fa-file-contract",
    virement: "fa-university",
    carte: "fa-credit-card",
  };
  return icons[mode] || "fa-question";
};

const getPaymentModeLabel = (mode) => {
  const labels = {
    espece: "Espèces",
    cheque: "Chèque",
    lcn: "LCN",
    virement: "Virement",
    carte: "Carte",
  };
  return labels[mode] || mode;
};

const getStatusBadgeClass = (status) => {
  const classes = {
    completed: "bg-green-100 text-green-700",
    pending: "bg-yellow-100 text-yellow-700",
    cancelled: "bg-red-100 text-red-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusLabel = (status) => {
  const labels = {
    completed: "Complété",
    pending: "En attente",
    cancelled: "Annulé",
  };
  return labels[status] || status;
};

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

onMounted(() => fetchPayments());
</script>

<template>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h3 class="text-sm font-semibold text-gray-900">Paiements enregistrés</h3>
    </div>

    <!-- Summary -->
    <div class="px-4 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
      <div class="grid grid-cols-3 gap-4 text-center">
        <div>
          <p class="text-xs text-gray-600 uppercase">Total Payé</p>
          <p class="text-lg font-bold text-green-600">
            {{ formatCurrency(totalPaid) }}
          </p>
        </div>
        <div>
          <p class="text-xs text-gray-600 uppercase">Reste à payer</p>
          <p class="text-lg font-bold" :class="remainingAmount > 0 ? 'text-orange-600' : 'text-gray-500'">
            {{ formatCurrency(remainingAmount) }}
          </p>
        </div>
        <div>
          <p class="text-xs text-gray-600 uppercase">Progression</p>
          <p class="text-lg font-bold text-gray-700">{{ paymentPercentage }}%</p>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="mt-3 w-full bg-gray-200 rounded-full h-2">
        <div
          class="bg-green-500 h-2 rounded-full transition-all duration-500"
          :style="{ width: Math.min(paymentPercentage, 100) + '%' }"
        ></div>
      </div>

      <p v-if="isFullyPaid" class="text-center text-green-600 text-sm font-medium mt-2">
        <i class="fas fa-check-circle mr-1"></i>
        Facture entièrement payée
      </p>
    </div>

    <!-- Payments list -->
    <div v-if="isLoading" class="p-4 text-center">
      <i class="fas fa-spinner fa-spin text-gray-400"></i>
      <p class="text-sm text-gray-500 mt-2">Chargement des paiements...</p>
    </div>

    <div v-else-if="payments.length === 0" class="p-4 text-center">
      <i class="fas fa-receipt text-gray-300 text-4xl mb-2 block"></i>
      <p class="text-sm text-gray-500">Aucun paiement enregistré</p>
    </div>

    <div v-else class="divide-y divide-gray-100">
      <div
        v-for="payment in payments"
        :key="payment.id"
        class="p-4 hover:bg-gray-50 transition-colors"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <!-- Payment mode icon -->
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i :class="['fas', getPaymentModeIcon(payment.payment_mode), 'text-blue-600']"></i>
            </div>

            <!-- Payment details -->
            <div>
              <p class="text-sm font-semibold text-gray-900">
                {{ getPaymentModeLabel(payment.payment_mode) }}
                <span
                  :class="[
                    'ml-2 px-2 py-0.5 text-xs font-medium rounded-full',
                    getStatusBadgeClass(payment.status),
                  ]"
                >
                  {{ getStatusLabel(payment.status) }}
                </span>
              </p>
              <div class="flex items-center gap-4 mt-1 text-xs text-gray-500">
                <span>{{ formatDate(payment.payment_date) }}</span>
                <span v-if="payment.reference">Réf: {{ payment.reference }}</span>
                <span v-if="payment.cash_transaction">
                  <i class="fas fa-cash-register mr-1"></i>
                  Caisse #{{ payment.cash_transaction.cash_register_id }}
                </span>
                <span v-if="payment.payment_document">
                  <i class="fas fa-file-invoice mr-1"></i>
                  Doc #{{ payment.payment_document.number }}
                </span>
              </div>
            </div>
          </div>

          <!-- Amount and actions -->
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="text-lg font-bold text-gray-900">
                {{ formatCurrency(payment.amount) }}
              </p>
            </div>

            <button
              v-if="payment.status === 'completed'"
              @click="cancelPayment(payment.id)"
              class="w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition-colors"
              title="Annuler le paiement"
            >
              <i class="fas fa-times-circle"></i>
            </button>
          </div>
        </div>

        <!-- Additional details for documentary payments -->
        <div
          v-if="payment.payment_document"
          class="mt-3 ml-14 pl-4 border-l-2 border-gray-200 text-xs text-gray-600"
        >
          <div class="grid grid-cols-2 gap-2">
            <span>Document: {{ payment.payment_document.number }}</span>
            <span>Échéance: {{ formatDate(payment.payment_document.due_date) }}</span>
            <span v-if="payment.payment_document.drawer_name">
              Tireur: {{ payment.payment_document.drawer_name }}
            </span>
            <span v-if="payment.payment_document.drawer_bank">
              Banque: {{ payment.payment_document.drawer_bank }}
            </span>
          </div>
          <div v-if="payment.payment_document.bank_remittance" class="mt-2">
            <span class="inline-flex px-2 py-1 bg-blue-100 text-blue-700 rounded">
              <i class="fas fa-university mr-1"></i>
              Remise #{{ payment.payment_document.bank_remittance.number }}
            </span>
          </div>
        </div>

        <!-- Cash transaction details -->
        <div
          v-if="payment.cash_transaction"
          class="mt-3 ml-14 pl-4 border-l-2 border-gray-200 text-xs text-gray-600"
        >
          <span>Transaction #{{ payment.cash_transaction.id }}</span>
          <span v-if="payment.cash_transaction.reference" class="ml-2">
            Réf: {{ payment.cash_transaction.reference }}
          </span>
        </div>

        <!-- Notes -->
        <div v-if="payment.notes" class="mt-2 ml-14 text-xs text-gray-500 italic">
          "{{ payment.notes }}"
        </div>
      </div>
    </div>
  </div>
</template>
