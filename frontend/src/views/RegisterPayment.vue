<!-- src/views/RegisterPayment.vue -->
<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import RegisterPaymentForm from "@/components/RegisterPaymentForm.vue";
import { success, error } from "@/helpers/notifications";
import axios from "axios";

const router = useRouter();
const route = useRoute();

const document = ref(null);
const isLoading = ref(true);
const documentType = ref(null);

const typeConfig = {
  invoice: {
    apiEndpoint: "/api/invoices",
    label: "Facture",
    returnRoute: "invoice.index",
    icon: "fa-file-invoice",
  },
  deposit: {
    apiEndpoint: "/api/deposits",
    label: "Facture d'acompte",
    returnRoute: "deposit.index",
    icon: "fa-file-invoice-dollar",
  },
  "balance-invoice": {
    apiEndpoint: "/api/balance-invoices",
    label: "Facture de solde",
    returnRoute: "balance-invoice.index",
    icon: "fa-file-invoice-dollar",
  },
};

const detectedType = computed(() => {
  const typeFromRoute = route.params.type;
  if (typeFromRoute === "balance" || typeFromRoute === "balance-invoice") {
    return "balance-invoice";
  }
  return typeFromRoute || "invoice";
});

const config = computed(() => {
  return typeConfig[detectedType.value] || typeConfig.invoice;
});

const documentTypeLabel = computed(() => {
  const labels = {
    'invoice': 'Facture',
    'deposit': 'Facture d\'acompte',
    'balance-invoice': 'Facture de solde',
  };
  return labels[detectedType.value] || 'Document';
});

const fetchDocument = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`${config.value.apiEndpoint}/${route.params.id}`);
    document.value = data;
    documentType.value = detectedType.value;
  } catch (err) {
    error("Erreur", err.response?.data?.error || "Impossible de charger le document.");
    goBack();
  } finally {
    isLoading.value = false;
  }
};

const handlePaymentRegistered = (payment) => {
  success("Paiement enregistré !", "Le paiement a été enregistré avec succès.");
  goBack();
};

const handleCancel = () => {
  goBack();
};

const handleOpenCashSession = () => {
  sessionStorage.setItem("returnToPayment", "true");
  sessionStorage.setItem("returnToDocumentId", route.params.id);
  sessionStorage.setItem("returnToDocumentType", detectedType.value);
  router.push({ name: "cash-register.index", query: { openSession: true } });
};

const goBack = () => {
  router.push({ name: config.value.returnRoute });
};

const checkReturnToPayment = () => {
  if (sessionStorage.getItem("returnToPayment") === "true") {
    const savedId = sessionStorage.getItem("returnToDocumentId");
    const savedType = sessionStorage.getItem("returnToDocumentType");
    sessionStorage.removeItem("returnToPayment");
    sessionStorage.removeItem("returnToDocumentId");
    sessionStorage.removeItem("returnToDocumentType");
    if (savedId == route.params.id && savedType === detectedType.value) {
      return;
    }
  }
};

onMounted(() => {
  checkReturnToPayment();
  fetchDocument();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8 max-w-4xl mx-auto">
        <!-- Entête avec bouton de retour et titre -->
        <div class="mb-6 flex items-center gap-4">
          <button
            @click="goBack"
            class="w-10 h-10 rounded-lg bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-800 transition-all duration-200 flex items-center justify-center"
            title="Retour à la liste"
          >
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="text-2xl font-bold text-[#062121]">Enregistrer un paiement</h1>
            <p class="text-sm text-gray-500 mt-0.5">
              {{ documentTypeLabel }} <span v-if="document">#{{ document.number }}</span>
            </p>
          </div>
        </div>

        <!-- Container principal blanc -->
        <div class="bg-white shadow-lg rounded-lg p-8">
          <!-- Loading state -->
          <div v-if="isLoading" class="text-center py-16">
            <svg
              class="animate-spin h-10 w-10 mx-auto text-[#C5F82A]"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-3 text-gray-500">Chargement des informations...</p>
          </div>

          <!-- Payment Form -->
          <RegisterPaymentForm
            v-else-if="document"
            :document="document"
            :document-type="documentType"
            @payment-registered="handlePaymentRegistered"
            @cancel="handleCancel"
            @open-cash-session="handleOpenCashSession"
          />

          <!-- No document state -->
          <div v-else class="text-center py-16">
            <i class="fas fa-exclamation-circle text-5xl text-gray-300 mb-4 block"></i>
            <p class="text-gray-500">Document non trouvé.</p>
            <button
              @click="goBack"
              class="mt-4 !px-4 !py-2 !bg-[#0F172A] !text-white border-none rounded-lg font-semibold text-sm inline-flex items-center gap-2"
            >
              <i class="fas fa-arrow-left"></i> Retour à la liste
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
