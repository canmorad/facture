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
  const icons = {
    created: "fa-plus",
    updated: "fa-pen",
    deleted: "fa-trash",
    finalized: "fa-check",
    signed: "fa-file-signature",
    sent: "fa-paper-plane",
    paid: "fa-money-bill-wave",
    validated: "fa-shield-check",
    confirmed: "fa-thumbs-up",
    delivered: "fa-truck-fast",
    applied: "fa-check-double",
  };
  return icons[event] || "fa-circle";
};

const getActionColor = (event) => {
  const colors = {
    created: "bg-emerald-100 text-emerald-700 border-emerald-200",
    updated: "bg-blue-50 text-blue-700 border-blue-200",
    deleted: "bg-red-50 text-red-700 border-red-200",
    finalized: "bg-green-50 text-green-700 border-green-200",
    signed: "bg-purple-50 text-purple-700 border-purple-200",
    sent: "bg-sky-50 text-sky-700 border-sky-200",
    paid: "bg-teal-50 text-teal-700 border-teal-200",
    validated: "bg-indigo-50 text-indigo-700 border-indigo-200",
    confirmed: "bg-lime-50 text-lime-700 border-lime-200",
    delivered: "bg-amber-50 text-amber-700 border-amber-200",
    applied: "bg-cyan-50 text-cyan-700 border-cyan-200",
  };
  return colors[event] || "bg-slate-50 text-slate-700 border-slate-200";
};

const getActivityText = (activity) => {
  // Use the backend description directly (now includes HTML markup)
  if (activity.description) return activity.description;

  const subjectTitle = activity.subject_title || "";
  const subjectType = activity.subject_type ? getActivityTypeLabel(activity.subject_type.split("\\").pop()) : "";
  const userName = activity.user_name || "Système";

  const eventLabels = {
    created: "a créé",
    updated: "a modifié",
    deleted: "a supprimé",
    finalized: "a finalisé",
    sent: "a envoyé",
    signed: "a signé",
    paid: "a marqué comme payé",
    validated: "a validé",
    confirmed: "a confirmé",
    delivered: "a livré",
    applied: "a appliqué",
    cancelled: "a annulé",
    expired: "a marqué comme expiré",
    converted: "a converti",
  };

  const eventLabel = eventLabels[activity.event] || activity.event;

  return `${userName} ${eventLabel} ${subjectType} : ${subjectTitle}`;
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
    Proforma: "/proformas",
    BalanceInvoice: "/balance-invoices",
    RecurringInvoice: "/recurring-invoices",
    PurchaseInvoice: "/suppliers",
    Document: "/document/preview/" + id,
  };
  return routes[type] || null;
};

const navigateToSubject = (activity) => {
  const link = getSubjectLink(activity);
  if (link) router.push(link);
};

const getHighlightedText = (activity) => {
  // The backend now provides properly formatted HTML with French names and blue highlighting
  // Use it directly if available
  const text = getActivityText(activity);

  // Check if text already contains HTML markup (from backend)
  if (text.includes('<span')) {
    return text;
  }

  // Fallback for old activities without HTML formatting
  const subjectTitle = activity.subject_title || "";

  // Highlight subject title (document number, entity name, etc.)
  if (subjectTitle && text.includes(subjectTitle)) {
    const parts = text.split(subjectTitle);
    return parts.map((part, index) => {
      if (index === parts.length - 1) return part;
      return `${part}<span class="font-semibold text-blue-600">${subjectTitle}</span>`;
    }).join('');
  }

  // Try to find number pattern like #49, #FA2026-001, etc.
  const numberMatch = text.match(/(#[\w-]+)/);
  if (numberMatch) {
    const number = numberMatch[1];
    return text.replace(number, `<span class="font-semibold text-blue-600">${number}</span>`);
  }

  return text;
};

const getActivityTypeLabel = (subjectType) => {
  const labels = {
    Document: "Document",
    Quote: "Devis",
    Invoice: "Facture",
    Proforma: "Proforma",
    BalanceInvoice: "Facture de solde",
    DeliveryNote: "Bon de livraison",
    PurchaseOrder: "Bon de commande",
    Deposit: "Acompte",
    CreditNote: "Avoir",
    RecurringInvoice: "Facture récurrente",
    PurchaseInvoice: "Facture d'achat",
    Expense: "Dépense",
    Customer: "Client",
    B2bCustomer: "Client (Professionnel)",
    B2cCustomer: "Client (Particulier)",
    Fournisseur: "Fournisseur",
    Supplier: "Fournisseur",
    Product: "Produit",
    ProductCategory: "Catégorie de produit",
    Company: "Entreprise",
    Payment: "Paiement",
    PaymentDocument: "Document de paiement",
    BankRemittance: "Remise bancaire",
    BankAccount: "Compte bancaire",
    CashRegister: "Caisse",
    CashRegisterSession: "Session de caisse",
    CashTransaction: "Transaction de caisse",
    TaxRate: "Taux de TVA",
    PaymentMode: "Mode de paiement",
    PaymentCondition: "Condition de paiement",
    LateFeeInterest: "Intérêts de retard",
    User: "Utilisateur",
  };
  return labels[subjectType] || subjectType;
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
    <div class="absolute left-[18px] top-0 bottom-0 w-px bg-gradient-to-b from-transparent via-slate-100 to-transparent"></div>
    <div v-for="activity in activities" :key="activity.id" class="relative flex items-start gap-4 pb-6 last:pb-0">
      <div class="relative z-10 flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-sm border shadow-sm" :class="getActionColor(activity.event)">
        <i :class="['fas', getActionIcon(activity.event)]"></i>
      </div>
      <div class="flex-1 min-w-0 pt-1.5">
        <div
          class="text-sm text-gray-700 leading-relaxed transition-colors"
          :class="{ 'cursor-pointer hover:text-[#062121]': getSubjectLink(activity) }"
          @click="navigateToSubject(activity)"
          v-html="getHighlightedText(activity)"
        ></div>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-xs text-slate-400">{{ formatDate(activity.created_at) }}</span>
          <span class="text-xs text-slate-300">·</span>
          <span class="text-xs text-slate-500 font-medium">{{ activity.user_name }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
