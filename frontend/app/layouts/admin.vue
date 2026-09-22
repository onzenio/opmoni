<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const inSupportMode = useSupportMode()
const open = ref(false)

const links: NavigationMenuItem[] = [{
  label: 'Resumo',
  icon: 'i-lucide-layout-dashboard',
  to: '/admin',
  exact: true,
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Contas',
  icon: 'i-lucide-building-2',
  to: '/admin/contas',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Planos',
  icon: 'i-lucide-layers',
  to: '/admin/planos',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Assinaturas',
  icon: 'i-lucide-receipt',
  to: '/admin/assinaturas',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Usuários',
  icon: 'i-lucide-users',
  to: '/admin/usuarios',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Suporte',
  icon: 'i-lucide-life-buoy',
  to: '/admin/suporte',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Voltar ao app',
  icon: 'i-lucide-arrow-left',
  to: '/',
  onSelect: () => {
    open.value = false
  }
}]
</script>

<template>
  <div class="min-h-dvh bg-default">
    <SupportBanner />

    <UDashboardGroup unit="rem" :class="[inSupportMode && 'pt-12']">
      <UDashboardSidebar
        id="admin"
        v-model:open="open"
        collapsible
        resizable
        class="bg-elevated/25"
        :ui="{ footer: 'lg:border-t lg:border-default' }"
      >
        <template #header="{ collapsed }">
          <AccountSwitcher :collapsed="collapsed" />
        </template>

        <template #default="{ collapsed }">
          <UNavigationMenu
            :collapsed="collapsed"
            :items="links"
            orientation="vertical"
            tooltip
            popover
          />
        </template>

        <template #footer="{ collapsed }">
          <UserMenu :collapsed="collapsed" />
        </template>
      </UDashboardSidebar>

      <slot />
    </UDashboardGroup>
  </div>
</template>
