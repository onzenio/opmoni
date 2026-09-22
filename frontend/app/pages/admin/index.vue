<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
})

interface Totals {
  accounts: number
  users: number
  subscriptions: number
  plans: number
}

interface SupportLog {
  id: number
  action: string
  created_at: string
  super_admin?: { name: string } | null
  account?: { name: string } | null
}

const { $api } = useNuxtApp()
const toast = useToast()

const totals = ref<Totals>({ accounts: 0, users: 0, subscriptions: 0, plans: 0 })
const recentLogs = ref<SupportLog[]>([])
const loading = ref(false)

const cards = computed(() => [
  { label: 'Contas', value: totals.value.accounts, icon: 'i-lucide-building-2', to: '/admin/contas' },
  { label: 'Planos', value: totals.value.plans, icon: 'i-lucide-layers', to: '/admin/planos' },
  { label: 'Assinaturas', value: totals.value.subscriptions, icon: 'i-lucide-receipt', to: '/admin/assinaturas' },
  { label: 'Usuários', value: totals.value.users, icon: 'i-lucide-users', to: '/admin/usuarios' }
])

async function load() {
  loading.value = true
  try {
    const [accountsRes, usersRes, subsRes, plansRes, logsRes] = await Promise.all([
      $api<{ total: number }>('/admin/accounts', { params: { page: 1 } }),
      $api<{ total: number }>('/admin/users', { params: { page: 1 } }),
      $api<{ total: number }>('/admin/subscriptions', { params: { page: 1 } }),
      $api<unknown[]>('/admin/plans'),
      $api<{ data: SupportLog[] }>('/admin/support/logs', { params: { page: 1 } })
    ])
    totals.value = {
      accounts: accountsRes.total,
      users: usersRes.total,
      subscriptions: subsRes.total,
      plans: plansRes.length
    }
    recentLogs.value = logsRes.data.slice(0, 5)
  } catch {
    toast.add({ title: 'Não foi possível carregar o resumo', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <UDashboardPanel id="admin-resumo">
    <template #header>
      <UDashboardNavbar title="Administração">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #right>
          <UButton label="Suporte" icon="i-lucide-life-buoy" to="/admin/suporte" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <UCard v-for="card in cards" :key="card.label">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted">
                {{ card.label }}
              </p>
              <p class="text-3xl font-semibold mt-1">
                <USkeleton v-if="loading" class="h-9 w-16" />
                <span v-else>{{ card.value }}</span>
              </p>
            </div>
            <UIcon :name="card.icon" class="size-8 text-dimmed" />
          </div>
          <ULink :to="card.to" class="text-sm text-primary font-medium mt-3 inline-block">
            Gerenciar →
          </ULink>
        </UCard>
      </div>

      <UCard>
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-scroll-text" class="size-5 text-dimmed" />
              <h2 class="font-semibold">
                Acessos de suporte recentes
              </h2>
            </div>
            <ULink to="/admin/suporte" class="text-sm text-primary font-medium">
              Ver auditoria →
            </ULink>
          </div>
        </template>

        <ul v-if="recentLogs.length" class="divide-y divide-default">
          <li v-for="log in recentLogs" :key="log.id" class="flex items-center justify-between gap-3 py-2 text-sm">
            <span class="min-w-0">
              <span class="font-medium">{{ log.super_admin?.name ?? '—' }}</span>
              <span class="text-muted"> · {{ log.action }} · {{ log.account?.name ?? '—' }}</span>
            </span>
            <span class="text-muted shrink-0">{{ new Date(log.created_at).toLocaleString('pt-BR') }}</span>
          </li>
        </ul>
        <p v-else-if="!loading" class="text-sm text-muted">
          Nenhum acesso de suporte registrado ainda.
        </p>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
