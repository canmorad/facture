<script setup>
import { ref, onMounted, watch, nextTick } from "vue";
import SignaturePad from "signature_pad";
import { success, error, confirm } from "../helpers/notifications";
import InputError from "./InputError.vue";

const props = defineProps({
    modelValue: { type: Object, default: null },
    preview: { type: String, default: null },
    error: { type: String, default: null },
    date: { type: String, default: null }
});

const emit = defineEmits(['update:modelValue', 'update:preview', 'save']);

const showModal = ref(false);
const canvasRef = ref(null);
let signaturePad = null;

const initSignaturePad = () => {
    if (!canvasRef.value) return;
    const canvas = canvasRef.value;
    const container = canvas.parentElement;
    canvas.width = container.clientWidth;
    canvas.height = 200;

    if (signaturePad) signaturePad.off();
    
    signaturePad = new SignaturePad(canvas, {
        penColor: "#062121",
        backgroundColor: "#ffffff",
        minWidth: 1,
        maxWidth: 3,
    });

    if (props.preview) {
        const img = new Image();
        img.onload = () => signaturePad.fromDataURL(props.preview);
        img.src = props.preview;
    }
};

const openModal = async () => {
    showModal.value = true;
    await nextTick();
    setTimeout(initSignaturePad, 100);
};

const closeModal = () => {
    showModal.value = false;
    signaturePad?.clear();
};

const clearSignature = () => signaturePad?.clear();

const dataURLToFile = (dataURL, filename) => {
    const arr = dataURL.split(",");
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8 = new Uint8Array(n);
    while (n--) u8[n] = bstr.charCodeAt(n);
    return new File([u8], filename, { type: mime });
};

const saveSignature = () => {
    if (signaturePad && !signaturePad.isEmpty()) {
        const dataURL = signaturePad.toDataURL();
        const file = dataURLToFile(dataURL, `signature_${Date.now()}.png`);
        emit('update:modelValue', file);
        emit('update:preview', dataURL);
        emit('save', { file, preview: dataURL });
        closeModal();
    } else if (signaturePad?.isEmpty() && props.preview) {
        closeModal();
    } else {
        error("Signature requise", "Veuillez dessiner votre signature avant de sauvegarder");
    }
};

const removeSignature = async () => {
    const result = await confirm("Supprimer la signature ?", "Cette action est irréversible.");
    if (result.isConfirmed) {
        emit('update:modelValue', null);
        emit('update:preview', null);
        success("Supprimée !", "La signature a été supprimée.");
    }
};
</script>

<template>
    <div>
        <div class="bg-gradient-to-br from-gray-50 to-white rounded-xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-4">
                        <i class="fas fa-signature text-[#C5F82A] text-lg"></i>
                        <span class="text-sm font-bold text-[#062121]">Signature numérique</span>
                    </div>
                    
                    <div v-if="preview" class="space-y-3">
                        <div class="relative inline-block">
                            <img :src="preview" alt="Signature" class="h-20 object-contain border-2 rounded-xl p-2 bg-white shadow-sm" />
                            <button 
                                type="button"
                                @click="removeSignature"
                                class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white hover:bg-red-600 transition-all duration-200 flex items-center justify-center shadow-md hover:scale-110"
                                title="Supprimer"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>
                        <p v-if="date" class="text-xs text-gray-500">
                            <i class="far fa-calendar-alt mr-1"></i>
                            Signé le : {{ date }}
                        </p>
                    </div>
                    
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="openModal"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                            :class="preview 
                                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' 
                                : 'bg-[#C5F82A] text-[#062121] hover:shadow-lg hover:-translate-y-0.5'"
                        >
                            <i :class="preview ? 'fas fa-edit' : 'fas fa-pen-fancy'"></i>
                            {{ preview ? 'Modifier la signature' : 'Créer ma signature' }}
                        </button>
                    </div>
                </div>
                
                <div class="hidden sm:block w-32 text-center p-3 bg-[#C5F82A]/10 rounded-lg">
                    <i class="fas fa-shield-alt text-2xl text-[#C5F82A] mb-1"></i>
                    <p class="text-[10px] text-gray-600">Document légal<br>contraignant</p>
                </div>
            </div>
            <p v-if="!preview" class="text-xs text-gray-500 mt-4">
                <i class="fas fa-info-circle mr-1"></i>
                Cliquez pour ajouter votre signature numérique
            </p>
            <InputError v-if="error" :message="error" class="mt-2" />
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-all"></div>
            
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-[#062121] to-[#0a3a3a] rounded-t-2xl p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[#C5F82A]/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-pen-fancy text-[#C5F82A] text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">Signature numérique</h3>
                                <p class="text-xs text-white/70 mt-0.5">Dessinez votre signature dans la zone ci-dessous</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="text-white/70 hover:text-white transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="relative">
                        <div class="absolute inset-0 pointer-events-none flex flex-col justify-between p-4">
                            <div class="border-b border-dashed border-gray-200"></div>
                            <div class="border-b border-dashed border-gray-200"></div>
                            <div class="border-b border-dashed border-gray-200"></div>
                        </div>
                        
                        <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-white shadow-inner">
                            <canvas 
                                ref="canvasRef" 
                                style="width: 100%; height: 200px; touch-action: none; display: block; cursor: crosshair;"
                            ></canvas>
                        </div>
                        
                        <div class="absolute bottom-2 right-2 text-xs text-gray-400 bg-white/90 px-2 py-1 rounded">
                            <i class="fas fa-mouse-pointer"></i> Dessinez ici
                        </div>
                    </div>
                </div>
                
                <div class="px-6 pb-6 flex flex-wrap justify-between items-center gap-3">
                    <button 
                        type="button" 
                        @click="clearSignature"
                        class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors flex items-center gap-2"
                    >
                        <i class="fas fa-eraser"></i>
                        Effacer tout
                    </button>
                    
                    <div class="flex gap-2">
                        <button 
                            type="button" 
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
                        >
                            Annuler
                        </button>
                        <button 
                            type="button" 
                            @click="saveSignature"
                            class="px-6 py-2 text-sm font-semibold text-white bg-gradient-to-r from-[#062121] to-[#0a3a3a] hover:shadow-lg rounded-lg transition-all duration-200 transform hover:-translate-y-0.5 flex items-center gap-2"
                        >
                            <i class="fas fa-save"></i>
                            Sauvegarder
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>