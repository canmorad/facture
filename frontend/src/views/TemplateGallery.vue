<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import AuthenticatedLayout from "../layouts/AuthenticatedLayout.vue";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();
const isLoading = ref(false);
const isSaving = ref(false);
const selectedTemplate = ref("classic");
const currentTemplate = ref("classic");

const TEMPLATES = [
  {
    id: "classic",
    label: "Classique",
    desc: "Corporate & structuré",
    icon: "fa-building",
    preview: {
      accent: "#062121",
      style: "columns",
    },
  },
  {
    id: "modern",
    label: "Moderne",
    desc: "Bannière colorée & dynamique",
    icon: "fa-layer-group",
    preview: {
      accent: "#C5F82A",
      style: "banner",
    },
  },
  {
    id: "minimal",
    label: "Minimaliste",
    desc: "Épuré & élégant",
    icon: "fa-minus",
    preview: {
      accent: "#1a1a1a",
      style: "minimal",
    },
  },
  {
    id: "creative",
    label: "Créatif",
    desc: "Design latéral unique",
    icon: "fa-paint-brush",
    preview: {
      accent: "#C5F82A",
      style: "sidebar",
    },
  },
];

const fetchCurrentTemplate = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/api/company-settings");
    if (data && data.template_id) {
      currentTemplate.value = data.template_id;
      selectedTemplate.value = data.template_id;
    }
  } catch (error) {
    console.error("Erreur chargement template", error);
  } finally {
    isLoading.value = false;
  }
};

const selectTemplate = (templateId) => {
  selectedTemplate.value = templateId;
};

const saveTemplate = async () => {
  isSaving.value = true;
  try {
    await axios.patch("/api/company-settings/template", {
      template_id: selectedTemplate.value,
    });
    currentTemplate.value = selectedTemplate.value;
    Swal.fire({
      title: "Modèle enregistré !",
      text: "Le modèle sera utilisé pour vos prochaines factures et devis.",
      icon: "success",
      confirmButtonColor: "#062121",
    });
  } catch (error) {
    Swal.fire({
      title: "Erreur",
      text: "Impossible d'enregistrer le modèle.",
      icon: "error",
      confirmButtonColor: "#062121",
    });
  } finally {
    isSaving.value = false;
  }
};

const goBack = () => {
  router.push("/company");
};

onMounted(() => {
  fetchCurrentTemplate();
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        
        <!-- En-tête -->
        <div class="mb-8 flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-black text-[#062121]">Choisissez votre modèle de facture</h1>
            <p class="text-sm text-gray-500 mt-1">
              Sélectionnez le style qui correspond le mieux à votre image de marque
            </p>
          </div>
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <i class="fas fa-arrow-left text-xs"></i>
            Retour aux infos
          </button>
        </div>

        <!-- Grille des templates -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div
            v-for="tpl in TEMPLATES"
            :key="tpl.id"
            @click="selectTemplate(tpl.id)"
            class="cursor-pointer transition-all duration-200"
          >
            <div
              :class="[
                'bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all',
                selectedTemplate === tpl.id
                  ? 'ring-2 ring-[#C5F82A] ring-offset-2'
                  : 'ring-1 ring-gray-200',
              ]"
            >
              <!-- Zone de preview visuelle -->
              <div class="aspect-[3/4] relative overflow-hidden bg-white p-4">
                
                <!-- Preview Classic -->
                <div v-if="tpl.preview.style === 'columns'" class="w-full h-full">
                  <div class="h-6 w-full rounded mb-2" :style="{ background: tpl.preview.accent }"></div>
                  <div class="flex gap-2 mb-3">
                    <div class="flex-1 space-y-1">
                      <div class="h-2 w-3/4 bg-gray-200 rounded"></div>
                      <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                    </div>
                    <div class="flex-1 space-y-1 flex flex-col items-end">
                      <div class="h-2 w-3/4 bg-gray-200 rounded"></div>
                      <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                    </div>
                  </div>
                  <div class="h-3 w-full rounded mb-2" :style="{ background: tpl.preview.accent, opacity: 0.9 }"></div>
                  <div class="space-y-1 mb-3">
                    <div class="h-2 w-full bg-gray-100 rounded"></div>
                    <div class="h-2 w-full bg-gray-50 rounded"></div>
                    <div class="h-2 w-full bg-gray-100 rounded"></div>
                    <div class="h-2 w-full bg-gray-50 rounded"></div>
                  </div>
                  <div class="flex justify-end">
                    <div class="w-1/2 space-y-1">
                      <div class="h-2 w-full bg-gray-200 rounded"></div>
                      <div class="h-2 w-full bg-gray-200 rounded"></div>
                      <div class="h-3 w-full rounded" :style="{ background: tpl.preview.accent, opacity: 0.8 }"></div>
                    </div>
                  </div>
                </div>

                <!-- Preview Modern -->
                <div v-else-if="tpl.preview.style === 'banner'" class="w-full h-full">
                  <div class="h-12 w-full rounded-t-lg flex items-center justify-between px-3" :style="{ background: 'linear-gradient(135deg, #062121, #0d3d3d)' }">
                    <div class="space-y-1">
                      <div class="h-1.5 w-10 bg-white/80 rounded"></div>
                      <div class="h-1 w-7 bg-white/40 rounded"></div>
                    </div>
                    <div class="h-5 w-8 rounded flex items-center justify-center" :style="{ background: tpl.preview.accent }">
                      <div class="h-1.5 w-5 rounded" style="background: #062121"></div>
                    </div>
                  </div>
                  <div class="h-1 w-full" :style="{ background: tpl.preview.accent }"></div>
                  <div class="p-3 space-y-2">
                    <div class="flex gap-2">
                      <div class="flex-1 space-y-1">
                        <div class="h-2 w-2/3 bg-gray-200 rounded"></div>
                        <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                      </div>
                      <div class="flex-1 space-y-1 flex flex-col items-end">
                        <div class="h-2 w-2/3 bg-gray-200 rounded"></div>
                        <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                      </div>
                    </div>
                    <div class="h-2.5 w-full rounded" :style="{ background: tpl.preview.accent, opacity: 0.85 }"></div>
                    <div class="space-y-1">
                      <div class="h-2 w-full bg-gray-100 rounded"></div>
                      <div class="h-2 w-full bg-gray-50 rounded"></div>
                      <div class="h-2 w-full bg-gray-100 rounded"></div>
                    </div>
                    <div class="flex justify-end pt-1">
                      <div class="w-1/2 space-y-1">
                        <div class="h-2 w-full bg-gray-200 rounded"></div>
                        <div class="h-2.5 w-full rounded" :style="{ background: tpl.preview.accent, opacity: 0.9 }"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Preview Minimal -->
                <div v-else-if="tpl.preview.style === 'minimal'" class="w-full h-full bg-white">
                  <div class="flex justify-between items-start mb-3">
                    <div class="space-y-1">
                      <div class="h-2.5 w-12 bg-gray-900 rounded"></div>
                      <div class="h-1.5 w-8 bg-gray-200 rounded"></div>
                    </div>
                    <div class="space-y-1 flex flex-col items-end">
                      <div class="h-2 w-10 bg-gray-400 rounded"></div>
                      <div class="h-1.5 w-7 bg-gray-200 rounded"></div>
                    </div>
                  </div>
                  <div class="h-px w-full bg-gray-800 mb-3"></div>
                  <div class="flex gap-2 mb-3">
                    <div class="flex-1 space-y-1">
                      <div class="h-2 w-3/4 bg-gray-200 rounded"></div>
                      <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                    </div>
                    <div class="flex-1 space-y-1 flex flex-col items-end">
                      <div class="h-2 w-3/4 bg-gray-200 rounded"></div>
                      <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                    </div>
                  </div>
                  <div class="h-px w-full bg-gray-200 mb-2"></div>
                  <div class="space-y-1">
                    <div class="h-2 w-full bg-gray-100 rounded"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-2 w-full bg-gray-50 rounded"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-2 w-full bg-gray-100 rounded"></div>
                  </div>
                  <div class="flex justify-end mt-3">
                    <div class="w-1/2 space-y-1">
                      <div class="h-2 w-full bg-gray-200 rounded"></div>
                      <div class="h-px w-full bg-gray-300"></div>
                      <div class="flex justify-between">
                        <div class="h-2 w-1/2 bg-gray-900 rounded"></div>
                        <div class="h-2 w-1/3 bg-gray-900 rounded"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Preview Creative / Sidebar -->
                <div v-else-if="tpl.preview.style === 'sidebar'" class="w-full h-full flex">
                  <div class="w-10 h-full flex flex-col items-center py-2 gap-1.5" :style="{ background: tpl.preview.accent }">
                    <div class="w-4 h-4 rounded-full" style="background: #C5F82A; opacity: 0.9"></div>
                    <div class="h-1 w-3 bg-white/30 rounded"></div>
                    <div class="h-1 w-3 bg-white/30 rounded"></div>
                    <div class="h-1 w-3 bg-white/20 rounded"></div>
                    <div class="flex-1"></div>
                    <div class="w-3 h-0.5 rounded" style="background: #C5F82A"></div>
                  </div>
                  <div class="flex-1 p-2">
                    <div class="flex justify-between items-start mb-2">
                      <div>
                        <div class="h-2 w-10 bg-gray-800 rounded mb-0.5"></div>
                        <div class="h-1.5 w-7 bg-gray-300 rounded"></div>
                      </div>
                    </div>
                    <div class="rounded p-1 mb-2" style="background: rgba(197,248,42,0.15); border: 1px solid rgba(197,248,42,0.3)">
                      <div class="h-1.5 w-3/4 bg-gray-700 rounded mb-0.5"></div>
                      <div class="h-1.5 w-1/2 bg-gray-300 rounded"></div>
                    </div>
                    <div class="h-2 w-full rounded mb-1" :style="{ background: tpl.preview.accent, opacity: 0.85 }"></div>
                    <div class="space-y-0.5 mb-2">
                      <div class="h-1.5 w-full bg-gray-100 rounded"></div>
                      <div class="h-1.5 w-full bg-gray-50 rounded"></div>
                      <div class="h-1.5 w-full bg-gray-100 rounded"></div>
                    </div>
                    <div class="flex justify-end">
                      <div class="w-12 h-3 rounded" :style="{ background: tpl.preview.accent, opacity: 0.85 }"></div>
                    </div>
                  </div>
                </div>

                <!-- Badge actif -->
                <div
                  v-if="currentTemplate === tpl.id"
                  class="absolute top-2 right-2 bg-[#C5F82A] text-[#062121] text-[10px] font-bold px-2 py-0.5 rounded-full"
                >
                  Actif
                </div>
              </div>

              <!-- Footer du template -->
              <div class="p-4 border-t border-gray-100">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="flex items-center gap-2">
                      <i :class="['fas', tpl.icon, 'text-sm', selectedTemplate === tpl.id ? 'text-[#062121]' : 'text-gray-400']"></i>
                      <h3 class="font-bold text-gray-800">{{ tpl.label }}</h3>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ tpl.desc }}</p>
                  </div>
                  <div
                    v-if="selectedTemplate === tpl.id"
                    class="w-6 h-6 rounded-full flex items-center justify-center"
                    style="background: #C5F82A"
                  >
                    <i class="fas fa-check text-[#062121] text-xs"></i>
                  </div>
                  <div
                    v-else
                    class="w-6 h-6 rounded-full border-2 border-gray-300"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bouton de validation -->
        <div class="flex justify-center mt-8">
          <button
            @click="saveTemplate"
            :disabled="isSaving || selectedTemplate === currentTemplate"
            class="inline-flex items-center gap-3 px-8 py-3 rounded-xl text-white font-bold transition-all hover:opacity-90 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
            style="background-color: #062121"
          >
            <i v-if="isSaving" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-save"></i>
            <span v-if="isSaving">Enregistrement...</span>
            <span v-else-if="selectedTemplate === currentTemplate">Modèle déjà actif</span>
            <span v-else>Appliquer ce modèle</span>
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>