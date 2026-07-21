<script setup>
import { reactive, ref, computed, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import CustomSelect from "@/components/CustomSelect.vue";
import BaseInputNumber from "@/components/BaseInputNumber.vue";
import TaxRateInput from "@/components/TaxRateInput.vue";
import InvoiceAIUploader from "@/components/InvoiceAIUploader.vue";
import Checkbox from "@/components/Checkbox.vue";
import ProductForm from "@/components/ProductForm.vue";
import SupplierForm from "@/components/SupplierForm.vue";
import axios from "axios";
import { success, error, confirm, toast } from "@/helpers/notifications";

const router = useRouter();
const authStore = useAuthStore();

// ========== État principal ==========
const activeTab = ref("list");
const isLoading = ref(false);
const isLoadingInvoices = ref(false);
const isLoadingSuppliers = ref(false);
const isLoadingProducts = ref(false);
const editingInvoiceId = ref(null);
const selectedStatus = ref("all");
const openDropdownId = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

// ========== Dynamic Tabs State ==========
const showProductTab = ref(false);
const showSupplierTab = ref(false);

// ========== AI Upload State ==========
const uploadedFile = ref(null);

const invoices = ref([]);
const suppliers = ref([]);
const products = ref([]);
const taxRates = ref([]);
const isLoadingTaxRates = ref(false);

const form = reactive({
  fournisseur_id: "",
  supplier_invoice_number: "",
  invoice_date: new Date().toISOString().split("T")[0],
  due_date: "",
  tva_rate: 20,
  apply_withholding_tax: false,
  withholding_tax_rate: 20,
  payment_terms: "",
  payment_mode: "",
  notes: "",
  items: [],
});

const errors = reactive({
  fournisseur_id: "",
  date: "",
  tva_rate: "",
  items: "",
  server: "",
});

// ========== État pour la modale produit ==========
const showProductModal = ref(false);
const productModalForm = reactive({
  name: "",
  sku: "",
  price: "",
});
const productModalErrors = reactive({
  name: "",
  sku: "",
  price: "",
});
const currentProductRowIndex = ref(null);
const isCreatingProduct = ref(false);

// ========== Computed ==========
const filteredInvoices = computed(() => {
  if (selectedStatus.value === "all") return invoices.value;
  return invoices.value.filter((inv) => inv.status === selectedStatus.value);
});

// ========== Fonctions métier ==========
const makeItem = () => ({
  product_id: "",
  designation: "",
  quantity: 1,
  unit_price: 0,
  tax_rate: defaultTaxRate.value || 20,
});

const addItem = () => {
  form.items.push(makeItem());
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const onProductSelect = (index) => {
  const item = form.items[index];
  const product = products.value.find((p) => p.id == item.product_id);
  if (product) {
    item.designation = product.name;
    item.unit_price = product.price;
    item.tax_rate = product.tax_rate || form.tva_rate || 20;
  }
};

const handleSelectProduct = ({ index, product }) => {
  const item = form.items[index];
  item.product_id = product.id;
  item.designation = product.name;
  item.unit_price = product.price;
  item.tax_rate = product.tax_rate || form.tva_rate || 20;
};

const handleProductChange = (index, productId) => {
  const item = form.items[index];
  const product = products.value.find((p) => p.id == productId);
  if (product) {
    item.product_id = product.id;
    item.designation = product.name;
    item.unit_price = product.price;
    item.tax_rate = product.tax_rate || form.tva_rate || 20;
  } else {
    item.product_id = "";
    item.designation = "";
    item.unit_price = 0;
    item.tax_rate = defaultTaxRate.value || 20;
  }
};

const handleSelectTaxRate = ({ index, taxRate }) => {
  form.items[index].tax_rate = taxRate.rate;
};

const lineTotal = (item) => {
  return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
};

const totals = computed(() => {
  let totalHt = 0;
  let totalTva = 0;

  form.items.forEach((item) => {
    const itemHt = lineTotal(item);
    const itemTva = itemHt * ((item.tax_rate || 0) / 100);
    totalHt += itemHt;
    totalTva += itemTva;
  });

  let withholdingAmount = 0;
  if (form.apply_withholding_tax) {
    withholdingAmount = (totalHt * form.withholding_tax_rate) / 100;
  }

  const totalTtc = totalHt + totalTva;
  const amountAfterWithholding = totalTtc - withholdingAmount;

  return {
    ht: totalHt.toFixed(2),
    tva: totalTva.toFixed(2),
    ttc: totalTtc.toFixed(2),
    withholdingAmount: withholdingAmount.toFixed(2),
    amountAfterWithholding: amountAfterWithholding.toFixed(2),
  };
});

// ========== Appels API ==========
const fetchInvoices = async () => {
  isLoadingInvoices.value = true;
  try {
    const { data } = await axios.get("/api/purchase-invoices");
    invoices.value = data;
  } catch {
    error("Erreur", "Impossible de charger les factures d'achat.");
  } finally {
    isLoadingInvoices.value = false;
  }
};

const fetchSuppliers = async () => {
  isLoadingSuppliers.value = true;
  try {
    const { data } = await axios.get("/api/suppliers");
    suppliers.value = data;
  } catch {
    error("Erreur", "Impossible de charger les fournisseurs.");
  } finally {
    isLoadingSuppliers.value = false;
  }
};

const fetchProducts = async () => {
  isLoadingProducts.value = true;
  try {
    const { data } = await axios.get("/api/products");
    products.value = data;
  } catch {
    error("Erreur", "Impossible de charger les produits.");
  } finally {
    isLoadingProducts.value = false;
  }
};

const fetchTaxRates = async () => {
  isLoadingTaxRates.value = true;
  try {
    const companyId = authStore.currentCompanyId;
    const params = companyId ? { company_id: companyId } : {};
    const { data } = await axios.get("/api/tax-rates", { params });
    taxRates.value = data.filter(t => t.is_actif).map(t => ({
      label: `${t.libelle} (${t.rate}%)`,
      value: t.rate
    }));

    if (taxRates.value.length > 0) {
      const defaultRate = data.find(t => t.is_default && t.is_actif);
      if (defaultRate) {
        form.tva_rate = defaultRate.rate;
      } else if (!taxRates.value.find(t => t.value === form.tva_rate)) {
        form.tva_rate = taxRates.value[0].value;
      }
    }
  } catch (err) {
    console.error("Error fetching tax rates:", err);
    taxRates.value = [
      { label: "0% — Exonéré", value: 0 },
      { label: "7% — Eau/Pharmaceutiques", value: 7 },
      { label: "10% — Banques/Hôtels", value: 10 },
      { label: "14% — Transport/Électricité", value: 14 },
      { label: "20% — Standard", value: 20 },
    ];
  } finally {
    isLoadingTaxRates.value = false;
  }
};

// ========== Gestion du formulaire ==========
const resetForm = () => {
  form.fournisseur_id = "";
  form.supplier_invoice_number = "";
  form.invoice_date = new Date().toISOString().split("T")[0];
  form.due_date = "";
  form.tva_rate = defaultTaxRate.value || 20;
  form.apply_withholding_tax = false;
  form.withholding_tax_rate = 20;
  form.payment_terms = "";
  form.payment_mode = "";
  form.notes = "";
  form.items = [makeItem()];
  uploadedFile.value = null;
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  editingInvoiceId.value = null;
};

// ========== Status Management ==========
const getStatusBadgeClass = (status) => {
  const classes = {
    draft: "bg-yellow-100 text-yellow-700",
    validated: "bg-blue-100 text-blue-700",
    paid: "bg-green-100 text-green-700",
    overdue: "bg-red-100 text-red-700",
    cancelled: "bg-gray-100 text-gray-700",
  };
  return classes[status] || "bg-gray-100 text-gray-700";
};

const getStatusText = (status) => {
  const texts = {
    draft: "Brouillon",
    validated: "Validée",
    paid: "Payée",
    overdue: "En retard",
    cancelled: "Annulée",
  };
  return texts[status] || status;
};

const isImmutable = (invoice) => {
  return invoice.status === 'paid' || invoice.status === 'cancelled';
};

// ========== Dropdown Management ==========
const toggleDropdown = (id, event) => {
  if (openDropdownId.value === id) {
    closeDropdown();
    return;
  }
  const target = event.currentTarget;
  const rect = target.getBoundingClientRect();
  dropdownPosition.value = {
    top: rect.bottom + window.scrollY + 4,
  };
  openDropdownId.value = id;
};

const closeDropdown = () => {
  openDropdownId.value = null;
};

// ========== Actions ==========
const viewInvoice = (invoice) => {
  closeDropdown();
  router.push({ name: 'purchase-invoice.preview', params: { id: invoice.id } });
};

const editInvoice = async (invoice) => {
  closeDropdown();
  try {
    const { data: inv } = await axios.get(`/api/purchase-invoices/${invoice.id}`);

    editingInvoiceId.value = inv.id;
    form.fournisseur_id = inv.fournisseur_id;
    form.supplier_invoice_number = inv.supplier_invoice_number || "";
    form.invoice_date = inv.invoice_date?.split("T")[0] || new Date().toISOString().split("T")[0];
    form.due_date = inv.due_date?.split("T")[0] || "";
    form.payment_terms = inv.payment_terms || "";
    form.payment_mode = inv.payment_mode || "";
    form.notes = inv.notes || "";
    form.apply_withholding_tax = inv.apply_withholding_tax || false;
    form.withholding_tax_rate = inv.withholding_tax_rate || 20;
    form.tva_rate = 20;

    form.items = (inv.items || []).map((item) => ({
      product_id: item.product_id || "",
      designation: item.designation || "",
      quantity: item.quantity || 1,
      unit_price: item.unit_price || 0,
      tax_rate: item.tax_rate || 20,
    }));

    if (form.items.length === 0) {
      form.items = [makeItem()];
    }

    activeTab.value = "add";

    if (suppliers.value.length === 0) {
      await fetchSuppliers();
    }
    if (products.value.length === 0) {
      await fetchProducts();
    }
    if (taxRates.value.length === 0) {
      await fetchTaxRates();
    }
  } catch (err) {
    error("Erreur", "Impossible de charger les détails de la facture.");
    console.error("Error loading invoice details:", err);
  }
};

const deleteInvoice = async (id, number) => {
  closeDropdown();
  const result = await confirm("Êtes-vous sûr ?", `Supprimer la facture "${number}" définitivement ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/api/purchase-invoices/${id}`);
    success("Supprimée !", "La facture a été supprimée.");
    await fetchInvoices();
  } catch {
    error("Erreur", "Impossible de supprimer la facture.");
  }
};

const validateInvoice = async (invoice) => {
  closeDropdown();
  const result = await confirm("Valider", `Valider la facture "${invoice.supplier_invoice_number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/purchase-invoices/${invoice.id}/validate`);
    success("Validée !", "La facture a été validée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de valider la facture.");
  }
};

const markAsPaid = async (invoice) => {
  closeDropdown();
  const result = await confirm("Marquer comme payée", `Confirmer que la facture "${invoice.supplier_invoice_number}" a été payée ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/purchase-invoices/${invoice.id}/mark-paid`);
    success("Payée !", "La facture a été marquée comme payée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de marquer la facture comme payée.");
  }
};

const markAsUnpaid = async (invoice) => {
  closeDropdown();
  const result = await confirm("Marquer comme impayée", `Marquer la facture "${invoice.supplier_invoice_number}" comme impayée ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/purchase-invoices/${invoice.id}/mark-unpaid`);
    success("Mis à jour !", "La facture a été marquée comme impayée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de mettre à jour la facture.");
  }
};

const cancelInvoice = async (invoice) => {
  closeDropdown();
  const result = await confirm("Annuler", `Annuler la facture "${invoice.supplier_invoice_number}" ?`);
  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/purchase-invoices/${invoice.id}/cancel`);
    success("Annulée !", "La facture a été annulée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible d'annuler la facture.");
  }
};

const downloadPdf = (invoice) => {
  closeDropdown();
  // TODO: Implement PDF download when backend endpoint is available
  error("Info", "Le téléchargement PDF sera bientôt disponible.");
};

const duplicateInvoice = async (invoice) => {
  closeDropdown();
  try {
    const { data } = await axios.post(`/api/purchase-invoices/${invoice.id}/duplicate`);
    success("Dupliquée !", "La facture a été dupliquée.");
    await fetchInvoices();
  } catch (err) {
    error("Erreur", err.response?.data?.message || "Impossible de dupliquer la facture.");
  }
};

const changeStatusFilter = (status) => {
  selectedStatus.value = status;
};

// ========== Modale produit (legacy) ==========
const openProductModal = (rowIndex) => {
  currentProductRowIndex.value = rowIndex;
  productModalForm.name = "";
  productModalForm.sku = "";
  productModalForm.price = "";
  Object.keys(productModalErrors).forEach((k) => (productModalErrors[k] = ""));
  showProductModal.value = true;
};

const closeProductModal = () => {
  showProductModal.value = false;
  currentProductRowIndex.value = null;
};

const createProductAndSelect = async () => {
  Object.keys(productModalErrors).forEach((k) => (productModalErrors[k] = ""));
  if (!productModalForm.name.trim()) {
    productModalErrors.name = "Le nom est requis.";
    return;
  }
  if (!productModalForm.price || productModalForm.price <= 0) {
    productModalErrors.price = "Le prix doit être supérieur à 0.";
    return;
  }

  isCreatingProduct.value = true;
  try {
    const payload = {
      name: productModalForm.name,
      price: productModalForm.price,
      description: productModalForm.sku ? `Référence: ${productModalForm.sku}` : "",
    };
    const { data } = await axios.post("/api/products", payload);

    await fetchProducts();

    const newProductId = data.id;
    const rowIndex = currentProductRowIndex.value;
    if (rowIndex !== null && form.items[rowIndex]) {
      form.items[rowIndex].product_id = newProductId;
      onProductSelect(rowIndex);
    }

    toast("Produit ajouté !", "Le produit a été créé et sélectionné automatiquement.");
    closeProductModal();
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors;
      if (e.name) productModalErrors.name = e.name[0];
      if (e.price) productModalErrors.price = e.price[0];
      if (e.description) productModalErrors.sku = e.description[0];
    } else {
      productModalErrors.server = "Impossible d'ajouter le produit.";
    }
  } finally {
    isCreatingProduct.value = false;
  }
};

// ========== AI Analysis Handler ==========
const handleInvoiceAnalyzed = (result) => {
  if (result.data) {
    const data = result.data;

    if (data.fournisseur?.name || data.fournisseur?.ice) {
      const matchedSupplier = suppliers.value.find(s => {
        const nameMatch = data.fournisseur.name && s.name.toLowerCase().includes(data.fournisseur.name.toLowerCase());
        const iceMatch = data.fournisseur.ice && s.ice === data.fournisseur.ice;
        return nameMatch || iceMatch;
      });

      if (matchedSupplier) {
        form.fournisseur_id = matchedSupplier.id;
        toast('Fournisseur trouvé', `"${matchedSupplier.name}" a été automatiquement sélectionné.`);
      }
    }

    if (data.supplier_invoice_number) {
      form.supplier_invoice_number = data.supplier_invoice_number;
    }
    if (data.invoice_date) {
      form.invoice_date = data.invoice_date;
    }
    if (data.due_date) {
      form.due_date = data.due_date;
    }
    if (data.payment_terms) {
      form.payment_terms = data.payment_terms;
    }
    if (data.payment_mode) {
      form.payment_mode = data.payment_mode;
    }

    if (data.items && data.items.length > 0) {
      form.items = data.items.map(item => ({
        product_id: '',
        designation: item.designation || '',
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        tax_rate: item.tax_rate || form.tva_rate || 20,
      }));

      if (data.items.length > 0) {
        toast('Articles extraits', `${data.items.length} ligne(s) d'article(s) extraite(s).`);
      }
    }
  }
};

// ========== Dynamic Tabs Functions ==========
const openProductTab = () => {
  showProductTab.value = true;
  activeTab.value = "add-product";
};

const closeProductTab = () => {
  showProductTab.value = false;
  activeTab.value = "add";
};

const openSupplierTab = () => {
  showSupplierTab.value = true;
  activeTab.value = "add-supplier";
};

const closeSupplierTab = () => {
  showSupplierTab.value = false;
  activeTab.value = "add";
};

const handleProductCreated = async (newProduct) => {
  await fetchProducts();

  const emptyLineIndex = form.items.findIndex(item => !item.product_id || !item.designation);

  if (emptyLineIndex > -1) {
    const item = form.items[emptyLineIndex];
    item.product_id = newProduct.id;
    item.designation = newProduct.name;
    item.unit_price = newProduct.price;
  } else {
    const newItem = makeItem();
    newItem.product_id = newProduct.id;
    newItem.designation = newProduct.name;
    newItem.unit_price = newProduct.price;
    form.items.push(newItem);
  }

  closeProductTab();
  toast("Produit ajouté !", `"${newProduct.name}" est maintenant disponible.`);
};

const handleSupplierCreated = async (newSupplier) => {
  await fetchSuppliers();
  form.fournisseur_id = newSupplier.id;
  closeSupplierTab();
  toast("Fournisseur ajouté !", `"${newSupplier.name}" a été sélectionné automatiquement.`);
};

// ========== Tab Management ==========
const changeTab = (tab) => {
  activeTab.value = tab;

  if (tab !== "add-product") {
    showProductTab.value = false;
  }
  if (tab !== "add-supplier") {
    showSupplierTab.value = false;
  }

  if (tab === "list") {
    resetForm();
    if (invoices.value.length === 0) fetchInvoices();
  } else if (tab === "add") {
    resetForm();
    fetchSuppliers();
    fetchProducts();
    fetchTaxRates();
  }
};

const submitInvoice = async () => {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  isLoading.value = true;

  if (form.items.some((i) => !i.designation.trim() || !i.quantity || i.unit_price < 0)) {
    errors.items = "Veuillez remplir correctement toutes les lignes.";
    isLoading.value = false;
    return;
  }

  try {
    const payload = {
      fournisseur_id: form.fournisseur_id,
      supplier_invoice_number: form.supplier_invoice_number || null,
      invoice_date: form.invoice_date,
      due_date: form.due_date || null,
      tva_rate: form.tva_rate,
      apply_withholding_tax: form.apply_withholding_tax,
      withholding_tax_rate: form.withholding_tax_rate,
      payment_terms: form.payment_terms,
      payment_mode: form.payment_mode,
      notes: form.notes,
      items: form.items.map((item) => ({
        product_id: item.product_id,
        designation: item.designation,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate: item.tax_rate,
      })),
    };

    if (editingInvoiceId.value) {
      await axios.put(`/api/purchase-invoices/${editingInvoiceId.value}`, payload);
      success("Facture modifiée !", "La facture d'achat a été modifiée avec succès.");
    } else {
      await axios.post("/api/purchase-invoices", payload);
      success("Facture ajoutée !", "La facture d'achat a été enregistrée avec succès.");
    }

    resetForm();
    await fetchInvoices();
    activeTab.value = "list";
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.errors) {
      const e = err.response.data.errors;
      Object.keys(e).forEach((k) => {
        if (errors[k] !== undefined) errors[k] = e[k][0];
      });
    } else {
      errors.server = "Une erreur est survenue lors de l'enregistrement.";
    }
  } finally {
    isLoading.value = false;
  }
};

// ========== Utilities ==========
const formatAmount = (amount) => {
  return new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("fr-MA");
};

const formatCurrency = (amount) =>
  new Intl.NumberFormat("fr-MA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " DH";

// ========== Supplier options for CustomSelect ==========
const supplierOptions = computed(() => [
  { label: "-- Sélectionnez un fournisseur --", value: "" },
  ...suppliers.value.map(s => ({ label: s.name, value: s.id }))
]);

// ========== Product options for CustomSelect ==========
const productOptions = computed(() => [
  { label: "-- Choisir un produit --", value: "" },
  ...products.value.map(p => ({ label: p.name, value: p.id }))
]);

// ========== TVA options for CustomSelect ==========
const tvaOptions = computed(() => {
  if (taxRates.value.length === 0) {
    return [
      { label: "0% — Exonéré", value: 0 },
      { label: "7% — Eau/Pharmaceutiques", value: 7 },
      { label: "10% — Banques/Hôtels", value: 10 },
      { label: "14% — Transport/Électricité", value: 14 },
      { label: "20% — Standard", value: 20 },
    ];
  }
  return taxRates.value;
});

// ========== Default tax rate for new items ==========
const defaultTaxRate = computed(() => {
  if (taxRates.value.length === 0) return 20;
  const defaultOption = taxRates.value.find(t => t.value === form.tva_rate);
  return defaultOption ? defaultOption.value : taxRates.value[0].value;
});

onMounted(() => {
  fetchInvoices();
  fetchSuppliers();
  fetchProducts();
  fetchTaxRates();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Tab Navigation -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between flex-wrap gap-4">
              <div class="flex gap-6">
                <button
                  @click="changeTab('list')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'list'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-list"></i>
                  Liste des factures d'achat
                  <span v-if="invoices.length > 0" class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ invoices.length }}
                  </span>
                </button>

                <button
                  @click="changeTab('add')"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'add'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas" :class="editingInvoiceId ? 'fa-edit' : 'fa-plus-circle'"></i>
                  {{ editingInvoiceId ? "Modifier la facture" : "Ajouter une facture" }}
                </button>

                <button
                  v-if="showProductTab"
                  @click="activeTab = 'add-product'"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'add-product'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-box"></i>
                  Ajouter un produit
                </button>

                <button
                  v-if="showSupplierTab"
                  @click="activeTab = 'add-supplier'"
                  :class="[
                    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                    activeTab === 'add-supplier'
                      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                      : 'text-gray-500 hover:text-gray-700',
                  ]"
                >
                  <i class="fas fa-truck-field"></i>
                  Ajouter un fournisseur
                </button>
              </div>

              <button
                v-if="activeTab === 'list'"
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2"
              >
                <i class="fas fa-plus"></i> Créer une facture
              </button>
            </div>

            <!-- Status Filters (only show in list tab) -->
            <div
              v-if="activeTab === 'list'"
              class="flex gap-4 pb-3 mt-2 overflow-x-auto"
            >
              <button
                @click="changeStatusFilter('all')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'all'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-list"></i> Tous
                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{
                  invoices.length
                }}</span>
              </button>
              <button
                @click="changeStatusFilter('draft')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'draft'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-pen"></i> Brouillons
                <span
                  class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full"
                >{{
                  invoices.filter((inv) => inv.status === "draft").length
                }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('validated')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'validated'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-check-circle"></i> Validées
                <span
                  class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"
                >{{
                  invoices.filter((inv) => inv.status === "validated").length
                }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('paid')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'paid'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-check-double"></i> Payées
                <span
                  class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                >{{
                  invoices.filter((inv) => inv.status === "paid").length
                }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('overdue')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'overdue'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-clock"></i> En retard
                <span
                  class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full"
                >{{
                  invoices.filter((inv) => inv.status === "overdue").length
                }}</span>
              >
              </button>
              <button
                @click="changeStatusFilter('cancelled')"
                :class="[
                  'text-xs font-medium transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg',
                  selectedStatus === 'cancelled'
                    ? 'bg-[#C5F82A]/15 text-[#062121]'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                ]"
              >
                <i class="fas fa-times-circle"></i> Annulées
                <span
                  class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full"
                >{{
                  invoices.filter((inv) => inv.status === "cancelled").length
                }}</span>
              >
              </button>
            </div>
          </div>

          <!-- LIST TAB CONTENT -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoadingInvoices" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement...</p>
            </div>

            <div v-else-if="filteredInvoices.length === 0" class="text-center py-12">
              <i class="fas fa-file-invoice-dollar text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">
                {{
                  selectedStatus === "all"
                    ? "Aucune facture d'achat enregistrée."
                    : "Aucune facture avec ce statut."
                }}
              </p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Créer une facture
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">N° Facture</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Fournisseur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Total TTC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="inv in filteredInvoices" :key="inv.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-gray-900">{{ inv.supplier_invoice_number || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-700">{{ inv.fournisseur?.name || "—" }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600">{{ formatDate(inv.invoice_date) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="text-sm font-bold text-[#062121]">{{ formatCurrency(inv.amount_ttc || 0) }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-full', getStatusBadgeClass(inv.status)]">
                        {{ getStatusText(inv.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button @click="viewInvoice(inv)" title="Aperçu" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-eye text-sm"></i>
                        </button>
                        <button @click="toggleDropdown(inv.id, $event)" class="w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#062121] transition-all duration-200">
                          <i class="fas fa-ellipsis-v text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ADD/EDIT INVOICE TAB CONTENT -->
          <form v-else-if="activeTab === 'add'" @submit.prevent="submitInvoice" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="fournisseur_id" value="Fournisseur *" />
                  <div class="flex items-center gap-2 mt-1">
                    <CustomSelect
                      id="fournisseur_id"
                      v-model="form.fournisseur_id"
                      :options="supplierOptions"
                      label-key="label"
                      value-key="value"
                      placeholder="Sélectionner un fournisseur"
                      :use-portal="true"
                      class="flex-1"
                    />
                    <button
                      type="button"
                      @click="openSupplierTab"
                      class="shrink-0 w-10 h-10 rounded-lg bg-[#062121] text-white hover:bg-[#0F2A2A] transition-colors flex items-center justify-center"
                      title="Ajouter un fournisseur"
                    >
                      <i class="fas fa-plus"></i>
                    </button>
                  </div>
                  <InputError class="mt-2" :message="errors.fournisseur_id" />
                </div>

                <div>
                  <InputLabel for="invoice_date" value="Date de la facture *" />
                  <TextInput
                    id="invoice_date"
                    type="date"
                    v-model="form.invoice_date"
                    class="mt-1"
                  />
                  <InputError class="mt-2" :message="errors.date" />
                </div>

                <div>
                  <InputLabel for="due_date" value="Date d'échéance" />
                  <TextInput
                    id="due_date"
                    type="date"
                    v-model="form.due_date"
                    class="mt-1"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <InputLabel for="supplier_invoice_number" value="N° Facture fournisseur" />
                  <TextInput
                    id="supplier_invoice_number"
                    v-model="form.supplier_invoice_number"
                    placeholder="FA-2024-001"
                    class="mt-1"
                  />
                  <InputError class="mt-2" :message="errors.supplier_invoice_number" />
                </div>

                <div class="md:col-span-2">
                  <InputLabel for="payment_mode" value="Mode de paiement" />
                  <TextInput
                    id="payment_mode"
                    v-model="form.payment_mode"
                    placeholder="Virement, Chèque..."
                    class="mt-1"
                  />
                  <InputError class="mt-2" :message="errors.payment_mode" />
                </div>
              </div>

              <!-- AI ANALYSIS SECTION -->
              <div>
                <InputLabel value="Analyse automatique par IA" />
                <InvoiceAIUploader
                  v-model="uploadedFile"
                  @analyzed="handleInvoiceAnalyzed"
                />
                <p class="mt-2 text-xs text-gray-500">
                  Importez votre facture (PDF ou image) et l'IA extraira automatiquement toutes les données.
                </p>
              </div>

              <!-- WITHHOLDING TAX -->
              <div v-if="form.fournisseur_id" class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                  <Checkbox v-model="form.apply_withholding_tax" />
                  <span class="text-sm font-semibold text-gray-700">
                    Appliquer la retenue à la source
                  </span>
                </div>
                <CustomSelect
                  v-if="form.apply_withholding_tax"
                  v-model.number="form.withholding_tax_rate"
                  :options="[
                    { label: '20% - Taux standard', value: 20 },
                    { label: '30% - Services professionnels', value: 30 },
                    { label: '15% - Agriculture/Commerce', value: 15 },
                  ]"
                  label-key="label"
                  value-key="value"
                  placeholder="Sélectionner un taux"
                  :searchable="false"
                  container-class="w-64"
                />
                <span v-if="form.apply_withholding_tax" class="text-sm font-medium text-gray-600">
                  Montant de la retenue: <span class="text-[#062121]">{{ totals.withholdingAmount }} DH</span>
                </span>
              </div>

              <!-- LINE ITEMS -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-sm font-bold text-[#062121] uppercase tracking-wider">Articles achetés</h3>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click="openProductTab"
                      class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#062121] text-white rounded-lg text-xs font-bold hover:bg-[#0F2A2A] transition-colors"
                    >
                      <i class="fas fa-box text-[10px]"></i> Nouveau produit
                    </button>
                    <button
                      type="button"
                      @click="addItem"
                      class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#C5F82A] text-[#062121] rounded-lg text-xs font-bold hover:bg-[#b8e626] transition-colors"
                    >
                      <i class="fas fa-plus text-[10px]"></i> Ajouter une ligne
                    </button>
                  </div>
                </div>

                <InputError :message="errors.items" class="mb-3" />

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                  <table class="min-w-full">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Produit</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[10%]">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[15%]">P.U. HT</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">TVA %</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-[12%]">Total HT</th>
                        <th class="px-4 py-3 w-[3%]"></th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                      <tr v-for="(item, idx) in form.items" :key="idx" :class="idx % 2 === 1 ? 'bg-gray-50/60' : 'bg-white'">
                        <td class="px-4 py-3 relative z-10 overflow-visible">
                          <CustomSelect
                            :model-value="item.product_id"
                            :options="productOptions"
                            label-key="label"
                            value-key="value"
                            placeholder="-- Choisir un produit --"
                            @update:model-value="(value) => handleProductChange(idx, value)"
                            :use-portal="true"
                          />
                        </td>

                        <td class="px-4 py-3">
                          <BaseInputNumber
                            v-model="item.quantity"
                            :min="0.01"
                            :step="'0.01'"
                          />
                        </td>

                        <td class="px-4 py-3">
                          <BaseInputNumber
                            v-model="item.unit_price"
                            :min="0"
                            :step="'0.01'"
                          />
                        </td>

                        <td class="px-4 py-3 relative z-10 overflow-visible">
                          <TaxRateInput
                            v-model="item.tax_rate"
                            :tax-rates="taxRates"
                            @select-tax-rate="(taxRate) => handleSelectTaxRate({ index: idx, taxRate })"
                          />
                        </td>

                        <td class="px-4 py-3 text-center">
                          <span class="text-sm font-semibold text-[#062121] font-mono">
                            {{ formatAmount(lineTotal(item)) }} MAD
                          </span>
                        </td>

                        <td class="px-4 py-3 text-center">
                          <button
                            type="button"
                            @click="removeItem(idx)"
                            :disabled="form.items.length === 1"
                            class="text-red-400 hover:text-red-600 transition-colors disabled:opacity-20 disabled:cursor-not-allowed"
                          >
                            <i class="fas fa-times text-xs"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- TOTALS -->
              <div class="flex justify-end">
                <div class="w-full md:w-1/2 rounded-xl border border-gray-200 overflow-hidden">
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100">
                    <span class="text-sm text-gray-500">Total HT</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono">{{ formatAmount(totals.ht) }} DH</span>
                  </div>
                  <div class="px-5 py-3 flex justify-between border-b border-gray-100 bg-gray-50/50">
                    <span class="text-sm text-gray-500">TVA totale</span>
                    <span class="text-sm font-semibold text-gray-800 font-mono">{{ formatAmount(totals.tva) }} DH</span>
                  </div>
                  <div
                    v-if="form.apply_withholding_tax"
                    class="px-5 py-3 flex justify-between border-b border-gray-100 bg-orange-50"
                  >
                    <span class="text-sm text-gray-500">Retenue à la source ({{ form.withholding_tax_rate }}%)</span>
                    <span class="text-sm font-semibold text-orange-700 font-mono">- {{ formatAmount(totals.withholdingAmount) }} DH</span>
                  </div>
                  <div class="px-5 py-4 flex justify-between bg-gray-50">
                    <span class="text-sm font-bold text-[#062121] uppercase tracking-wide">Total TTC</span>
                    <span class="text-lg font-black text-[#062121] font-mono">{{ formatAmount(totals.amountAfterWithholding) }} DH</span>
                  </div>
                </div>
              </div>

              <!-- FOOTER ACTIONS -->
              <div class="flex flex-wrap justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-bold text-sm transition-all"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  :disabled="isLoading"
                  class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-[#062121] hover:opacity-90 transition-all disabled:opacity-50"
                >
                  <span v-if="isLoading">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>
                    <i class="fas fa-save"></i> {{ editingInvoiceId ? "Modifier" : "Enregistrer" }}
                  </span>
                </button>
              </div>
            </div>
          </form>

          <!-- PRODUCT TAB CONTENT -->
          <div v-else-if="activeTab === 'add-product'" class="p-6 lg:p-8">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h3 class="text-lg font-bold text-[#062121]">Ajouter un produit</h3>
                <p class="text-sm text-gray-500 mt-1">Créez un nouveau produit et ajoutez-le instantanément à votre facture.</p>
              </div>
              <button @click="closeProductTab" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <ProductForm @product-created="handleProductCreated" @cancel="closeProductTab" />
          </div>

          <!-- SUPPLIER TAB CONTENT -->
          <div v-else-if="activeTab === 'add-supplier'" class="p-6 lg:p-8">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h3 class="text-lg font-bold text-[#062121]">Ajouter un fournisseur</h3>
                <p class="text-sm text-gray-500 mt-1">Créez un nouveau fournisseur et sélectionnez-le instantanément.</p>
              </div>
              <button @click="closeSupplierTab" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <SupplierForm @supplier-created="handleSupplierCreated" @cancel="closeSupplierTab" />
          </div>
        </div>
      </div>
    </div>

    <!-- ACTIONS DROPDOWN (Teleport to body) -->
    <Teleport to="body">
      <div
        v-if="openDropdownId"
        class="fixed inset-0 z-30"
        @click.self="closeDropdown"
      ></div>
      <div
        v-if="openDropdownId"
        class="fixed z-40 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1 max-h-[360px] overflow-y-auto"
        :style="{
          top: dropdownPosition.top + 'px',
          right: '16px',
        }"
        @click.stop
      >
        <template v-for="inv in filteredInvoices" :key="inv.id">
          <div v-if="openDropdownId === inv.id">
            <!-- Draft Actions -->
            <template v-if="inv.status === 'draft'">
              <button
                @click="editInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-edit w-4 text-blue-500"></i> Modifier
              </button>
              <button
                @click="validateInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-check-circle w-4 text-green-500"></i> Valider
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="duplicateInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
              <button
                @click="deleteInvoice(inv.id, inv.supplier_invoice_number)"
                class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
              >
                <i class="fas fa-trash-alt w-4 text-red-400"></i> Supprimer
              </button>
            </template>

            <!-- Validated Actions -->
            <template v-else-if="inv.status === 'validated'">
              <button
                @click="viewInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="markAsPaid(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-money-bill-wave w-4 text-green-500"></i> Marquer payée
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="duplicateInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
              <button
                @click="cancelInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-times-circle w-4 text-orange-500"></i> Annuler
              </button>
            </template>

            <!-- Paid Actions -->
            <template v-else-if="inv.status === 'paid'">
              <button
                @click="viewInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="markAsUnpaid(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-undo w-4 text-orange-500"></i> Marquer impayée
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
              <button
                @click="duplicateInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-copy w-4 text-gray-400"></i> Dupliquer
              </button>
            </template>

            <!-- Overdue Actions -->
            <template v-else-if="inv.status === 'overdue'">
              <button
                @click="viewInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="markAsPaid(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-money-bill-wave w-4 text-green-500"></i> Marquer payée
              </button>
              <div class="border-t border-gray-100 my-1"></div>
              <button
                @click="downloadPdf(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
            </template>

            <!-- Cancelled Actions -->
            <template v-else-if="inv.status === 'cancelled'">
              <button
                @click="viewInvoice(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-eye w-4 text-gray-400"></i> Aperçu
              </button>
              <button
                @click="downloadPdf(inv)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3"
              >
                <i class="fas fa-file-pdf w-4 text-red-500"></i> Télécharger PDF
              </button>
            </template>
          </div>
        </template>
      </div>
    </Teleport>

    <!-- MODALE CRÉATION PRODUIT (legacy) -->
    <div
      v-if="showProductModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-all"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all">
        <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
          <h3 class="text-lg font-bold text-[#062121]">Créer un produit</h3>
          <button @click="closeProductModal" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <InputLabel value="Nom / Désignation *" />
            <TextInput v-model="productModalForm.name" placeholder="Ex: Écran 24 pouces" autofocus />
            <InputError :message="productModalErrors.name" />
          </div>
          <div>
            <InputLabel value="Référence / SKU (optionnel)" />
            <TextInput v-model="productModalForm.sku" placeholder="Référence interne" />
            <InputError :message="productModalErrors.sku" />
          </div>
          <div>
            <InputLabel value="Prix d'achat HT *" />
            <TextInput type="number" step="0.01" min="0" v-model="productModalForm.price" placeholder="0.00" />
            <InputError :message="productModalErrors.price" />
          </div>
          <InputError :message="productModalErrors.server" />
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-2xl">
          <button type="button" @click="closeProductModal" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
            Annuler
          </button>
          <PrimaryButton :disabled="isCreatingProduct" @click="createProductAndSelect">
            <span v-if="isCreatingProduct">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Création...
            </span>
            <span v-else>Enregistrer le produit</span>
          </PrimaryButton>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
