<script setup>
import { reactive, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import InputError from "../components/InputError.vue";
import InputLabel from "../components/InputLabel.vue";
import PrimaryButton from "../components/PrimaryButton.vue";
import TextInput from "../components/TextInput.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();
const authStore = useAuthStore();

const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingClients = ref(false);
const editingClientId = ref(null);

const clients = ref([]);

const form = reactive({
  type: "b2c",
  email: "",
  phone: "",
  address_street: "",
  city: "",
  postal_code: "",
  country: "",
  notes: "",
  is_active: true,
  legal_name: "",
  ice: "",
  rc: "",
  if: "",
  patente: "",
  name: "",
  cin: "",
});

const errors = reactive({
  type: "",
  email: "",
  phone: "",
  address_street: "",
  city: "",
  postal_code: "",
  country: "",
  notes: "",
  legal_name: "",
  ice: "",
  rc: "",
  if: "",
  patente: "",
  name: "",
  cin: "",
  server: "",
});

const fetchClients = async () => {
  isLoadingClients.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const { data } = await axios.get("/api/customers", { params });
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

const resetForm = () => {
  Object.keys(form).forEach((k) => (form[k] = ""));
  form.type = "b2c";
  form.is_active = true;
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingClientId.value = null;
};

const editClient = (client) => {
  editingClientId.value = client.id;
  Object.keys(form).forEach((key) => {
    if (key === "type") {
      form.type = client.type;
    } else if (client.hasOwnProperty(key) && client[key] !== null) {
      form[key] = client[key] || "";
    }
  });
  if (client.customerable) {
    if (client.type === "b2b") {
      form.legal_name = client.customerable.legal_name || "";
      form.ice = client.customerable.ice || "";
      form.rc = client.customerable.rc || "";
      form.if = client.customerable.if || "";
      form.patente = client.customerable.patente || "";
    } else {
      form.name = client.customerable.name || "";
      form.cin = client.customerable.cin || "";
    }
  }
  activeTab.value = "add";
};

const submitClient = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = "Veuillez sélectionner une entreprise avant d'ajouter un client.";
    isLoading.value = false;
    return;
  }

  const cleanForm = { ...form };
  Object.keys(cleanForm).forEach((key) => {
    if (cleanForm[key] === "") {
      cleanForm[key] = null;
    }
  });

  const payload = { ...cleanForm, company_id: companyId };

  try {
    if (editingClientId.value) {
      await axios.put(`/api/customers/${editingClientId.value}`, payload);
      Swal.fire({
        title: "Client modifié !",
        text: "Le client a été modifié avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    } else {
      await axios.post("/api/customers", payload);
      Swal.fire({
        title: "Client ajouté !",
        text: "Le client a été enregistré avec succès.",
        icon: "success",
        confirmButtonColor: "#062121",
      });
    }
    resetForm();
    await fetchClients();
    activeTab.value = "list";
  } catch (error) {
    console.log(error.response?.data?.message)
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

const deleteClient = async (id, name) => {
  const result = await Swal.fire({
    title: "Êtes-vous sûr ?",
    text: `Supprimer "${name}" définitivement ?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
  });
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/customers/${id}`);
    Swal.fire("Supprimé !", "Le client a été supprimé.", "success");
    await fetchClients();
  } catch {
    Swal.fire("Erreur", "Impossible de supprimer le client.", "error");
  }
};

const createInvoice = (client) => {
  router.push(`/document/create?type=invoice&client_id=${client.id}`);
};

const createDevis = (client) => {
  router.push(`/document/create?type=devis&client_id=${client.id}`);
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (clients.value.length === 0) fetchClients();
  }
};

onMounted(() => {
  fetchClients();
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
                Liste des clients
                <span
                  v-if="clients.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ clients.length }}</span
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
                  :class="editingClientId ? 'fa-edit' : 'fa-user-plus'"
                ></i>
                {{
                  editingClientId ? "Modifier le client" : "Ajouter un client"
                }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingClients" class="text-center py-12">
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
              <p class="mt-2 text-gray-500">Chargement des clients...</p>
            </div>

            <div
              v-else-if="clients.length === 0"
              class="text-center py-12"
            >
              <i class="fas fa-users text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                Aucun client enregistré pour le moment.
              </p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-user-plus"></i>
                Ajouter votre premier client
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Client
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Type
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Contact
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Téléphone
                    </th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider"
                    >
                      Adresse
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
                    v-for="client in clients"
                    :key="client.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div>
                          <div class="text-sm font-semibold text-gray-900">
                            {{
                              client.customerable
                                ? client.type === "b2b"
                                  ? client.customerable.legal_name
                                  : client.customerable.name
                                : client.name || "—"
                            }}
                          </div>
                          <div class="text-xs text-gray-400">
                            ID #{{ client.id }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="
                          client.type === 'b2b'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-green-100 text-green-800'
                        "
                      >
                        {{ client.type === "b2b" ? "B2B" : "B2C" }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="space-y-0.5">
                        <div
                          class="text-sm text-gray-700 flex items-center gap-1"
                        >
                          <i class="fas fa-envelope text-xs text-gray-400"></i>
                          {{ client.email || "—" }}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="space-y-0.5">
                        <div
                          class="text-sm text-gray-700 flex items-center gap-1"
                        >
                          <i class="fas fa-phone text-xs text-gray-400"></i>
                          {{ client.phone || "—" }}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="space-y-0.5">
                        <div
                          class="text-sm text-gray-700 flex items-center gap-1"
                        >
                          <i class="fas fa-city text-xs text-gray-400"></i>
                          {{ client.city || "—" }}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="createInvoice(client)"
                          title="Nouvelle Facture"
                          class="relative w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-file-invoice text-sm"></i>
                        </button>

                        <button
                          @click="createDevis(client)"
                          title="Nouveau Devis"
                          class="relative w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-600 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-calculator text-sm"></i>
                        </button>

                        <button
                          @click="editClient(client)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>

                        <button
                          @click="deleteClient(client.id, client.name || client.customerable?.name || client.customerable?.legal_name || '')"
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
            @submit.prevent="submitClient"
            class="p-6 lg:p-8"
          >
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="mb-6">
                <label
                  class="block text-sm font-medium text-gray-700 mb-2"
                  >Type de client</label
                >
                <div class="flex gap-4">
                  <label class="inline-flex items-center">
                    <input
                      type="radio"
                      value="b2b"
                      v-model="form.type"
                      class="form-radio text-[#C5F82A] focus:ring-[#C5F82A]"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                      >Professionnel (B2B)</span
                    >
                  </label>
                  <label class="inline-flex items-center">
                    <input
                      type="radio"
                      value="b2c"
                      v-model="form.type"
                      class="form-radio text-[#C5F82A] focus:ring-[#C5F82A]"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                      >Particulier (B2C)</span
                    >
                  </label>
                </div>
                <InputError class="mt-2" :message="errors.type" />
              </div>

              <div
                v-if="form.type === 'b2b'"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 border-t border-gray-100 pt-6 mt-6"
              >
                <div class="md:col-span-2">
                  <InputLabel for="legal_name" value="Raison sociale *" />
                  <TextInput
                    id="legal_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.legal_name"
                    placeholder="Nom de l'entreprise"
                  />
                  <InputError class="mt-2" :message="errors.legal_name" />
                </div>
                <div>
                  <InputLabel for="ice" value="ICE *" />
                  <TextInput
                    id="ice"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.ice"
                    placeholder="000000000000000"
                  />
                  <InputError class="mt-2" :message="errors.ice" />
                </div>
                <div>
                  <InputLabel for="rc" value="RC" />
                  <TextInput
                    id="rc"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.rc"
                    placeholder="123456"
                  />
                  <InputError class="mt-2" :message="errors.rc" />
                </div>
                <div>
                  <InputLabel for="if" value="IF" />
                  <TextInput
                    id="if"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.if"
                    placeholder="123456789"
                  />
                  <InputError class="mt-2" :message="errors.if" />
                </div>
                <div>
                  <InputLabel for="patente" value="Patente" />
                  <TextInput
                    id="patente"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.patente"
                    placeholder="12345678"
                  />
                  <InputError class="mt-2" :message="errors.patente" />
                </div>
              </div>

              <div
                v-if="form.type === 'b2c'"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 border-t border-gray-100 pt-6 mt-6"
              >
                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nom complet *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="Nom du client"
                  />
                  <InputError class="mt-2" :message="errors.name" />
                </div>
                <div>
                  <InputLabel for="cin" value="CIN" />
                  <TextInput
                    id="cin"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.cin"
                    placeholder="Carte d'identité nationale"
                  />
                  <InputError class="mt-2" :message="errors.cin" />
                </div>
              </div>

              <div class="border-t border-gray-100 pt-6 mt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">
                  Coordonnées
                </h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                      id="email"
                      type="email"
                      class="mt-1 block w-full"
                      v-model="form.email"
                      placeholder="contact@client.com"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                  </div>
                  <div>
                    <InputLabel for="phone" value="Téléphone" />
                    <TextInput
                      id="phone"
                      type="tel"
                      class="mt-1 block w-full"
                      v-model="form.phone"
                      placeholder="+212 5XX XXX XXX"
                    />
                    <InputError class="mt-2" :message="errors.phone" />
                  </div>
                  <div class="md:col-span-2">
                    <InputLabel for="address_street" value="Adresse" />
                    <TextInput
                      id="address_street"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.address_street"
                      placeholder="Rue, numéro, quartier..."
                    />
                    <InputError
                      class="mt-2"
                      :message="errors.address_street"
                    />
                  </div>
                  <div>
                    <InputLabel for="city" value="Ville" />
                    <TextInput
                      id="city"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.city"
                      placeholder="Casablanca"
                    />
                    <InputError class="mt-2" :message="errors.city" />
                  </div>
                  <div>
                    <InputLabel for="postal_code" value="Code postal" />
                    <TextInput
                      id="postal_code"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.postal_code"
                      placeholder="20000"
                    />
                    <InputError class="mt-2" :message="errors.postal_code" />
                  </div>
                  <div>
                    <InputLabel for="country" value="Pays" />
                    <TextInput
                      id="country"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.country"
                      placeholder="Maroc"
                    />
                    <InputError class="mt-2" :message="errors.country" />
                  </div>
                  <div>
                    <InputLabel for="notes" value="Notes" />
                    <TextInput
                      id="notes"
                      type="text"
                      class="mt-1 block w-full"
                      v-model="form.notes"
                      placeholder="Informations supplémentaires"
                    />
                    <InputError class="mt-2" :message="errors.notes" />
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
                    editingClientId
                      ? "Modifier le client"
                      : "Enregistrer le client"
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