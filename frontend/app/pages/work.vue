<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import { isCalendarRoute, workNav } from '~/utils/workNav'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const cleanCalendarShell = computed(() => isCalendarRoute(route.path))

const title = computed(() => {
  const current = workNav.find(item => route.path === item.to || route.path.startsWith(`${item.to}/`))
  return current?.label ?? 'Work'
})

const pageTabs = computed<NavigationMenuItem[][]>(() => [[
  ...workNav.map(item => ({
    label: item.label,
    icon: item.icon,
    to: item.to,
    active: route.path === item.to || route.path.startsWith(`${item.to}/`)
  }))
]])
</script>

<template>
  <UDashboardPanel id="work" :ui="{ body: 'min-h-0 flex-1 gap-0 overflow-hidden p-0 sm:p-0' }">
    <template v-if="!cleanCalendarShell" #header>
      <UDashboardNavbar :title="title">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu :items="pageTabs" highlight class="-mx-1 min-w-0 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
