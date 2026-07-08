import { ref, computed, watch } from "vue";

export function useDocumentLock(document) {
  const isLocked = computed(() => {
    if (!document.value) return false;
    return !!(document.value.parent_document_id ||
      document.value.source_type);
  });

  const sourceType = computed(() => {
    if (!document.value) return null;
    return document.value.source_type || null;
  });

  const ancestorChain = ref([]);

  const fetchAncestorChain = async (documentId, documentType) => {
    try {
      const endpoint = documentType === 'invoice'
        ? `/api/invoices/${documentId}/ancestor-chain`
        : `/api/purchase-orders/${documentId}/ancestor-chain`;
      const { data } = await axios.get(endpoint, {
        params: { company_id: authStore.currentCompanyId },
      });
      ancestorChain.value = data.ancestor_chain || [];
    } catch {
      ancestorChain.value = [];
    }
  };

  const canEditItems = computed(() => {
    return !isLocked.value;
  });

  const canEditTotals = computed(() => {
    return !isLocked.value;
  });

  const lockReason = computed(() => {
    if (!isLocked.value) return null;
    if (sourceType.value === 'Quote') {
      return 'Ce document est dérivé d\'un devis. Les lignes et totaux sont protégés.';
    }
    if (sourceType.value === 'Invoice') {
      return 'Ce document est dérivé d\'une facture. Les lignes et totaux sont protégés.';
    }
    return 'Ce document a un document parent. Les lignes et totaux sont protégés.';
  });

  return {
    isLocked,
    sourceType,
    ancestorChain,
    fetchAncestorChain,
    canEditItems,
    canEditTotals,
    lockReason,
  };
}