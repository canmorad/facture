// stores/auth.js
import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    emailVerified: false,
    hasCompany: false,
    hasNumbering: false,
    isAuthenticated: false,
    companies: [],
    currentCompanyId: null,
  }),
  getters: {
    currentCompany: (state) => {
      if (!state.currentCompanyId || !state.companies.length) return null
      return state.companies.find(c => c.id === state.currentCompanyId) || null
    },
  },
  actions: {
    setAuthData(data) {
      this.user = data.user || null
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
        const response = await axios.get('/api/user-status', { withCredentials: true })
        this.setAuthData(response.data)
      } catch (error) {
        if (error.response && error.response.status === 401) {
          this.clearAuth()
        } else {
          this.clearAuth()
        }
      }
    },
    setActiveCompany(companyId) {
      if (this.companies.some(c => c.id === companyId)) {
        this.currentCompanyId = companyId
        this.persistCompanyId(companyId)
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
  },
})