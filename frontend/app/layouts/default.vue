<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import { monitoringSidebarChildren } from '~/utils/monitoringNav'
import { workSidebarChildren } from '~/utils/workNav'

const route = useRoute()
const toast = useToast()
const { isSuperAdmin } = useAuth()
const inSupportMode = useSupportMode()

const open = ref(false)

const links = [[{
  label: 'Home',
  icon: 'i-lucide-house',
  to: '/',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Inbox',
  icon: 'i-lucide-inbox',
  to: '/inbox',
  badge: '4',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Clientes',
  icon: 'i-lucide-users',
  to: '/customers',
  defaultOpen: true,
  type: 'trigger',
  children: [{
    label: 'Painel',
    to: '/customers/painel',
    exact: true,
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Meus clientes',
    to: '/customers/certificados',
    onSelect: () => {
      open.value = false
    }
  }]
}, {
  label: 'Monitoramento',
  icon: 'i-lucide-radar',
  to: '/monitoring',
  type: 'trigger',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Work',
  icon: 'i-lucide-briefcase',
  to: '/work',
  type: 'trigger',
  onSelect: () => {
    open.value = false
  }
}, {
  label: 'Settings',
  to: '/settings',
  icon: 'i-lucide-settings',
  defaultOpen: true,
  type: 'trigger',
  children: [{
    label: 'General',
    to: '/settings',
    exact: true,
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Members',
    to: '/settings/members',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Notifications',
    to: '/settings/notifications',
    onSelect: () => {
      open.value = false
    }
  }, {
    label: 'Security',
    to: '/settings/security',
    onSelect: () => {
      open.value = false
    }
  }]
}], [{
  label: 'Feedback',
  icon: 'i-lucide-message-circle',
  to: 'https://github.com/nuxt-ui-templates/dashboard',
  target: '_blank'
}, {
  label: 'Help & Support',
  icon: 'i-lucide-info',
  to: 'https://github.com/nuxt-ui-templates/dashboard',
  target: '_blank'
}]] satisfies NavigationMenuItem[][]

const navLinks = computed<NavigationMenuItem[][]>(() => {
  const close = () => {
    open.value = false
  }
  const main: NavigationMenuItem[] = (links[0] ?? []).map((item) => {
    if (item.label === 'Clientes') {
      return {
        ...item,
        children: [{
          label: 'Painel',
          to: '/customers/painel',
          exact: true,
          active: route.path === '/customers/painel',
          onSelect: close
        }, {
          label: 'Meus clientes',
          to: '/customers/certificados',
          active: route.path.startsWith('/customers/certificados') || route.path.startsWith('/customers/procuracao'),
          onSelect: close
        }]
      }
    }
    if (item.label === 'Monitoramento') {
      return {
        ...item,
        defaultOpen: route.path.startsWith('/monitoring'),
        children: monitoringSidebarChildren(route.path).map(child => ({
          ...child,
          onSelect: close
        }))
      }
    }
    if (item.label === 'Work') {
      return {
        ...item,
        defaultOpen: route.path.startsWith('/work'),
        children: workSidebarChildren(route.path).map(child => ({
          ...child,
          onSelect: close
        }))
      }
    }
    return item
  })
  if (isSuperAdmin.value) {
    const close = () => {
      open.value = false
    }
    main.push({
      label: 'Admin',
      icon: 'i-lucide-shield-check',
      to: '/admin',
      defaultOpen: true,
      type: 'trigger',
      children: [{
        label: 'Resumo',
        to: '/admin',
        exact: true,
        onSelect: close
      }, {
        label: 'Contas',
        to: '/admin/contas',
        onSelect: close
      }, {
        label: 'Planos',
        to: '/admin/planos',
        onSelect: close
      }, {
        label: 'Assinaturas',
        to: '/admin/assinaturas',
        onSelect: close
      }, {
        label: 'Usuários',
        to: '/admin/usuarios',
        onSelect: close
      }, {
        label: 'Suporte',
        to: '/admin/suporte',
        onSelect: close
      }]
    })
  }
  return [main, links[1] ?? []]
})

const groups = computed(() => [{
  id: 'links',
  label: 'Go to',
  items: links.flat()
}, {
  id: 'code',
  label: 'Code',
  items: [{
    id: 'source',
    label: 'View page source',
    icon: 'i-simple-icons-github',
    to: `https://github.com/nuxt-ui-templates/dashboard/blob/main/app/pages${route.path === '/' ? '/index' : route.path}.vue`,
    target: '_blank'
  }]
}])

onMounted(async () => {
  const cookie = useCookie('cookie-consent')
  if (cookie.value === 'accepted') {
    return
  }

  toast.add({
    title: 'We use first-party cookies to enhance your experience on our website.',
    duration: 0,
    close: false,
    actions: [{
      label: 'Accept',
      color: 'neutral',
      variant: 'outline',
      onClick: () => {
        cookie.value = 'accepted'
      }
    }, {
      label: 'Opt out',
      color: 'neutral',
      variant: 'ghost'
    }]
  })
})
</script>

<template>
  <SupportBanner />

  <UDashboardGroup unit="rem" :class="[inSupportMode && 'pt-12']">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <AccountSwitcher v-if="isSuperAdmin" :collapsed="collapsed" />
        <TeamsMenu v-else :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UDashboardSearchButton :collapsed="collapsed" class="bg-transparent ring-default" />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="navLinks[0]"
          orientation="vertical"
          tooltip
          popover
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="navLinks[1]"
          orientation="vertical"
          tooltip
          class="mt-auto"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardSearch :groups="groups" />

    <slot />

    <NotificationsSlideover />
  </UDashboardGroup>
</template>
