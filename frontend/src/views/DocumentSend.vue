<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import { success, error } from '@/helpers/notifications'
import { useAuthStore } from '@/stores/auth'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/components/PrimaryButton.vue'
import InputError from '@/components/InputError.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const isLoading = ref(true)
const document = ref(null)
const isSending = ref(false)
const errors = ref({ to_email: '', subject: '', server: '' })

const docType = computed(() => route.query.type || 'devis')
const backPage = computed(() => route.query.page || 'quote')

const docLabel = computed(() => {
  const labels = { devis: 'Devis', invoice: 'Facture', purchase_order: 'Bon de commande', delivery_note: 'Bon de livraison', deposit: "Facture d'acompte" }
  return labels[docType.value] || 'Document'
})

const docNumber = computed(() => document.value?.number || 'Brouillon')
const customerName = computed(() => {
  const c = document.value?.customer
  if (!c) return 'Client'
  return c.name || c.customerable?.legal_name || c.customerable?.name || 'Client'
})
const customerEmail = computed(() => document.value?.customer?.email || '')
const companyName = computed(() => document.value?.company?.company_name || authStore.currentCompany?.name || 'Facturex')
const docDate = computed(() => {
  const d = document.value?.created_at || document.value?.date
  if (!d) return ''
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
})

const generatedSubject = computed(() => `Votre ${docLabel.value.toLowerCase()} ${docNumber.value}`)
const defaultMessage = computed(() => `Bonjour ${customerName.value},\n\nJe vous prie de trouver ci-joint votre ${docLabel.value.toLowerCase()} ${docNumber.value} en date du ${docDate.value}.\n\nVous en souhaitant bonne réception.\n\nCordialement,\n${companyName.value}`)

const form = ref({
  to_email: '',
  subject: '',
  message: '',
})

const fetchDocument = async () => {
  isLoading.value = true
  try {
    const docId = route.params.id
    const { data } = await axios.get(`/api/${getApiPath()}/${docId}`)
    document.value = data.document || data
  } catch (err) {
    document.value = { number: 'Brouillon', customer: null, company: null }
  } finally {
    isLoading.value = false
  }
}

const getApiPath = () => {
  switch (docType.value) {
    case 'devis': return 'quotes'
    case 'invoice': return 'invoices'
    case 'purchase_order': return 'purchase-orders'
    case 'delivery_note': return 'delivery-notes'
    case 'deposit': return 'deposits'
    default: return 'quotes'
  }
}

onMounted(async () => {
  await fetchDocument()
  form.value = {
    to_email: customerEmail.value,
    subject: generatedSubject.value,
    message: defaultMessage.value,
  }
})

const sendEmail = async () => {
  errors.value = { to_email: '', subject: '', server: '' }
  if (!form.value.to_email.trim()) { errors.value.to_email = "L'email du destinataire est requis."; return }
  if (!form.value.subject.trim()) { errors.value.subject = "L'objet est requis."; return }

  isSending.value = true
  try {
    await axios.post('/api/documents/send', {
      document_id: Number(route.params.id),
      to_email: form.value.to_email,
      subject: form.value.subject,
      message: form.value.message,
      sender_name: companyName.value,
      sender_email: document.value?.company?.email || authStore.user?.email || 'contact@facturex.com',
    })
    success('Envoyé !', `Email envoyé à ${form.value.to_email}`)
    goBack()
  } catch (err) {
    const message = err.response?.data?.message || "Erreur lors de l'envoi de l'email."
    errors.value.server = message
  } finally {
    isSending.value = false
  }
}

const goBack = () => {
  const pageMap = {
    quote: '/quote',
    invoice: '/invoices',
    purchase_order: '/purchase-orders',
    delivery_note: '/delivery-notes',
    deposit: '/deposits',
  }
  router.push(pageMap[backPage.value] || '/')
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex items-center justify-between">
              <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                  <button @click="goBack" class="text-gray-500 hover:text-[#062121] transition-colors">
                    <i class="fas fa-arrow-left text-lg"></i>
                  </button>
                  <span class="text-sm font-bold text-[#062121]">{{ docLabel }} {{ docNumber }} — Envoyer par email à {{ customerName }}</span>
                </div>
                <span class="text-xs text-gray-500 ml-10">De : {{ companyName }}</span>
              </div>
            </div>
          </div>

          <div v-if="isLoading" class="text-center py-12">
            <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="mt-2 text-gray-500">Chargement...</p>
          </div>

          <form v-else @submit.prevent="sendEmail" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <div class="grid grid-cols-1 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1.5">À *</label>
                  <input
                    v-model="form.to_email"
                    type="email"
                    placeholder="client@email.com"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                    required
                  />
                  <InputError class="mt-2" :message="errors.to_email" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1.5">Objet *</label>
                  <input
                    v-model="form.subject"
                    type="text"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
                    required
                  />
                  <InputError class="mt-2" :message="errors.subject" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1.5">Message *</label>
                  <textarea
                    v-model="form.message"
                    rows="8"
                    class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20 resize-none"
                    required
                  ></textarea>
                </div>

                <div class="pt-2 text-xs text-gray-400">
                  <i class="fas fa-paperclip mr-1"></i> Le document PDF sera automatiquement joint à l'email.
                </div>
              </div>

              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button type="button" @click="goBack" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">Annuler</button>
                <PrimaryButton :disabled="isSending">
                  <span v-if="isSending">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Envoi...
                  </span>
                  <span v-else><i class="fas fa-paper-plane mr-1.5"></i> Envoyer</span>
                </PrimaryButton>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>