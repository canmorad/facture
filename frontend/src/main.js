import './assets/main.css'
import './assets/all.min.css'
import axios from 'axios'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

axios.defaults.baseURL = 'http://127.0.0.1:8000'
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

axios.interceptors.request.use((config) => {
  const companyId = localStorage.getItem('current_company_id')
  if (companyId) {
    config.headers['X-Company-Id'] = companyId
  }
  return config
})

const pinia = createPinia()
const app = createApp(App)

app.directive('click-outside', {
  mounted(el, binding) {
    el.__clickOutsideHandler = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event)
      }
    }
    document.addEventListener('click', el.__clickOutsideHandler)
  },
  unmounted(el) {
    document.removeEventListener('click', el.__clickOutsideHandler)
  }
})

app.directive('can', {
  mounted(el, binding) {
    const authStore = useAuthStore()
    if (!authStore.can(binding.value)) {
      el.style.display = 'none'
    }
  },
  updated(el, binding) {
    const authStore = useAuthStore()
    if (!authStore.can(binding.value)) {
      el.style.display = 'none'
    } else {
      el.style.display = ''
    }
  }
})

app.directive('cannot', {
  mounted(el, binding) {
    const authStore = useAuthStore()
    if (authStore.can(binding.value)) {
      el.style.display = 'none'
    }
  },
  updated(el, binding) {
    const authStore = useAuthStore()
    if (authStore.can(binding.value)) {
      el.style.display = 'none'
    } else {
      el.style.display = ''
    }
  }
})

app.use(pinia)
app.use(router)

app.mount('#app')