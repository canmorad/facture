import './assets/main.css'
import './assets/all.min.css'
import axios from 'axios'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

axios.defaults.baseURL = ''
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Axios interceptor to add X-Company-Id header for authenticated requests
// This is automatically added for all requests once a company is selected
axios.interceptors.request.use((config) => {
  const companyId = localStorage.getItem('current_company_id')
  if (companyId) {
    config.headers['X-Company-Id'] = companyId
  }
  return config
})

// Axios response interceptor to handle 401 errors globally
// This ensures the user is redirected to login when session expires
let authStore = null // Will be set after app mounts

axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Ignore 401 errors for user-status endpoint (handled by guard)
    if (error.config?.url === '/api/user-status') {
      return Promise.reject(error)
    }

    // Handle 401 Unauthorized errors
    if (error.response?.status === 401 && authStore) {
      // Only clear auth if we were previously authenticated
      if (authStore.isAuthenticated) {
        authStore.clearAuth()
        // Don't redirect immediately - let the guard handle it on next navigation
        // This prevents disrupting the user mid-action
        console.warn('Session expired. Please log in again.')
      }
    }

    return Promise.reject(error)
  }
)

// ============================================
// CSRF INITIALIZATION
// ============================================
/**
 * Initialize CSRF token from Sanctum
 * Must be called before any authenticated requests
 * This fetches the XSRF-TOKEN cookie required for POST/PUT/DELETE requests
 */
async function initializeCsrf() {
  try {
    await axios.get('/sanctum/csrf-cookie')
  } catch (error) {
    console.error('Failed to initialize CSRF:', error)
    throw error
  }
}

// ============================================
// APPLICATION INITIALIZATION
// ============================================
/**
 * Initialize application
 * Flow:
 * 1. Fetch CSRF cookie from Sanctum
 * 2. Create and mount Vue app
 * 3. Check authentication status
 */
async function initializeApp() {
  // Step 1: Initialize CSRF token first
  // This ensures XSRF-TOKEN cookie is set before any POST/PUT/DELETE requests
  await initializeCsrf()

  // Step 2: Create and mount Vue app
  const pinia = createPinia()
  const app = createApp(App)

  // Register directives
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

  // Register plugins
  app.use(pinia)
  app.use(router)

  // Mount app
  app.mount('#app')

  // Step 3: Check authentication status
  // After app is mounted, fetch user status to determine if user is logged in
  const authStoreInstance = useAuthStore()

  // Make auth store available to axios interceptor
  authStore = authStoreInstance

  try {
    await authStoreInstance.fetchAuthStatus()
  } catch (error) {
    // If auth check fails, user remains unauthenticated
    // The navigation guard will handle redirecting to login if needed
    console.debug('Initial auth check failed or user not logged in:', error.message)
  }
}

// Start the application
initializeApp().catch((error) => {
  console.error('Failed to initialize application:', error)
})
