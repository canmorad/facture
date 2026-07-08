import Swal from 'sweetalert2'

const baseSwal = Swal.mixin({
  width: 340,
  padding: '1rem 1.25rem',
  customClass: {
    popup: 'sweet-popup rounded-xl shadow-2xl border-0 !max-w-[340px]',
    title: 'sweet-title text-base font-bold text-[#062121] !text-sm',
    htmlContainer: 'sweet-html text-gray-600 !text-xs !leading-relaxed',
    icon: 'sweet-icon',
    confirmButton: 'sweet-confirm bg-[#062121] hover:bg-[#0F2A2A] text-white font-semibold py-1 px-4 rounded-lg transition-all duration-200 text-xs',
    cancelButton: 'sweet-cancel bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-1 px-4 rounded-lg transition-all duration-200 text-xs',
    actions: 'sweet-actions !mt-1',
    closeButton: 'sweet-close !text-gray-400 hover:!text-gray-600',
  },
  buttonsStyling: false,
  reverseButtons: true,
  backdrop: 'rgba(0,0,0,0.3)',
  allowOutsideClick: true,
})

export function success(title = 'Succès', text = '') {
  return baseSwal.fire({
    icon: 'success',
    title: title,
    text: text,
    iconColor: '#C5F82A',
    timer: 2000,
    timerProgressBar: true,
  })
}

export function error(title = 'Erreur', text = '') {
  return baseSwal.fire({
    icon: 'error',
    title: title,
    text: text,
    confirmButtonColor: '#062121',
  })
}

export function validation(text = 'Veuillez corriger les erreurs de saisie.', title = 'Erreur de validation') {
  return baseSwal.fire({
    icon: 'warning',
    title: title,
    text: text,
    confirmButtonColor: '#062121',
  })
}

export function confirm(title = 'Confirmer', text = 'Êtes-vous sûr de vouloir continuer ?') {
  return baseSwal.fire({
    title: title,
    text: text,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Oui, continuer',
    cancelButtonText: 'Annuler',
    confirmButtonColor: '#062121',
    cancelButtonColor: '#64748B',
    width: 360,
  })
}

export function info(title = 'Information', text = '') {
  return baseSwal.fire({
    icon: 'info',
    title: title,
    text: text,
    confirmButtonColor: '#062121',
  })
}

export function successSaved(text = 'Vos modifications ont été enregistrées avec succès.') {
  return success('Enregistré !', text)
}

export function toast(title = '', text = '', icon = 'success') {
  return baseSwal.fire({
    icon: icon,
    title: title,
    text: text,
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    iconColor: '#C5F82A',
  })
}

export function showWelcomeModal(title = 'Bienvenue !', text = '', buttonText = 'Commencer') {
  return baseSwal.fire({
    icon: 'info',
    title,
    text,
    confirmButtonText: buttonText,
    confirmButtonColor: '#062121',
    iconColor: '#C5F82A',
    showCancelButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    width: 380,
    padding: '1.5rem',
  })
}