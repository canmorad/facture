// composables/useAuth.js
import { ref } from 'vue'

const user = ref(null)
const emailVerified = ref(false)
const hasCompany = ref(false)
const isAuthenticated = ref(false)
const currentCompanyId = ref(null)

export function useAuth() {
  const setAuthData = (data) => {
    user.value = data.user
    emailVerified.value = data.email_verified
    hasCompany.value = data.has_company
    isAuthenticated.value = !!data.user
  }

  const clearAuth = () => {
    user.value = null
    emailVerified.value = false
    hasCompany.value = false
    isAuthenticated.value = false
    currentCompanyId.value = null
    localStorage.removeItem('current_company_id')
  }

  const setCurrentCompany = (companyId) => {
    currentCompanyId.value = companyId
    if (companyId) {
      localStorage.setItem('current_company_id', String(companyId))
    } else {
      localStorage.removeItem('current_company_id')
    }
  }

  const loadCurrentCompany = () => {
    const id = localStorage.getItem('current_company_id')
    if (id) currentCompanyId.value = Number(id)
  }

  const updateHasCompany = (value) => {
    hasCompany.value = value
  }

  return {
    user,
    emailVerified,
    hasCompany,
    isAuthenticated,
    currentCompanyId,
    setAuthData,
    clearAuth,
    setCurrentCompany,
    loadCurrentCompany,
    updateHasCompany
  }
}