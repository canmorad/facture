<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <div v-if="isLoading" class="text-center py-12">
            <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-2 text-gray-500">Chargement du document...</p>
          </div>

          <div v-else>
            <div class="px-6 pt-4 pb-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <button class="pb-3 text-sm font-bold transition-colors flex items-center gap-2 text-[#062121] border-b-2 border-[#C5F82A]">
                  <i :class="docIcon"></i>
                  {{ docLabel }} {{ document.number || 'Brouillon' }}
                </button>
                <div class="relative">
                  <button
                    @click="toggleDropdown"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200"
                  >
                    <i class="fas fa-ellipsis-v text-sm"></i>
                  </button>
                  <div
                    v-if="showDropdown"
                    v-click-outside="closeDropdown"
                    class="absolute right-0 top-full mt-1 z-50 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1"
                  >
                    <button
                      v-for="btn in actionButtons"
                      :key="btn.key"
                      @click="btn.action(); closeDropdown()"
                      :disabled="btn.disabled"
                      class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <i :class="[btn.icon, 'w-4 text-center']" :style="{ color: btn.color }"></i>
                      {{ btn.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-6 lg:p-8 space-y-5">

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-info-circle text-gray-400"></i> Informations
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Statut</span>
                    <span class="font-semibold" :style="{ color: statusBadgeColor }">{{ statusLabel }}</span>
                  </div>
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Créé le</span>
                    <span class="text-gray-800">{{ fmtDate(document.created_at) }}</span>
                  </div>
                  <div v-if="document.finalized_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Finalisé le</span>
                    <span class="text-gray-800">{{ fmtDate(document.finalized_at) }}</span>
                  </div>
                  <div v-if="document.sent_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Envoyé le</span>
                    <span class="text-gray-800">{{ fmtDate(document.sent_at) }}</span>
                  </div>
                  <div v-if="document.due_date" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">{{ dueDateLabel }}</span>
                    <span class="text-gray-800">{{ fmtDate(document.due_date) }}</span>
                  </div>
                  <div v-if="document.valid_until" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Valide jusqu'au</span>
                    <span class="text-gray-800">{{ fmtDate(document.valid_until) }}</span>
                  </div>
                  <div v-if="document.paid_at" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Payé le</span>
                    <span class="text-gray-800">{{ fmtDate(document.paid_at) }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-user text-gray-400"></i> Destinataire
                </h3>
                <div v-if="document.customer" class="text-sm text-gray-700 space-y-1.5">
                  <div class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Nom</span>
                    <span class="font-semibold text-gray-800">{{ document.customer.name }}</span>
                  </div>
                  <div v-if="document.customer.email" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Email</span>
                    <span class="text-gray-800">{{ document.customer.email }}</span>
                  </div>
                  <div v-if="document.customer.phone" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Téléphone</span>
                    <span class="text-gray-800">{{ document.customer.phone }}</span>
                  </div>
                  <div v-if="customerAddressLine" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-28">Adresse</span>
                    <span class="text-gray-800">{{ customerAddressLine }}</span>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400 italic">Aucun client</div>
              </div>

              <div v-if="allRelatedDocs.length > 0">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-link text-gray-400"></i> Documents liés ({{ allRelatedDocs.length }})
                </h3>
                <div class="divide-y divide-gray-100 border border-gray-100 rounded-lg">
                  <div
                    v-for="doc in allRelatedDocs"
                    :key="doc.id"
                    class="flex items-center px-4 py-2.5"
                  >
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full mr-3"
                      :style="{ backgroundColor: getDocTypeColor(doc.documentable_type) + '15', color: getDocTypeColor(doc.documentable_type) }"
                    >
                      {{ getDocTypeLabel(doc.documentable_type) }}
                    </span>
                    <button
                      @click="goToDocument(doc.id)"
                      class="font-medium text-sm text-gray-800 hover:text-[#062121] hover:underline cursor-pointer"
                    >
                      {{ doc.number || 'Brouillon' }}
                    </button>
                    <span v-if="doc.status" class="text-xs text-gray-500 ml-2">({{ getStatusText(doc.status) }})</span>
                    <span class="text-xs text-gray-400 mx-4">{{ doc.customer?.name || '—' }}</span>
                    <span class="text-xs text-gray-400">{{ fmt(doc.total_ttc) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="document.payment_condition || document.payment_mode || document.late_fee_interest">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-handshake text-gray-400"></i> Conditions
                </h3>
                <div class="text-sm text-gray-700 space-y-1.5">
                  <div v-if="document.payment_condition" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Conditions de règlement</span>
                    <span class="text-gray-800">{{ document.payment_condition }}</span>
                  </div>
                  <div v-if="document.payment_mode" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Mode de règlement</span>
                    <span class="text-gray-800">{{ document.payment_mode }}</span>
                  </div>
                  <div v-if="document.late_fee_interest" class="flex gap-3">
                    <span class="text-gray-500 font-medium w-40">Intérêts de retard</span>
                    <span class="text-gray-800">{{ document.late_fee_interest }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-list-ul text-gray-400"></i> Détail
                </h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Type</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Description</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Prix unit. HT</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-[#062121] uppercase tracking-wider w-14">Qté</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-[#062121] uppercase tracking-wider w-14">TVA</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total HT</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr v-for="(item, i) in document.items" :key="i" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-2.5 text-gray-700">{{ item.product_type || '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ item.description }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-700">{{ fmt(item.unit_price) }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ item.quantity }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ item.tax_rate }}%</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-800">{{ fmt(item.total_ht) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-calculator text-gray-400"></i> Totaux
                </h3>
                <div class="max-w-sm bg-gray-50 rounded-lg border border-gray-100 p-4 space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-500">Total HT</span>
                    <span class="font-medium text-gray-800">{{ fmt(document.total_ht) }}</span>
                  </div>
                  <div v-if="document.global_discount_amount > 0" class="flex justify-between">
                    <span class="text-red-500">Remise ({{ document.global_discount_value }}%)</span>
                    <span class="font-medium text-red-500">- {{ fmt(document.global_discount_amount) }}</span>
                  </div>
                  <div v-if="document.global_discount_amount > 0" class="flex justify-between">
                    <span class="text-gray-500">Total HT final</span>
                    <span class="font-medium text-gray-800">{{ fmt(document.total_ht - document.global_discount_amount) }}</span>
                  </div>
                  <div class="flex justify-between border-t border-gray-200 pt-1.5">
                    <span class="text-gray-500">TVA</span>
                    <span class="font-medium text-gray-800">{{ fmt(document.total_tva) }}</span>
                  </div>
                  <div class="flex justify-between items-center pt-1.5 border-t-2 border-[#C5F82A]">
                    <span class="text-base font-bold text-[#062121]">Total TTC</span>
                    <span class="text-base font-black text-[#062121]">{{ fmt(document.total_ttc) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="document.intro_text || document.conclusion_text || document.footer_text || document.terms || document.notes">
                <h3 class="text-sm font-bold text-[#062121] mb-3 flex items-center gap-2">
                  <i class="fas fa-file-alt text-gray-400"></i> Textes
                </h3>
                <div class="space-y-3 text-sm text-gray-700">
                  <div v-if="document.intro_text">
                    <p class="whitespace-pre-line">{{ document.intro_text }}</p>
                  </div>
                  <div v-if="document.conclusion_text">
                    <p class="whitespace-pre-line">{{ document.conclusion_text }}</p>
                  </div>
                  <div v-if="document.footer_text">
                    <p class="whitespace-pre-line">{{ document.footer_text }}</p>
                  </div>
                  <div v-if="document.terms">
                    <p class="whitespace-pre-line">{{ document.terms }}</p>
                  </div>
                  <div v-if="document.notes">
                    <p class="whitespace-pre-line italic">{{ document.notes }}</p>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import { success, error, confirm as confirmModal } from "../helpers/notifications";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const document = ref({ number: null, items: [], customer: null });
const documentType = ref("");
const ancestorChain = ref([]);
const descendantChain = ref([]);
const availableActions = ref({});
const isLoading = ref(true);
const isBusy = ref(false);
const showDropdown = ref(false);

const docLabels = {
  Quote: "Devis",
  Invoice: "Facture",
  DeliveryNote: "Bon de livraison",
  PurchaseOrder: "Bon de commande",
  Deposit: "Acompte",
  CreditNote: "Avoir",
};

const docIcons = {
  Quote: "fas fa-file-signature",
  Invoice: "fas fa-file-invoice",
  DeliveryNote: "fas fa-truck",
  PurchaseOrder: "fas fa-shopping-cart",
  Deposit: "fas fa-hand-holding-usd",
  CreditNote: "fas fa-credit-card",
};

const dueDateLabels = {
  Quote: "Valide jusqu'au",
  Invoice: "Date d'échéance",
  DeliveryNote: "Livraison prévue",
  PurchaseOrder: "Livraison prévue",
};

const statusLabels = {
  DRAFT: "Brouillon",
  FINALIZED: "Finalisé",
  SENT: "Envoyé",
  SIGNED: "Signé",
  PAID: "Payé",
  OVERDUE: "En retard",
  CANCELLED: "Annulé",
  DELIVERED: "Livré",
  CONFIRMED: "Confirmé",
  APPLIED: "Appliqué",
  EXPIRED: "Expiré",
};

const statusColors = {
  DRAFT: "#94a3b8",
  FINALIZED: "#22c55e",
  SENT: "#3b82f6",
  SIGNED: "#8b5cf6",
  PAID: "#22c55e",
  OVERDUE: "#ef4444",
  CANCELLED: "#f97316",
  DELIVERED: "#8b5cf6",
  CONFIRMED: "#22c55e",
  APPLIED: "#22c55e",
  EXPIRED: "#ef4444",
};

const docLabel = computed(() => docLabels[documentType.value] || "Document");
const docIcon = computed(() => docIcons[documentType.value] || "fas fa-file");
const dueDateLabel = computed(() => dueDateLabels[documentType.value] || "Échéance");
const statusLabel = computed(() => statusLabels[document.value.status] || document.value.status || "");
const statusBadgeColor = computed(() => statusColors[document.value.status] || "#94a3b8");

const customerAddressLine = computed(() => {
  const c = document.value.customer;
  if (!c) return "";
  const parts = [c.address_street, c.postal_code, c.city, c.country].filter(Boolean);
  return parts.join(", ") || "";
});

const allRelatedDocs = computed(() => {
  const docs = [];
  ancestorChain.value.forEach((d) => {
    if (d.id !== document.value.id) docs.push(d);
  });
  descendantChain.value.forEach((d) => {
    if (d.id !== document.value.id) docs.push(d);
  });
  return docs;
});

const actionButtons = computed(() => {
  const actions = [];
  const a = availableActions.value;

  if (a.can_edit) actions.push({ key: 'edit', label: 'Modifier', icon: 'fas fa-edit', color: '#3b82f6', action: editDocument });
  if (a.can_finalize) actions.push({ key: 'finalize', label: 'Finaliser', icon: 'fas fa-check-circle', color: '#22c55e', action: finalizeDocument, disabled: isBusy.value });
  if (a.can_send) actions.push({ key: 'send', label: 'Envoyer', icon: 'fas fa-paper-plane', color: '#3b82f6', action: sendDocument, disabled: isBusy.value });
  if (a.can_sign) actions.push({ key: 'sign', label: 'Signer', icon: 'fas fa-file-signature', color: '#8b5cf6', action: signDocument, disabled: isBusy.value });
  if (a.can_mark_paid) actions.push({ key: 'mark_paid', label: 'Marquer payé', icon: 'fas fa-check-double', color: '#22c55e', action: markPaid, disabled: isBusy.value });
  if (a.can_mark_delivered) actions.push({ key: 'mark_delivered', label: 'Marquer livré', icon: 'fas fa-box-check', color: '#8b5cf6', action: markDelivered, disabled: isBusy.value });
  if (a.can_convert_to_invoice) actions.push({ key: 'convert_invoice', label: 'Convertir en facture', icon: 'fas fa-file-invoice-dollar', color: '#059669', action: convertToInvoice, disabled: isBusy.value });
  if (a.can_convert_to_purchase_order) actions.push({ key: 'convert_po', label: 'Convertir en commande', icon: 'fas fa-file-contract', color: '#2563eb', action: convertToPurchaseOrder, disabled: isBusy.value });
  if (a.can_create_deposit) actions.push({ key: 'create_deposit', label: 'Créer acompte', icon: 'fas fa-hand-holding-usd', color: '#7c3aed', action: createDeposit, disabled: isBusy.value });
  if (a.can_create_delivery_note) actions.push({ key: 'create_dn', label: 'Créer bon de livraison', icon: 'fas fa-truck', color: '#ea580c', action: createDeliveryNoteAction, disabled: isBusy.value });
  if (a.can_download) actions.push({ key: 'download', label: 'Télécharger PDF', icon: 'fas fa-file-pdf', color: '#dc2626', action: downloadPdf });
  if (a.can_duplicate) actions.push({ key: 'duplicate', label: 'Dupliquer', icon: 'fas fa-copy', color: '#64748b', action: duplicateDocument, disabled: isBusy.value });
  if (a.can_cancel) actions.push({ key: 'cancel', label: 'Annuler', icon: 'fas fa-times-circle', color: '#f97316', action: cancelDocument, disabled: isBusy.value });
  if (a.can_delete) actions.push({ key: 'delete', label: 'Supprimer', icon: 'fas fa-trash-alt', color: '#ef4444', action: deleteDocument, disabled: isBusy.value });
  if (a.can_apply) actions.push({ key: 'apply', label: 'Appliquer', icon: 'fas fa-check-circle', color: '#22c55e', action: applyDocument, disabled: isBusy.value });

  return actions;
});

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
  showDropdown.value = false;
};

const fmt = (n) =>
  new Intl.NumberFormat("fr-MA", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0) + " DH";

const fmtDate = (d) => {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("fr-MA", { year: "numeric", month: "short", day: "numeric" });
};

const getDocTypeLabel = (type) => docLabels[type] || type;
const getDocTypeColor = (type) => {
  const colors = { Quote: "#8b5cf6", Invoice: "#22c55e", DeliveryNote: "#ea580c", PurchaseOrder: "#2563eb", Deposit: "#7c3aed", CreditNote: "#f97316" };
  return colors[type] || "#94a3b8";
};
const getStatusText = (status) => statusLabels[status] || status;

const goToDocument = (docId) => {
  closeDropdown();
  router.push({ name: 'document.preview', params: { id: docId } });
};

const fetchPreview = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/documents/${route.params.id}/preview`);
    document.value = data.document;
    documentType.value = data.document_type;
    ancestorChain.value = data.ancestor_chain || [];
    descendantChain.value = data.descendant_chain || [];
    availableActions.value = data.available_actions || {};
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de charger le document.");
  } finally {
    isLoading.value = false;
  }
};

const refresh = async () => await fetchPreview();

const editDocument = () => {
  closeDropdown();
  const type = documentType.value;
  const id = document.value.id;
  const routes = { Quote: 'quote.edit', Invoice: 'invoice.edit', DeliveryNote: 'delivery-note.edit', PurchaseOrder: 'purchase-order.edit', Deposit: 'deposit.edit' };
  const name = routes[type];
  if (name) router.push({ name, params: { id } });
};

const downloadPdf = () => {
  closeDropdown();
  window.open(`/document/print/${document.value.id}`, '_blank');
};

const finalizeDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Finaliser", "Le numéro sera généré automatiquement.");
  if (!result.isConfirmed) return;
  await doAction('finalize');
};

const sendDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Envoyer", "Confirmer l'envoi du document ?");
  if (!result.isConfirmed) return;
  await doAction('send');
};

const signDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Signer", "Confirmer la signature du devis ?");
  if (!result.isConfirmed) return;
  await doAction('sign');
};

const markPaid = async () => {
  closeDropdown();
  const result = await confirmModal("Marquer payé", "Confirmer le paiement ?");
  if (!result.isConfirmed) return;
  await doAction('mark-paid');
};

const markDelivered = async () => {
  closeDropdown();
  const result = await confirmModal("Marquer livré", "Confirmer la livraison ?");
  if (!result.isConfirmed) return;
  await doAction('deliver');
};

const convertToInvoice = async () => {
  closeDropdown();
  const result = await confirmModal("Convertir en facture", "Créer une facture à partir de ce document ?");
  if (!result.isConfirmed) return;
  await doAction('convert-to-invoice');
};

const convertToPurchaseOrder = async () => {
  closeDropdown();
  const result = await confirmModal("Convertir en commande", "Créer un bon de commande à partir de ce devis ?");
  if (!result.isConfirmed) return;
  await doAction('convert-to-purchase-order');
};

const createDeposit = () => {
  closeDropdown();
  router.push({ name: 'deposit.create', query: { quote_id: document.value.id } });
};

const createDeliveryNoteAction = async () => {
  closeDropdown();
  const result = await confirmModal("Créer un bon de livraison", "Créer un bon de livraison à partir de ce document ?");
  if (!result.isConfirmed) return;
  await doAction('create-delivery-note');
};

const duplicateDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Dupliquer", "Créer une copie de ce document ?");
  if (!result.isConfirmed) return;
  await doAction('duplicate');
};

const cancelDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Annuler", "Confirmer l'annulation du document ?");
  if (!result.isConfirmed) return;
  await doAction('cancel');
};

const applyDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Appliquer", "Confirmer l'application de l'avoir ?");
  if (!result.isConfirmed) return;
  await doAction('apply');
};

const deleteDocument = async () => {
  closeDropdown();
  const result = await confirmModal("Supprimer", "Supprimer définitivement ce document ?");
  if (!result.isConfirmed) return;
  isBusy.value = true;
  try {
    await axios.delete(`/api/documents/${document.value.id}`);
    success("Supprimé !", "Le document a été supprimé.");
    router.back();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de supprimer.");
  } finally {
    isBusy.value = false;
  }
};

const doAction = async (action) => {
  isBusy.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const type = documentType.value;
    const id = document.value.id;

    const endpoints = {
      Quote: { finalize: `/api/quotes/${id}/finalize`, send: `/api/quotes/${id}/send`, sign: `/api/quotes/${id}/sign`, 'convert-to-invoice': `/api/quotes/${id}/convert-to-invoice`, 'convert-to-purchase-order': `/api/quotes/${id}/convert-to-purchase-order`, 'create-delivery-note': `/api/quotes/${id}/create-delivery-note`, duplicate: `/api/quotes/${id}/duplicate` },
      Invoice: { finalize: `/api/invoices/${id}/finalize`, send: `/api/invoices/${id}/send`, 'mark-paid': `/api/invoices/${id}/mark-paid`, cancel: `/api/invoices/${id}/cancel`, duplicate: `/api/invoices/${id}/duplicate` },
      DeliveryNote: { finalize: `/api/delivery-notes/${id}/finalize`, send: `/api/delivery-notes/${id}/send`, deliver: `/api/delivery-notes/${id}/deliver`, 'convert-to-invoice': `/api/delivery-notes/${id}/convert-to-invoice`, duplicate: `/api/delivery-notes/${id}/duplicate` },
      PurchaseOrder: { finalize: `/api/purchase-orders/${id}/finalize`, send: `/api/purchase-orders/${id}/send`, confirm: `/api/purchase-orders/${id}/confirm`, duplicate: `/api/purchase-orders/${id}/duplicate` },
      Deposit: { finalize: `/api/deposits/${id}/finalize`, duplicate: `/api/deposits/${id}/duplicate` },
    };

    const url = endpoints[type]?.[action];
    if (!url) { error("Erreur", "Action non supportée."); return; }

    await axios.put(url);
    const messages = { finalize: 'Document finalisé.', send: 'Document envoyé.', sign: 'Devis signé.', 'mark-paid': 'Facture marquée payée.', deliver: 'Bon de livraison livré.', confirm: 'Commande confirmée.', 'convert-to-invoice': 'Converti en facture.', 'convert-to-purchase-order': 'Converti en commande.', 'create-delivery-note': 'Bon de livraison créé.', cancel: 'Document annulé.', apply: 'Avoir appliqué.', duplicate: 'Document dupliqué.' };
    success("Succès", messages[action] || 'Action effectuée.');
    await refresh();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "L'action a échoué.");
  } finally {
    isBusy.value = false;
  }
};

onMounted(() => fetchPreview());
</script>