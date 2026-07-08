import { useAuthStore } from '../stores/auth'

export function usePermission() {
  const authStore = useAuthStore()

  const can = (permission) => {
    return authStore.can(permission)
  }

  return {
    can,
  }
}