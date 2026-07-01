<!-- views/settings/Users.vue -->
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
                Liste des utilisateurs
                <span
                  v-if="users.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                  >{{ users.length }}</span
                >
              </button>

              <button
                @click="changeTab('invite')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'invite'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-user-plus"></i>
                Inviter un utilisateur
              </button>
            </div>
          </div>

          <!-- Content: List -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement des utilisateurs...</p>
            </div>

            <div v-else-if="users.length === 0" class="text-center py-12">
              <i class="fas fa-users-slash text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun utilisateur dans cette organisation.</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Utilisateur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Rôle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="user in users"
                    :key="user.id"
                    class="group hover:bg-white/50 transition-colors duration-200"
                  >
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-[#062121] to-[#0F172A] flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-sm">
                          {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                          <div class="text-sm font-semibold text-gray-900">{{ user.name }}</div>
                          <div class="text-xs text-gray-400">{{ user.email }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span
                        :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          user.role === 'owner' ? 'bg-[#C5F82A]/20 text-[#062121]' :
                          user.role === 'manager' ? 'bg-blue-100 text-blue-800' :
                          user.role === 'accountant' ? 'bg-purple-100 text-purple-800' :
                          user.role === 'assistant-accountant' ? 'bg-teal-100 text-teal-800' :
                          'bg-gray-100 text-gray-600'
                        ]"
                      >
                        {{ user.role_label }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <span
                        :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          !user.email_verified_at ? 'bg-yellow-100 text-yellow-800' :
                          user.is_active ? 'bg-green-100 text-green-800' :
                          'bg-gray-200 text-gray-600'
                        ]"
                      >
                        {{ !user.email_verified_at ? 'Invitation en attente' :
                           user.is_active ? 'Actif' : 'Inactif' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          v-if="!user.is_owner"
                          @click="toggleStatus(user)"
                          :title="user.is_active ? 'Désactiver' : 'Activer'"
                          :class="[
                            'w-8 h-8 rounded-lg transition-all duration-200',
                            user.is_active
                              ? 'text-yellow-600 hover:bg-yellow-50 hover:text-yellow-800'
                              : 'text-green-600 hover:bg-green-50 hover:text-green-800'
                          ]"
                        >
                          <i :class="user.is_active ? 'fas fa-pause' : 'fas fa-play'"></i>
                        </button>
                        <button
                          v-if="!user.is_owner"
                          @click="deleteUser(user)"
                          title="Retirer de l'organisation"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-user-slash text-sm"></i>
                        </button>
                        <span v-else class="text-xs text-gray-400 italic">Propriétaire</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Content: Invitation Form -->
          <div v-else-if="activeTab === 'invite'" class="p-6 lg:p-8">
            <form @submit.prevent="submitInvite" class="space-y-6">
              <InputError :message="errors.server" />

              <div>
                <InputLabel for="email" value="Adresse email *" />
                <TextInput
                  id="email"
                  type="email"
                  class="mt-1 block w-full"
                  v-model="inviteForm.email"
                  placeholder="exemple@mail.com"
                  autofocus
                />
                <InputError class="mt-2" :message="errors.email" />
              </div>

              <div>
                <InputLabel for="role" value="Rôle *" />
                <CustomSelect
                  id="role"
                  v-model="inviteForm.role_id"
                  :options="roleOptions"
                  label-key="label"
                  value-key="id"
                  placeholder="Sélectionner un rôle"
                />
                <InputError class="mt-2" :message="errors.role_id" />
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Envoi en cours...
                  </span>
                  <span v-else>Envoyer l'invitation</span>
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import SettingsLayout from '../../layouts/SettingsLayout.vue'
import InputError from '../../components/InputError.vue'
import InputLabel from '../../components/InputLabel.vue'
import PrimaryButton from '../../components/PrimaryButton.vue'
import TextInput from '../../components/TextInput.vue'
import CustomSelect from '../../components/CustomSelect.vue'
import axios from 'axios'
import { success, error, validation, confirm } from '../../helpers/notifications'

const authStore = useAuthStore()

const activeTab = ref('list')
const isLoading = ref(false)
const isSubmitting = ref(false)
const users = ref([])
const availableRoles = ref([])

const inviteForm = reactive({
  email: '',
  role_id: '',
})

const errors = reactive({
  email: '',
  role_id: '',
  server: '',
})

const roleOptions = computed(() => {
  return availableRoles.value.map(role => ({
    id: role.id,
    label: role.label,
  }))
})

const fetchUsers = async () => {
  isLoading.value = true
  try {
    const companyId = authStore.currentCompanyId
    const { data } = await axios.get('/api/company/users', {
      params: { company_id: companyId },
    })
    users.value = data.users || []
    availableRoles.value = data.roles || []
  } catch {
    error('Erreur', 'Impossible de charger les utilisateurs.')
  } finally {
    isLoading.value = false
  }
}

const changeTab = (tab) => {
  activeTab.value = tab
  if (tab === 'invite') {
    inviteForm.email = ''
    inviteForm.role_id = ''
    errors.email = ''
    errors.role_id = ''
    errors.server = ''
  }
}

const submitInvite = async () => {
  errors.email = ''
  errors.role_id = ''
  errors.server = ''
  isSubmitting.value = true

  if (!inviteForm.email.trim()) {
    errors.email = "L'email est requis."
    isSubmitting.value = false
    return
  }
  if (!inviteForm.role_id) {
    errors.role_id = 'Veuillez sélectionner un rôle.'
    isSubmitting.value = false
    return
  }

  try {
    const companyId = authStore.currentCompanyId
    const payload = {
      email: inviteForm.email,
      role_id: inviteForm.role_id,
      company_id: companyId,
    }
    const { data } = await axios.post('/api/company/invitations', payload)
    users.value.push(data.user)
    success('Invitation envoyée', `Un email d'invitation a été envoyé à ${inviteForm.email}.`)
    changeTab('list')
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors
      if (e.email) errors.email = e.email[0]
      if (e.role_id) errors.role_id = e.role_id[0]
      validation(Object.values(e).flat().join('\n'))
    } else if (err.response?.data?.error) {
      errors.server = err.response.data.error
      validation(err.response.data.error)
    } else {
      error('Erreur', 'Impossible d\'envoyer l\'invitation.')
    }
  } finally {
    isSubmitting.value = false
  }
}

const toggleStatus = async (user) => {
  const action = user.is_active ? 'désactiver' : 'activer'
  const result = await confirm('Confirmer', `Voulez-vous ${action} cet utilisateur ?`)
  if (!result.isConfirmed) return

  try {
    const companyId = authStore.currentCompanyId
    await axios.patch(`/api/company/users/${user.id}/toggle-status`, {
      params: { company_id: companyId },
    })
    user.is_active = !user.is_active
    success('Statut mis à jour', `L'utilisateur est maintenant ${user.is_active ? 'actif' : 'inactif'}.`)
  } catch (err) {
    if (err.response?.data?.error) {
      error('Erreur', err.response.data.error)
    } else {
      error('Erreur', 'Impossible de modifier le statut.')
    }
  }
}

const deleteUser = async (user) => {
  const result = await confirm(
    'Retirer l\'utilisateur',
    `Voulez-vous vraiment retirer "${user.name}" de cette organisation ?`
  )
  if (!result.isConfirmed) return

  try {
    const companyId = authStore.currentCompanyId
    await axios.delete(`/api/company/users/${user.id}`, {
      params: { company_id: companyId },
    })
    users.value = users.value.filter(u => u.id !== user.id)
    success('Utilisateur retiré', `${user.name} a été retiré de l'organisation.`)
  } catch (err) {
    if (err.response?.data?.error) {
      error('Erreur', err.response.data.error)
    } else {
      error('Erreur', 'Impossible de retirer l\'utilisateur.')
    }
  }
}

onMounted(() => {
  fetchUsers()
})
</script>