<script setup>
import { ref, computed, watch } from 'vue';
import { error, success } from '@/helpers/notifications';
import axios from 'axios';

const props = defineProps({
  modelValue: {
    type: File,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  autoAnalyze: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['update:modelValue', 'analyzed', 'analyzing', 'error']);

const isDragging = ref(false);
const isAnalyzing = ref(false);
const analysisProgress = ref(0);
const previewUrl = ref(null);
const extractedData = ref(null);
const analysisConfidence = ref(0);

const fileExtensions = '.pdf,.jpg,.jpeg,.png,.webp,.bmp,.tiff';
const maxFileSize = 20 * 1024; // 20MB

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatFileName = (fileName) => {
  const name = fileName.split('.').slice(0, -1).join('.');
  return name.length > 30 ? name.substring(0, 27) + '...' : name;
};

// Drag & Drop handlers
const handleDragEnter = (e) => {
  if (props.disabled) return;
  e.preventDefault();
  isDragging.value = true;
};

const handleDragLeave = (e) => {
  if (props.disabled) return;
  e.preventDefault();
  isDragging.value = false;
};

const handleDragOver = (e) => {
  if (props.disabled) return;
  e.preventDefault();
};

const handleDrop = (e) => {
  if (props.disabled) return;
  e.preventDefault();
  isDragging.value = false;

  const files = e.dataTransfer.files;
  if (files.length > 0) {
    validateAndSelectFile(files[0]);
  }
};

const handleFileSelect = (e) => {
  const files = e.target.files;
  if (files.length > 0) {
    validateAndSelectFile(files[0]);
  }
};

const validateAndSelectFile = (file) => {
  const extension = '.' + file.name.split('.').pop().toLowerCase();
  const acceptedExtensions = fileExtensions.split(',').map(ext => ext.trim().toLowerCase());

  if (!acceptedExtensions.includes(extension) && !acceptedExtensions.includes('*')) {
    error('Type de fichier invalide', `Les types acceptés sont: ${fileExtensions}`);
    return;
  }

  if (file.size > maxFileSize * 1024) {
    error('Fichier trop volumineux', `La taille maximale est de ${formatFileSize(maxFileSize * 1024)}`);
    return;
  }

  // Générer preview pour images
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      previewUrl.value = e.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    previewUrl.value = null;
  }

  emit('update:modelValue', file);

  // Lancer l'analyse automatiquement si activé
  if (props.autoAnalyze) {
    analyzeInvoice(file);
  }
};

const analyzeInvoice = async (file) => {
  if (!file || isAnalyzing.value) return;

  isAnalyzing.value = true;
  analysisProgress.value = 0;
  extractedData.value = null;
  emit('analyzing', true);

  try {
    const formData = new FormData();
    formData.append('file', file);

    // Simulation de progression (l'API Gemini ne renvoie pas de progression)
    const progressInterval = setInterval(() => {
      if (analysisProgress.value < 90) {
        analysisProgress.value += 10;
      }
    }, 1000);

    const { data } = await axios.post('/api/purchase-invoices/analyze', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 180000, // 180 secondes timeout pour l'analyse (3 minutes)
    });

    clearInterval(progressInterval);
    analysisProgress.value = 100;

    if (data.success && data.data) {
      extractedData.value = data.data;
      analysisConfidence.value = data.confidence || 0;

      emit('analyzed', {
        data: data.data,
        confidence: data.confidence,
        warnings: data.warnings || [],
      });

      // Message de succès basé sur le score de confiance
      if (data.confidence >= 90) {
        success('Analyse réussie !', 'Facture analysée avec succès. Veuillez vérifier les données extraites.');
      } else if (data.confidence >= 70) {
        success('Analyse terminée', 'Données extraites avec confiance moyenne. Vérification recommandée.');
      } else {
        success('Analyse partielle', 'Certaines données n\'ont pas pu être extraites. Veuillez compléter le formulaire.');
      }
    } else {
      throw new Error(data.error || 'Échec de l\'analyse');
    }

  } catch (err) {
    console.error('Erreur analyse facture:', err);
    emit('error', err);
    error(
      'Erreur d\'analyse',
      err.response?.data?.error || 'L\'analyse de la facture a échoué. Veuillez réessayer ou remplir le formulaire manuellement.'
    );
  } finally {
    isAnalyzing.value = false;
    emit('analyzing', false);
  }
};

const removeFile = () => {
  emit('update:modelValue', null);
  previewUrl.value = null;
  extractedData.value = null;
  analysisConfidence.value = 0;
};

const triggerFileInput = () => {
  document.getElementById('invoice-ai-input').click();
};

// Ré-analyser le fichier
const reanalyze = () => {
  if (props.modelValue) {
    analyzeInvoice(props.modelValue);
  }
};

// Confiance color
const confidenceColor = computed(() => {
  if (analysisConfidence.value >= 90) return 'text-green-600';
  if (analysisConfidence.value >= 70) return 'text-yellow-600';
  return 'text-red-600';
});

const confidenceLabel = computed(() => {
  if (analysisConfidence.value >= 90) return 'Excellent';
  if (analysisConfidence.value >= 70) return 'Moyen';
  if (analysisConfidence.value >= 50) return 'Faible';
  return 'Très faible';
});

// Exposer la méthode d'analyse
defineExpose({
  analyzeInvoice,
  reanalyze,
  isAnalyzing,
  extractedData,
});
</script>

<template>
  <div
    class="invoice-ai-uploader"
    @dragenter="handleDragEnter"
    @dragleave="handleDragLeave"
    @dragover="handleDragOver"
    @drop="handleDrop"
  >
    <input
      id="invoice-ai-input"
      type="file"
      :accept="fileExtensions"
      :disabled="disabled || isAnalyzing"
      class="hidden"
      @change="handleFileSelect"
    />

    <!-- Zone vide avec Drag & Drop -->
    <div
      v-if="!modelValue"
      :class="[
        'relative border-2 border-dashed rounded-2xl p-8 text-center transition-all duration-300',
        isDragging ? 'border-[#C5F82A] bg-[#C5F82A]/10 scale-[1.02]' : 'border-gray-300 hover:border-[#C5F82A]/50 bg-gray-50/50',
        (disabled || isAnalyzing) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
      ]"
      @click="!disabled && !isAnalyzing && triggerFileInput()"
    >
      <!-- État d'analyse -->
      <div v-if="isAnalyzing" class="space-y-4">
        <div class="flex justify-center">
          <div class="relative">
            <div class="absolute inset-0 rounded-full border-4 border-gray-200"></div>
            <div class="absolute inset-0 rounded-full border-4 border-t-[#C5F82A] border-r-transparent border-b-transparent border-l-transparent animate-spin"></div>
            <svg class="w-16 h-16 text-[#C5F82A] animate-pulse" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
              <span class="text-xs font-bold text-gray-700">{{ analysisProgress }}%</span>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-base font-semibold text-gray-700">
            Analyse de votre facture par l'IA en cours...
          </p>
          <p class="text-sm text-gray-500">
            Extraction automatique des données (fournisseur, articles, montants)
          </p>
        </div>

        <div class="w-full max-w-xs mx-auto bg-gray-200 rounded-full h-2 overflow-hidden">
          <div
            class="bg-gradient-to-r from-[#C5F82A] to-[#A3D82A] h-2 rounded-full transition-all duration-500 ease-out"
            :style="{ width: analysisProgress + '%' }"
          ></div>
        </div>

        <p class="text-xs text-gray-400">
          Cela peut prendre 10 à 30 secondes...
        </p>
      </div>

      <!-- État initial -->
      <div v-else class="space-y-5">
        <!-- Icône animée -->
        <div class="flex justify-center">
          <div class="relative">
            <div class="absolute inset-0 bg-[#C5F82A]/20 rounded-full blur-xl animate-pulse"></div>
            <svg
              :class="['w-20 h-20 transition-colors duration-300', isDragging ? 'text-[#C5F82A]' : 'text-gray-400']"
              fill="none"
              viewBox="0 0 24 24"
            >
              <path
                stroke="currentColor"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
              />
            </svg>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-lg font-semibold text-gray-700">
            <span v-if="isDragging" class="text-[#C5F82A]">✨ Relâchez pour analyser</span>
            <span v-else>Importez votre facture d'achat</span>
          </p>
          <p class="text-sm text-gray-500">
            L'IA va extraire automatiquement toutes les données
          </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
          <button
            type="button"
            :disabled="disabled"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#062121] text-white rounded-xl text-sm font-semibold hover:bg-[#0F2A2A] transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Parcourir
          </button>

          <span class="text-sm text-gray-400">ou</span>

          <button
            type="button"
            class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all duration-200"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Coller depuis presse-papier
          </button>
        </div>

        <div class="flex items-center justify-center gap-4 text-xs text-gray-400">
          <span>Formats: PDF, JPG, PNG, WebP</span>
          <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
          <span>Max: {{ formatFileSize(maxFileSize * 1024) }}</span>
        </div>

        <!-- Badge IA -->
        <div class="flex justify-center pt-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gradient-to-r from-purple-50 to-pink-50 rounded-full text-xs font-medium text-purple-700 border border-purple-200">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            Propulsé par Google Gemini AI
          </span>
        </div>
      </div>
    </div>

    <!-- Fichier uploadé -->
    <div v-else class="space-y-4">
      <!-- Aperçu du fichier -->
      <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
        <div class="flex items-start gap-4">
          <!-- Preview -->
          <div class="flex-shrink-0">
            <div v-if="previewUrl" class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200">
              <img :src="previewUrl" class="w-full h-full object-cover" />
            </div>
            <div v-else class="w-20 h-20 rounded-lg bg-gradient-to-br from-red-50 to-red-100 flex items-center justify-center border border-red-200">
              <svg class="w-10 h-10 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zm-3 9h2v5H9v-5zm0-4h2V9H9v4zm4 4h2v5h-2v-5zm0-4h2V9h-2v4zm-8 4h2v5H5v-5zm0-4h2V9H5v4z" />
              </svg>
            </div>
          </div>

          <!-- Info fichier -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate" :title="modelValue.name">
                  {{ formatFileName(modelValue.name) }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">{{ formatFileSize(modelValue.size) }}</p>
              </div>

              <button
                type="button"
                @click="removeFile"
                class="flex-shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                title="Supprimer"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Statut d'analyse -->
            <div v-if="isAnalyzing" class="mt-3">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span>Analyse en cours... {{ analysisProgress }}%</span>
              </div>
            </div>

            <!-- Résultat d'analyse -->
            <div v-else-if="extractedData" class="mt-3 space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 rounded-md">
                  <svg class="w-3.5 h-3.5 text-green-600" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-xs font-medium text-green-700">Analyse terminée</span>
                </div>

                <div class="flex items-center gap-1 text-xs">
                  <span class="text-gray-500">Confiance:</span>
                  <span :class="['font-semibold', confidenceColor]">{{ analysisConfidence }}%</span>
                  <span class="text-gray-400">({{ confidenceLabel }})</span>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 text-xs">
                <span v-if="extractedData.fournisseur?.name" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-md text-gray-700">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1" />
                  </svg>
                  {{ extractedData.fournisseur.name }}
                </span>
                <span v-if="extractedData.supplier_invoice_number" class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 rounded-md text-blue-700">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  {{ extractedData.supplier_invoice_number }}
                </span>
                <span v-if="extractedData.items?.length" class="inline-flex items-center gap-1 px-2 py-1 bg-purple-50 rounded-md text-purple-700">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  {{ extractedData.items.length }} ligne(s)
                </span>
                <span v-if="extractedData.amount_ttc" class="inline-flex items-center gap-1 px-2 py-1 bg-green-50 rounded-md text-green-700">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ Number(extractedData.amount_ttc).toLocaleString('fr-MA') }} {{ extractedData.currency || 'MAD' }}
                </span>
              </div>
            </div>

            <!-- Bouton ré-analyser -->
            <div v-else class="mt-3">
              <button
                type="button"
                @click="reanalyze"
                class="inline-flex items-center gap-2 text-xs text-[#062121] hover:text-[#0F2A2A] font-medium"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Ré-analyser
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Formulaire pré-rempli (summary) -->
      <div v-if="extractedData && !isAnalyzing" class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold text-green-900">Données extraites avec succès !</p>
            <p class="text-xs text-green-700 mt-1">
              Le formulaire a été pré-rempli. Veuillez vérifier et corriger si nécessaire avant d'enregistrer.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invoice-ai-uploader {
  @apply relative;
}

/* Animation subtile pour la zone de drop */
@keyframes pulse-subtle {
  0%, 100% {
    opacity: 0.5;
  }
  50% {
    opacity: 0.8;
  }
}

.animate-pulse-subtle {
  animation: pulse-subtle 2s ease-in-out infinite;
}
</style>
