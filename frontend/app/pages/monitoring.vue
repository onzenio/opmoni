<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import { monitoringListPath, monitoringPageKey, parseMonitoringSlug } from '~/utils/monitoringNav'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const listing = computed(() => parseMonitoringSlug(route.params.slug))

const title = computed(() => listing.value?.group.label ?? 'Monitoramento')

const pageTabs = computed<NavigationMenuItem[][] | null>(() => {
  const current = listing.value
  if (!current || current.group.pages.length < 2) return null
  const currentKey = monitoringPageKey(current.page)
  return [[
    ...current.group.pages.map(page => ({
      label: page.label,
      icon: page.icon,
      to: monitoringListPath(page, current.status),
      active: monitoringPageKey(page) === currentKey
    }))
  ]]
})
</script>

<template>
  <UDashboardPanel id="monitoring" :ui="{ body: 'min-h-0 flex-1 gap-0 overflow-hidden p-0 sm:p-0' }">
    <template #header>
      <UDashboardNavbar :title="title">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar v-if="pageTabs">
        <UNavigationMenu :items="pageTabs" highlight class="-mx-1 min-w-0 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
