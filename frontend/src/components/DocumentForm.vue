<script setup>
import { reactive, ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { success, error, confirm } from "../helpers/notifications";
import InputError from "./InputError.vue";

// ---------- PROPS ----------
const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (value) => ["invoice", "devis"].includes(value),
  },
  clientId: {
    type: [Number, String],
    default: null,
  },
  editData: {
    type: Object,
    default: null,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
  isLocked: {
    type: Boolean,
    default: false,
  },
  lockReason: {
    type: String,
    default: null,
  },
  availableActions: {
    type: Object,
    default: () => ({}),
  },
});

const router = useRouter();

// ---------- ÉTATS ----------
const isLoadingProducts = ref(false);
const isLoadingCompany = ref(false);
const isLoadingClients = ref(false);
const isLoadingCategories = ref(false);
const isSavingDoc = ref(false);

const products = ref([]);
const productCategories = ref([]);
const companySettings = ref(null);
const clients = ref([]);
const selectedClient = ref(null);
const selectedCategory = ref(null);

// Formulaire principal
const documentForm = reactive({
  client_id: null,
  type: props.type,
  date: new Date().toISOString().split("T")[0],
  due_date: "",
  tva_rate: 20,
  items: [],
});

const docErrors = reactive({ server: "", items: "" });

// ---------- CALCULS & UTILITAIRES ----------
const round2 = (n) => Math.round(n * 100) / 100;

const fmt = (n) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);

const totals = computed(() => {
  const ht = documentForm.items.reduce(
    (sum, item) =>
      sum + (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0),
    0,
  );
  const tva = ht * (documentForm.tva_rate / 100);
  return {
    ht: round2(ht),
    tva: round2(tva),
    ttc: round2(ht + tva),
  };
});

const filteredProducts = computed(() => {
  if (!selectedCategory.value) return products.value;
  return products.value.filter(p => p.category_id === selectedCategory.value);
});

const docTitle = computed(() => {
  if (props.isEdit) {
    return props.type === "invoice" ? "Modifier Facture" : "Modifier Devis";
  }
  return props.type === "invoice" ? "Création Facture" : "Création Devis";
});

const docBadgeClass = computed(() =>
  props.type === "invoice"
    ? "bg-emerald-100 text-emerald-700"
    : "bg-sky-100 text-sky-700",
);

const isDisabled = computed(() => props.isLocked || (props.isEdit && !props.availableActions.can_edit));

const lockMessage = computed(() => {
  if (!props.isLocked) return null;
  return props.lockReason || 'Document verrouillé (Article 145)';
});

const makeItem = () => ({
  product_id: null,
  designation: "",
  quantity: 1,
  price: 0,
});

const addLine = () => documentForm.items.push(makeItem());

const removeLine = (index) => {
  if (documentForm.items.length > 1) documentForm.items.splice(index, 1);
};

const onProductSelect = (index) => {
  const item = documentForm.items[index];
  const product = products.value.find((p) => p.id == item.product_id);
  if (product) {
    item.designation = product.name;
    item.price = product.price;
  }
};

const lineTotal = (item) =>
  round2((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0));

// ---------- CHARGEMENT DES DONNÉES ----------
const fetchProducts = async () => {
  if (products.value.length > 0) return;
  isLoadingProducts.value = true;
  try {
    const { data } = await axios.get("/api/products", {
      params: { with: 'category' }
    });
    products.value = data;
  } catch {
    // Silencieux
  } finally {
    isLoadingProducts.value = false;
  }
};

const fetchProductCategories = async () => {
  if (productCategories.value.length > 0) return;
  isLoadingCategories.value = true;
  try {
    const { data } = await axios.get("/api/product-categories");
    productCategories.value = data;
  } catch {
    // Silencieux
  } finally {
    isLoadingCategories.value = false;
  }
};

const fetchCompanySettings = async () => {
  isLoadingCompany.value = true;
  try {
    const { data } = await axios.get("/api/company-settings");
    companySettings.value = data;
  } catch {
    companySettings.value = null;
  } finally {
    isLoadingCompany.value = false;
  }
};

const fetchClients = async () => {
  isLoadingClients.value = true;
  try {
    const { data } = await axios.get("/api/clients");
    clients.value = data;
  } catch {
    // Silencieux
  } finally {
    isLoadingClients.value = false;
  }
};

const fetchClientById = async (id) => {
  try {
    const { data } = await axios.get(`/api/clients/${id}`);
    selectedClient.value = data;
    documentForm.client_id = data.id;
  } catch {
    error("Erreur", "Client non trouvé");
    router.push("/invoices");
  }
};

const loadEditData = () => {
  if (!props.editData) return;

  documentForm.client_id = props.editData.client_id;
  documentForm.type = props.editData.type || props.type;
  documentForm.date = props.editData.date.split("T")[0];
  documentForm.due_date = props.editData.due_date
    ? props.editData.due_date.split("T")[0]
    : "";
  documentForm.tva_rate = props.editData.tva_rate;
  documentForm.items = props.editData.items.map((item) => ({
    product_id: item.product_id,
    designation: item.designation,
    quantity: item.quantity,
    price: item.price,
  }));

  if (props.editData.client) {
    selectedClient.value = props.editData.client;
  }
};

// ---------- SAUVEGARDE ----------
const saveDocument = async () => {
  docErrors.server = "";
  docErrors.items = "";

  if (!documentForm.client_id) {
    docErrors.server = "Veuillez sélectionner un client.";
    return;
  }

  if (documentForm.items.some((i) => !i.designation.trim())) {
    docErrors.items = "Toutes les lignes doivent avoir une désignation.";
    return;
  }

  const result = await confirm(
    props.isEdit
      ? `Mettre à jour ce ${props.type === "invoice" ? "Facture" : "Devis"} ?`
      : `Enregistrer ce ${props.type === "invoice" ? "Facture" : "Devis"} ?`,
    `Client : ${selectedClient.value?.name} — Total TTC : ${fmt(totals.value.ttc)} DH`
  );
  if (!result.isConfirmed) return;

  isSavingDoc.value = true;

  const payload = {
    client_id: documentForm.client_id,
    type: documentForm.type,
    date: documentForm.date,
    due_date: documentForm.due_date || null,
    tva_rate: documentForm.tva_rate,
    items: documentForm.items.map((i) => ({
      product_id: i.product_id,
      designation: i.designation,
      quantity: i.quantity,
      price: i.price,
    })),
  };

  try {
    if (props.isEdit && props.editData) {
      await axios.put(`/api/invoices/${props.editData.id}`, payload);
      success("Mis à jour !", `${props.type === "invoice" ? "Facture" : "Devis"} modifié avec succès.`);
    } else {
      await axios.post("/api/invoices", payload);
      success("Enregistré !", `${props.type === "invoice" ? "Facture" : "Devis"} créé avec succès.`);
    }
    router.push(props.type === "invoice" ? "/invoices" : "/devis");
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      // Handle Laravel validation errors format
      if (e && typeof e === 'object') {
        docErrors.server = Object.values(e).flat().join(" — ");
      } else if (error.response.data?.message) {
        // Handle simple message format (from our custom exceptions)
        docErrors.server = error.response.data.message;
      } else {
        docErrors.server = "Erreur de validation.";
      }
    } else if (error.response?.status) {
      docErrors.server = error.response.data.message || "Erreur serveur.";
    } else {
      docErrors.server = "Erreur réseau. Vérifiez votre connexion.";
    }
  } finally {
    isSavingDoc.value = false;
  }
};

// ---------- INITIALISATION ----------
onMounted(async () => {
  await Promise.all([fetchProducts(), fetchProductCategories(), fetchCompanySettings()]);

  if (props.isEdit && props.editData) {
    loadEditData();
    if (props.editData.client_id) {
      await fetchClientById(props.editData.client_id);
    }
  } else if (props.clientId) {
    await fetchClientById(props.clientId);
  } else {
    await fetchClients();
  }

  if (!props.isEdit && documentForm.items.length === 0) {
    documentForm.items = [makeItem()];
  }
});

watch(
  () => props.type,
  (newType) => {
    documentForm.type = newType;
  },
);
</script>

<template>
  <div>
    <!-- Barre d'en-tête collante (sticky) -->
    <div
      class="print:hidden sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-100 shadow-lg"
    >
      <div class="flex items-center justify-between px-6 lg:px-8 py-2">
        <button
          @click="router.back()"
          class="flex items-center gap-2 text-gray-600 hover:text-[#C5F82A] transition-colors"
        >
          <i class="fas fa-arrow-left text-sm"></i>
          <span class="font-medium">Retour</span>
        </button>
        <div
          class="flex items-center gap-2 bg-gray-100/50 rounded-full px-3 py-1"
        >
          <span
            :class="[
              'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold',
              docBadgeClass,
            ]"
          >
            <i
              :class="
                type === 'invoice' ? 'fas fa-file-invoice' : 'fas fa-calculator'
              "
            ></i>
            {{ type === "invoice" ? "FACTURE" : "DEVIS" }}
          </span>
          <span class="text-sm font-semibold text-gray-700">|</span>
          <h2 class="text-base font-bold text-[#062121]">{{ docTitle }}</h2>
          <span
            v-if="isLocked"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"
          >
            <i class="fas fa-lock text-xs"></i>
            Verrouillé
          </span>
        </div>
        <div class="w-8"></div>
        <!-- placeholder for symmetry -->
      </div>
    </div>

    <!-- Contenu principal (carte du document) -->
    <div class="p-6 lg:p-8">
      <div
        class="rounded-2xl border border-gray-200 bg-gry-50 shadow-sm overflow-hidden"
      >
        <!-- Zone d'en-tête interne avec logo (positionné à droite) -->
        <div class="px-6 lg:px-8 pt-6 lg:pt-8 pb-0">
          <div class="flex justify-end mb-4">
            <div v-if="companySettings?.logo" class="flex-shrink-0">
              <img
                :src="companySettings.logo"
                alt="Company Logo"
                class="h-16 w-auto object-contain rounded-lg border border-gray-200 p-1"
              />
            </div>
            <div v-else class="flex-shrink-0">
              <div
                class="h-16 w-16 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center"
              >
                <i class="fas fa-building text-gray-400 text-2xl"></i>
              </div>
            </div>
          </div>

          <InputError :message="docErrors.server" class="mb-4" />

          <div
            v-if="isLocked"
            class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200"
          >
            <div class="flex items-start gap-3">
              <i class="fas fa-lock text-red-500 mt-0.5"></i>
              <div class="flex-1">
                <h4 class="text-sm font-bold text-red-700">Document verrouillé</h4>
                <p class="text-xs text-red-600 mt-1">{{ lockMessage }}</p>
              </div>
            </div>
          </div>

          <!-- Info société & client -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- FROM (Société) -->
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h3
                  class="text-sm font-bold text-gray-700 uppercase tracking-wider"
                >
                  De
                </h3>
              </div>
              <div class="p-5 space-y-2 bg-gray-50/30">
                <div v-if="isLoadingCompany" class="text-sm text-gray-500">
                  Chargement des informations...
                </div>
                <div v-else-if="companySettings" class="space-y-2">
                  <p class="font-bold text-gray-800">
                    {{ companySettings.company_name }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ companySettings.email }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ companySettings.phone }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ companySettings.address }}, {{ companySettings.city }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ companySettings.postal_code }},
                    {{ companySettings.country }}
                  </p>
                  <div
                    class="pt-2 mt-2 border-t border-gray-200 text-xs space-y-1"
                  >
                    <p v-if="companySettings.ice" class="text-gray-500">
                      <span class="font-semibold">ICE:</span>
                      {{ companySettings.ice }}
                    </p>
                    <p v-if="companySettings.if" class="text-gray-500">
                      <span class="font-semibold">IF:</span>
                      {{ companySettings.if }}
                    </p>
                    <p v-if="companySettings.rc" class="text-gray-500">
                      <span class="font-semibold">RC:</span>
                      {{ companySettings.rc }}
                    </p>
                  </div>
                </div>
                <div v-else class="text-sm text-yellow-600">
                  <i class="fas fa-exclamation-triangle"></i>
                  Aucune information d'entreprise configurée.
                </div>
              </div>
            </div>

            <!-- BILL TO (Client) -->
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h3
                  class="text-sm font-bold text-gray-700 uppercase tracking-wider"
                >
                  Client
                </h3>
              </div>
              <div class="p-5 space-y-3 bg-gray-50/30">
                <div
                  v-if="!clientId && isLoadingClients"
                  class="text-sm text-gray-500"
                >
                  Chargement des clients...
                </div>
                <div v-else-if="!clientId && clients.length > 0 && !isEdit">
                  <select
                    @change="
                      (e) => {
                        const id = e.target.value;
                        if (!id) {
                          selectedClient = null;
                          documentForm.client_id = null;
                          return;
                        }
                        const client = clients.find((c) => c.id == id);
                        if (client) {
                          selectedClient = client;
                          documentForm.client_id = client.id;
                        }
                      }
                    "
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                  >
                    <option value="">Sélectionner un client</option>
                    <option
                      v-for="client in clients"
                      :key="client.id"
                      :value="client.id"
                    >
                      {{ client.name }}
                    </option>
                  </select>
                </div>
                <div v-if="selectedClient" class="space-y-2 mt-3">
                  <p class="font-bold text-gray-800">
                    {{ selectedClient.name }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ selectedClient.email }}
                  </p>
                  <p class="text-sm text-gray-600">
                    {{ selectedClient.phone }}
                  </p>
                  <div class="pt-2 mt-2 border-t border-gray-200 text-sm">
                    <p class="text-gray-700">{{ selectedClient.address }}</p>
                    <p class="text-gray-700">
                      {{ selectedClient.city }}, {{ selectedClient.country }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Champs date, échéance, TVA -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div>
              <label
                class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"
                >Date d'émission *</label
              >
              <input
                type="date"
                v-model="documentForm.date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
              />
            </div>
            <div>
              <label
                class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"
                >Date d'échéance</label
              >
              <input
                type="date"
                v-model="documentForm.due_date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
              />
            </div>
            <div>
              <label
                class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"
                >Taux TVA (%)</label
              >
              <select
                v-model.number="documentForm.tva_rate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
              >
                <option :value="0">0% — Exonéré</option>
                <option :value="7">7%</option>
                <option :value="10">10%</option>
                <option :value="14">14%</option>
                <option :value="20">20% — Standard</option>
              </select>
            </div>
          </div>

          <!-- Tableau des lignes -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
              <h3
                class="text-sm font-bold text-[#062121] uppercase tracking-wider"
              >
                Lignes du document
              </h3>
              <button
                type="button"
                @click="addLine"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
              >
                <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
              </button>
            </div>

            <InputError :message="docErrors.items" class="mb-3" />

            <div class="overflow-x-auto rounded-xl border border-gray-200">
              <table class="min-w-full">
                <thead class="bg-gray-50">
                  <tr>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[35%]"
                    >
                      Produit
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[25%]"
                    >
                      Désignation
                    </th>
                    <th
                      class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]"
                    >
                      Qté
                    </th>
                    <th
                      class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]"
                    >
                      P.U. HT
                    </th>
                    <th
                      class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]"
                    >
                      Total HT
                    </th>
                    <th class="px-4 py-3 w-[3%]"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                  <tr
                    v-for="(item, index) in documentForm.items"
                    :key="index"
                    :class="index % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'"
                  >
                    <td class="px-4 py-3">
                      <div class="space-y-1">
                        <select
                          v-model="selectedCategory"
                          @change="selectedCategory = $event.target.value || null"
                          class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-700 bg-white focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                        >
                          <option :value="null">Toutes catégories</option>
                          <option
                            v-for="cat in productCategories"
                            :key="cat.id"
                            :value="cat.id"
                          >
                            {{ cat.name }}
                          </option>
                        </select>
                        <select
                          v-model="item.product_id"
                          @change="onProductSelect(index)"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 bg-white focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                        >
                          <option :value="null">— Saisie libre —</option>
                          <option
                            v-for="product in filteredProducts"
                            :key="product.id"
                            :value="product.id"
                          >
                            {{ product.name }}
                          </option>
                        </select>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <input
                        type="text"
                        v-model="item.designation"
                        placeholder="Description de la ligne..."
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                      />
                    </td>
                    <td class="px-4 py-3">
                      <input
                        type="number"
                        v-model.number="item.quantity"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-center text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                      />
                    </td>
                    <td class="px-4 py-3">
                      <input
                        type="number"
                        v-model.number="item.price"
                        min="0"
                        step="0.01"
                        class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm text-right text-gray-700 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                      />
                    </td>
                    <td class="px-4 py-3 text-right">
                      <span
                        class="text-sm font-semibold text-[#062121] font-mono"
                        >{{ fmt(lineTotal(item)) }}</span
                      >
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button
                        type="button"
                        @click="removeLine(index)"
                        :disabled="documentForm.items.length === 1"
                        class="text-red-400 hover:text-red-600 transition-colors disabled:opacity-20 disabled:cursor-not-allowed"
                      >
                        <i class="fas fa-times text-xs"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Totaux -->
          <div
            class="flex flex-col lg:flex-row justify-between items-start gap-6 pt-4 pb-6 lg:pb-8"
          >
            <div class="flex-1 w-full lg:w-auto">
              <div v-if="companySettings?.signature" class="pt-2">
                <div
                  class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2"
                >
                  Signature :
                </div>
                <img
                  :src="companySettings.signature"
                  alt="Signature"
                  class="h-24 w-auto object-contain mix-blend-multiply"
                />
              </div>
            </div>

            <div class="w-full lg:w-80 shrink-0">
              <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div
                  class="px-5 py-3 flex justify-between border-b border-gray-100"
                >
                  <span class="text-sm text-gray-500">Total HT</span>
                  <span class="text-sm font-semibold text-gray-800 font-mono"
                    >{{ fmt(totals.ht) }} DH</span
                  >
                </div>
                <div
                  class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50"
                >
                  <span class="text-sm text-gray-500"
                    >TVA ({{ documentForm.tva_rate }}%)</span
                  >
                  <span class="text-sm font-semibold text-gray-800 font-mono"
                    >{{ fmt(totals.tva) }} DH</span
                  >
                </div>
                <div class="px-5 py-4 flex justify-between bg-gray-50">
                  <span
                    class="text-sm font-bold text-[#062121] uppercase tracking-wide"
                    >Total TTC</span
                  >
                  <span class="text-lg font-black text-[#062121] font-mono"
                    >{{ fmt(totals.ttc) }} DH</span
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Boutons d'action sous la carte -->
      <div class="flex justify-end gap-3 mt-6">
        <button
          type="button"
          @click="router.back()"
          class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all"
        >
          Annuler
        </button>
        <button
          type="button"
          @click="saveDocument"
          :disabled="
            isSavingDoc ||
            documentForm.items.length === 0 ||
            !documentForm.client_id ||
            isDisabled
          "
          class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white transition-all hover:opacity-90 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
          style="background-color: #062121"
        >
          <span v-if="isSavingDoc">
            <svg
              class="animate-spin h-4 w-4 text-white inline mr-1"
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
            {{ isEdit ? "Mise à jour..." : "Enregistrement..." }}
          </span>
          <span v-else>
            <i class="fas fa-save mr-1.5"></i>
            {{ isEdit ? "Mettre à jour" : "Enregistrer" }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
