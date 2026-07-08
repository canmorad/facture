<script setup>
import { useRouter } from "vue-router";

const props = defineProps({
  activities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const router = useRouter();

const formatDate = (datetime) => {
  const d = new Date(datetime);
  return d.toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" }) +
    " à " + d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
};

const getActionIcon = (event) => {
  const icons = { created: "fa-plus", updated: "fa-pen", deleted: "fa-trash", finalized: "fa-check", signed: "fa-file-signature", sent: "fa-paper-plane" };
  return icons[event] || "fa-circle";
};

const getActionColor = (event) => {
  const colors = { created: "bg-emerald-500", updated: "bg-blue-500", deleted: "bg-red-500", finalized: "bg-green-600", signed: "bg-purple-500", sent: "bg-sky-500" };
  return colors[event] || "bg-gray-400";
};

const getActivityText = (activity) => {
  if (activity.description) return activity.description;
  const subjectTitle = activity.subject_title || "";
  const subjectType = activity.subject_type ? activity.subject_type.split("\\").pop() : "";
  if (activity.event === "created") return "Nouveau " + subjectType + " : " + subjectTitle;
  if (activity.event === "updated") return subjectType + " modifié : " + subjectTitle;
  if (activity.event === "deleted") return subjectType + " supprimé : " + subjectTitle;
  return activity.description || "Activité enregistrée";
};

const getSubjectLink = (activity) => {
  if (!activity.subject || !activity.subject.type) return null;
  const type = activity.subject.type;
  const id = activity.subject.id;
  const routes = {
    Quote: "/quote",
    Invoice: "/invoices",
    DeliveryNote: "/delivery-notes",
    PurchaseOrder: "/purchase-orders",
    Deposit: "/deposits",
    CreditNote: "/credit-notes",
    Document: "/document/preview/" + id,
  };
  return routes[type] || null;
};

const navigateToSubject = (activity) => {
  const link = getSubjectLink(activity);
  if (link) router.push(link);
};
</script>

<template>
  <div v-if="loading" class="text-center py-12">
    <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
    <p class="mt-2 text-gray-500">Chargement des activités...</p>
  </div>

  <div v-else-if="activities.length === 0" class="text-center py-12">
    <i class="fas fa-inbox text-5xl text-gray-300 mb-4 block"></i>
    <p class="text-gray-500">Aucune activité récente.</p>
  </div>

  <div v-else class="relative">
    <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-100"></div>
    <div v-for="activity in activities" :key="activity.id" class="relative flex items-start gap-4 pb-6 last:pb-0">
      <div class="relative z-10 flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white text-sm shadow-sm" :class="getActionColor(activity.event)">
        <i :class="['fas', getActionIcon(activity.event)]"></i>
      </div>
      <div class="flex-1 min-w-0 pt-1">
        <div class="text-sm text-gray-800 leading-relaxed" :class="{ 'cursor-pointer hover:text-[#062121] hover:underline': getSubjectLink(activity) }" @click="navigateToSubject(activity)">
          {{ getActivityText(activity) }}
        </div>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-xs text-gray-400">{{ formatDate(activity.created_at) }}</span>
          <span class="text-xs text-gray-300">·</span>
          <span class="text-xs text-gray-500 font-medium">{{ activity.user_name }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
