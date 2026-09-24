<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const inSupportMode = useSupportMode()
const { fetchMe, user, isSuperAdmin } = useAuth()

// Gate duplo (auth + super-admin): sem sessão vai para /login, comum vai para /.
// Redundante com middleware das páginas, mas garante o shell mesmo em acesso direto.
if (!user.value) {
  await fetchMe().catch(() => {})
}
if (!user.value) {
  await navigateTo('/login')
} else if (!isSuperAdmin.value) {
  await navigateTo('/')
}

const links = [{
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
}] satisfies NavigationMenuItem[]
</script>

<template>
  <SupportBanner />

  <UDashboardGroup unit="rem" :class="[inSupportMode && 'pt-12']">
    <UDashboardSidebar
      id="admin"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <TeamsMenu :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UNavigationMenu
          :collapsed="collapsed"
          :items="links"
          orientation="vertical"
          tooltip
          highlight
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="[{ label: 'Voltar ao app', icon: 'i-lucide-arrow-left', to: '/' }]"
          orientation="vertical"
          tooltip
          class="mt-auto"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <slot />

    <NotificationsSlideover />
  </UDashboardGroup>
</template>
