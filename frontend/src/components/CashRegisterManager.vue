<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { paymentApi } from "@/services/paymentApi";
import { success, error } from "@/helpers/notifications";

const emit = defineEmits(["session-opened", "session-closed"]);

const isLoading = ref(false);
const cashRegisters = ref([]);
const activeSessions = ref([]);
const creationData = ref(null);

const hasOpenSession = computed(() => {
  return activeSessions.value && activeSessions.value.length > 0;
});

const activeSessionCount = computed(() => {
  return activeSessions.value?.length || 0;
});

const totalBalance = computed(() => {
  if (!activeSessions.value || activeSessions.value.length === 0) return 0;
  return activeSessions.value.reduce((sum, session) => {
    return sum + (session.cashRegister?.current_balance || 0);
  }, 0);
});

const fetchCreationData = async () => {
  isLoading.value = true;
  try {
    const data = await paymentApi.getCreationData();
    creationData.value = data;
    cashRegisters.value = data.cash_registers || [];
    activeSessions.value = Object.values(data.active_sessions || {});
  } catch (err) {
    console.error("Error fetching cash register data:", err);
    error(
      "Erreur",
      "Impossible de charger les données des caisses."
    );
  } finally {
    isLoading.value = false;
  }
};

const openSession = async (cashRegisterId) => {
  if (!cashRegisterId) {
    const defaultRegister = cashRegisters.value.find((cr) => cr.is_default);
    if (!defaultRegister) {
      error("Erreur", "Aucune caisse par défaut configurée.");
      return;
    }
    cashRegisterId = defaultRegister.id;
  }

  isLoading.value = true;
  try {
    const openingBalance = cashRegisters.value.find(
      (cr) => cr.id === cashRegisterId
    )?.current_balance || 0;

    await axios.post(`/api/cash-registers/${cashRegisterId}/open-session`, {
      opening_balance: openingBalance,
      notes: "Ouverture via gestion des paiements",
    });

    success(
      "Session ouverte !",
      "La session de caisse a été ouverte avec succès."
    );

    emit("session-opened");
    await fetchCreationData();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible d'ouvrir la session de caisse."
    );
  } finally {
    isLoading.value = false;
  }
};

const closeSession = async (sessionId) => {
  const session = activeSessions.value.find((s) => s.id === sessionId);
  if (!session) return;

  isLoading.value = true;
  try {
    const expectedBalance = session.calculateExpectedClosingBalance
      ? session.calculateExpectedClosingBalance()
      : session.expected_closing_balance || session.cashRegister?.current_balance || 0;

    await axios.post(`/api/cash-registers/${session.cash_register_id}/close-session`, {
      actual_closing_balance: expectedBalance,
      notes: "Clôture via gestion des paiements",
    });

    success(
      "Session clôturée !",
      "La session de caisse a été clôturée avec succès."
    );

    emit("session-closed");
    await fetchCreationData();
  } catch (err) {
    error(
      "Erreur",
      err.response?.data?.message || "Impossible de clôturer la session de caisse."
    );
  } finally {
    isLoading.value = false;
  }
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

const openFirstSession = () => {
  if (cashRegisters.value.length === 0) {
    error("Erreur", "Aucune caisse configurée.");
    return;
  }

  const defaultRegister = cashRegisters.value.find((cr) => cr.is_default);
  if (defaultRegister) {
    openSession(defaultRegister.id);
  } else if (cashRegisters.value.length === 1) {
    openSession(cashRegisters.value[0].id);
  } else {
    openSession(cashRegisters.value[0].id);
  }
};

onMounted(() => fetchCreationData());

defineExpose({
  refresh: fetchCreationData,
  hasOpenSession,
  activeSessions,
});
</script>

<template>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-900">
        <i class="fas fa-cash-register mr-2"></i>
        Gestion des Caisses
      </h3>
      <div class="flex items-center gap-2">
        <span
          v-if="!isLoading"
          class="text-xs px-2 py-1 rounded-full"
          :class="
            hasOpenSession
              ? 'bg-green-100 text-green-700'
              : 'bg-yellow-100 text-yellow-700'
          "
        >
          <i
            :class="hasOpenSession ? 'fa-check-circle' : 'fa-exclamation-circle'"
            class="fas mr-1"
          ></i>
          {{ activeSessionCount }} session{{ activeSessionCount > 1 ? 's' : '' }} ouverte{{ activeSessionCount > 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <div v-if="isLoading" class="p-4 text-center">
      <i class="fas fa-spinner fa-spin text-gray-400"></i>
      <p class="text-sm text-gray-500 mt-2">Chargement...</p>
    </div>

    <div v-else class="p-4">
      <div
        v-if="hasOpenSession"
        class="space-y-3"
      >
        <div
          v-for="session in activeSessions"
          :key="session.id"
          class="bg-green-50 rounded-lg p-4 border border-green-200"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-medium text-green-800">
                <i class="fas fa-lock-open mr-2"></i>
                {{ session.cashRegister?.name || `Caisse #${session.cash_register_id}` }}
              </p>
              <p class="text-xs text-green-600 mt-1">
                Ouverte le {{ new Date(session.opened_at).toLocaleDateString("fr-MA") }}
                à {{ new Date(session.opened_at).toLocaleTimeString("fr-MA", { hour: '2-digit', minute: '2-digit' }) }}
              </p>
            </div>
            <button
              @click="closeSession(session.id)"
              class="px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-100 rounded-lg transition-colors"
            >
              <i class="fas fa-lock mr-1"></i>
              Clôturer
            </button>
          </div>

          <div class="mt-3 pt-3 border-t border-green-200">
            <p class="text-xs text-green-600">Solde actuel</p>
            <p class="text-2xl font-bold text-green-900">
              {{ formatCurrency(session.cashRegister?.current_balance || 0) }}
            </p>
          </div>
        </div>

        <div v-if="activeSessions.length > 1" class="text-center pt-2">
          <p class="text-sm text-gray-600">
            Total des caisses: <span class="font-bold text-gray-900">{{ formatCurrency(totalBalance) }}</span>
          </p>
        </div>
      </div>

      <div v-else class="text-center py-6">
        <i
          class="fas fa-cash-register text-gray-300 text-4xl mb-3 block"
        ></i>
        <p class="text-sm text-gray-600 mb-4">
          Aucune session de caisse ouverte
        </p>
        <p class="text-xs text-gray-500 mb-4">
          Les paiements en espèces nécessitent une session ouverte
        </p>
        <button
          @click="openFirstSession"
          class="inline-flex items-center gap-2 px-4 py-2 bg-[#0F172A] text-white text-sm font-medium rounded-lg hover:bg-[#1a2744] transition-colors"
        >
          <i class="fas fa-lock-open"></i>
          Ouvrir une Session
        </button>
      </div>
    </div>

    <div
      v-if="!isLoading && cashRegisters.length === 0"
      class="p-4 text-center bg-yellow-50 border-t border-yellow-200"
    >
      <p class="text-sm text-yellow-800">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Aucune caisse configurée. Contactez l'administrateur.
      </p>
    </div>
  </div>
</template>