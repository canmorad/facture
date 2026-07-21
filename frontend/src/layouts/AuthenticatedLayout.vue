<script setup>
import { ref, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { usePermission } from "../composables/usePermission";
import CompanySelect from "../components/CompanySelect.vue";
import logoUrl from "../assets/images/logo.png";
import profile from "../assets/images/profile.png";

const { can } = usePermission();

const router = useRouter();
const sidebarOpen = ref(false);
const authStore = useAuthStore();

const selectedCompany = ref(authStore.currentCompanyId || null);

watch(selectedCompany, (newVal) => {
  if (newVal) authStore.setActiveCompany(Number(newVal));
});

watch(
  () => authStore.currentCompanyId,
  (newId) => {
    if (newId && selectedCompany.value !== newId) {
      selectedCompany.value = newId;
    }
  },
);

const goToCreateCompany = () => {
  router.push({ name: "company", query: { mode: "create" } });
};

onMounted(async () => {
  // Wait for auth to be fully initialized before reloading companies
  // This ensures we don't make API calls before authentication state is confirmed
  await authStore.fetchAuthStatus().catch(() => {})

  if (authStore.isAuthenticated && authStore.companies.length === 0) {
    await authStore.reloadCompanies();
  }
  if (authStore.currentCompanyId) {
    selectedCompany.value = authStore.currentCompanyId;
  } else if (authStore.companies.length > 0) {
    const firstId = authStore.companies[0].id;
    selectedCompany.value = firstId;
    authStore.setActiveCompany(firstId);
  }
});
</script>

<template>
  <div class="flex h-screen bg-gray-50 font-sans antialiased">
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-20 bg-black/50 transition-opacity lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <aside
      :class="[
        'fixed inset-y-0 left-0 z-30 w-72 bg-white border-r border-[#E2E8F0] flex flex-col justify-between h-screen overflow-y-auto position-sticky top-0 transition-transform duration-300 ease-in-out lg:relative lg:translate-x-0 select-none',
        'font-kranky',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
      style="scrollbar-width: thin"
    >
      <main class="flex flex-col flex-1 px-5 py-[30px] gap-[25px]">
        <div class="logo flex items-center h-[50px] pl-[15px] flex-shrink-0">
          <img
            :src="logoUrl"
            alt="Facturex Logo"
            class="h-[120px] max-h-[120px] w-auto object-contain scale-150 origin-left contrast-125 brightness-105 drop-shadow-[0_2px_10px_rgba(197,248,42,0.4)]"
          />
        </div>
        <nav class="flex-shrink-0">
          <p
            class="text-[12px] font-bold text-[#94A3B8] mb-[15px] pl-[15px] tracking-[1.5px] uppercase"
          >
            Menu principal
          </p>
          <div class="flex flex-col gap-[5px]">
            <router-link
              to="/dashboard"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-house text-[18px] w-[22px] text-center"></i>
              Tableau de bord
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/invoices"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-file-invoice-dollar text-[18px] w-[22px] text-center"
              ></i>
              Factures
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/quote"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-file-invoice-dollar text-[18px] w-[22px] text-center"
              ></i>
              Devis
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/proformas"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-file-contract text-[18px] w-[22px] text-center"
              ></i>
              Factures Proforma
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/purchase-orders"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-clipboard-list text-[18px] w-[22px] text-center"
              ></i>
              Bons de commande
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/delivery-notes"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-truck text-[18px] w-[22px] text-center"></i>
              Bons de livraison
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/deposits"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-file-invoice-dollar text-[18px] w-[22px] text-center"
              ></i>
              Factures d'acompte
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/balance-invoices"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-file-invoice text-[18px] w-[22px] text-center"
              ></i>
              Factures de solde
            </router-link>
            <router-link
              v-if="can('manage-recurring-invoices')"
              to="/recurring-invoices"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i
                class="fa-solid fa-rotate text-[18px] w-[22px] text-center"
              ></i>
              Factures récurrentes
            </router-link>
            <router-link
              v-if="can('view-customers')"
              to="/clients"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-users text-[18px] w-[22px] text-center"></i>
              Clients
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/expenses"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-money-bill-wave text-[18px] w-[22px] text-center"></i>
              Dépenses
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/suppliers"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-truck-field text-[18px] w-[22px] text-center"></i>
              Fournisseurs
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/cash-registers"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-cash-register text-[18px] w-[22px] text-center"></i>
              Gestion des Caisses
            </router-link>
            <router-link
              v-if="can('view-documents')"
              to="/bank-remittances"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-money-check-alt text-[18px] w-[22px] text-center"></i>
              Remises Bancaires
            </router-link>
            <router-link
              v-if="can('view-products')"
              to="/products"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-cubes text-[18px] w-[22px] text-center"></i>
              Produits & Services
            </router-link>
          </div>
        </nav>
        <nav class="flex-shrink-0">
          <p
            class="text-[12px] font-bold text-[#94A3B8] mb-[15px] pl-[15px] tracking-[1.5px] uppercase"
          >
            Compte & support
          </p>
          <div class="flex flex-col gap-[5px]">
            <router-link
              v-if="can('view-settings')"
              to="/settings/product-types"
              class="flex items-center gap-[12px] px-[15px] py-[12px] rounded-[12px] text-[17px] font-semibold text-[#64748B] transition-all duration-300 hover:bg-[#F8FAFC] hover:text-[#062121]"
              active-class="!font-bold !bg-[#C5F82A] !text-[#062121] !shadow-[0_4px_12px_rgba(197,248,42,0.2)]"
            >
              <i class="fa-solid fa-gear text-[18px] w-[22px] text-center"></i>
              Paramètres
            </router-link>
            <button
              @click="authStore.logout()"
              class="flex items-center gap-[12px] px-[15px] py-[12px] text-[17px] font-semibold text-[#FF4D4D] transition-all duration-300 mt-5 border-t border-[#E2E8F0] pt-4 rounded-none hover:bg-red-50/50 w-full text-left cursor-pointer border-0 bg-transparent"
            >
              <i
                class="fa-solid fa-right-from-bracket text-[18px] w-[22px] text-center"
              ></i>
              Déconnexion
            </button>
          </div>
        </nav>
      </main>
    </aside>

    <main class="flex-1 overflow-y-auto">
      <header
        class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b border-[#E2E8F0]"
      >
        <div class="flex h-16 items-center justify-between px-6 lg:px-8">
          <button
            @click="sidebarOpen = !sidebarOpen"
            class="rounded-md p-2 text-gray-500 hover:bg-gray-100/70 lg:hidden transition-colors mr-4"
          >
            <svg
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
            </svg>
          </button>

          <div class="flex items-center gap-1.5">
            <CompanySelect
              v-model="selectedCompany"
              :companies="authStore.companies"
            />
            <button
              @click="goToCreateCompany"
              class="flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-[#062121] hover:bg-gray-100 transition-all"
              title="Ajouter une entreprise"
            >
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"
                />
              </svg>
            </button>
          </div>

          <div class="flex items-center gap-3 ml-auto">
            <router-link
              to="/settings"
              class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-[#062121] transition-all"
              title="Paramètres"
            >
              <i class="fa-solid fa-gear text-[17px]"></i>
            </router-link>

            <button
              class="relative flex items-center justify-center w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-[#062121] transition-all"
              title="Notifications"
            >
              <i class="fa-solid fa-bell text-[17px]"></i>
            </button>

            <div class="h-6 w-[1px] bg-[#E2E8F0] mx-1 hidden sm:block"></div>

            <div class="flex items-center gap-3 select-none">
              <div
                class="relative w-10 h-10 rounded-full bg-gray-100/80 border border-[#E2E8F0] p-1 flex items-center justify-center shadow-sm overflow-hidden group-hover:border-gray-300 transition-colors"
              >
                <img
                  :src="profile"
                  class="w-full h-full object-contain"
                  alt="User Avatar"
                />
              </div>

              <div class="hidden md:flex flex-col text-left leading-tight">
                <span class="text-sm font-semibold text-gray-800">
                  {{ authStore.user?.name || "Utilisateur" }}
                </span>
                <span class="text-xs text-gray-400">
                  {{ authStore.user?.email || "user@gmail.com" }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </header>

      <slot />
    </main>
  </div>
</template>
