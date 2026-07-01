<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <h2 class="text-lg font-bold text-[#062121]">
              Thème des documents
            </h2>
          </div>

          <div class="flex flex-col xl:flex-row gap-8 p-6 lg:p-8">
            <!-- Settings Panel (40%) -->
            <div class="xl:w-2/5 space-y-6">
              <form @submit.prevent="submit" class="space-y-6">
                <div>
                  <InputLabel for="font_family" value="Police d'écriture" />
                  <CustomSelect
                    id="font_family"
                    v-model="form.font_family"
                    :options="fontOptions"
                    label-key="label"
                    value-key="value"
                    placeholder="Choisir une police"
                  />
                  <InputError class="mt-2" :message="errors.font_family" />
                </div>

                <div>
                  <InputLabel for="primary_color" value="Couleur principale" />
                  <div class="flex items-center gap-3 mt-1">
                    <input
                      id="primary_color"
                      type="color"
                      v-model="form.primary_color"
                      class="w-12 h-12 rounded-lg border border-gray-200 cursor-pointer p-1"
                    />
                    <input
                      type="text"
                      v-model="form.primary_color"
                      class="flex-1 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                    />
                  </div>
                  <InputError class="mt-2" :message="errors.primary_color" />
                </div>

                <div>
                  <InputLabel for="background_pattern" value="Thème de fond" />
                  <CustomSelect
                    id="background_pattern"
                    v-model="form.background_pattern"
                    :options="patternOptions"
                    label-key="label"
                    value-key="value"
                    placeholder="Choisir un thème"
                  />
                  <InputError
                    class="mt-2"
                    :message="errors.background_pattern"
                  />
                </div>

                <div>
                  <InputLabel
                    for="table_border_style"
                    value="Bordure du tableau"
                  />
                  <CustomSelect
                    id="table_border_style"
                    v-model="form.table_border_style"
                    :options="borderOptions"
                    label-key="label"
                    value-key="value"
                    placeholder="Choisir une bordure"
                  />
                  <InputError
                    class="mt-2"
                    :message="errors.table_border_style"
                  />
                </div>

                <div>
                  <InputLabel
                    for="table_line_style"
                    value="Lignes du tableau"
                  />
                  <CustomSelect
                    id="table_line_style"
                    v-model="form.table_line_style"
                    :options="lineOptions"
                    label-key="label"
                    value-key="value"
                    placeholder="Choisir un style de ligne"
                  />
                  <InputError class="mt-2" :message="errors.table_line_style" />
                </div>

                <div
                  class="flex justify-end gap-3 pt-6 border-t border-gray-100"
                >
                  <button
                    type="button"
                    @click="resetForm"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    Réinitialiser
                  </button>
                  <PrimaryButton :disabled="isSubmitting">
                    <span v-if="isSubmitting">
                      <svg
                        class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline"
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
                      Enregistrement...
                    </span>
                    <span v-else>Enregistrer</span>
                  </PrimaryButton>
                </div>
              </form>
            </div>

            <!-- Preview Panel (60%) -->
            <div
              class="xl:w-3/5 bg-white rounded-xl border border-gray-200/80 shadow-sm overflow-hidden"
            >
              <div class="p-4 overflow-auto max-h-[650px]">
                <div
                  class="bg-white font-sans text-gray-800"
                  style="min-height: 500px; padding: 8mm 10mm"
                  :style="{
                    fontFamily: styleVariables.fontFamily,
                    backgroundColor:
                      styleVariables.backgroundPattern === 'none'
                        ? 'white'
                        : '#f8fafc',
                    backgroundImage:
                      styleVariables.backgroundPattern === 'dots'
                        ? 'radial-gradient(circle, #e2e8f0 1px, transparent 1px)'
                        : styleVariables.backgroundPattern === 'lines'
                          ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)'
                          : styleVariables.backgroundPattern === 'grid'
                            ? 'repeating-linear-gradient(0deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px), repeating-linear-gradient(90deg, transparent, transparent 8px, #e2e8f0 8px, #e2e8f0 9px)'
                            : 'none',
                    backgroundSize:
                      styleVariables.backgroundPattern === 'dots'
                        ? '20px 20px'
                        : '20px 20px',
                  }"
                >
                  <!-- En-tête -->
                  <!-- En-tête -->
                  <div class="flex justify-between items-start mb-5">
                    <div>
                      <h1
                        class="text-xl font-bold"
                        :style="{ color: styleVariables.primaryColor }"
                      >
                        Facture {{ previewInvoice.number }}
                      </h1>
                      <p class="text-xs text-gray-400 mt-0.5">
                        {{ fmtDate(previewInvoice.date) }}
                      </p>
                    </div>
                    <!-- Logo sans fond - agrandi -->
                    <img
                      v-if="logoUrl"
                      :src="logoUrl"
                      alt="Logo"
                      class="h-20 w-auto object-contain"
                    />
                  </div>

                  <!-- Émetteur / Destinataire -->
                  <div class="grid grid-cols-2 gap-6 mb-5">
                    <div>
                      <p
                        class="text-xs font-bold uppercase tracking-wider"
                        :style="{ color: styleVariables.primaryColor }"
                      >
                        Émetteur
                      </p>
                      <div class="space-y-0.5 text-[11px] mt-1">
                        <div class="grid grid-cols-[85px_1fr] gap-1">
                          <span class="text-gray-500">Société :</span>
                          <span class="font-semibold text-gray-800">{{
                            companySettings.company_name
                          }}</span>
                        </div>
                        <div class="grid grid-cols-[85px_1fr] gap-1">
                          <span class="text-gray-500">Votre contact :</span>
                          <span class="text-gray-700">{{
                            companySettings.contact
                          }}</span>
                        </div>
                        <div class="grid grid-cols-[85px_1fr] gap-1">
                          <span class="text-gray-500">Adresse :</span>
                          <span class="font-semibold text-gray-800">
                            {{ companySettings.address }}<br />
                            {{ companySettings.postal_code }}
                            {{ companySettings.city }}
                          </span>
                        </div>
                        <div class="grid grid-cols-[85px_1fr] gap-1">
                          <span class="text-gray-500"
                            >Numéro d'entreprise :</span
                          >
                          <span class="font-semibold text-gray-800">{{
                            companySettings.ice
                          }}</span>
                        </div>
                        <div class="grid grid-cols-[85px_1fr] gap-1">
                          <span class="text-gray-500">Adresse email :</span>
                          <span class="text-gray-700">{{
                            companySettings.email
                          }}</span>
                        </div>
                      </div>
                    </div>

                    <div>
                      <p
                        class="text-xs font-bold uppercase tracking-wider"
                        :style="{ color: styleVariables.primaryColor }"
                      >
                        Destinataire
                      </p>
                      <div class="space-y-0.5 text-[11px] mt-1">
                        <div class="grid grid-cols-[75px_1fr] gap-1">
                          <span class="text-gray-500">Nom :</span>
                          <span class="font-semibold text-gray-800">{{
                            previewInvoice.client.name
                          }}</span>
                        </div>
                        <div class="grid grid-cols-[75px_1fr] gap-1">
                          <span class="text-gray-500">Adresse :</span>
                          <span class="font-semibold text-gray-800">
                            {{ previewInvoice.client.address }}<br />
                            {{ previewInvoice.client.postal_code }}
                            {{ previewInvoice.client.city }}
                          </span>
                        </div>
                        <div class="grid grid-cols-[75px_1fr] gap-1">
                          <span class="text-gray-500">Pays :</span>
                          <span class="text-gray-700">{{
                            previewInvoice.client.country
                          }}</span>
                        </div>
                        <div class="grid grid-cols-[75px_1fr] gap-1">
                          <span class="text-gray-500">Adresse email :</span>
                          <span class="font-semibold text-gray-800">{{
                            previewInvoice.client.email
                          }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Détail (tableau) -->
                  <div class="mb-4">
                    <p
                      class="text-xs font-bold uppercase tracking-wider mb-2"
                      :style="{ color: styleVariables.primaryColor }"
                    >
                      Détail
                    </p>
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse text-[11px]">
                        <thead>
                          <tr
                            :style="{
                              backgroundColor: styleVariables.primaryColor,
                            }"
                          >
                            <th
                              class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider"
                            >
                              Type
                            </th>
                            <th
                              class="py-1.5 px-2 text-left text-[10px] font-bold text-white uppercase tracking-wider"
                            >
                              Description
                            </th>
                            <th
                              class="py-1.5 px-2 text-right text-[10px] font-bold text-white uppercase tracking-wider"
                            >
                              Prix unitaire HT
                            </th>
                            <th
                              class="py-1.5 px-2 text-center text-[10px] font-bold text-white uppercase tracking-wider w-12"
                            >
                              Qté
                            </th>
                            <th
                              class="py-1.5 px-2 text-center text-[10px] font-bold text-white uppercase tracking-wider w-12"
                            >
                              TVA
                            </th>
                            <th
                              class="py-1.5 px-2 text-right text-[10px] font-bold text-white uppercase tracking-wider"
                            >
                              Total HT
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="(item, i) in previewInvoice.items"
                            :key="i"
                            :class="i % 2 === 1 ? 'bg-gray-50' : 'bg-white'"
                            :style="{
                              borderBottom:
                                styleVariables.tableLineStyle === 'none'
                                  ? 'none'
                                  : styleVariables.tableLineStyle === 'bold'
                                    ? '2px solid #e5e7eb'
                                    : styleVariables.tableLineStyle === 'dashed'
                                      ? '1px dashed #d1d5db'
                                      : '1px solid #e5e7eb',
                            }"
                          >
                            <td class="py-1.5 px-2 text-gray-600">
                              {{ item.type || "Service" }}
                            </td>
                            <td class="py-1.5 px-2 text-gray-700">
                              {{ item.designation }}
                            </td>
                            <td class="py-1.5 px-2 text-right text-gray-700">
                              {{ fmt(item.price) }}
                            </td>
                            <td class="py-1.5 px-2 text-center text-gray-600">
                              {{ item.quantity }}
                            </td>
                            <td class="py-1.5 px-2 text-center text-gray-600">
                              {{ previewInvoice.tva_rate }}%
                            </td>
                            <td
                              class="py-1.5 px-2 text-right font-semibold text-gray-800"
                            >
                              {{ fmt(lineTotal(item)) }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Totaux -->
                  <div class="flex justify-end mb-5">
                    <div class="w-2/3 sm:w-1/2 space-y-0.5 text-[11px]">
                      <div class="flex justify-between">
                        <span
                          class="font-semibold"
                          :style="{ color: styleVariables.primaryColor }"
                          >Total HT</span
                        >
                        <span class="font-semibold text-gray-800">{{
                          fmt(totalHT)
                        }}</span>
                      </div>
                      <div
                        v-if="previewInvoice.discount_rate > 0"
                        class="flex justify-between"
                      >
                        <span class="font-semibold text-red-500"
                          >Remise générale ({{
                            previewInvoice.discount_rate
                          }}%)</span
                        >
                        <span class="font-semibold text-red-500"
                          >- {{ fmt(discountAmount) }}</span
                        >
                      </div>
                      <div
                        v-if="previewInvoice.discount_rate > 0"
                        class="flex justify-between"
                      >
                        <span
                          class="font-semibold"
                          :style="{ color: styleVariables.primaryColor }"
                          >Total HT final</span
                        >
                        <span class="font-semibold text-gray-800">{{
                          fmt(totalHTAfterDiscount)
                        }}</span>
                      </div>
                      <div class="flex justify-between">
                        <span
                          class="font-semibold"
                          :style="{ color: styleVariables.primaryColor }"
                          >TVA ({{ previewInvoice.tva_rate }}%)</span
                        >
                        <span class="font-semibold text-gray-800">{{
                          fmt(totalTVA)
                        }}</span>
                      </div>
                      <div
                        class="flex justify-between items-center pt-1 border-t-2"
                        :style="{ borderColor: styleVariables.primaryColor }"
                      >
                        <span
                          class="text-sm font-bold"
                          :style="{ color: styleVariables.primaryColor }"
                          >Total TTC</span
                        >
                        <span
                          class="text-base font-bold"
                          :style="{ color: styleVariables.primaryColor }"
                          >{{ fmt(totalTTC) }}</span
                        >
                      </div>
                    </div>
                  </div>

                  <!-- Conditions -->
                  <div>
                    <p
                      class="text-xs font-bold uppercase tracking-wider mb-2"
                      :style="{ color: styleVariables.primaryColor }"
                    >
                      Conditions
                    </p>
                    <div class="text-[11px] text-gray-700 space-y-0.5">
                      <p>
                        <span
                          class="font-semibold"
                          :style="{ color: styleVariables.primaryColor }"
                          >Conditions de règlement :</span
                        >
                        {{ previewInvoice.payment_terms }}
                      </p>
                      <p>
                        <span
                          class="font-semibold"
                          :style="{ color: styleVariables.primaryColor }"
                          >Mode de règlement :</span
                        >
                        {{ previewInvoice.payment_method }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../stores/auth";
import SettingsLayout from "../../layouts/SettingsLayout.vue";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import CustomSelect from "../../components/CustomSelect.vue";
import axios from "axios";
import { success, error } from "../../helpers/notifications";
import logoUrl from "../../assets/images/logo.png";

const authStore = useAuthStore();

const fontOptions = [
  { label: "Nunito", value: "Nunito" },
  { label: "Inter", value: "Inter" },
  { label: "Roboto", value: "Roboto" },
  { label: "Open Sans", value: "Open Sans" },
  { label: "Montserrat", value: "Montserrat" },
  { label: "Playfair Display", value: "Playfair Display" },
];

const patternOptions = [
  { label: "Sans thème", value: "none" },
  { label: "Points", value: "dots" },
  { label: "Lignes", value: "lines" },
  { label: "Grille", value: "grid" },
];

const borderOptions = [
  { label: "Sharp", value: "sharp" },
  { label: "Arrondi", value: "rounded" },
  { label: "Aucune", value: "none" },
];

const lineOptions = [
  { label: "Standard", value: "standard" },
  { label: "Gras", value: "bold" },
  { label: "Tireté", value: "dashed" },
  { label: "Aucune", value: "none" },
];

const form = reactive({
  font_family: "Nunito",
  primary_color: "#062121",
  background_pattern: "none",
  table_border_style: "sharp",
  table_line_style: "standard",
});

const errors = reactive({});
const isSubmitting = ref(false);
const isLoading = ref(false);

const companySettings = {
  company_name: "Frost and Larsen Inc",
  address: "12 rue du Lapin Blanc",
  city: "Wonderland",
  country: "Maroc",
  postal_code: "12345",
  phone: "+212 5XX XXX XXX",
  email: "contact@wonderland.ma",
  ice: "123 456 789 0001",
  contact: "Susan Petersen",
};

const previewInvoice = {
  number: "F23012345",
  date: "2026-05-06",
  discount_rate: 0,
  payment_terms: "45 jours fin de mois",
  payment_method: "Virement bancaire",
  client: {
    name: "Alice Liddell",
    address: "12 rue du Lapin Blanc",
    city: "Wonderland",
    country: "Maroc",
    postal_code: "12345",
    email: "alice.liddell@wonderland.ma",
  },
  items: [
    {
      type: "Service",
      designation: "Installation du logiciel de gestion de factures",
      quantity: 1,
      price: 1700,
    },
    {
      type: "Service",
      designation: "Formation de 2 jours",
      quantity: 1,
      price: 2000,
    },
    {
      type: "Produit",
      designation: "Ordinateur de bureau PASOKON",
      quantity: 3,
      price: 1200,
    },
  ],
  tva_rate: 20,
};

const totalHT = computed(() => {
  return previewInvoice.items.reduce(
    (sum, item) => sum + item.quantity * item.price,
    0,
  );
});

const totalTVA = computed(() => {
  return (
    Math.round(((totalHT.value * previewInvoice.tva_rate) / 100) * 100) / 100
  );
});

const totalTTC = computed(() => {
  return totalHT.value + totalTVA.value;
});

const discountAmount = computed(() => 0);
const totalHTAfterDiscount = computed(() => totalHT.value);

const fmt = (n) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n) + " DH";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const lineTotal = (item) =>
  Math.round(
    (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) * 100,
  ) / 100;

const styleVariables = computed(() => {
  const t = form || {};
  return {
    fontFamily: t.font_family || "Nunito",
    primaryColor: t.primary_color || "#062121",
    backgroundPattern: t.background_pattern || "none",
    tableBorderStyle: t.table_border_style || "sharp",
    tableLineStyle: t.table_line_style || "standard",
  };
});

const fetchTheme = async () => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get("/api/document-theme", {
      params: { company_id: companyId },
    });
    Object.assign(form, data);
  } catch {
    error("Erreur", "Impossible de charger le thème.");
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  fetchTheme();
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

  try {
    const payload = { ...form, company_id: companyId };
    const { data } = await axios.put("/api/document-theme", payload);
    Object.assign(form, data);
    success("Enregistré !", "Le thème a été mis à jour.");
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  fetchTheme();
});
</script>
