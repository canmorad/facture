import { useAuthStore } from '../stores/auth'

const publicRoutes = ['login', 'register', 'home', 'verify-email']
const onboardingRoutes = ['settings.coordinates', 'settings.numbering']

export async function navigationGuard(to, from) {
  const auth = useAuthStore()

  if (!auth.isAuthenticated) {
    await auth.fetchAuthStatus()
  }

  if (!auth.isAuthenticated) {
    if (publicRoutes.includes(to.name)) {
      return true
    }
    return { name: 'login' }
  }

  if (!auth.emailVerified) {
    if (to.name === 'verify-email') {
      return true
    }
    return { name: 'verify-email' }
  }

  if (!auth.hasCompany) {
    if (to.name === 'settings.coordinates' && to.query.mode === 'create') {
      return true
    }
    return { name: 'settings.coordinates', query: { mode: 'create' } }
  }

  if (!auth.hasNumbering) {
    if (to.name === 'settings.numbering') {
      return true
    }
    return { name: 'settings.numbering' }
  }

  if (publicRoutes.includes(to.name)) {
    return { name: 'dashboard' }
  }

  return true
}
