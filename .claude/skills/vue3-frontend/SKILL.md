# Vue 3 Frontend Skill - Facture App

## Core Stack
- Vue 3 (Composition API, `<script setup>`)
- Pinia (state management)
- Vue Router
- Axios (HTTP)
- SweetAlert2 (notifications)
- Tailwind CSS

## 1. Pinia Store Pattern

```js
// stores/auth.js
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    permissions: [],
    companies: [],
    currentCompanyId: null,
    isAuthenticated: false,
    hasCompany: false,
    hasNumbering: false,
  }),
  getters: {
    currentCompany: (state) => state.companies.find(c => c.id === state.currentCompanyId),
    isOwner: (state) => state.user?.is_owner ?? false,
  },
  actions: {
    setAuthData(data) { /* hydrate state */ },
    clearAuth() { /* reset + remove localStorage */ },
    async fetchAuthStatus() { /* GET /api/user-status */ },
    async setActiveCompany(companyId) { /* switch company */ },
    can(permission) {
      if (this.isOwner) return true;
      return this.permissions.includes(permission);
    },
  },
})
```

## 2. Composables Pattern

```js
// composables/usePermission.js
import { useAuthStore } from '../stores/auth'

export function usePermission() {
  const authStore = useAuthStore()
  const can = (permission) => authStore.can(permission)
  return { can }
}
```

## 3. HTTP Axios Interceptor

```js
// main.js
axios.defaults.baseURL = 'http://127.0.0.1:8000'
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

axios.interceptors.request.use((config) => {
  const companyId = localStorage.getItem('current_company_id')
  if (companyId) config.headers['X-Company-Id'] = companyId
  return config
})
```

## 4. View Pattern (Index Pages)

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { success, error, confirm } from '@/helpers/notifications'

const items = ref([])
const isLoading = ref(false)

const fetchItems = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/api/invoices')
    items.value = data
  } catch (err) {
    error('Erreur', err.response?.data?.message || 'Impossible de charger.')
  } finally {
    isLoading.value = false
  }
}

const deleteItem = async (id, label) => {
  const result = await confirm('Supprimer', `Supprimer ${label} ?`)
  if (!result.isConfirmed) return
  try {
    await axios.delete(`/api/documents/${id}`)
    success('Supprimé !', `${label} supprimé.`)
    await fetchItems()
  } catch (err) {
    error('Erreur', err.response?.data?.message || 'Impossible de supprimer.')
  }
}

onMounted(fetchItems)
</script>
```

## 5. Custom Directives (Permissions)

```js
// v-can directive
app.directive('can', {
  mounted(el, binding) {
    const authStore = useAuthStore()
    if (!authStore.can(binding.value)) el.style.display = 'none'
  },
})

// Usage
<button v-can="'create-document'">Créer</button>
```

## 6. Notifications (SweetAlert2)

```js
// helpers/notifications.js
import Swal from 'sweetalert2'

export function success(title, text) { /* Swal.fire success */ }
export function error(title, text) { /* Swal.fire error */ }
export function confirm(title, text) { /* Swal.fire confirm */ }
```

## 7. Layout Pattern

```vue
<script setup>
import { useAuthStore } from '../stores/auth'
import { usePermission } from '../composables/usePermission'
const { can } = usePermission()
const authStore = useAuthStore()
</script>

<template>
  <aside>
    <nav>
      <router-link v-if="can('view-documents')" to="/invoices">Factures</router-link>
    </nav>
  </aside>
  <main><slot /></main>
</template>
```

## 8. Router Guards

```js
// router/guard.js
export function navigationGuard(to, from, next) {
  const authStore = useAuthStore()
  if (!authStore.isAuthenticated && to.name !== 'login') return next('/login')
  next()
}
```

## Interdictions Frontend

- ❌ Options API (toujours `<script setup>`)
- ❌ `this.$store` (utiliser Pinia `useXStore()`)
- ❌ `fetch()` natif (utiliser `axios`)
- ❌ Hardcoded URLs (utiliser `axios.defaults.baseURL`)
- ❌ Messages en anglais dans les notifications
- ❌ `v-if` manuel pour permissions (utiliser `v-can` directive)