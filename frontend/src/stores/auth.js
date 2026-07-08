import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => {
    const storedId = localStorage.getItem('current_company_id')
    return {
      user: null,
      permissions: [],
      emailVerified: false,
      hasCompany: false,
      hasNumbering: false,
      isAuthenticated: false,
      companies: [],
      currentCompanyId: storedId ? Number(storedId) : null,
    }
  },
  getters: {
    currentCompany: (state) => {
      if (!state.currentCompanyId || !state.companies.length) return null
      return state.companies.find(c => c.id === state.currentCompanyId) || null
    },
    isOwner: (state) => {
      return state.user?.is_owner ?? false
    },
  },
  actions: {
    setAuthData(data) {
      this.user = data.user || null
      this.permissions = data.permissions || []
      this.emailVerified = data.email_verified ?? false
      this.hasCompany = data.has_company ?? false
      this.hasNumbering = data.has_numbering ?? false
      this.isAuthenticated = !!data.user
      this.companies = data.user?.companies || []

      if (this.companies.length > 0 && !this.currentCompanyId) {
        const storedId = this.getStoredCompanyId()
        if (storedId && this.companies.some(c => c.id === storedId)) {
          this.currentCompanyId = storedId
        } else {
          this.currentCompanyId = this.companies[0].id
        }
      } else if (this.companies.length === 0) {
        this.currentCompanyId = null
      }

      if (this.currentCompanyId) {
        this.persistCompanyId(this.currentCompanyId)
      }
    },
    clearAuth() {
      this.user = null
      this.permissions = []
      this.emailVerified = false
      this.hasCompany = false
      this.hasNumbering = false
      this.isAuthenticated = false
      this.companies = []
      this.currentCompanyId = null
      localStorage.removeItem('current_company_id')
    },
    async fetchAuthStatus() {
      try {
        const config = { withCredentials: true }
        if (this.currentCompanyId) {
          config.headers = { 'X-Company-Id': String(this.currentCompanyId) }
        }
        const response = await axios.get('/api/user-status', config)
        this.setAuthData(response.data)
      } catch (error) {
        if (error.response && error.response.status === 401) {
          this.clearAuth()
        } else {
          this.clearAuth()
        }
      }
    },
    async setActiveCompany(companyId) {
      if (this.companies.some(c => c.id === companyId)) {
        this.currentCompanyId = companyId
        this.persistCompanyId(companyId)
        await this.fetchAuthStatus()
      }
    },
    addCompany(company) {
      if (!this.companies.some(c => c.id === company.id)) {
        this.companies.push(company)
        this.currentCompanyId = company.id
        this.hasCompany = true
        this.persistCompanyId(company.id)
      }
    },
    updateHasCompany(value) {
      this.hasCompany = value
    },
    updateHasNumbering(value) {
      this.hasNumbering = value
    },
    async reloadCompanies() {
      try {
        const response = await axios.get('/api/user-companies')
        this.companies = response.data
        if (!this.currentCompanyId && this.companies.length > 0) {
          const storedId = this.getStoredCompanyId()
          if (storedId && this.companies.some(c => c.id === storedId)) {
            this.currentCompanyId = storedId
          } else {
            this.currentCompanyId = this.companies[0].id
          }
          this.persistCompanyId(this.currentCompanyId)
        }
      } catch {
        // ignore
      }
    },
    getStoredCompanyId() {
      const stored = localStorage.getItem('current_company_id')
      return stored ? Number(stored) : null
    },
    persistCompanyId(id) {
      if (id) {
        localStorage.setItem('current_company_id', String(id))
      } else {
        localStorage.removeItem('current_company_id')
      }
    },
    hydrateFromStorage() {
      const storedId = this.getStoredCompanyId()
      if (storedId && this.companies.some(c => c.id === storedId)) {
        this.currentCompanyId = storedId
        return true
      }
      return false
    },
    can(permission) {
      if (this.isOwner) {
        return true
      }
      return this.permissions.includes(permission)
    },
    async logout() {
      try {
        await axios.post('/api/logout')
      } catch {
        // ignore network errors during logout
      }
      this.clearAuth()
      window.location.href = '/login'
    },
  },
})
