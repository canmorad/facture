<script setup>
import { ref, computed } from 'vue';
import { error } from '@/helpers/notifications';

const props = defineProps({
  modelValue: {
    type: File,
    default: null,
  },
  accept: {
    type: String,
    default: '.pdf,.jpg,.jpeg,.png,.bmp,.tiff',
  },
  maxSize: {
    type: Number,
    default: 10240,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'upload-start', 'upload-complete', 'upload-error']);

const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const previewUrl = ref(null);

const fileExtensions = computed(() => {
  return props.accept.split(',').map(ext => ext.trim()).join(', ');
});

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

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
  const acceptedExtensions = props.accept.split(',').map(ext => ext.trim().toLowerCase());

  if (!acceptedExtensions.includes(extension) && !acceptedExtensions.includes('*')) {
    error('Type de fichier invalide', `Les types acceptés sont: ${fileExtensions.value}`);
    return;
  }

  if (file.size > props.maxSize * 1024) {
    error('Fichier trop volumineux', `La taille maximale est de ${formatFileSize(props.maxSize * 1024)}`);
    return;
  }

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
};

const removeFile = () => {
  emit('update:modelValue', null);
  previewUrl.value = null;
};

const triggerFileInput = () => {
  document.getElementById('file-input').click();
};
</script>

<template>
  <div
    class="relative"
    @dragenter="handleDragEnter"
    @dragleave="handleDragLeave"
    @dragover="handleDragOver"
    @drop="handleDrop"
  >
    <input
      id="file-input"
      type="file"
      :accept="accept"
      :disabled="disabled"
      class="hidden"
      @change="handleFileSelect"
    />

    <div
      v-if="!modelValue"
      :class="[
        'border-2 border-dashed rounded-xl p-8 text-center transition-all duration-200 cursor-pointer',
        isDragging ? 'border-[#C5F82A] bg-[#C5F82A]/5' : 'border-gray-300 hover:border-[#C5F82A]',
        disabled ? 'opacity-50 cursor-not-allowed' : ''
      ]"
      @click="!disabled && triggerFileInput()"
    >
      <div v-if="isUploading" class="space-y-3">
        <div class="flex justify-center">
          <svg class="animate-spin h-10 w-10 text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
        <p class="text-sm text-gray-600">Traitement OCR en cours...</p>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div class="bg-[#C5F82A] h-2 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="flex justify-center">
          <i :class="['fas text-4xl text-gray-400', isDragging ? 'fa-cloud-upload-alt text-[#C5F82A]' : 'fa-cloud-upload-alt']"></i>
        </div>
        <div>
          <p class="text-sm font-semibold text-gray-700">
            <span v-if="isDragging">Relâchez pour importer</span>
            <span v-else>Glissez-déposez votre facture ici</span>
          </p>
          <p class="text-xs text-gray-500 mt-1">ou</p>
        </div>
        <button
          type="button"
          :disabled="disabled"
          class="inline-flex items-center gap-2 px-4 py-2 bg-[#062121] text-white rounded-lg text-sm font-semibold hover:bg-[#0F2A2A] transition-colors disabled:opacity-50"
        >
          <i class="fas fa-folder-open"></i> Parcourir
        </button>
        <p class="text-xs text-gray-400">
          Formats acceptés: {{ fileExtensions }} (Max {{ formatFileSize(maxSize * 1024) }})
        </p>
      </div>
    </div>

    <div v-else class="border border-gray-300 rounded-xl p-4 bg-white">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div v-if="previewUrl" class="w-16 h-16 rounded-lg overflow-hidden border border-gray-200">
            <img :src="previewUrl" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-16 h-16 rounded-lg bg-gray-100 flex items-center justify-center">
            <i class="fas fa-file-pdf text-2xl text-red-500"></i>
          </div>

          <div>
            <p class="text-sm font-semibold text-gray-700">{{ modelValue.name }}</p>
            <p class="text-xs text-gray-500">{{ formatFileSize(modelValue.size) }}</p>
          </div>
        </div>

        <button
          type="button"
          @click="removeFile"
          class="w-8 h-8 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>
