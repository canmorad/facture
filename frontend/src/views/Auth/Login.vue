<script setup>
import { ref, reactive } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import InputError from "../../components/InputError.vue";
import InputLabel from "../../components/InputLabel.vue";
import PrimaryButton from "../../components/PrimaryButton.vue";
import TextInput from "../../components/TextInput.vue";
import Checkbox from "../../components/Checkbox.vue";

import logoUrl from "../../assets/images/logo.png";
import loginBackgroundUrl from "../../assets/images/login-background.png";

defineProps({
  canResetPassword: {
    type: Boolean,
    default: true,
  },
  status: {
    type: String,
    default: "",
  },
});

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  email: "",
  password: "",
  remember: false,
});

const errors = reactive({
  email: "",
  password: "",
});

const processing = ref(false);
const showPassword = ref(false);

const submit = async () => {
  processing.value = true;
  Object.keys(errors).forEach((key) => (errors[key] = ""));

  try {
    await axios.get("/sanctum/csrf-cookie");
    const response = await axios.post("/login", form);
    authStore.setAuthData(response.data);

    if (!authStore.emailVerified) {
      router.push({ name: "verify-email" });
    } else if (!authStore.hasCompany) {
      router.push({ name: "settings.coordinates" });
    } else {
      router.push({ name: "clients" });
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      const validationErrors = error.response.data.errors;
      Object.keys(validationErrors).forEach((key) => {
        if (errors[key] !== undefined) {
          errors[key] = validationErrors[key][0];
        }
      });
    } else {
      console.error("Une erreur est survenue lors de la connexion:", error);
    }
  } finally {
    processing.value = false;
    form.password = "";
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
        <div class="mb-[35px]">
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
            Bon retour !
          </h1>
          <p class="text-[#64748B] text-sm">
            Connectez-vous pour accéder à votre espace.
          </p>
        </div>

        <div
          v-if="status"
          class="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-lg border border-green-200"
        >
          {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <InputLabel for="email" value="E-mail" />
            <TextInput
              id="email"
              type="email"
              class="mt-1 block w-full"
              v-model="form.email"
              placeholder="exemple@mail.com"
              required
              autofocus
              autocomplete="username"
            />
            <InputError class="mt-2" :message="errors.email" />
          </div>

          <div>
            <div class="flex justify-between items-center mb-[6px]">
              <InputLabel
                for="password"
                value="Mot de passe"
                class="block text-xs sm:text-[13px] font-semibold text-[#062121]"
              />
              <a
                v-if="canResetPassword"
                href="/password/reset"
                class="text-xs font-semibold text-[#062121] no-underline hover:underline"
              >
                Mot de passe oublié ?
              </a>
            </div>

            <div class="relative mt-1">
              <TextInput
                id="password"
                :type="showPassword ? 'text' : 'password'"
                class="mt-1 block w-full"
                v-model="form.password"
                placeholder="••••••••"
                required
                autocomplete="current-password"
              />
              <span
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#A0AEC0] cursor-pointer z-10"
              >
                <i
                  :class="
                    showPassword
                      ? 'fa-regular fa-eye'
                      : 'fa-regular fa-eye-slash'
                  "
                ></i>
              </span>
            </div>
            <InputError class="mt-2" :message="errors.password" />
          </div>

          <div class="flex items-center gap-[10px] my-5">
            <Checkbox id="remember" v-model:checked="form.remember" />

            <label
              for="remember"
              class="text-[13px] text-[#4A5568] cursor-pointer select-none"
            >
              Se souvenir de moi
            </label>
          </div>

          <div class="pt-2">
            <PrimaryButton
              class="w-full !p-[14px] !bg-[#062121] !text-white border-none rounded-lg font-bold text-sm sm:text-base justify-center transition-all duration-300 hover:-translate-y-[2px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)]"
              :class="{ 'opacity-25': processing }"
              :disabled="processing"
            >
              Se connecter
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
            Nouveau ici ?

            <RouterLink
              to="/register"
              class="text-[#062121] font-bold no-underline border-b-2 border-[#C5F82A] ml-1"
            >
              Créer un compte
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