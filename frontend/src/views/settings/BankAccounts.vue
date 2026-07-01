<!-- views/settings/BankAccountsIndex.vue -->
<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Onglets -->
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
                Liste des comptes bancaires
                <span
                  v-if="items.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ items.length }}</span
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
                <i class="fas" :class="editingId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingId ? 'Modifier le compte' : 'Ajouter un compte' }}
              </button>
            </div>
          </div>

          <!-- Liste -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des comptes...</p>
            </div>

            <div v-else-if="items.length === 0" class="text-center py-12">
              <i class="fas fa-university text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun compte bancaire enregistré.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Ajouter votre premier compte
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Libellé</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Banque</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">RIB</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Défaut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="item in items"
                    :key="item.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ item.label }}</td>
                    <td class="px-4 py-4 text-sm text-gray-700">{{ item.bank_name }}</td>
                    <td class="px-4 py-4 text-sm font-mono text-gray-600">
                      {{ formatRib(item.rib) }}
                    </td>
                    <td class="px-4 py-4">
                      <span
                        :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          item.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'
                        ]"
                      >
                        {{ item.is_active ? 'Actif' : 'Inactif' }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="item.is_default" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#C5F82A]/20 text-[#062121]">
                        <i class="fas fa-star text-[#C5F82A] mr-1"></i> Défaut
                      </span>
                      <span v-else class="text-xs text-gray-400">—</span>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="toggleActive(item)"
                          :title="item.is_active ? 'Désactiver' : 'Activer'"
                          :class="[
                            'w-8 h-8 rounded-lg transition-all duration-200',
                            item.is_active
                              ? 'text-yellow-600 hover:bg-yellow-50 hover:text-yellow-800'
                              : 'text-green-600 hover:bg-green-50 hover:text-green-800'
                          ]"
                        >
                          <i :class="item.is_active ? 'fas fa-pause' : 'fas fa-play'"></i>
                        </button>
                        <button
                          v-if="!item.is_default"
                          @click="setDefault(item)"
                          title="Définir par défaut"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-[#C5F82A]/20 hover:text-[#062121] transition-all duration-200"
                        >
                          <i class="fas fa-star"></i>
                        </button>
                        <button
                          @click="editItem(item)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteItem(item)"
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

          <!-- Formulaire -->
          <form v-else-if="activeTab === 'add'" @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-6">
              <InputError :message="errors.server" />

              <div>
                <InputLabel for="label" value="Libellé *" />
                <TextInput
                  id="label"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.label"
                  placeholder="Ex: Compte Principal"
                  autofocus
                />
                <InputError class="mt-2" :message="errors.label" />
              </div>

              <div>
                <InputLabel for="bank_name" value="Nom de la banque *" />
                <TextInput
                  id="bank_name"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.bank_name"
                  placeholder="Ex: Attijariwafa Bank"
                />
                <InputError class="mt-2" :message="errors.bank_name" />
              </div>

              <div>
                <InputLabel for="rib" value="RIB (24 chiffres) *" />
                <TextInput
                  id="rib"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.rib"
                  placeholder="Ex: 123456789012345678901234"
                  maxlength="24"
                />
                <InputError class="mt-2" :message="errors.rib" />
                <p class="text-xs text-gray-400 mt-1">Le RIB marocain est composé de 24 chiffres.</p>
              </div>

              <div>
                <InputLabel for="iban" value="IBAN" />
                <TextInput
                  id="iban"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.iban"
                  placeholder="Ex: MA1234567890123456789012345678"
                />
                <InputError class="mt-2" :message="errors.iban" />
              </div>

              <div>
                <InputLabel for="swift" value="SWIFT / BIC" />
                <TextInput
                  id="swift"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.swift"
                  placeholder="Ex: BCMAMAMC"
                />
                <InputError class="mt-2" :message="errors.swift" />
              </div>

              <div>
                <InputLabel for="currency" value="Devise" />
                <TextInput
                  id="currency"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.currency"
                  placeholder="MAD"
                />
                <InputError class="mt-2" :message="errors.currency" />
              </div>

              <div class="flex items-center gap-3">
                <Checkbox v-model="form.is_default" />
                <span class="text-sm text-gray-700">Définir comme compte par défaut</span>
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>{{ editingId ? 'Modifier' : 'Ajouter' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
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
import TextInput from "../../components/TextInput.vue";
import Checkbox from "../../components/Checkbox.vue";
import axios from "axios";
import { success, error, validation, confirm } from "../../helpers/notifications";

const authStore = useAuthStore();

const baseUrl = computed(() => {
  return `/api/companies/${authStore.currentCompanyId}/bank-accounts`;
});

const activeTab = ref("list");
const isLoading = ref(false);
const isSubmitting = ref(false);
const editingId = ref(null);
const items = ref([]);

const form = reactive({
  label: "",
  bank_name: "",
  rib: "",
  iban: "",
  swift: "",
  currency: "MAD",
  is_default: false,
});

const errors = reactive({
  label: "",
  bank_name: "",
  rib: "",
  iban: "",
  swift: "",
  currency: "",
  server: "",
});

const formatRib = (rib) => {
  if (!rib) return '—';
  // Display only first 4 and last 4 digits, hide the middle
  return rib.length >= 8 ? `${rib.slice(0, 4)} •••• ${rib.slice(-4)}` : rib;
};

const fetchItems = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(baseUrl.value);
    items.value = data;
  } catch {
    error("Erreur", "Impossible de charger les comptes bancaires.");
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  form.label = "";
  form.bank_name = "";
  form.rib = "";
  form.iban = "";
  form.swift = "";
  form.currency = "MAD";
  form.is_default = false;
  errors.label = "";
  errors.bank_name = "";
  errors.rib = "";
  errors.iban = "";
  errors.swift = "";
  errors.currency = "";
  errors.server = "";
  editingId.value = null;
};

const editItem = (item) => {
  editingId.value = item.id;
  form.label = item.label;
  form.bank_name = item.bank_name;
  form.rib = item.rib;
  form.iban = item.iban || "";
  form.swift = item.swift || "";
  form.currency = item.currency || "MAD";
  form.is_default = item.is_default;
  activeTab.value = "add";
};

const submit = async () => {
  Object.keys(errors).forEach(k => errors[k] = "");
  isSubmitting.value = true;

  if (!form.label.trim()) {
    errors.label = "Le libellé est requis.";
    isSubmitting.value = false;
    return;
  }
  if (!form.bank_name.trim()) {
    errors.bank_name = "Le nom de la banque est requis.";
    isSubmitting.value = false;
    return;
  }
  if (!form.rib.trim() || form.rib.length !== 24) {
    errors.rib = "Le RIB doit comporter exactement 24 chiffres.";
    isSubmitting.value = false;
    return;
  }

  const payload = { ...form };

  try {
    if (editingId.value) {
      await axios.put(`${baseUrl.value}/${editingId.value}`, payload);
      success("Modifié !", "Le compte bancaire a été modifié.");
    } else {
      await axios.post(baseUrl.value, payload);
      success("Ajouté !", "Le compte bancaire a été ajouté.");
    }
    resetForm();
    await fetchItems();
    activeTab.value = "list";
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      Object.keys(e).forEach(k => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
      validation(Object.values(e).flat().join("\n"));
    } else {
      errors.server = "Une erreur est survenue.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

const toggleActive = async (item) => {
  try {
    await axios.patch(`${baseUrl.value}/${item.id}/toggle-active`);
    item.is_active = !item.is_active;
    success("Statut mis à jour", `Le compte est maintenant ${item.is_active ? 'actif' : 'inactif'}.`);
  } catch {
    error("Erreur", "Impossible de modifier le statut.");
  }
};

const setDefault = async (item) => {
  try {
    await axios.put(`${baseUrl.value}/${item.id}/set-default`);
    items.value = items.value.map(i => ({
      ...i,
      is_default: i.id === item.id ? true : false
    }));
    success("Défaut", "Ce compte est maintenant le compte par défaut.");
  } catch {
    error("Erreur", "Impossible de définir ce compte par défaut.");
  }
};

const deleteItem = async (item) => {
  const result = await confirm("Supprimer", `Voulez-vous vraiment supprimer le compte "${item.label}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`${baseUrl.value}/${item.id}`);
    items.value = items.value.filter(i => i.id !== item.id);
    success("Supprimé !", "Le compte a été supprimé.");
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.error) {
      error("Erreur", err.response.data.error);
    } else {
      error("Erreur", "Impossible de supprimer le compte.");
    }
  }
};

const changeTab = (tab) => {
  activeTab.value = tab;
  if (tab === "list") {
    resetForm();
    if (items.value.length === 0) fetchItems();
  }
};

onMounted(() => {
  fetchItems();
});
</script>