<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { useAuthStore } from "@/stores/auth";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import TextInput from "../../components/TextInput.vue";
import logoUrl from "../../assets/images/logo.png";
import loginBackgroundUrl from "../../assets/images/login-background.png";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const token = ref(route.query.token || "");
const isValid = ref(false);
const isLoading = ref(true);
const isSubmitting = ref(false);
const companyName = ref("");
const email = ref("");
const roleName = ref("");
const errorMessage = ref("");

const form = reactive({
  name: "",
  password: "",
});

const errors = reactive({
  name: "",
  password: "",
  server: "",
});

const verifyToken = async () => {
  if (!token.value) {
    errorMessage.value = "Token d'invitation manquant.";
    isLoading.value = false;
    return;
  }

  try {
    const { data } = await axios.get(`/api/invitations/verify/${token.value}`);
    if (data.success) {
      isValid.value = true;
      email.value = data.data.email;
      companyName.value = data.data.company_name;
      roleName.value = data.data.role;
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || "Cette invitation est invalide ou a expiré.";
  } finally {
    isLoading.value = false;
  }
};

const submit = async () => {
  errors.name = "";
  errors.password = "";
  errors.server = "";
  isSubmitting.value = true;

  if (!form.name.trim()) {
    errors.name = "Le nom complet est requis.";
    isSubmitting.value = false;
    return;
  }
  if (form.password.length < 8) {
    errors.password = "Le mot de passe doit contenir au moins 8 caractères.";
    isSubmitting.value = false;
    return;
  }

  try {
    const { data } = await axios.post("/api/invitations/accept", {
      token: token.value,
      name: form.name,
      password: form.password,
    });

    authStore.setAuthData({
      user: data.user,
      token: data.token,
    });

    router.push({ name: "clients" });
  } catch (err) {
    if (err.response?.status === 422) {
      const validationErrors = err.response.data.errors;
      if (validationErrors) {
        if (validationErrors.name) errors.name = validationErrors.name[0];
        if (validationErrors.password) errors.password = validationErrors.password[0];
      }
      errors.server = err.response.data.message || "Erreur de validation.";
    } else {
      errors.server = err.response?.data?.message || "Une erreur est survenue.";
    }
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  verifyToken();
});
</script>

<template>
  <div
    class="min-h-screen bg-[#F5F0E8] flex items-center justify-center p-4 md:p-10 font-['Inter',sans-serif]"
  >
    <div
      class="bg-white w-full max-w-[1000px] flex rounded-[24px] overflow-hidden shadow-[0_15px_35px_rgba(0,0,0,0.08)]"
    >
      <div class="flex-1 p-8 sm:p-12 md:p-[70px]">
        <div class="mb-[40px]">
          <div class="mb-6 flex items-center h-[50px]">
            <img
              :src="logoUrl"
              alt="Logo"
              class="h-[120px] max-h-[120px] w-auto object-contain scale-150 origin-left contrast-125 brightness-105 drop-shadow-[0_2px_10px_rgba(197,248,42,0.4)]"
            />
          </div>
          <h1 class="text-[#062121] text-2xl sm:text-[28px] font-extrabold mb-2">
            Finaliser votre inscription
          </h1>
          <p class="text-[#64748B] text-sm">
            Vous avez été invité à rejoindre <strong>{{ companyName }}</strong>.
          </p>

          <InputError class="mt-2" :message="errors.server" />
        </div>

        <div v-if="isLoading" class="text-center py-8">
          <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <p class="mt-2 text-gray-500">Vérification de l'invitation...</p>
        </div>

        <div v-else-if="!isValid" class="text-center py-8">
          <i class="fas fa-exclamation-circle text-5xl text-red-400 mb-4 block"></i>
          <p class="text-gray-700 font-medium">{{ errorMessage }}</p>
          <p class="text-gray-400 text-sm mt-2">Contactez l'administrateur pour obtenir une nouvelle invitation.</p>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-[22px]">
          <div>
            <InputLabel for="email" value="Adresse email" />
            <TextInput
              id="email"
              type="email"
              class="mt-1 block w-full bg-gray-50"
              :model-value="email"
              disabled
            />
          </div>

          <div>
            <InputLabel for="name" value="Nom complet *" />
            <TextInput
              id="name"
              type="text"
              class="mt-1 block w-full"
              v-model="form.name"
              placeholder="Votre nom complet"
              autofocus
            />
            <InputError class="mt-2" :message="errors.name" />
          </div>

          <div>
            <InputLabel for="password" value="Mot de passe *" />
            <TextInput
              id="password"
              type="password"
              class="mt-1 block w-full"
              v-model="form.password"
              placeholder="Minimum 8 caractères"
            />
            <InputError class="mt-2" :message="errors.password" />
          </div>

          <div class="bg-[#F4F7F7] border border-gray-200 rounded-lg p-4 text-sm text-gray-600 flex items-center gap-2">
            <i class="fas fa-building text-[#062121]"></i>
            <span><strong>{{ companyName }}</strong></span>
            <span class="mx-1">·</span>
            <i class="fas fa-user-tag"></i>
            <span>{{ roleName }}</span>
          </div>

          <div class="pt-3">
            <PrimaryButton
              class="w-full !p-[14px] !bg-[#062121] !text-white border-none rounded-lg font-bold text-sm sm:text-base justify-center transition-all duration-300 hover:-translate-y-[2px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)]"
              :class="{ 'opacity-25': isSubmitting }"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Création en cours...
              </span>
              <span v-else>Finaliser mon inscription</span>
            </PrimaryButton>
          </div>

          <div
            class="text-center my-5 relative before:content-[''] before:absolute before:top-1/2 before:left-0 before:right-0 before:h-[1px] before:bg-[#E2E8F0]"
          >
            <span class="relative bg-white px-[15px] text-[#64748B] text-sm">Ou</span>
          </div>

          <p class="text-center text-[13px] text-[#718096]">
            Déjà inscrit ?
            <RouterLink
              to="/login"
              class="text-[#062121] font-bold no-underline border-b-2 border-[#C5F82A] ml-1"
            >
              Se connecter
            </RouterLink>
          </p>
        </form>
      </div>

      <div
        class="hidden md:flex flex-1 bg-cover bg-center items-center justify-center relative"
        :style="{
          backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.3), rgba(15, 23, 42, 0.3)), url(${loginBackgroundUrl})`,
        }"
      >
        <div class="text-center">
          <h2
            class="text-white text-[32px] font-black tracking-[3px] border-4 border-white p-[15px_25px]"
          >
            BÂTIR. CHANGER.
          </h2>
        </div>
      </div>
    </div>
  </div>
</template>