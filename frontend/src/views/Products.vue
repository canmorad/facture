<script setup>
import { reactive, ref, onMounted } from "vue";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue"; 
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import CustomSelect from "../components/CustomSelect.vue";
import { useAuthStore } from "../stores/auth";
import axios from "axios";
import { success, error, validation, confirm } from "../helpers/notifications";

const authStore = useAuthStore();

const isLoading = ref(false);
const isLoadingProducts = ref(false);
const isLoadingLookups = ref(false);
const activeTab = ref("list");
const editingProductId = ref(null);

const products = ref([]);
const taxRates = ref([]);
const categories = ref([]);

const form = reactive({
  name: "",
  price: "",
  description: "",
  category_id: null,
  tax_rate_id: null,
});

const errors = reactive({
  name: "",
  price: "",
  description: "",
  category_id: "",
  tax_rate_id: "",
  server: "",
});

const fetchLookups = async () => {
  isLoadingLookups.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const response = await axios.get("/api/products/create", { params });
    
    categories.value = response.data.categories.map(cat => ({
      label: cat.label || cat.name || `Catégorie ${cat.id}`,
      value: cat.id,
    }));
    
    taxRates.value = response.data.tax_rates.map(tax => ({
      label: `${tax.libelle || tax.label || 'TVA'} (${tax.rate}%)`,
      value: tax.id,
    }));
  } catch {
    error("Erreur", "Impossible de charger les données du formulaire.");
  } finally {
    isLoadingLookups.value = false;
  }
};

const fetchProducts = async () => {
  isLoadingProducts.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const response = await axios.get("/api/products", { params });
    products.value = response.data;
  } catch {
    error("Erreur", "Impossible de charger les produits.");
  } finally {
    isLoadingProducts.value = false;
  }
};

const resetForm = () => {
  form.name = "";
  form.price = "";
  form.description = "";
  form.category_id = null;
  form.tax_rate_id = null;
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingProductId.value = null;
};

const editProduct = (product) => {
  editingProductId.value = product.id;
  form.name = product.name;
  form.price = product.price;
  form.description = product.description || "";
  form.category_id = product.category_id || null;
  form.tax_rate_id = product.tax_rate_id || null;
  activeTab.value = "add";
};

const submit = async () => {
  Object.keys(errors).forEach((key) => (errors[key] = ""));
  isLoading.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise.";
    isLoading.value = false;
    return;
  }

  const payload = {
    ...form,
    company_id: companyId,
  };

  try {
    if (editingProductId.value) {
      await axios.put(`/api/products/${editingProductId.value}`, payload);
      success("Produit modifié !", "Le produit a été modifié avec succès.");
    } else {
      await axios.post("/api/products", payload);
      success("Produit ajouté !", "Le produit a été enregistré avec succès.");
    }
    resetForm();
    await fetchProducts();
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
      error("Erreur", "Impossible d'enregistrer le produit.");
    }
  } finally {
    isLoading.value = false;
  }
};

const deleteProduct = async (id, name) => {
  const result = await confirm(
    "Supprimer le produit",
    `Supprimer "${name}" définitivement ?`
  );
  if (!result.isConfirmed) return;
  
  try {
    await axios.delete(`/api/products/${id}`);
    success("Supprimé !", "Le produit a été supprimé.");
    await fetchProducts();
  } catch {
    error("Erreur", "Impossible de supprimer le produit.");
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (products.value.length === 0) fetchProducts();
  }
  if (tab === "add" && (taxRates.value.length === 0 || categories.value.length === 0)) {
    fetchLookups();
  }
};

const formatPrice = (price) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

onMounted(() => {
  fetchProducts();
  fetchLookups();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          
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
                Liste des produits
                <span
                  v-if="products.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ products.length }}</span
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
                <i class="fas" :class="editingProductId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingProductId ? 'Modifier le produit' : 'Ajouter un produit' }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingProducts" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des produits...</p>
            </div>

            <div v-else-if="products.length === 0" class="text-center py-12">
              <i class="fas fa-cubes text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun produit enregistré pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre premier produit
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Produit / Service</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Prix HT</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Catégorie</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">TVA</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="product in products" :key="product.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div>
                          <div class="text-sm font-semibold text-gray-900">{{ product.name }}</div>
                          <div class="text-xs text-gray-400">ID #{{ product.id }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-[#062121]">
                        {{ formatPrice(product.price) }} MAD
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                        {{ product.category?.name || '—' }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-50 text-blue-700">
                        {{ product.tax_rate ? product.tax_rate.rate + '%' : '—' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 max-w-[200px]">
                      <div class="text-sm text-gray-600 truncate">
                        {{ product.description || "—" }}
                      </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="editProduct(product)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteProduct(product.id, product.name)"
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

          <form v-else-if="activeTab === 'add'" @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div v-if="isLoadingLookups" class="text-center py-4">
                <svg class="animate-spin h-6 w-6 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              </div>

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nom du produit / service *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="Ex: Écran 24 pouces"
                    autofocus
                  />
                  <InputError class="mt-2" :message="errors.name" />
                </div>

                <div>
                  <InputLabel for="price" value="Prix unitaire HT (MAD) *" />
                  <TextInput
                    id="price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    v-model="form.price"
                    placeholder="0.00"
                  />
                  <InputError class="mt-2" :message="errors.price" />
                </div>

                <div>
                  <InputLabel for="category_id" value="Catégorie" />
                  <CustomSelect
                    id="category_id"
                    v-model="form.category_id"
                    :options="categories"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner une catégorie"
                  />
                  <InputError class="mt-2" :message="errors.category_id" />
                </div>

                <div>
                  <InputLabel for="tax_rate_id" value="Taux de TVA" />
                  <CustomSelect
                    id="tax_rate_id"
                    v-model="form.tax_rate_id"
                    :options="taxRates"
                    label-key="label"
                    value-key="value"
                    placeholder="Sélectionner un taux"
                  />
                  <InputError class="mt-2" :message="errors.tax_rate_id" />
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6">
                <InputLabel for="description" value="Description" />
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  placeholder="Description du produit..."
                  class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                ></textarea>
                <InputError class="mt-2" :message="errors.description" />
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
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>{{ editingProductId ? 'Modifier le produit' : 'Enregistrer le produit' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>