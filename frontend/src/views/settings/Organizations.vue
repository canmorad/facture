<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <!-- Tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
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
                Liste des organisations
                <span
                  v-if="organizations.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ organizations.length }}</span
                >
              </button>

              <button
                @click="createOrganization"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'add'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-plus-circle"></i>
                Ajouter une organisation
              </button>
            </div>
          </div>

          <!-- Table content -->
          <div class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des organisations...</p>
            </div>

            <div v-else-if="organizations.length === 0" class="text-center py-12">
              <i class="fas fa-building text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Vous ne faites partie d'aucune organisation.</p>
              <button
                @click="createOrganization"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus"></i> Créer votre première organisation
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Organisation</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Propriétaire</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Rôle</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="org in organizations"
                    :key="org.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <!-- <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-[#062121] to-[#0F172A] flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-sm">
                          {{ org.name.charAt(0).toUpperCase() }}
                        </div> -->
                        <div>
                          <div class="text-sm font-semibold text-gray-900">{{ org.name }}</div>
                          <div class="text-xs text-gray-400">ID #{{ org.id }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="org.is_owner" class="inline-flex items-center text-green-600">
                        <i class="fas fa-check-circle text-base mr-1"></i>
                        <span class="text-sm font-medium">Propriétaire</span>
                      </span>
                      <span v-else class="text-sm text-gray-400">—</span>
                    </td>
                    <td class="px-4 py-4 text-sm text-gray-600">
                      {{ org.role || '—' }}
                    </td>
                    <td class="px-4 py-4 text-right">
                      <button
                        v-if="!org.is_owner"
                        @click="leaveOrganization(org)"
                        class="px-3 py-1.5 rounded-lg bg-[#FFF1F2] text-[#E11D48] hover:bg-[#FFE4E6] text-sm font-medium transition-all duration-200"
                      >
                        Quitter l'organisation
                      </button>
                      <span v-else class="text-xs text-gray-400 italic">Propriétaire</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SettingsLayout from '../../layouts/SettingsLayout.vue'
import axios from 'axios'
import { success, error, confirm } from '../../helpers/notifications'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const isLoading = ref(false)
const organizations = ref([])

const activeTab = computed(() => {
  return 'list'
})

const fetchOrganizations = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/api/user/organizations')
    organizations.value = data
  } catch {
    error('Erreur', 'Impossible de charger vos organisations.')
  } finally {
    isLoading.value = false
  }
}

const createOrganization = () => {
  router.push({ name: 'settings.coordinates', query: { mode: 'create' } })
}

const changeTab = (tab) => {
  if (tab === 'add') {
    createOrganization()
  }
}

const leaveOrganization = async (org) => {
  const result = await confirm(
    'Quitter l\'organisation',
    `Voulez-vous vraiment quitter "${org.name}" ? Vous perdrez l'accès à ses données.`
  )
  if (!result.isConfirmed) return

  try {
    await axios.delete(`/api/organizations/${org.id}/leave`)
    organizations.value = organizations.value.filter(o => o.id !== org.id)
    success('Départ effectué', `Vous avez quitté "${org.name}".`)
  } catch {
    error('Erreur', 'Impossible de quitter cette organisation.')
  }
}

onMounted(fetchOrganizations)
</script>