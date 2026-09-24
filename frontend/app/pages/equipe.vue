<script setup lang="ts">
import { equipeTabs } from '~/utils/equipeNav'

definePageMeta({ middleware: 'auth' })

const route = useRoute()

const navbarTitle = computed(() => route.path.startsWith('/equipe/departamentos') ? 'Departamentos' : 'Membros')
const links = computed(() => equipeTabs(route.path))
</script>

<template>
  <UDashboardPanel id="equipe" :ui="{ body: 'min-h-0 flex-1 gap-0 overflow-hidden p-0 sm:p-0' }">
    <template #header>
      <UDashboardNavbar :title="navbarTitle">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu :items="links" highlight class="-mx-1 min-w-0 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
