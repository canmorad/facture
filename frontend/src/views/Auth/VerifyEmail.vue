<script setup>
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";
import { useAuthStore } from "@/stores/auth";

import PrimaryButton from "../../components/PrimaryButton.vue";
import logoUrl from "../../assets/images/logo.png";
import loginBackgroundUrl from "../../assets/images/login-background.png";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const resending = ref(false);
const verificationStatus = ref(null);

const checkVerifiedFromQuery = () => {
  if (route.query.verified === "1") {
    refreshUserStatus();
  }
};

const refreshUserStatus = async () => {
  try {
    const response = await axios.get("/api/user-status");
    authStore.setAuthData(response.data);

    if (authStore.emailVerified) {
      if (!authStore.hasCompany) {
        router.push({ name: "settings.coordinates" });
      } else {
        router.push({ name: "dashboard" });
      }
    }
  } catch {
    // Stay on page
  }
};

const resendVerification = async () => {
  resending.value = true;
  verificationStatus.value = null;

  try {
    await axios.post("/api/email/verification-notification");
    verificationStatus.value = {
      type: "success",
      message:
        "Un nouvel email de vérification a été envoyé. Veuillez vérifier votre boîte de réception.",
    };
  } catch (error) {
    verificationStatus.value = {
      type: "error",
      message: "Une erreur est survenue. Veuillez réessayer plus tard.",
    };
    console.error("Erreur lors du renvoi de l'email:", error);
  } finally {
    resending.value = false;
  }
};

const logout = async () => {
  try {
    await axios.post("/api/logout");
    authStore.clearAuth();
    router.push({ name: "login" });
  } catch (error) {
    console.error("Erreur lors de la déconnexion:", error);
  }
};

onMounted(() => {
  checkVerifiedFromQuery();

  if (authStore.emailVerified) {
    if (!authStore.hasCompany) {
      router.push({ name: "settings.coordinates" });
    } else {
      router.push({ name: "dashboard" });
    }
  }
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
          <div
            class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mb-6"
          >
            <i class="fas fa-check-circle text-4xl text-green-600"></i>
          </div>
          <h1 class="text-[#062121] text-2xl sm:text-[28px] font-extrabold mb-2">
            Vérifiez votre email
          </h1>
          <p class="text-[#64748B] text-sm max-w-sm">
            Un lien de vérification a été envoyé à votre adresse email. Veuillez
            cliquer sur le lien pour activer votre compte.
          </p>
        </div>

        <div
          v-if="verificationStatus"
          class="mb-6 p-3 rounded-lg text-sm"
          :class="{
            'bg-green-50 text-green-700 border border-green-200':
              verificationStatus.type === 'success',
            'bg-red-50 text-red-700 border border-red-200':
              verificationStatus.type === 'error',
          }"
        >
          {{ verificationStatus.message }}
        </div>

        <form @submit.prevent="resendVerification" class="space-y-[22px]">
          <PrimaryButton
            class="w-full !p-[14px] !bg-[#062121] !text-white border-none rounded-lg font-bold text-sm sm:text-base justify-center transition-all duration-300 hover:-translate-y-[2px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)]"
            :class="{ 'opacity-50 cursor-not-allowed': resending }"
            :disabled="resending"
          >
            <span v-if="resending">Envoi en cours...</span>
            <span v-else>Renvoyer l'email de vérification</span>
          </PrimaryButton>

          <div
            class="text-center my-5 relative before:content-[''] before:absolute before:top-1/2 before:left-0 before:right-0 before:h-[1px] before:bg-[#E2E8F0]"
          >
            <span class="relative bg-white px-[15px] text-[#64748B] text-sm"
              >Ou</span
            >
          </div>

          <p class="text-center text-[13px] text-[#718096]">
            <button
              @click="logout"
              class="text-[#062121] font-bold no-underline border-b-2 border-[#C5F82A]"
            >
              Se déconnecter
            </button>
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