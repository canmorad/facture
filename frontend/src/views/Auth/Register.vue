<script setup>
import { ref, reactive } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import TextInput from "../../components/TextInput.vue";
import logoUrl from "../../assets/images/logo.png";
import loginBackgroundUrl from "../../assets/images/login-background.png";

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
});

const errors = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  server: "",
});

const processing = ref(false);

const submit = async () => {
  processing.value = true;
  Object.keys(errors).forEach((key) => (errors[key] = ""));

  try {
    await axios.get("/sanctum/csrf-cookie");
    const response = await axios.post("/register", form);
    authStore.setAuthData(response.data);

    // After registration, email is not yet verified, so redirect to verify-email
    router.push({ name: "verify-email" });
  } catch (error) {
    if (error.response && error.response.status === 422) {
      const validationErrors = error.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        errors[key] = validationErrors[key][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'inscription.";
    }
  } finally {
    processing.value = false;
    form.password = "";
    form.password_confirmation = "";
  }
};
</script>

<template>
  <div
    class="min-h-screen bg-[#F5F0E8] flex items-center justify-center p-4 md:p-10 font-['Inter',sans-serif]"
  >
    <div
      class="bg-white w-full max-w-[1000px] flex rounded-[24px] overflow-hidden shadow-[0_15px_35px_rgba(0,0,0,0.08)]"
    >
      <div class="flex-1 p-6 sm:p-10 md:p-[60px]">
        <div class="mb-[30px]">
          <div class="mb-5 flex items-center h-[50px]">
            <img
              :src="logoUrl"
              alt="Logo"
              class="h-[120px] max-h-[120px] w-auto object-contain scale-150 origin-left contrast-125 brightness-105 drop-shadow-[0_2px_10px_rgba(197,248,42,0.4)]"
            />
          </div>
          <h1
            class="text-[#062121] text-2xl sm:text-[28px] font-extrabold mb-2"
          >
            Créer un compte
          </h1>
          <p class="text-[#64748B] text-sm">
            Remplissez les informations pour vous inscrire.
          </p>

          <InputError class="mt-2" :message="errors.server" />
        </div>

        <form @submit.prevent="submit" class="space-y-[18px]">
          <div>
            <InputLabel for="name" value="Nom complet" />
            <TextInput
              id="name"
              type="text"
              class="mt-1 block w-full"
              v-model="form.name"
              placeholder="Votre nom"
              required
              autofocus
              autocomplete="name"
            />
            <InputError class="mt-2" :message="errors.name" />
          </div>

          <div>
            <InputLabel
              for="email"
              value="E-mail"
              class="block text-xs sm:text-[13px] font-semibold text-[#062121] mb-[6px]"
            />
            <TextInput
              id="email"
              type="email"
              class="mt-1 block w-full"
              v-model="form.email"
              placeholder="exemple@mail.com"
              required
              autocomplete="username"
            />
            <InputError class="mt-2" :message="errors.email" />
          </div>

          <div>
            <InputLabel
              for="password"
              value="Mot de passe"
              class="block text-xs sm:text-[13px] font-semibold text-[#062121] mb-[6px]"
            />
            <TextInput
              id="password"
              type="password"
              class="mt-1 block w-full"
              v-model="form.password"
              placeholder="••••••••"
              required
              autocomplete="new-password"
            />
            <InputError class="mt-2" :message="errors.password" />
          </div>

          <div>
            <InputLabel
              for="password_confirmation"
              value="Confirmer le mot de passe"
              class="block text-xs sm:text-[13px] font-semibold text-[#062121] mb-[6px]"
            />
            <TextInput
              id="password_confirmation"
              type="password"
              class="mt-1 block w-full"
              v-model="form.password_confirmation"
              placeholder="••••••••"
              required
              autocomplete="new-password"
            />
            <InputError class="mt-2" :message="errors.password_confirmation" />
          </div>

          <div class="pt-2">
            <PrimaryButton
              class="w-full !p-[14px] !bg-[#062121] !text-white border-none rounded-lg font-bold text-sm sm:text-base justify-center transition-all duration-300 hover:-translate-y-[2px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)]"
              :class="{ 'opacity-25': processing }"
              :disabled="processing"
            >
              S'inscrire
            </PrimaryButton>
          </div>

          <div
            class="text-center my-4 relative before:content-[''] before:absolute before:top-1/2 before:left-0 before:right-0 before:h-[1px] before:bg-[#E2E8F0]"
          >
            <span class="relative bg-white px-[15px] text-[#64748B] text-sm"
              >Ou</span
            >
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