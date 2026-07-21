<script setup>
import { useAuthStore } from './stores/auth'

const authStore = useAuthStore()

// Auth status is now initialized in main.js after mounting
// This component just needs to access the store
</script>

<template>
  <router-view />
</template>