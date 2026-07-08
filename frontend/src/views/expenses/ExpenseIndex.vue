<script setup>
import { reactive, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { success, error, validation, confirm } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

const isLoading = ref(false);
const isLoadingExpenses = ref(false);
const isLoadingLookups = ref(false);
const activeTab = ref("list");
const editingExpenseId = ref(null);
const selectedFiles = ref([]);

const expenses = ref([]);
const suppliers = ref([]);

const paymentMethods = [
  { value: "virement", label: "Virement" },
  { value: "cheque", label: "Chèque" },
  { value: "espece", label: "Espèce" },
  { value: "carte", label: "Carte" },
];

  const form = reactive({
    supplier_id: null,
    reference: "",
    issue_date: new Date().toISOString().split("T")[0],
    total_ht: "0",
    total_tva: "0",
    total_ttc: 0,
    status: "unpaid",
    payment_method: "virement",
    notes: "",
  });

const errors = reactive({
  supplier_id: "",
  reference: "",
  issue_date: "",
  total_ht: "",
  total_tva: "",
  total_ttc: "",
  status: "",
  payment_method: "",
  notes: "",
  files: "",
  server: "",
});

const fetchLookups = async () => {
  isLoadingLookups.value = true;
  try {
    const { data } = await axios.get("/api/expenses/create");
    suppliers.value = data.suppliers.map((s) => ({
      label: s.name,
      value: s.id,
    }));
  } catch {
    suppliers.value = [];
  } finally {
    isLoadingLookups.value = false;
  }
};

const fetchExpenses = async () => {
  isLoadingExpenses.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const response = await axios.get("/api/expenses", { params });
    expenses.value = response.data;
  } catch {
    error("Erreur", "Impossible de charger les dépenses.");
  } finally {
    isLoadingExpenses.value = false;
  }
};

const resetForm = () => {
  form.supplier_id = null;
  form.reference = "";
  form.issue_date = new Date().toISOString().split("T")[0];
  form.total_ht = "0";
  form.total_tva = "0";
  form.total_ttc = 0;
  form.status = "unpaid";
  form.payment_method = "virement";
  form.notes = "";
  selectedFiles.value = [];
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingExpenseId.value = null;
};

const editExpense = (expense) => {
  editingExpenseId.value = expense.id;
  form.supplier_id = expense.supplier_id;
  form.reference = expense.reference;
  form.issue_date = expense.issue_date?.split("T")[0] || "";
  form.total_ht = String(expense.total_ht ?? "0");
  form.total_tva = String(expense.total_tva ?? "0");
  form.total_ttc = expense.total_ttc;
  form.status = expense.status;
  form.payment_method = expense.payment_method;
  form.notes = expense.notes || "";
  selectedFiles.value = [];
  activeTab.value = "add";
};

const onTotalChange = () => {
  const ht = parseFloat(form.total_ht) || 0;
  const tva = parseFloat(form.total_tva) || 0;
  form.total_ttc = ht + tva;
};

const handleFileChange = (event) => {
  selectedFiles.value = Array.from(event.target.files);
};

const removeFile = (index) => {
  selectedFiles.value.splice(index, 1);
};

const formatSize = (bytes) => {
  if (bytes < 1024) return bytes + " o";
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " Ko";
  return (bytes / 1048576).toFixed(1) + " Mo";
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  isLoading.value = true;

  if (form.total_ttc <= 0) {
    errors.total_ttc = "Le total TTC doit être supérieur à 0.";
    isLoading.value = false;
    return;
  }

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isLoading.value = false;
    return;
  }

  const fd = new FormData();
  Object.keys(form).forEach((key) => {
    if (form[key] !== null && form[key] !== "") {
      fd.append(key, form[key]);
    }
  });
  fd.append("company_id", companyId);
  selectedFiles.value.forEach((file) => {
    fd.append("files[]", file);
  });

  try {
    if (editingExpenseId.value) {
      await axios.post(`/api/expenses/${editingExpenseId.value}`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      success("Dépense modifiée !", "La dépense a été modifiée avec succès.");
    } else {
      await axios.post("/api/expenses", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      success("Dépense ajoutée !", "La dépense a été enregistrée avec succès.");
    }
    resetForm();
    await fetchExpenses();
    activeTab.value = "list";
  } catch (err) {
    if (err.response && err.response.status === 422) {
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
      });
      validation("Veuillez corriger les erreurs de saisie.");
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
      error("Erreur", "Impossible d'enregistrer la dépense.");
    }
  } finally {
    isLoading.value = false;
  }
};

const viewExpense = (expense) => {
  router.push({ name: "expenses.preview", params: { id: expense.id } });
};

const toggleExpenseStatus = async (expense) => {
  try {
    const { data } = await axios.patch(
      `/api/expenses/${expense.id}/toggle-status`,
    );
    const index = expenses.value.findIndex((e) => e.id === expense.id);
    if (index !== -1) {
      expenses.value[index] = { ...expenses.value[index], ...data };
    }
    const newLabel = getStatusLabel(data.status);
    success("Statut modifié", `La dépense est maintenant "${newLabel}".`);
  } catch (err) {
    error("Erreur", "Impossible de changer le statut.");
  }
};

const deleteExpense = async (id, reference) => {
  const result = await confirm(
    "Supprimer la dépense",
    `Supprimer la dépense "${reference}" définitivement ?`,
  );
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/expenses/${id}`);
    success("Supprimé !", "La dépense a été supprimée.");
    await fetchExpenses();
  } catch {
    error("Erreur", "Impossible de supprimer la dépense.");
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (expenses.value.length === 0) fetchExpenses();
  }
  if (tab === "add" && suppliers.value.length === 0) {
    fetchLookups();
  }
};

const formatCurrency = (amount) => {
  return (
    new Intl.NumberFormat("fr-MA", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " DH"
  );
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString("fr-MA") : "—");

const getStatusLabel = (status) => {
  const labels = { unpaid: "Impayé", paid: "Payé" };
  return labels[status] || status;
};

const getStatusBadgeClass = (status) => {
  const classes = {
    unpaid: "bg-red-100 text-red-700",
    paid: "bg-green-100 text-green-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getMethodText = (method) => {
  const texts = {
    virement: "Virement",
    cheque: "Chèque",
    espece: "Espèce",
    carte: "Carte",
  };
  return texts[method] || method;
};

onMounted(() => {
  fetchExpenses();
  fetchLookups();
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
            <div class="flex gap-6">
              <button
                @click="changeTab('list')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'list'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-list"></i>
                Liste des dépenses
                <span
                  v-if="expenses.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ expenses.length }}</span
                >
              </button>

              <button
                @click="changeTab('add')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'add'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i
                  class="fas"
                  :class="editingExpenseId ? 'fa-edit' : 'fa-plus-circle'"
                ></i>
                {{
                  editingExpenseId
                    ? "Modifier la dépense"
                    : "Ajouter une dépense"
                }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingExpenses" class="text-center py-12">
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
              <p class="mt-2 text-gray-500">Chargement des dépenses...</p>
            </div>

            <div v-else-if="expenses.length === 0" class="text-center py-12">
              <i
                class="fas fa-money-bill-wave text-5xl text-gray-300 mb-4 block"
              ></i>
              <p class="text-gray-500">
                Aucune dépense enregistrée pour le moment.
              </p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre première
                dépense
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Référence
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Fournisseur
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Date
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Paiement
                    </th>
                    <th
                      class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Total TTC
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Statut
                    </th>
                    <th
                      class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="expense in expenses"
                    :key="expense.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-gray-900">
                        {{ expense.reference || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-700">
                        {{ expense.supplier?.name || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600">
                        {{ formatDate(expense.issue_date) }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span
                        class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800"
                        >{{ getMethodText(expense.payment_method) }}</span
                      >
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="text-sm font-bold text-[#062121]">
                        {{ formatCurrency(expense.total_ttc) }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-1">
                        <button
                          @click="toggleExpenseStatus(expense)"
                          :class="[
                            'px-2.5 py-1 text-xs font-semibold rounded-full transition-all duration-200 cursor-pointer border-0',
                            expense.status === 'unpaid'
                              ? 'bg-red-100 text-red-700 hover:bg-green-100 hover:text-green-700'
                              : 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-700',
                          ]"
                          :title="
                            expense.status === 'unpaid'
                              ? 'Marquer comme payé'
                              : 'Marquer comme impayé'
                          "
                        >
                          <span class="flex items-center gap-1">
                            <i
                              :class="
                                expense.status === 'unpaid'
                                  ? 'fas fa-times-circle'
                                  : 'fas fa-check-circle'
                              "
                            ></i>
                            {{ getStatusLabel(expense.status) }}
                          </span>
                        </button>
                      </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="viewExpense(expense)"
                          title="Voir détails"
                          class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button
                          @click="editExpense(expense)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="
                            deleteExpense(
                              expense.id,
                              expense.reference || 'sans référence',
                            )
                          "
                          title="Supprimer"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <form
            v-else-if="activeTab === 'add'"
            @submit.prevent="submit"
            class="p-6 lg:p-8"
          >
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div v-if="isLoadingLookups" class="text-center py-4">
                <svg
                  class="animate-spin h-6 w-6 mx-auto text-[#C5F82A]"
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
              </div>

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <InputLabel for="supplier_id" value="Fournisseur" />
                  <CustomSelect
                    id="supplier_id"
                    v-model="form.supplier_id"
                    :options="[
                      { label: 'Aucun fournisseur', value: null },
                      ...suppliers,
                    ]"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner un fournisseur"
                  />
                  <InputError class="mt-2" :message="errors.supplier_id" />
                </div>

                <div>
                  <InputLabel for="reference" value="Référence facture" />
                  <TextInput
                    id="reference"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.reference"
                    placeholder="N° facture fournisseur"
                  />
                  <InputError class="mt-2" :message="errors.reference" />
                </div>

                <div>
                  <InputLabel for="issue_date" value="Date d'émission *" />
                  <TextInput
                    id="issue_date"
                    type="date"
                    class="mt-1 block w-full"
                    v-model="form.issue_date"
                    required
                  />
                  <InputError class="mt-2" :message="errors.issue_date" />
                </div>

                <div>
                  <InputLabel for="payment_method" value="Mode de paiement *" />
                  <CustomSelect
                    id="payment_method"
                    v-model="form.payment_method"
                    :options="paymentMethods"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner"
                  />
                  <InputError class="mt-2" :message="errors.payment_method" />
                </div>

                <div>
                  <InputLabel for="status" value="Statut" />
                  <CustomSelect
                    id="status"
                    v-model="form.status"
                    :options="[
                      { label: 'Impayé', value: 'unpaid' },
                      { label: 'Payé', value: 'paid' },
                    ]"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner"
                  />
                  <InputError class="mt-2" :message="errors.status" />
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">
                  Montants
                </h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                  <div>
                    <InputLabel for="total_ht" value="Total HT *" />
                    <TextInput
                      id="total_ht"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1 block w-full"
                      v-model="form.total_ht"
                      @input="onTotalChange"
                      placeholder="0.00"
                    />
                    <InputError class="mt-2" :message="errors.total_ht" />
                  </div>

                  <div>
                    <InputLabel for="total_tva" value="Total TVA *" />
                    <TextInput
                      id="total_tva"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1 block w-full"
                      v-model="form.total_tva"
                      @input="onTotalChange"
                      placeholder="0.00"
                    />
                    <InputError class="mt-2" :message="errors.total_tva" />
                  </div>

                  <div>
                    <InputLabel for="total_ttc" value="Total TTC *" />
                    <div
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm font-bold text-[#062121]"
                    >
                      {{
                        form.total_ttc
                          ? form.total_ttc.toFixed(2) + " DH"
                          : "0.00 DH"
                      }}
                    </div>
                    <InputError class="mt-2" :message="errors.total_ttc" />
                  </div>
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <InputLabel for="notes" value="Notes" />
                <textarea
                  id="notes"
                  v-model="form.notes"
                  rows="3"
                  placeholder="Notes..."
                  class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                ></textarea>
                <InputError class="mt-2" :message="errors.notes" />
              </div>

              <div class="border-t border-gray-100 pt-6">
                <InputLabel
                  for="files"
                  value="Pièces jointes (factures, reçus)"
                />
                <input
                  id="files"
                  type="file"
                  multiple
                  accept=".jpg,.jpeg,.png,.pdf"
                  @change="handleFileChange"
                  class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#C5F82A] file:text-[#062121] hover:file:bg-[#b8e626] transition-colors"
                />
                <InputError class="mt-2" :message="errors.files" />
                <div v-if="selectedFiles.length > 0" class="mt-3 space-y-2">
                  <div
                    v-for="(file, idx) in selectedFiles"
                    :key="idx"
                    class="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2"
                  >
                    <div class="flex items-center gap-2">
                      <i class="fas fa-paperclip text-gray-400 text-sm"></i>
                      <span class="text-sm text-gray-700">{{ file.name }}</span>
                      <span class="text-xs text-gray-400"
                        >({{ formatSize(file.size) }})</span
                      >
                    </div>
                    <button
                      type="button"
                      @click="removeFile(idx)"
                      class="text-red-400 hover:text-red-600"
                    >
                      <i class="fas fa-times text-sm"></i>
                    </button>
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isLoading">
                  <span v-if="isLoading">
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
                  <span v-else>{{
                    editingExpenseId
                      ? "Modifier la dépense"
                      : "Enregistrer la dépense"
                  }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
