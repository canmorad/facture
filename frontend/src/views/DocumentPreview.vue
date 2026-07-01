<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from "vue";
import { useRoute, useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import Swal from "sweetalert2";

// ── Async template imports ────────────────────────────────────────────────────
const templateComponents = {
  classic: defineAsyncComponent(
    () => import("../components/templates/TemplateClassic.vue"),
  ),
  modern: defineAsyncComponent(
    () => import("../components/templates/TemplateModern.vue"),
  ),
  minimal: defineAsyncComponent(
    () => import("../components/templates/TemplateMinimal.vue"),
  ),
  creative: defineAsyncComponent(
    () => import("../components/templates/TemplateCreative.vue"),
  ),
};

// ── State ─────────────────────────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();
const invoice = ref(null);
const companySettings = ref(null);
const isLoading = ref(false);

const invoiceId = computed(() =>
  route.params.id ? parseInt(route.params.id) : null,
);

// ── Active template driven purely by companySettings ─────────────────────────
const ActiveTemplate = computed(() => {
  const id = companySettings.value?.template_id || "classic";
  return templateComponents[id] ?? templateComponents.classic;
});

const templateLabel = computed(() => {
  const map = {
    classic: "Classique",
    modern: "Moderne",
    minimal: "Minimaliste",
    creative: "Créatif",
  };
  return map[companySettings.value?.template_id] ?? "Classique";
});

// ── Data fetching ─────────────────────────────────────────────────────────────
const fetchInvoice = async () => {
  if (!invoiceId.value) {
    router.push("/invoices");
    return;
  }
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/invoices/${invoiceId.value}`);
    invoice.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger la facture.",
      icon: "error",
      confirmButtonColor: "#062121",
    });
    router.push("/invoices");
  } finally {
    isLoading.value = false;
  }
};

const fetchCompanySettings = async () => {
  try {
    const { data } = await axios.get("/api/company-settings");
    companySettings.value = data;
  } catch {
    companySettings.value = null;
  }
};

// ── Actions ───────────────────────────────────────────────────────────────────
const printDocument = () => window.print();

const goEdit = () => {
  const path = invoice.value?.type === "devis"
    ? `/document/edit/${invoiceId.value}`
    : `/document/edit/${invoiceId.value}`;
  router.push(path);
};

const goToTemplateSettings = () => {
  router.push("/company");
};

const statusMap = {
  draft: { label: "Brouillon", classes: "bg-gray-100 text-gray-600" },
  sent: { label: "Envoyé", classes: "bg-blue-100 text-blue-700" },
  paid: { label: "Payé", classes: "bg-green-100 text-green-700" },
  overdue: { label: "En retard", classes: "bg-red-100 text-red-700" },
  cancelled: { label: "Annulé", classes: "bg-orange-100 text-orange-700" },
};

onMounted(() => Promise.all([fetchInvoice(), fetchCompanySettings()]));
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-100 min-h-screen">
      <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
      <div
        class="print:hidden sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm"
      >
        <div class="max-w-5xl mx-auto px-6 py-3">
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <!-- Left -->
            <div class="flex items-center gap-3">
              <button
                @click="router.back()"
                class="back-btn inline-flex items-center gap-2 px-3 py-2 text-sm text-[#062121] hover:text-[#C5F82A] transition-colors duration-200 bg-transparent border-none cursor-pointer font-medium"
              >
                <i class="fas fa-arrow-left text-xs"></i> Retour
              </button>

              <div v-if="invoice" class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-800">{{
                  invoice.number
                }}</span>
                <span
                  v-if="invoice.status"
                  class="text-xs font-semibold px-2 py-0.5 rounded-full"
                  :class="
                    statusMap[invoice.status]?.classes ||
                    'bg-gray-100 text-gray-600'
                  "
                >
                  {{ statusMap[invoice.status]?.label || invoice.status }}
                </span>
              </div>
            </div>

            <!-- Right -->
            <div class="flex items-center gap-2">
              <!-- Template indicator (read-only — managed in Settings) -->
              <button
                @click="goToTemplateSettings"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-palette text-xs"></i>
                Modèle : {{ templateLabel }}
              </button>

              <!-- Edit -->
              <button
                @click="goEdit"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-edit text-xs"></i> Modifier
              </button>

              <!-- Print -->
              <button
                @click="printDocument"
                      class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
                style="background: #062121"
              >
                <i class="fas fa-print text-xs"></i> Imprimer / PDF
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Loading ─────────────────────────────────────────────────────── -->
      <div v-if="isLoading" class="flex items-center justify-center py-24">
        <div class="text-center">
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
          <p class="mt-3 text-sm text-gray-500">Chargement du document...</p>
        </div>
      </div>

      <!-- ── Document ────────────────────────────────────────────────────── -->
      <div
        v-else-if="invoice && companySettings"
        class="max-w-4xl mx-auto my-8 print:my-0 print:max-w-none"
      >
        <div
          class="bg-white shadow-xl print:shadow-none rounded-2xl print:rounded-none overflow-hidden"
        >
          <component
            :is="ActiveTemplate"
            :invoice="invoice"
            :company-settings="companySettings"
          />
        </div>
      </div>

      <div v-else class="text-center py-24 text-gray-500 text-sm">
        Préparation du document...
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
@media print {
  .print\:hidden {
    display: none !important;
  }
  @page {
    size: A4;
    margin: 0;
  }
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>