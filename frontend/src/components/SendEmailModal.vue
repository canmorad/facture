<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { success, error } from '@/helpers/notifications'
import PrimaryButton from '@/components/PrimaryButton.vue'
import InputError from '@/components/InputError.vue'
import InputLabel from '@/components/InputLabel.vue'

const props = defineProps({
  document: { type: Object, required: true },
  documentType: { type: String, default: 'devis' },
  companyName: { type: String, default: '' },
  companyEmail: { type: String, default: '' },
})

const emit = defineEmits(['sent', 'cancel'])

const isSending = ref(false)
const errors = ref({ to_email: '', subject: '', server: '' })

const docLabel = computed(() => {
  const labels = {
    devis: 'Devis',
    invoice: 'Facture',
    purchase_order: 'Bon de commande',
    delivery_note: 'Bon de livraison',
    deposit: "Facture d'acompte",
  }
  return labels[props.documentType] || 'Document'
})

const docNumber = computed(() => props.document?.number || 'Brouillon')
const customerName = computed(() => props.document?.customer?.name || 'Client')
const customerEmail = computed(() => props.document?.customer?.email || '')
const docDate = computed(() => {
  const d = props.document?.created_at || props.document?.date
  if (!d) return ''
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
})

const generatedSubject = computed(() => `Votre ${docLabel.value.toLowerCase()} ${docNumber.value}`)

const defaultMessage = computed(() => {
  return `Bonjour ${customerName.value},

Je vous prie de trouver ci-joint votre ${docLabel.value.toLowerCase()} ${docNumber.value} en date du ${docDate.value}.

Vous en souhaitant bonne réception.

Cordialement,
${props.companyName}`
})

const form = ref({
  to_email: customerEmail.value,
  subject: generatedSubject.value,
  message: defaultMessage.value,
})

const sendEmail = async () => {
  errors.value = { to_email: '', subject: '', server: '' }
  if (!form.value.to_email.trim()) {
    errors.value.to_email = "L'email du destinataire est requis."
    return
  }
  if (!form.value.subject.trim()) {
    errors.value.subject = "L'objet est requis."
    return
  }

  isSending.value = true
  try {
    await axios.post('/api/documents/send', {
      document_id: props.document.id,
      to_email: form.value.to_email,
      subject: form.value.subject,
      message: form.value.message,
      sender_name: props.companyName || 'Facturex',
      sender_email: props.companyEmail || 'contact@facturex.com',
    })
    success('Envoyé !', `Email envoyé à ${form.value.to_email}`)
    emit('sent')
  } catch (err) {
    const message = err.response?.data?.message || "Erreur lors de l'envoi de l'email."
    errors.value.server = message
  } finally {
    isSending.value = false
  }
}
</script>

<template>
  <form @submit.prevent="sendEmail" class="p-6 lg:p-8">
    <div class="space-y-8">
      <InputError :message="errors.server" />

      <div class="grid grid-cols-1 gap-6">
        <div>
          <InputLabel for="to_email" value="À *" />
          <input
            id="to_email"
            v-model="form.to_email"
            type="email"
            placeholder="client@email.com"
            class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
            required
          />
          <InputError class="mt-2" :message="errors.to_email" />
        </div>

        <div>
          <InputLabel for="subject" value="Objet *" />
          <input
            id="subject"
            v-model="form.subject"
            type="text"
            class="mt-1 block w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3 text-sm text-gray-700 transition-all duration-300 focus:border-[#C5F82A] focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-[#C5F82A]/20"
            required
          />
          <InputError class="mt-2" :message="errors.subject" />
        </div>

        <div>
          <InputLabel for="message" value="Message *" />
          <textarea
            id="message"
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
        <button
          type="button"
          @click="emit('cancel')"
          class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
        >
          Annuler
        </button>
        <PrimaryButton :disabled="isSending">
          <span v-if="isSending">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Envoi...
          </span>
          <span v-else>
            <i class="fas fa-paper-plane mr-1.5"></i> Envoyer
          </span>
        </PrimaryButton>
      </div>
    </div>
  </form>
</template>