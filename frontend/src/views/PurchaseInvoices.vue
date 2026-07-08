<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import { success, error, confirm, toast } from "../helpers/notifications";

// ========== État principal ==========
const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingInvoices = ref(false);
const isLoadingSuppliers = ref(false);
const isLoadingProducts = ref(false);
const editingInvoiceId = ref(null);

const invoices = ref([]);
const suppliers = ref([]);
const products = ref([]);

const form = reactive({
  fournisseur_id: "",
  date: new Date().toISOString().split("T")[0],
  tva_rate: 20,
  items: [],
});

const errors = reactive({
  fournisseur_id: "",
  date: "",
  tva_rate: "",
  items: "",
  server: "",
});

// ========== État pour la modale produit ==========
const showProductModal = ref(false);
const productModalForm = reactive({
  name: "",
  sku: "",
  price: "",
});
const productModalErrors = reactive({
  name: "",
  sku: "",
  price: "",
});
const currentProductRowIndex = ref(null); // index de la ligne qui a déclenché la modale
const isCreatingProduct = ref(false);

// ========== Fonctions métier existantes ==========
const makeItem = () => ({
  product_id: "",
  quantity: 1,
  unit_price: 0,
});

const addItem = () => {
  form.items.push(makeItem());
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const onProductSelect = (index) => {
  const item = form.items[index];
  const product = products.value.find((p) => p.id == item.product_id);
  if (product) {
    item.unit_price = product.price;
  }
};

const lineTotal = (item) => {
  return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
};

const totals = computed(() => {
  const ht = form.items.reduce((sum, item) => sum + lineTotal(item), 0);
  const tva = ht * (form.tva_rate / 100);
  return {
    ht: ht.toFixed(2),
    tva: tva.toFixed(2),
    ttc: (ht + tva).toFixed(2),
  };
});

// ========== Appels API ==========
const fetchInvoices = async () => {
  isLoadingInvoices.value = true;
  try {
    const { data } = await axios.get("/api/purchase-invoices");
    invoices.value = data;
  } catch {
    error("Erreur", "Impossible de charger les factures d'achat.");
  } finally {
    isLoadingInvoices.value = false;
  }
};

const fetchSuppliers = async () => {
  isLoadingSuppliers.value = true;
  try {
    const { data } = await axios.get("/api/fournisseurs");
    suppliers.value = data;
  } catch {
    error("Erreur", "Impossible de charger les fournisseurs.");
  } finally {
    isLoadingSuppliers.value = false;
  }
};

const fetchProducts = async () => {
  isLoadingProducts.value = true;
  try {
    const { data } = await axios.get("/api/products");
    products.value = data;
  } catch {
    error("Erreur", "Impossible de charger les produits.");
  } finally {
    isLoadingProducts.value = false;
  }
};

// ========== Gestion du formulaire principal ==========
const resetForm = () => {
  form.fournisseur_id = "";
  form.date = new Date().toISOString().split("T")[0];
  form.tva_rate = 20;
  form.items = [makeItem()];
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingInvoiceId.value = null;
};

const editInvoice = (invoice) => {
  editingInvoiceId.value = invoice.id;
  form.fournisseur_id = invoice.fournisseur_id;
  form.date = invoice.date.split("T")[0];
  form.tva_rate = invoice.tva_rate;
  form.items = invoice.items.map((item) => ({
    product_id: item.product_id,
    quantity: item.quantity,
    unit_price: item.unit_price,
  }));
  activeTab.value = "add";
};

const submitInvoice = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  if (
    form.items.some((i) => !i.product_id || i.quantity <= 0 || i.unit_price < 0)
  ) {
    errors.items = "Veuillez remplir correctement toutes les lignes.";
    isLoading.value = false;
    return;
  }

  try {
    if (editingInvoiceId.value) {
      await axios.put(`/api/purchase-invoices/${editingInvoiceId.value}`, form);
      success("Facture modifiée !", "La facture d'achat a été modifiée avec succès.");
    } else {
      await axios.post("/api/purchase-invoices", form);
      success("Facture ajoutée !", "La facture d'achat a été enregistrée avec succès et le stock a été mis à jour.");
    }

    resetForm();
    await fetchInvoices();
    activeTab.value = "list";
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
    }
  } finally {
    isLoading.value = false;
  }
};

const deleteInvoice = async (id, number) => {
  const result = await confirm("Êtes-vous sûr ?", `Supprimer la facture "${number}" définitivement ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/purchase-invoices/${id}`);
    success("Supprimée !", "La facture a été supprimée et le stock ajusté.");
    await fetchInvoices();
  } catch {
    error("Erreur", "Impossible de supprimer la facture.");
  }
};

// ========== Gestion de la création à la volée (modal) ==========
const openProductModal = (rowIndex) => {
  currentProductRowIndex.value = rowIndex;
  productModalForm.name = "";
  productModalForm.sku = "";
  productModalForm.price = "";
  Object.keys(productModalErrors).forEach((k) => (productModalErrors[k] = ""));
  showProductModal.value = true;
};

const closeProductModal = () => {
  showProductModal.value = false;
  currentProductRowIndex.value = null;
};

const createProductAndSelect = async () => {
  Object.keys(productModalErrors).forEach((k) => (productModalErrors[k] = ""));
  if (!productModalForm.name.trim()) {
    productModalErrors.name = "Le nom est requis.";
    return;
  }
  if (!productModalForm.price || productModalForm.price <= 0) {
    productModalErrors.price = "Le prix doit être supérieur à 0.";
    return;
  }

  isCreatingProduct.value = true;
  try {
    const payload = {
      name: productModalForm.name,
      price: productModalForm.price,
      description: productModalForm.sku
        ? `Référence: ${productModalForm.sku}`
        : "",
    };
    const { data } = await axios.post("/api/products", payload);

    await fetchProducts();

    const newProductId = data.id;
    const rowIndex = currentProductRowIndex.value;
    if (rowIndex !== null && form.items[rowIndex]) {
      form.items[rowIndex].product_id = newProductId;
      onProductSelect(rowIndex);
    }

    toast("Produit ajouté !", "Le produit a été créé et sélectionné automatiquement.");

    closeProductModal();
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      if (e.name) productModalErrors.name = e.name[0];
      if (e.price) productModalErrors.price = e.price[0];
      if (e.description) productModalErrors.sku = e.description[0];
      if (
        !productModalErrors.name &&
        !productModalErrors.price &&
        !productModalErrors.sku
      ) {
        productModalErrors.server = "Erreur de validation.";
      }
    } else {
      productModalErrors.server = "Impossible d'ajouter le produit.";
    }
    error("Erreur", productModalErrors.server || "Vérifiez les champs.");
  } finally {
    isCreatingProduct.value = false;
  }
};

// ========== Utilitaires d'affichage ==========
const formatAmount = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA");
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (invoices.value.length === 0) fetchInvoices();
  } else if (tab === "add") {
    resetForm();
    fetchSuppliers();
    fetchProducts();
  }
};

onMounted(() => {
  fetchInvoices();
  fetchSuppliers();
  fetchProducts();
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
                Liste des factures d'achat
                <span
                  v-if="invoices.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ invoices.length }}</span
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
                  :class="editingInvoiceId ? 'fa-edit' : 'fa-plus-circle'"
                ></i>
                {{
                  editingInvoiceId
                    ? "Modifier la facture"
                    : "Ajouter une facture"
                }}
              </button>
            </div>
          </div>

          <!-- Liste des factures (inchangée) -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <!-- ... le contenu reste strictement identique ... -->
            <div v-if="isLoadingInvoices" class="text-center py-12">
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
            <div v-else-if="invoices.length === 0" class="text-center py-12">
              <i
                class="fas fa-file-invoice-dollar text-5xl text-gray-300 mb-4 block"
              ></i>
              <p class="text-gray-500">Aucune facture d'achat enregistrée.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Créer une facture
              </button>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      N° Facture
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
                    v-for="inv in invoices"
                    :key="inv.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">
                      {{ inv.invoice_number }}
                    </td>
                    <td class="px-4 py-4 text-sm text-gray-700">
                      {{ inv.fournisseur?.name || "—" }}
                    </td>
                    <td class="px-4 py-4 text-sm text-gray-600">
                      {{ formatDate(inv.date) }}
                    </td>
                    <td class="px-4 py-4 text-sm font-semibold text-[#062121]">
                      {{ formatAmount(inv.total_ttc) }} MAD
                    </td>
                    <td class="px-4 py-4">
                      <span
                        class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                        :class="
                          inv.status === 'payé'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-700'
                        "
                      >
                        {{ inv.status === "payé" ? "Payée" : "Impayée" }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="editInvoice(inv)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteInvoice(inv.id, inv.invoice_number)"
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

          <!-- Formulaire d'ajout / modification avec le bouton "+" dans les lignes -->
          <form
            v-else-if="activeTab === 'add'"
            @submit.prevent="submitInvoice"
            class="p-6 lg:p-8"
          >
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="fournisseur_id" value="Fournisseur *" />
                  <select
                    id="fournisseur_id"
                    v-model="form.fournisseur_id"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  >
                    <option value="">-- Sélectionnez un fournisseur --</option>
                    <option
                      v-for="sup in suppliers"
                      :key="sup.id"
                      :value="sup.id"
                    >
                      {{ sup.name }}
                    </option>
                  </select>
                  <InputError class="mt-2" :message="errors.fournisseur_id" />
                </div>

                <div>
                  <InputLabel for="date" value="Date *" />
                  <input
                    id="date"
                    type="date"
                    v-model="form.date"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  />
                  <InputError class="mt-2" :message="errors.date" />
                </div>

                <div>
                  <InputLabel for="tva_rate" value="Taux TVA (%)" />
                  <select
                    id="tva_rate"
                    v-model.number="form.tva_rate"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  >
                    <option value="0">0% — Exonéré</option>
                    <option value="7">7%</option>
                    <option value="10">10%</option>
                    <option value="14">14%</option>
                    <option value="20">20% — Standard</option>
                  </select>
                  <InputError class="mt-2" :message="errors.tva_rate" />
                </div>
              </div>

              <!-- Tableau des lignes avec bouton + -->
              <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                  <h3
                    class="text-sm font-bold text-[#062121] uppercase tracking-wider"
                  >
                    Articles achetés
                  </h3>
                  <button
                    type="button"
                    @click="addItem"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
                  >
                    <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
                  </button>
                </div>

                <InputError :message="errors.items" class="mb-3" />

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                  <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                      <tr>
                        <th
                          class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Produit
                        </th>
                        <th
                          class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24"
                        >
                          Quantité
                        </th>
                        <th
                          class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32"
                        >
                          P.U. HT
                        </th>
                        <th
                          class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32"
                        >
                          Total HT
                        </th>
                        <th
                          class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-16"
                        >
                          Actions
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr
                        v-for="(item, idx) in form.items"
                        :key="idx"
                        class="hover:bg-gray-50 transition-colors"
                      >
                        <td class="px-6 py-4">
                          <div class="flex items-center gap-2">
                            <select
                              v-model="item.product_id"
                              @change="onProductSelect(idx)"
                              class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 bg-white focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                            >
                              <option value="">-- Choisir --</option>
                              <option
                                v-for="prod in products"
                                :key="prod.id"
                                :value="prod.id"
                              >
                                {{ prod.name }}
                              </option>
                            </select>
                            <button
                              type="button"
                              @click="openProductModal(idx)"
                              class="shrink-0 w-8 h-8 rounded-lg text-[#C5F82A] border border-[#C5F82A] bg-[#062121]/5 hover:bg-[#C5F82A] hover:text-[#062121] transition-all duration-200 flex items-center justify-center"
                              title="Créer un produit à la volée"
                            >
                              <i class="fas fa-plus text-xs"></i>
                            </button>
                          </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                          <input
                            type="number"
                            v-model.number="item.quantity"
                            min="0.01"
                            step="0.01"
                            class="w-24 text-center rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                          />
                        </td>
                        <td class="px-6 py-4 text-right">
                          <input
                            type="number"
                            v-model.number="item.unit_price"
                            min="0"
                            step="0.01"
                            class="w-32 ml-auto rounded-lg border border-gray-300 px-2 py-2 text-sm text-right text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none"
                          />
                        </td>
                        <td
                          class="px-6 py-4 text-right font-semibold text-[#062121]"
                        >
                          {{ formatAmount(lineTotal(item)) }} MAD
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                          <button
                            type="button"
                            @click="removeItem(idx)"
                            :disabled="form.items.length === 1"
                            class="text-red-400 hover:text-red-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                          >
                            <i class="fas fa-trash-alt text-sm"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Totaux (inchangés) -->
              <div class="flex justify-end">
                <div
                  class="w-80 rounded-xl border border-gray-200 overflow-hidden bg-gray-50"
                >
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100"
                  >
                    <span class="text-sm text-gray-500">Total HT</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ formatAmount(totals.ht) }} MAD</span
                    >
                  </div>
                  <div
                    class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
                  >
                    <span class="text-sm text-gray-500"
                      >TVA ({{ form.tva_rate }}%)</span
                    >
                    <span class="text-sm font-semibold text-gray-800 font-mono"
                      >{{ formatAmount(totals.tva) }} MAD</span
                    >
                  </div>
                  <div class="px-5 py-4 flex justify-between">
                    <span
                      class="text-sm font-bold text-[#062121] uppercase tracking-wide"
                      >Total TTC</span
                    >
                    <span class="text-lg font-black text-[#062121] font-mono"
                      >{{ formatAmount(totals.ttc) }} MAD</span
                    >
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
                    editingInvoiceId ? "Modifier" : "Enregistrer"
                  }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODALE CRÉATION PRODUIT (à la volée) -->
    <div
      v-if="showProductModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-all"
    >
      <div
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all"
      >
        <div
          class="border-b border-gray-100 px-6 py-4 flex items-center justify-between"
        >
          <h3 class="text-lg font-bold text-[#062121]">Créer un produit</h3>
          <button
            @click="closeProductModal"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <InputLabel value="Nom / Désignation *" />
            <TextInput
              v-model="productModalForm.name"
              placeholder="Ex: Écran 24 pouces"
              autofocus
            />
            <InputError :message="productModalErrors.name" />
          </div>
          <div>
            <InputLabel value="Référence / SKU (optionnel)" />
            <TextInput
              v-model="productModalForm.sku"
              placeholder="Référence interne"
            />
            <InputError :message="productModalErrors.sku" />
          </div>
          <div>
            <InputLabel value="Prix d’achat HT *" />
            <TextInput
              type="number"
              step="0.01"
              min="0"
              v-model="productModalForm.price"
              placeholder="0.00"
            />
            <InputError :message="productModalErrors.price" />
          </div>
          <InputError :message="productModalErrors.server" />
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-2xl">
          <button
            type="button"
            @click="closeProductModal"
            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
          >
            Annuler
          </button>
          <PrimaryButton
            :disabled="isCreatingProduct"
            @click="createProductAndSelect"
          >
            <span v-if="isCreatingProduct">
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
              Création...
            </span>
            <span v-else>Enregistrer le produit</span>
          </PrimaryButton>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
