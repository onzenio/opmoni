<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin']
})

const links = [[{
  label: 'Resumo',
  icon: 'i-lucide-layout-dashboard',
  to: '/admin',
  exact: true
}, {
  label: 'Contas',
  icon: 'i-lucide-building-2',
  to: '/admin/contas'
}, {
  label: 'Planos',
  icon: 'i-lucide-layers',
  to: '/admin/planos'
}, {
  label: 'Assinaturas',
  icon: 'i-lucide-receipt',
  to: '/admin/assinaturas'
}, {
  label: 'Usuários',
  icon: 'i-lucide-users',
  to: '/admin/usuarios'
}, {
  label: 'Suporte',
  icon: 'i-lucide-life-buoy',
  to: '/admin/suporte'
}]] satisfies NavigationMenuItem[][]
</script>

<template>
  <UDashboardPanel id="admin">
    <template #header>
      <UDashboardNavbar title="Admin">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu :items="links" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
