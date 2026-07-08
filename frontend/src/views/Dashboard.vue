<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import { usePermission } from '@/composables/usePermission'
import { error } from '@/helpers/notifications'
import DashboardOverview from '@/components/dashboard/DashboardOverview.vue'
import DashboardRevenue from '@/components/dashboard/DashboardRevenue.vue'
import DashboardActivities from '@/components/dashboard/DashboardActivities.vue'
import DashboardTvaReport from '@/components/dashboard/DashboardTvaReport.vue'
import DashboardDocuments from '@/components/dashboard/DashboardDocuments.vue'

const { can } = usePermission()

const activeTab = ref('overview')
const dashboardData = ref(null)
const loading = ref(false)

const tabs = [
  { key: 'overview', label: 'Vue d\'ensemble', icon: 'fa-chart-pie' },
  { key: 'revenue', label: 'Chiffre d\'affaires', icon: 'fa-chart-line' },
  { key: 'activities', label: 'Activités', icon: 'fa-bolt' },
  { key: 'tva', label: 'Rapport TVA', icon: 'fa-percent', requiresPermission: 'view-tva-report' },
  { key: 'documents', label: 'Documents générés', icon: 'fa-file' },
]

const visibleTabs = computed(() => {
  return tabs.filter(tab => {
    if (tab.requiresPermission) return can(tab.requiresPermission)
    return true
  })
})

const fetchDashboard = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/dashboard')
    dashboardData.value = data
  } catch (err) {
    const message = err.response?.data?.message || 'Erreur lors du chargement du dashboard.'
    error('Erreur', message)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboard()
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 px-6 pt-2">
            <nav class="flex space-x-6 overflow-x-auto">
              <button
                v-for="tab in visibleTabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2"
                :class="activeTab === tab.key
                  ? 'border-[#C5F82A] text-[#062121]'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              >
                <i :class="['fas', tab.icon]" class="text-xs"></i>
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <div class="p-6">
            <DashboardOverview
              v-if="activeTab === 'overview'"
              :stats="dashboardData?.stats || {}"
              :documentsByType="dashboardData?.documents_by_type || []"
              :loading="loading"
            />
            <DashboardRevenue
              v-else-if="activeTab === 'revenue'"
              :revenueByMonth="dashboardData?.revenue_by_month || []"
              :expensesByMonth="dashboardData?.expenses_by_month || []"
              :topCustomers="dashboardData?.top_customers || []"
              :loading="loading"
            />
            <DashboardActivities
              v-else-if="activeTab === 'activities'"
              :activities="dashboardData?.recent_activities || []"
              :loading="loading"
            />
            <DashboardTvaReport
              v-else-if="activeTab === 'tva'"
            />
            <DashboardDocuments
              v-else-if="activeTab === 'documents'"
              :documentsByType="dashboardData?.documents_by_type || []"
              :loading="loading"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
