<script setup>
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";
import { useAuthStore } from "@/stores/auth";

import PrimaryButton from "../../components/PrimaryButton.vue";
import logoUrl from "../../assets/images/logo.png";

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
    await axios.post("/email/verification-notification");
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
    await axios.post("/logout");
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
      class="bg-white w-full max-w-[600px] rounded-[24px] overflow-hidden shadow-[0_15px_35px_rgba(0,0,0,0.08)] p-6 sm:p-10 md:p-[60px]"
    >
      <div class="text-center mb-8">
        <div class="flex justify-center mb-5">
          <img
            :src="logoUrl"
            alt="Logo"
            class="h-[120px] w-auto object-contain contrast-125 brightness-105 drop-shadow-[0_2px_10px_rgba(197,248,42,0.4)]"
          />
        </div>

        <div
          class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6"
        >
          <svg
            class="w-10 h-10 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            ></path>
          </svg>
        </div>

        <h1 class="text-[#062121] text-2xl sm:text-[28px] font-extrabold mb-2">
          Vérifiez votre email
        </h1>
        <p class="text-[#64748B] text-sm max-w-sm mx-auto">
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

      <div class="space-y-4">
        <PrimaryButton
          @click="resendVerification"
          class="w-full !p-[14px] !bg-[#062121] !text-white border-none rounded-lg font-bold text-sm sm:text-base justify-center transition-all duration-300 hover:-translate-y-[2px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)]"
          :class="{ 'opacity-50 cursor-not-allowed': resending }"
          :disabled="resending"
        >
          <span v-if="resending">Envoi en cours...</span>
          <span v-else>Renvoyer l'email de vérification</span>
        </PrimaryButton>

        <div class="text-center text-sm text-[#718096]">
          <button
            @click="logout"
            class="text-[#64748B] hover:text-[#062121] transition-colors"
          >
            Se déconnecter
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped></style>