<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { error } from '@/helpers/notifications'

const authStore = useAuthStore()

const activeTab = ref('activites')
const activities = ref([])
const loading = ref(true)

const tabs = [
  { key: 'statistiques', label: 'Statistiques', icon: 'fa-chart-pie' },
  { key: 'activites', label: 'Activités', icon: 'fa-bolt' },
  { key: 'documents', label: 'Documents générés', icon: 'fa-file' },
  { key: 'chiffre', label: 'Chiffre d\'affaires', icon: 'fa-euro-sign' },
  { key: 'debours', label: 'Débours', icon: 'fa-receipt' },
]

const fetchActivities = async () => {
  const companyId = authStore.currentCompanyId

  if (!companyId) {
    error('Erreur', 'Aucune entreprise sélectionnée.')
    loading.value = false
    return
  }

  try {
    const { data } = await axios.get('/api/dashboard/activities', {
      params: { company_id: companyId }
    })
    activities.value = data
  } catch (err) {
    const message = err.response?.data?.error || 'Erreur lors du chargement des activités.'
    error('Erreur', message)
  } finally {
    loading.value = false
  }
}

const formatDate = (datetime) => {
  const d = new Date(datetime)
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) +
    ' à ' +
    d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const getActionIcon = (activity) => {
  const event = activity.event || ''
  if (event === 'created') return 'fa-plus'
  if (event === 'updated') return 'fa-pen'
  if (event === 'deleted') return 'fa-trash'
  if (event === 'finalized' || activity.description?.includes('signé')) return 'fa-check'
  if (event === 'signed') return 'fa-check-circle'
  return 'fa-circle'
}

const getActionColor = (activity) => {
  const event = activity.event || ''
  if (event === 'created') return 'bg-blue-500'
  if (event === 'updated') return 'bg-yellow-500'
  if (event === 'deleted') return 'bg-red-500'
  if (event === 'finalized' || activity.description?.includes('signé')) return 'bg-green-500'
  if (event === 'signed') return 'bg-orange-500'
  return 'bg-gray-400'
}

const getActivityText = (activity) => {
  if (activity.description) {
    return activity.description
  }

  const subject = activity.subject
  if (!subject) return 'Activité inconnue'

  const type = subject.type || 'Élément'
  const number = subject.number || `#${subject.id}`
  const customer = subject.customer_name || subject.name || ''

  if (activity.event === 'created') {
    return `Nouveau ${type} ${number}${customer ? ` pour ${customer}` : ''}`
  }
  if (activity.event === 'updated') {
    return `${type} ${number} modifié${customer ? ` (${customer})` : ''}`
  }
  if (activity.event === 'deleted') {
    return `${type} ${number} supprimé`
  }
  if (activity.event === 'finalized' || activity.description?.includes('finalisé')) {
    return `${type} ${number} finalisé${customer ? ` pour ${customer}` : ''}`
  }
  if (activity.event === 'signed') {
    return `${type} ${number} signé${customer ? ` par ${customer}` : ''}`
  }
  return activity.description || 'Activité'
}

onMounted(() => {
  fetchActivities()
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          

          <!-- Onglets -->
          <div class="border-b border-gray-200 px-6 pt-2">
            <nav class="flex space-x-6 overflow-x-auto">
              <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2"
                :class="activeTab === tab.key
                  ? 'border-[#C5F82A] text-[#062121]'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              >
                <i :class="['fas', tab.icon]" class="text-xs"></i>
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Contenu des onglets -->
          <div class="p-6">
            <!-- Statistiques -->
            <div v-if="activeTab === 'statistiques'" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Activités</span>
                    <i class="fas fa-bolt text-blue-500 text-lg"></i>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-2">24</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Documents générés</span>
                    <i class="fas fa-file text-purple-500 text-lg"></i>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-2">12</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Chiffre d'affaires</span>
                    <i class="fas fa-euro-sign text-green-500 text-lg"></i>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-2">4 250,00 DH</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Débours</span>
                    <i class="fas fa-receipt text-orange-500 text-lg"></i>
                  </div>
                  <p class="text-2xl font-bold text-gray-900 mt-2">320,00 DH</p>
                </div>
              </div>
            </div>

            <!-- Activités (dynamique) -->
            <div v-if="activeTab === 'activites'">
              <div v-if="loading" class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                <p class="mt-2 text-gray-500">Chargement des activités...</p>
              </div>

              <div v-else-if="activities.length === 0" class="text-center py-12">
                <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Aucune activité pour le moment.</p>
              </div>

              <div v-else class="space-y-6">
                <div
                  v-for="activity in activities"
                  :key="activity.id"
                  class="flex items-start gap-4 border-b border-gray-100 pb-5 last:border-0"
                >
                  <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold"
                       :class="getActionColor(activity)">
                    <i :class="['fas', getActionIcon(activity)]"></i>
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-800 leading-relaxed">
                      {{ getActivityText(activity) }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                      {{ formatDate(activity.created_at) }} par {{ activity.user_name }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Autres onglets (statiques) -->
            <div v-if="activeTab === 'documents'" class="text-center py-12 text-gray-500">
              <i class="fas fa-file-alt text-4xl mb-3 block"></i>
              <p>Contenu des documents générés (statique)</p>
            </div>
            <div v-if="activeTab === 'chiffre'" class="text-center py-12 text-gray-500">
              <i class="fas fa-chart-line text-4xl mb-3 block"></i>
              <p>Graphiques du chiffre d'affaires (statique)</p>
            </div>
            <div v-if="activeTab === 'debours'" class="text-center py-12 text-gray-500">
              <i class="fas fa-wallet text-4xl mb-3 block"></i>
              <p>Liste des débours (statique)</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Tailwind gère tout */
</style>