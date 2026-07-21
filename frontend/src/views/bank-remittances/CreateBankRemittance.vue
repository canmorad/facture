<script setup>
import { ref, computed, onMounted, reactive } from "vue";
import { useRouter, useRoute } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputLabel from "@/components/InputLabel.vue";
import TextInput from "@/components/TextInput.vue";
import InputError from "@/components/InputError.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import axios from "axios";
import { success, error } from "@/helpers/notifications";

const router = useRouter();
const route = useRoute();

const isEdit = computed(() => !!route.params.id);
const remittanceId = computed(() => route.params.id);

const isLoading = ref(false);
const isSaving = ref(false);

const bankAccounts = ref([]);
const pendingDocuments = ref([]);

const formData = ref({
  bank_account_id: null,
  remittance_date: new Date().toISOString().split("T")[0],
  payment_document_ids: [],
  notes: "",
});

const errors = reactive({
  bank_account_id: "",
  remittance_date: "",
  payment_document_ids: "",
  notes: "",
  server: "",
});

const selectedDocuments = computed(() => {
  return pendingDocuments.value.filter((doc) =>
    formData.value.payment_document_ids.includes(doc.id)
  );
});

const totalAmount = computed(() => {
  return selectedDocuments.value.reduce((sum, doc) => sum + parseFloat(doc.amount), 0);
});

const chequesCount = computed(() => {
  return selectedDocuments.value.filter((d) => d.type === "cheque").length;
});

const lcnCount = computed(() => {
  return selectedDocuments.value.filter((d) => d.type === "lcn").length;
});

const bankAccountOptions = computed(() => {
  return bankAccounts.value.map((acc) => ({
    value: acc.id,
    label: `${acc.label} - ${acc.bank_name}`,
  }));
});

const isDocumentSelected = (docId) => {
  return formData.value.payment_document_ids.includes(docId);
};

const toggleDocument = (docId) => {
  const index = formData.value.payment_document_ids.indexOf(docId);
  if (index > -1) {
    formData.value.payment_document_ids.splice(index, 1);
  } else {
    formData.value.payment_document_ids.push(docId);
  }
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-MA") : "—";

const getDocTypeLabel = (type) => {
  return type === "cheque" ? "Chèque" : "LCN";
};

const getDocTypeBadgeClass = (type) => {
  return type === "cheque"
    ? "bg-blue-100 text-blue-700"
    : "bg-orange-100 text-orange-700";
};

const fetchCreationData = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/bank-remittances/create");
    bankAccounts.value = data.bank_accounts;
    pendingDocuments.value = [
      ...(data.pending_cheques || []).map((d) => ({ ...d, type: "cheque" })),
      ...(data.pending_lcn || []).map((d) => ({ ...d, type: "lcn" })),
    ].sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de charger les données."
    );
  } finally {
    isLoading.value = false;
  }
};

const fetchRemittance = async () => {
  if (!remittanceId.value) return;

  isLoading.value = true;
  try {
    const { data } = await axios.get(
      `/api/bank-remittances/${remittanceId.value}`
    );
    formData.value = {
      bank_account_id: data.bank_account_id,
      remittance_date: data.remittance_date,
      payment_document_ids: data.payment_documents?.map((d) => d.id) || [],
      notes: data.notes || "",
    };
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de charger la remise."
    );
    router.push({ name: "bank-remittance.index" });
  } finally {
    isLoading.value = false;
  }
};

const saveRemittance = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  isSaving.value = true;

  try {
    if (isEdit.value) {
      await axios.put(
        `/api/bank-remittances/${remittanceId.value}`,
        formData.value
      );
      success("Enregistré !", "La remise a été mise à jour.");
    } else {
      await axios.post("/api/bank-remittances", formData.value);
      success("Créé !", "La remise a été créée.");
    }
    router.push({ name: "bank-remittance.index" });
  } catch (err) {
    if (err.response?.status === 422) {
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
      });
    } else {
      errors.server =
        err.response?.data?.message || "Impossible de sauvegarder la remise.";
      error("Erreur", errors.server);
    }
  } finally {
    isSaving.value = false;
  }
};

const goBack = () => {
  router.push({ name: "bank-remittance.index" });
};

onMounted(async () => {
  await fetchCreationData();
  if (isEdit.value) {
    await fetchRemittance();
  }
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div
          class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden"
        >
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between">
              <h1 class="text-2xl font-bold text-[#062121]">
                {{ isEdit ? "Modifier la Remise" : "Nouvelle Remise Bancaire" }}
              </h1>
              <button
                @click="goBack"
                class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors"
              >
                <i class="fas fa-arrow-left mr-2"></i> Retour
              </button>
            </div>
          </div>

          <div v-if="isLoading" class="p-8 text-center">
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
            <p class="mt-2 text-gray-500">Chargement...</p>
          </div>

          <form v-else @submit.prevent="saveRemittance" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-8">
                  <div>
                    <h3 class="mb-4 text-base font-semibold text-[#062121]">
                      Informations générales
                    </h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                      <div>
                        <InputLabel for="bank_account_id" value="Banque de destination *" />
                        <CustomSelect
                          id="bank_account_id"
                          v-model="formData.bank_account_id"
                          :options="bankAccountOptions"
                          placeholder="Sélectionner une banque"
                        />
                        <InputError class="mt-2" :message="errors.bank_account_id" />
                      </div>
                      <div>
                        <InputLabel for="remittance_date" value="Date de remise *" />
                        <TextInput
                          id="remittance_date"
                          type="date"
                          class="mt-1 block w-full"
                          v-model="formData.remittance_date"
                        />
                        <InputError class="mt-2" :message="errors.remittance_date" />
                      </div>
                      <div class="md:col-span-2">
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                          id="notes"
                          v-model="formData.notes"
                          rows="3"
                          class="block w-full p-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none resize-none"
                          placeholder="Notes ou commentaires..."
                        ></textarea>
                        <InputError class="mt-2" :message="errors.notes" />
                      </div>
                    </div>
                  </div>

                  <div class="border-t border-gray-100 pt-6">
                    <h3 class="mb-4 text-base font-semibold text-[#062121]">
                      Documents à inclure
                    </h3>

                    <div
                      v-if="pendingDocuments.length === 0"
                      class="text-center py-12 bg-white rounded-xl border border-gray-200"
                    >
                      <i class="fas fa-inbox text-5xl text-gray-300 mb-4 block"></i>
                      <p class="text-gray-500">
                        Aucun document en attente disponible.
                      </p>
                    </div>

                    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                      <div class="overflow-x-auto">
                        <table class="min-w-full">
                          <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                              <th class="px-4 py-3 text-left w-12">
                                <input
                                  type="checkbox"
                                  :checked="formData.payment_document_ids.length === pendingDocuments.length"
                                  @change="
                                    formData.payment_document_ids.length === pendingDocuments.length
                                      ? (formData.payment_document_ids = [])
                                      : (formData.payment_document_ids = pendingDocuments.map((d) => d.id))
                                  "
                                  class="w-4 h-4 text-[#C5F82A] rounded border-gray-300 focus:ring-[#C5F82A]"
                                />
                              </th>
                              <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                                Type
                              </th>
                              <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                                Numéro
                              </th>
                              <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                                Client
                              </th>
                              <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">
                                Échéance
                              </th>
                              <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">
                                Montant
                              </th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-100">
                            <tr
                              v-for="doc in pendingDocuments"
                              :key="doc.id"
                              class="group hover:bg-gray-50 transition-colors duration-200"
                              :class="{
                                'bg-[#C5F82A]/5': isDocumentSelected(doc.id),
                              }"
                            >
                              <td class="px-4 py-4">
                                <input
                                  type="checkbox"
                                  :checked="isDocumentSelected(doc.id)"
                                  @change="toggleDocument(doc.id)"
                                  class="w-4 h-4 text-[#C5F82A] rounded border-gray-300 focus:ring-[#C5F82A]"
                                />
                              </td>
                              <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                  :class="[
                                    'inline-flex px-2.5 py-1 text-xs font-semibold rounded-full',
                                    getDocTypeBadgeClass(doc.type),
                                  ]"
                                >
                                  {{ getDocTypeLabel(doc.type) }}
                                </span>
                              </td>
                              <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                  {{ doc.number }}
                                </div>
                              </td>
                              <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                  {{ doc.customer?.name || "—" }}
                                </div>
                              </td>
                              <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                  {{ formatDate(doc.due_date) }}
                                </div>
                              </td>
                              <td class="px-4 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-bold text-[#062121]">
                                  {{ formatCurrency(doc.amount) }}
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <InputError class="mt-2" :message="errors.payment_document_ids" />
                  </div>
                </div>

                <div class="space-y-6">
                  <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-[#062121] mb-4 flex items-center gap-2">
                      <i class="fas fa-clipboard-list text-[#C5F82A]"></i>
                      Résumé de la remise
                    </h3>
                    <div class="space-y-4">
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Nombre de documents</span>
                        <span class="text-lg font-bold text-[#062121]">
                          {{ formData.payment_document_ids.length }}
                        </span>
                      </div>
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Chèques</span>
                        <span class="text-lg font-bold text-[#062121]">
                          {{ chequesCount }}
                        </span>
                      </div>
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">LCN</span>
                        <span class="text-lg font-bold text-[#062121]">
                          {{ lcnCount }}
                        </span>
                      </div>
                      <div class="flex justify-between items-center py-4 bg-[#F4F7F7] rounded-lg px-4 mt-4">
                        <span class="text-sm font-semibold text-[#062121]">Montant total</span>
                        <span class="text-xl font-bold text-[#062121]">
                          {{ formatCurrency(totalAmount) }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <PrimaryButton
                    type="submit"
                    :disabled="isSaving || formData.payment_document_ids.length === 0"
                    class="w-full"
                  >
                    <svg
                      v-if="isSaving"
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
                    <span v-if="isEdit">Mettre à jour la remise</span>
                    <span v-else>Créer la remise</span>
                  </PrimaryButton>

                  <div
                    v-if="formData.payment_document_ids.length === 0"
                    class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg flex items-start gap-3"
                  >
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                    <p class="text-sm text-yellow-700">
                      Sélectionnez au moins un document pour créer la remise.
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="goBack"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
