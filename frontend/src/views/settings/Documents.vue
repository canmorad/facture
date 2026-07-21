<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex flex-wrap gap-4">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="changeTab(tab.value)"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === tab.value
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i :class="tab.icon"></i>
                {{ tab.label }}
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des paramètres...</p>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-8">
              <!-- Options card -->
              <div class="rounded-xl border border-gray-200/80 p-6 shadow-sm">
                <h3 class="text-base font-bold text-[#062121] mb-4 flex items-center gap-2">
                  <i class="fas fa-cog text-gray-400"></i> Options
                </h3>

                <div class="space-y-3">
                  <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                    <Checkbox v-model="form.show_username_pdf" />
                    Afficher le nom de l'utilisateur dans les PDF
                  </label>
                  <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                    <Checkbox v-model="form.hide_signature_block" />
                    Cacher le bloc de signature dans les PDF
                  </label>
                </div>
              </div>

              <!-- Text fields card -->
              <div class="rounded-xl border border-gray-200/80 p-6 shadow-sm">
                <h3 class="text-base font-bold text-[#062121] mb-4 flex items-center gap-2">
                  <i class="fas fa-file-alt text-gray-400"></i> Textes du document
                </h3>

                <div class="space-y-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Texte d'introduction par défaut
                    </label>
                    <textarea
                      v-model="form.intro_text"
                      rows="3"
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      placeholder="Texte d'introduction..."
                    ></textarea>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Texte de conclusion par défaut
                    </label>
                    <textarea
                      v-model="form.conclusion_text"
                      rows="3"
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      placeholder="Texte de conclusion..."
                    ></textarea>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Pied de page par défaut
                    </label>
                    <textarea
                      v-model="form.footer_text"
                      rows="3"
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      placeholder="Pied de page..."
                    ></textarea>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Conditions générales de vente par défaut
                    </label>
                    <textarea
                      v-model="form.terms"
                      rows="3"
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      placeholder="Conditions générales..."
                    ></textarea>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Notes supplémentaires
                    </label>
                    <textarea
                      v-model="form.notes"
                      rows="3"
                      class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                      placeholder="Notes..."
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- Save button -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="resetForm"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Réinitialiser
                </button>
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>Enregistrer</span>
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import { useAuthStore } from "../../stores/auth";
import SettingsLayout from "../../layouts/SettingsLayout.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import Checkbox from "../../components/Checkbox.vue";
import axios from "axios";
import { success, error, validation } from "../../helpers/notifications";

const authStore = useAuthStore();

const tabs = [
  { label: 'Devis', value: 'QUOTE', icon: 'fas fa-file-signature' },
  { label: 'Bons de commande', value: 'PURCHASE_ORDER', icon: 'fas fa-shopping-cart' },
  { label: 'Factures', value: 'INVOICE', icon: 'fas fa-file-invoice' },
  { label: 'Avoirs', value: 'CREDIT_NOTE', icon: 'fas fa-credit-card' },
  { label: 'Factures d\'acompte', value: 'DEPOSIT_INVOICE', icon: 'fas fa-hand-holding-usd' },
  { label: 'Avoirs d\'acompte', value: 'DEPOSIT_CREDIT_NOTE', icon: 'fas fa-hand-holding-heart' },
  { label: 'Factures de solde', value: 'BALANCE_INVOICE', icon: 'fas fa-file-invoice-dollar' },
  { label: 'Bons de livraison', value: 'DELIVERY_NOTE', icon: 'fas fa-truck' },
];

const activeTab = ref('QUOTE');
const isLoading = ref(false);
const isSubmitting = ref(false);

const form = reactive({
  document_type: 'QUOTE',
  hide_signature_block: false,
  show_username_pdf: true,
  intro_text: '',
  conclusion_text: '',
  footer_text: '',
  terms: '',
  notes: '',
});

const errors = reactive({
  server: '',
});

const fetchSettings = async (type) => {
  isLoading.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get(`/api/document-settings/${type}`, {
      params: { company_id: companyId },
    });
    Object.assign(form, data);
  } catch {
    error('Erreur', 'Impossible de charger les paramètres.');
  } finally {
    isLoading.value = false;
  }
};

const resetForm = () => {
  fetchSettings(activeTab.value);
};

const changeTab = (tab) => {
  activeTab.value = tab;
  errors.server = '';
  fetchSettings(tab);
};

const submit = async () => {
  errors.server = '';
  isSubmitting.value = true;

  const companyId = authStore.currentCompanyId;
  if (!companyId) {
    errors.server = 'Veuillez sélectionner une entreprise.';
    isSubmitting.value = false;
    return;
  }

  const payload = {
    ...form,
    company_id: companyId,
  };

  try {
    const { data } = await axios.post('/api/document-settings', payload);
    Object.assign(form, data);
    success('Enregistré !', 'Les paramètres ont été mis à jour.');
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      const msg = Object.values(e).flat().join('\n');
      validation(msg);
    } else {
      error('Erreur', 'Impossible de sauvegarder.');
    }
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  fetchSettings(activeTab.value);
});
</script>