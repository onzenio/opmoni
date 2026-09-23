<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import type { ClientPortfolioDocument } from '~/types/client'
import { customerListPath } from '~/utils/customerRoutes'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const { canManageClients } = useAuth()
const createRequest = useState('customers-create', () => 0)
const detailTitle = useState<string | null>('customers-detail-title', () => null)

function listTo(document: ClientPortfolioDocument) {
  return customerListPath(document)
}

const onDetail = computed(() => route.path.startsWith('/customers/empresa/'))
const onList = computed(() => route.path.startsWith('/customers/certificados') || route.path.startsWith('/customers/procuracao'))

const navbarTitle = computed(() => {
  if (onDetail.value) return detailTitle.value || 'Cliente'
  return onList.value ? 'Meus clientes' : 'Clientes'
})

watch(onDetail, (value) => {
  if (!value) detailTitle.value = null
})

const links = computed<NavigationMenuItem[][]>(() => [[
  {
    label: 'Painel',
    icon: 'i-lucide-layout-dashboard',
    to: '/customers/painel',
    exact: true
  },
  {
    label: 'Certificados',
    icon: 'i-lucide-key-round',
    to: listTo('certificate'),
    active: route.path.startsWith('/customers/certificados')
  },
  {
    label: 'Procuração',
    icon: 'i-lucide-file-key-2',
    to: listTo('poa'),
    active: route.path.startsWith('/customers/procuracao')
  }
]])

async function goBack() {
  if (import.meta.client && window.history.length > 1) {
    router.back()
    return
  }
  await navigateTo('/customers/certificados')
}

async function requestCreate() {
  const onListRoute = route.path.startsWith('/customers/certificados') || route.path.startsWith('/customers/procuracao')
  if (!onListRoute) await navigateTo('/customers/certificados')
  createRequest.value++
}
</script>

<template>
  <UDashboardPanel id="customers" :ui="{ body: 'min-h-0 flex-1 gap-0 overflow-hidden p-0 sm:p-0' }">
    <template #header>
      <UDashboardNavbar :title="navbarTitle">
        <template #leading>
          <UDashboardSidebarCollapse />
          <UButton
            v-if="onDetail"
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            square
            aria-label="Voltar"
            @click="goBack"
          />
        </template>

        <template #right>
          <UButton
            v-if="canManageClients && !onDetail"
            icon="i-lucide-plus"
            color="primary"
            aria-label="Novo cliente"
            class="sm:hidden"
            @click="requestCreate"
          />
          <UButton
            v-if="canManageClients && !onDetail"
            label="Novo cliente"
            icon="i-lucide-plus"
            color="primary"
            class="hidden sm:inline-flex"
            @click="requestCreate"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar v-if="!onDetail">
        <UNavigationMenu :items="links" highlight class="-mx-1 min-w-0 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
