<script setup>
import { reactive, ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import InputError from '@/components/InputError.vue'
import InputLabel from '@/components/InputLabel.vue'
import PrimaryButton from '@/components/PrimaryButton.vue'
import TextInput from '@/components/TextInput.vue'
import axios from 'axios'
import { success, error, validation } from '@/helpers/notifications'

const authStore = useAuthStore()

const activeTab = ref('profile')
const isLoading = ref(false)
const isSubmitting = ref(false)

// Profile form
const profileForm = reactive({
  name: '',
  email: '',
  avatar: null,
})

// Password form
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

// Errors
const profileErrors = reactive({
  name: '',
  email: '',
  avatar: '',
  server: '',
})

const passwordErrors = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
  server: '',
})

// Avatar preview
const avatarPreview = ref(null)

// Computed user data
const userData = computed(() => authStore.user)

// Load user profile
const loadProfile = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/api/user/profile')
    profileForm.name = data.name || ''
    profileForm.email = data.email || ''
    avatarPreview.value = data.avatar || null
  } catch (err) {
    error('Erreur', 'Impossible de charger votre profil.')
  } finally {
    isLoading.value = false
  }
}

// Handle avatar upload
const handleAvatarUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  // Validate file type
  const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!allowedTypes.includes(file.type)) {
    profileErrors.avatar = 'Format d\'image non supporté. Utilisez JPEG, PNG ou GIF.'
    return
  }

  // Validate file size (2MB max)
  if (file.size > 2 * 1024 * 1024) {
    profileErrors.avatar = 'L\'image ne doit pas dépasser 2MB.'
    return
  }

  profileErrors.avatar = ''
  profileForm.avatar = file

  const reader = new FileReader()
  reader.onload = (e) => {
    avatarPreview.value = e.target.result
  }
  reader.readAsDataURL(file)
}

// Remove avatar
const removeAvatar = () => {
  profileForm.avatar = null
  avatarPreview.value = null
}

// Submit profile update
const submitProfile = async () => {
  Object.keys(profileErrors).forEach((k) => (profileErrors[k] = ''))
  isSubmitting.value = true

  const formData = new FormData()
  formData.append('name', profileForm.name)
  formData.append('email', profileForm.email)
  if (profileForm.avatar) {
    formData.append('avatar', profileForm.avatar)
  }

  try {
    const { data } = await axios.put('/api/user/profile', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    // Update auth store user data
    authStore.setUser(data)

    success('Profil mis à jour', 'Vos informations ont été enregistrées avec succès.')
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors
      Object.keys(e).forEach((k) => {
        if (profileErrors[k] !== undefined) profileErrors[k] = e[k][0]
      })
      validation(Object.values(e).flat().join('\n'))
    } else {
      profileErrors.server = 'Une erreur est survenue.'
      error('Erreur', 'Impossible de mettre à jour votre profil.')
    }
  } finally {
    isSubmitting.value = false
  }
}

// Submit password update
const submitPassword = async () => {
  Object.keys(passwordErrors).forEach((k) => (passwordErrors[k] = ''))
  isSubmitting.value = true

  try {
    await axios.put('/api/user/password', passwordForm)
    success('Mot de passe modifié', 'Votre mot de passe a été mis à jour avec succès.')

    // Reset form
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data.errors
      Object.keys(e).forEach((k) => {
        if (passwordErrors[k] !== undefined) passwordErrors[k] = e[k][0]
      })
      validation(Object.values(e).flat().join('\n'))
    } else {
      passwordErrors.server = 'Une erreur est survenue.'
      error('Erreur', 'Impossible de mettre à jour votre mot de passe.')
    }
  } finally {
    isSubmitting.value = false
  }
}

// Change tab
const changeTab = (tab) => {
  activeTab.value = tab
}

onMounted(() => {
  loadProfile()
})
</script>

<template>
  <SettingsLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <!-- Tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex gap-6">
              <button
                @click="changeTab('profile')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'profile'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-user"></i>
                Profil
              </button>

              <button
                @click="changeTab('password')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'password'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-lock"></i>
                Mot de passe
              </button>
            </div>
          </div>

          <!-- Profile Tab -->
          <div v-if="activeTab === 'profile'" class="p-6 lg:p-8">
            <div v-if="isLoading" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement...</p>
            </div>

            <form v-else @submit.prevent="submitProfile" class="space-y-6">
              <InputError :message="profileErrors.server" />

              <!-- Avatar Upload -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2 pb-6 border-b border-gray-100">
                <div class="flex flex-col items-center sm:flex-row sm:items-start gap-6">
                  <div class="relative flex-shrink-0">
                    <div class="h-24 w-24 rounded-full bg-gray-100 border-2 border-[#C5F82A] flex items-center justify-center overflow-hidden">
                      <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar" class="h-full w-full object-cover" />
                      <svg v-else class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <label class="absolute bottom-0 right-0 cursor-pointer rounded-full bg-[#062121] p-1.5 shadow-md hover:bg-[#062121]/90">
                      <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <input type="file" accept="image/jpeg,image/png,image/jpg,image/gif" @change="handleAvatarUpload" class="hidden" />
                    </label>
                  </div>
                  <div class="text-center sm:text-left">
                    <p class="text-sm font-medium text-[#062121]">Photo de profil</p>
                    <p class="text-xs text-gray-500 mt-1">Format PNG, JPG, GIF. Taille max 2MB</p>
                    <button
                      v-if="avatarPreview"
                      type="button"
                      @click="removeAvatar"
                      class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium"
                    >
                      Supprimer la photo
                    </button>
                    <InputError class="mt-2" :message="profileErrors.avatar" />
                  </div>
                </div>
              </div>

              <!-- Profile Information -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <InputLabel for="name" value="Nom complet *" />
                  <TextInput
                    id="name"
                    type="text"
                    v-model="profileForm.name"
                    placeholder="Votre nom"
                    autofocus
                  />
                  <InputError :message="profileErrors.name" />
                </div>

                <div>
                  <InputLabel for="email" value="Adresse email *" />
                  <TextInput
                    id="email"
                    type="email"
                    v-model="profileForm.email"
                    placeholder="votre@email.com"
                  />
                  <InputError :message="profileErrors.email" />
                </div>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>Enregistrer les modifications</span>
                </PrimaryButton>
              </div>
            </form>
          </div>

          <!-- Password Tab -->
          <div v-if="activeTab === 'password'" class="p-6 lg:p-8">
            <form @submit.prevent="submitPassword" class="space-y-6">
              <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                  <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                  <div class="text-sm text-blue-700">
                    <p class="font-semibold mb-1">Conseils de sécurité</p>
                    <p>Utilisez un mot de passe fort d'au moins 8 caractères, avec des majuscules, minuscules, chiffres et symboles.</p>
                  </div>
                </div>
              </div>

              <InputError :message="passwordErrors.server" />

              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="current_password" value="Mot de passe actuel *" />
                  <TextInput
                    id="current_password"
                    type="password"
                    v-model="passwordForm.current_password"
                    placeholder="Entrez votre mot de passe actuel"
                    autofocus
                  />
                  <InputError :message="passwordErrors.current_password" />
                </div>

                <div>
                  <InputLabel for="password" value="Nouveau mot de passe *" />
                  <TextInput
                    id="password"
                    type="password"
                    v-model="passwordForm.password"
                    placeholder="Entrez votre nouveau mot de passe"
                  />
                  <InputError :message="passwordErrors.password" />
                </div>

                <div>
                  <InputLabel for="password_confirmation" value="Confirmer le mot de passe *" />
                  <TextInput
                    id="password_confirmation"
                    type="password"
                    v-model="passwordForm.password_confirmation"
                    placeholder="Confirmez votre nouveau mot de passe"
                  />
                  <InputError :message="passwordErrors.password_confirmation" />
                </div>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <PrimaryButton :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Mise à jour...
                  </span>
                  <span v-else>Mettre à jour le mot de passe</span>
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </SettingsLayout>
</template>
