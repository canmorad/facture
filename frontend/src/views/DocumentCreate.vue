<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import DocumentForm from "../components/DocumentForm.vue";
import axios from "axios";
import Swal from "sweetalert2";

const route = useRoute();
const invoiceData = ref(null);
const isLoading = ref(false);

const documentType = computed(() => {
  const type = route.query.type;
  return type === "devis" ? "devis" : "invoice";
});

const clientId = computed(() => {
  const id = route.query.client_id;
  return id ? parseInt(id) : null;
});

const invoiceId = computed(() => {
  const id = route.params.id;
  return id ? parseInt(id) : null;
});

const isEditMode = computed(() => {
  return route.path.includes('/edit/') && invoiceId.value;
});

const fetchInvoiceForEdit = async () => {
  if (!isEditMode.value) return;
  
  isLoading.value = true;
  try {
    const { data } = await axios.get(`/api/invoices/${invoiceId.value}`);
    invoiceData.value = data;
  } catch (error) {
    Swal.fire({
      title: "Erreur",
      text: "Impossible de charger les données de la facture.",
      icon: "error",
      confirmButtonColor: "#062121",
    });
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchInvoiceForEdit();
});
</script>

<template>
  <AuthenticatedLayout>
    <div v-if="isLoading" class="flex items-center justify-center h-screen">
      <svg class="animate-spin h-8 w-8 text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>
    <DocumentForm 
      v-else
      :type="documentType" 
      :client-id="clientId"
      :edit-data="invoiceData"
      :is-edit="isEditMode"
    />
  </AuthenticatedLayout>
</template>