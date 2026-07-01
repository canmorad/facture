<script setup>
import { reactive, ref, onMounted, nextTick, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../../stores/auth";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import SettingsLayout from "../../layouts/SettingsLayout.vue";
import TextInput from "../../components/TextInput.vue";
import SignaturePad from "signature_pad";
import axios from "axios";
import { success, error, validation, confirm, showWelcomeModal } from "../../helpers/notifications";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isCreateMode = computed(() => route.query.mode === 'create');
console.log(isCreateMode.value);

const logoPreview = ref(null);
const signaturePreview = ref(null);
const showSignatureModal = ref(false);
const canvasRef = ref(null);
let signaturePad = null;
const currentDate = ref(
  new Date().toLocaleDateString("fr-FR", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }),
);

const form = reactive({
  logo: null,
  signature: null,
  company_name: "",
  if: "",
  ice: "",
  rc: "",
  patente: "",
  cnss: "",
  email: "",
  phone: "",
  address: "",
  city: "",
  postal_code: "",
  country: "",
  website: "",
});

const errors = reactive({
  company_name: "",
  if: "",
  ice: "",
  rc: "",
  patente: "",
  cnss: "",
  email: "",
  phone: "",
  address: "",
  city: "",
  postal_code: "",
  country: "",
  website: "",
  logo: "",
  signature: "",
  server: "",
});

const fetchCompanySettings = async () => {
  if (isCreateMode.value) {
    return;
  }
  try {
    const companyId = authStore.currentCompanyId;
    const { data } = await axios.get("/api/company-settings", {
      params: { company_id: companyId },
    });
    if (data) {
      Object.assign(form, data);
      form.logo = null;
      form.signature = null;
      if (data.logo) logoPreview.value = data.logo;
      if (data.signature) signaturePreview.value = data.signature;
    }
  } catch {
    errors.server = "Erreur lors du chargement des données.";
  }
};

onMounted(() => {
  fetchCompanySettings();
  if (isCreateMode.value) {
    showWelcomeModal(
      'Bienvenue !',
      'Pour pouvoir générer vos devis et factures légalement, vous devez d\'abord configurer les coordonnées de votre entreprise.',
      'Commencer'
    );
  }
});

const handleLogoUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;
  form.logo = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    logoPreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

const initSignaturePad = () => {
  if (!canvasRef.value) return;
  const canvas = canvasRef.value;
  const container = canvas.parentElement;
  canvas.width = container.clientWidth;
  canvas.height = 200;

  if (signaturePad) signaturePad.off();

  signaturePad = new SignaturePad(canvas, {
    penColor: "#062121",
    backgroundColor: "#ffffff",
    minWidth: 1,
    maxWidth: 3,
  });

  signaturePad.clear();

  if (signaturePreview.value) {
    const img = new Image();
    img.onload = () => signaturePad.fromDataURL(signaturePreview.value);
    img.src = signaturePreview.value;
  }
};

const openSignatureModal = async () => {
  showSignatureModal.value = true;
  await nextTick();
  setTimeout(initSignaturePad, 100);
};

const closeSignatureModal = () => {
  showSignatureModal.value = false;
  signaturePad?.clear();
};

const clearSignature = () => signaturePad?.clear();

const dataURLToFile = (dataURL, filename) => {
  const arr = dataURL.split(",");
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8 = new Uint8Array(n);
  while (n--) u8[n] = bstr.charCodeAt(n);
  return new File([u8], filename, { type: mime });
};

const saveSignature = () => {
  if (signaturePad && !signaturePad.isEmpty()) {
    const dataURL = signaturePad.toDataURL();
    form.signature = dataURLToFile(dataURL, `signature_${Date.now()}.png`);
    signaturePreview.value = dataURL;
    closeSignatureModal();
  } else if (signaturePad?.isEmpty() && signaturePreview.value) {
    closeSignatureModal();
  } else {
    validation("Veuillez dessiner votre signature.", "Signature vide");
  }
};

const removeSignature = async () => {
  const result = await confirm(
    "Supprimer la signature ?",
    "Cette action est irréversible."
  );
  if (result.isConfirmed) {
    form.signature = null;
    signaturePreview.value = null;
    success("Supprimée !", "La signature a été supprimée.");
  }
};

const submit = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));

  const formData = new FormData();
  Object.keys(form).forEach((key) => {
    if (form[key] !== null && form[key] !== undefined) {
      formData.append(key, form[key]);
    }
  });

  try {
    if (isCreateMode.value) {
      const response = await axios.post("/api/companies", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      authStore.addCompany(response.data);
      success("Entreprise créée !", "La nouvelle entreprise a été créée avec succès.");
      router.push({ name: "settings.numbering" });
    } else {
      const companyId = authStore.currentCompanyId;
      if (!companyId) {
        errors.server = "Aucune entreprise sélectionnée.";
        return;
      }
      formData.append("company_id", companyId);
      await axios.post("/api/company-settings", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      const statusRes = await axios.get("/api/user-status");
      authStore.setAuthData(statusRes.data);
      success("Enregistré !", "Informations enregistrées avec succès !");
      router.push({ name: "dashboard" });
    }
  } catch (error) {
    if (error.response?.status === 422) {
      const e = error.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
    }
  }
};
</script>

<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3 flex justify-between items-center flex-wrap gap-4">
            <div class="flex gap-6">
              <button class="pb-3 text-sm font-bold text-[#062121] border-b-2 border-[#C5F82A] relative">
                <i class="fas fa-building mr-2"></i>
                {{ isCreateMode ? 'Créer une entreprise' : 'Informations générales' }}
              </button>
            </div>
          </div>

          <form @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-8">
              <!-- Logo + Signature -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-gray-100">
                <div class="flex flex-col items-center sm:flex-row sm:items-start gap-6">
                  <div class="relative flex-shrink-0">
                    <div class="h-24 w-24 rounded-full bg-gray-100 border-2 border-[#C5F82A] flex items-center justify-center overflow-hidden">
                      <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="h-full w-full object-cover" />
                      <svg v-else class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 21v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4M8 7h8" />
                      </svg>
                    </div>
                    <label class="absolute bottom-0 right-0 cursor-pointer rounded-full bg-[#062121] p-1.5 shadow-md hover:bg-[#062121]/90">
                      <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <input type="file" accept="image/*" @change="handleLogoUpload" class="hidden" />
                    </label>
                  </div>
                  <div class="text-center sm:text-left">
                    <p class="text-sm font-medium text-[#062121]">Logo de l'entreprise</p>
                    <p class="text-xs text-gray-500 mt-1">Format PNG, JPG. Taille max 2MB</p>
                    <InputError class="mt-2" :message="errors.logo" />
                  </div>
                </div>

                <div class="flex flex-col gap-3">
                  <p class="text-sm font-medium text-[#062121]">Signature numérique</p>
                  <div class="flex items-center gap-3 flex-wrap">
                    <button type="button" @click="openSignatureModal" class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2">
                      <i class="fas fa-pen"></i> Signature
                    </button>
                    <button v-if="signaturePreview" type="button" @click="removeSignature" class="w-8 h-8 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors flex items-center justify-center" title="Supprimer">
                      <i class="fas fa-trash-alt text-sm"></i>
                    </button>
                  </div>
                  <div v-if="signaturePreview" class="flex items-center gap-3">
                    <img :src="signaturePreview" alt="Signature" class="h-16 object-contain border rounded-lg p-1 bg-white" />
                    <p class="text-xs text-gray-500">Signé le : {{ currentDate }}</p>
                  </div>
                  <p class="text-xs text-gray-500">Cliquez pour ajouter ou modifier votre signature</p>
                  <InputError :message="errors.signature" />
                </div>
              </div>

              <InputError :message="errors.server" />

              <!-- Identifiants légaux -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <InputLabel for="company_name" value="Raison sociale *" />
                  <TextInput id="company_name" type="text" class="mt-1 block w-full" v-model="form.company_name" placeholder="Nom officiel de l'entreprise" autofocus />
                  <InputError class="mt-2" :message="errors.company_name" />
                </div>
                <div>
                  <InputLabel for="if" value="Identifiant Fiscal (IF)" />
                  <TextInput id="if" type="text" class="mt-1 block w-full" v-model="form.if" placeholder="12345678" />
                  <InputError class="mt-2" :message="errors.if" />
                </div>
                <div>
                  <InputLabel for="ice" value="ICE" />
                  <TextInput id="ice" type="text" class="mt-1 block w-full" v-model="form.ice" placeholder="000000000000000" />
                  <InputError class="mt-2" :message="errors.ice" />
                </div>
                <div>
                  <InputLabel for="rc" value="RC (Registre de commerce)" />
                  <TextInput id="rc" type="text" class="mt-1 block w-full" v-model="form.rc" placeholder="12345" />
                  <InputError class="mt-2" :message="errors.rc" />
                </div>
                <div>
                  <InputLabel for="patente" value="Patente" />
                  <TextInput id="patente" type="text" class="mt-1 block w-full" v-model="form.patente" placeholder="12345678" />
                  <InputError class="mt-2" :message="errors.patente" />
                </div>
                <div>
                  <InputLabel for="cnss" value="CNSS" />
                  <TextInput id="cnss" type="text" class="mt-1 block w-full" v-model="form.cnss" placeholder="1234567" />
                  <InputError class="mt-2" :message="errors.cnss" />
                </div>
              </div>

              <!-- Coordonnées -->
              <div class="border-t border-gray-100 pt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">Coordonnées</h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                    <InputLabel for="email" value="Email *" />
                    <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" placeholder="contact@entreprise.com" />
                    <InputError class="mt-2" :message="errors.email" />
                  </div>
                  <div>
                    <InputLabel for="phone" value="Téléphone *" />
                    <TextInput id="phone" type="tel" class="mt-1 block w-full" v-model="form.phone" placeholder="+212 6XX XXX XXX" />
                    <InputError class="mt-2" :message="errors.phone" />
                  </div>
                  <div class="md:col-span-2">
                    <InputLabel for="address" value="Adresse *" />
                    <TextInput id="address" type="text" class="mt-1 block w-full" v-model="form.address" placeholder="Adresse complète" />
                    <InputError class="mt-2" :message="errors.address" />
                  </div>
                  <div>
                    <InputLabel for="city" value="Ville" />
                    <TextInput id="city" type="text" class="mt-1 block w-full" v-model="form.city" placeholder="Casablanca" />
                    <InputError class="mt-2" :message="errors.city" />
                  </div>
                  <div>
                    <InputLabel for="postal_code" value="Code postal" />
                    <TextInput id="postal_code" type="text" class="mt-1 block w-full" v-model="form.postal_code" placeholder="20000" />
                    <InputError class="mt-2" :message="errors.postal_code" />
                  </div>
                  <div>
                    <InputLabel for="country" value="Pays" />
                    <TextInput id="country" type="text" class="mt-1 block w-full" v-model="form.country" placeholder="Maroc" />
                    <InputError class="mt-2" :message="errors.country" />
                  </div>
                  <div>
                    <InputLabel for="website" value="Site web" />
                    <TextInput id="website" type="url" class="mt-1 block w-full" v-model="form.website" placeholder="https://www.entreprise.com" />
                    <InputError class="mt-2" :message="errors.website" />
                  </div>
                </div>
              </div>

              <!-- Bouton Enregistrer -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <PrimaryButton>
                  {{ isCreateMode ? 'Créer l\'entreprise' : 'Enregistrer les modifications' }}
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Signature Modal -->
    <div v-if="showSignatureModal" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="px-6 pt-6 pb-4">
          <h3 class="text-lg font-bold text-[#062121]">Signature</h3>
        </div>
        <div class="px-6 pb-4">
          <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-white">
            <canvas ref="canvasRef" style="width: 100%; height: 200px; touch-action: none"></canvas>
          </div>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3">
          <button type="button" @click="closeSignatureModal" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">Cancel</button>
          <button type="button" @click="clearSignature" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">Clear</button>
          <button type="button" @click="saveSignature" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors" style="background-color: #062121">Save</button>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>