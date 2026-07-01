<script setup>
import { reactive, ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();
const route = useRoute();

const isLoading = ref(false);
const isLoadingClients = ref(false);
const isLoadingProducts = ref(false);
const isLoadingInvoice = ref(false);
const isSaving = ref(false);

const clients = ref([]);
const products = ref([]);

const form = reactive({
  client_id: "",
  date: new Date().toISOString().split("T")[0],
  transporteur: "",
  tracking_number: "",
  items: [],
});

const errors = reactive({
  client_id: "",
  date: "",
  transporteur: "",
  tracking_number: "",
  items: "",
  server: "",
});

const makeItem = () => ({
  product_id: "",
  quantity: 1,
});

const fetchClients = async () => {
  isLoadingClients.value = true;
  try {
    const { data } = await axios.get("/api/clients");
    clients.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les clients.",
      icon: "error",
    });
  } finally {
    isLoadingClients.value = false;
  }
};

const fetchProducts = async () => {
  isLoadingProducts.value = true;
  try {
    const { data } = await axios.get("/api/products");
    products.value = data;
  } catch {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les produits.",
      icon: "error",
    });
  } finally {
    isLoadingProducts.value = false;
  }
};

const addItem = () => {
  form.items.push(makeItem());
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const submitDelivery = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  if (!form.client_id) {
    errors.client_id = "Veuillez sélectionner un client.";
    return;
  }
  if (!form.date) {
    errors.date = "La date de livraison est requise.";
    return;
  }
  if (!form.transporteur.trim()) {
    errors.transporteur = "Le transporteur / chauffeur est requis.";
    return;
  }
  if (form.items.some((i) => !i.product_id || i.quantity <= 0)) {
    errors.items =
      "Toutes les lignes doivent avoir un produit et une quantité valide.";
    return;
  }

  isSaving.value = true;
  try {
    const payload = {
      client_id: form.client_id,
      date: form.date,
      transporteur: form.transporteur,
      tracking_number: form.tracking_number,
      items: form.items,
      type: "delivery_note",
    };
    await axios.post("/api/deliveries", payload);
    Swal.fire({
      title: "Bon de livraison créé !",
      text: "Le bon de livraison a été enregistré avec succès.",
      icon: "success",
      confirmButtonColor: "#062121",
    });
    router.push("/deliveries");
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
    isSaving.value = false;
  }
};

const loadInvoiceData = async (invoiceId) => {
  isLoadingInvoice.value = true;
  try {
    const { data } = await axios.get(`/api/invoices/${invoiceId}`);
    form.client_id = data.client_id;
    form.items = data.items.map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
    }));
    // La date de livraison, transporteur et numéro de suivi restent vides
  } catch (error) {
    console.error("Erreur chargement facture", error);
    Swal.fire({
      title: "Attention",
      text: "Impossible de charger la facture, le formulaire reste vide.",
      icon: "warning",
      confirmButtonColor: "#062121",
    });
  } finally {
    isLoadingInvoice.value = false;
  }
};

onMounted(async () => {
  await Promise.all([fetchClients(), fetchProducts()]);
  form.items = [makeItem()];

  const invoiceId = route.query.invoice_id;
  if (invoiceId) {
    await loadInvoiceData(invoiceId);
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
            <div class="flex items-center gap-6">
              <div
                class="pb-3 text-sm font-bold text-[#062121] border-b-2 border-[#C5F82A] flex items-center gap-2"
              >
                <i class="fas fa-truck"></i>
                Créer un bon de livraison
              </div>
            </div>
          </div>

          <form @submit.prevent="submitDelivery" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <!-- Champs spécifiques livraison -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="client_id" value="Client *" />
                  <select
                    id="client_id"
                    v-model="form.client_id"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                    :disabled="isLoadingInvoice"
                  >
                    <option value="">-- Sélectionnez un client --</option>
                    <option
                      v-for="client in clients"
                      :key="client.id"
                      :value="client.id"
                    >
                      {{ client.name }}
                    </option>
                  </select>
                  <InputError class="mt-2" :message="errors.client_id" />
                </div>

                <div>
                  <InputLabel for="date" value="Date de livraison *" />
                  <input
                    id="date"
                    type="date"
                    v-model="form.date"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  />
                  <InputError class="mt-2" :message="errors.date" />
                </div>

                <div>
                  <InputLabel
                    for="transporteur"
                    value="Transporteur / Chauffeur *"
                  />
                  <TextInput
                    id="transporteur"
                    type="text"
                    v-model="form.transporteur"
                    placeholder="Ex: Trans Express, Ahmed Benali..."
                  />
                  <InputError class="mt-2" :message="errors.transporteur" />
                </div>

                <div>
                  <InputLabel
                    for="tracking_number"
                    value="Numéro de suivi / Matricule"
                  />
                  <TextInput
                    id="tracking_number"
                    type="text"
                    v-model="form.tracking_number"
                    placeholder="Ex: 1Z999AA1, 1234-A-56..."
                  />
                  <InputError class="mt-2" :message="errors.tracking_number" />
                </div>
              </div>

              <!-- Tableau des articles logistiques -->
              <div class="mt-6">

                <InputError :message="errors.items" class="mb-3" />

                <!-- Tableau des articles logistiques (style identique au tableau clients) -->
                <div class="mt-6">
                  <div class="flex items-center justify-between mb-3">
                    <h3
                      class="text-sm font-bold text-[#062121] uppercase tracking-wider"
                    >
                      Articles à livrer
                    </h3>
                    <button
                      type="button"
                      @click="addItem"
                      class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
                    >
                      <i class="fas fa-plus text-[10px]"></i> Ajouter un article
                    </button>
                  </div>

                  <InputError :message="errors.items" class="mb-3" />

                  <div
                    class="overflow-x-auto rounded-xl border border-gray-200"
                  >
                    <table class="min-w-full">
                      <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                          <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Produit
                          </th>
                          <th
                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32"
                          >
                            Quantité à livrer
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
                            <select
                              v-model="item.product_id"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 bg-white focus:border-[#C5F82A] focus:outline-none focus:ring-1 focus:ring-[#C5F82A]"
                            >
                              <option value="">-- Choisir un produit --</option>
                              <option
                                v-for="product in products"
                                :key="product.id"
                                :value="product.id"
                              >
                                {{ product.name }}
                              </option>
                            </select>
                          </td>
                          <td class="px-6 py-4 text-center">
                            <input
                              type="number"
                              v-model.number="item.quantity"
                              min="0.01"
                              step="0.01"
                              class="w-32 text-center rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:outline-none focus:ring-1 focus:ring-[#C5F82A]"
                            />
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
              </div>

              <!-- Boutons -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="router.back()"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium text-sm"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isSaving || isLoadingInvoice">
                  <span v-if="isSaving">
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
                  <span v-else>
                    <i class="fas fa-save mr-1.5"></i>
                    Créer le bon de livraison
                  </span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
