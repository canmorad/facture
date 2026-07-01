<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth'

const authStore = useAuthStore()

onMounted(() => {
  if (!authStore.isAuthenticated && !authStore.user) {
    authStore.fetchAuthStatus()
  }
})
</script>

<template>
  <router-view />
</template>