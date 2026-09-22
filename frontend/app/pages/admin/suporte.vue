<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
})

interface AdminAccount {
  id: number
  name: string
  status: 'active' | 'suspended'
}

interface SupportLog {
  id: number
  action: string
  ip?: string | null
  metadata?: Record<string, unknown> | null
  created_at: string
  super_admin?: { id: number, name: string, email: string } | null
  account?: { id: number, name: string } | null
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const ACTION_META: Record<string, { label: string, color: 'info' | 'neutral' | 'success' | 'warning' | 'error' }> = {
  enter: { label: 'Entrada', color: 'info' },
  exit: { label: 'Saída', color: 'neutral' },
  create: { label: 'Criação', color: 'success' },
  update: { label: 'Edição', color: 'warning' },
  delete: { label: 'Exclusão', color: 'error' }
}

const { $api } = useNuxtApp()
const toast = useToast()
const { accounts, switchAccount, enterSupport } = useAuth()

const query = ref('')
const found = ref<AdminAccount[]>([])
const searching = ref(false)

const logs = ref<SupportLog[]>([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsPerPage = 15
const logsAccountId = ref<number | null>(null)
const loadingLogs = ref(false)

const logColumns: TableColumn<SupportLog>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'created', header: 'Quando' },
  { accessorKey: 'actor', header: 'Super admin' },
  { accessorKey: 'account', header: 'Conta' },
  { accessorKey: 'action', header: 'Ação' },
  { accessorKey: 'ip', header: 'IP' }
]

function isOwnAccount(id: number) {
  return accounts.value.some(a => a.id === id)
}

async function enterAccount(account: AdminAccount) {
  try {
    if (isOwnAccount(account.id)) {
      await switchAccount(account.id)
    } else {
      await enterSupport(account.id)
    }
    window.location.assign('/')
  } catch {
    toast.add({ title: 'Não foi possível entrar na conta', color: 'error' })
  }
}

async function search() {
  const term = query.value.trim()
  if (!term) {
    found.value = []
    return
  }
  searching.value = true
  try {
    if (/^\d+$/.test(term)) {
      const account = await $api<AdminAccount>(`/admin/accounts/${term}`)
      found.value = [account]
    } else {
      const res = await $api<Paginated<AdminAccount>>('/admin/accounts', { params: { page: 1 } })
      const lower = term.toLowerCase()
      found.value = res.data.filter(a => a.name.toLowerCase().includes(lower))
    }
    if (!found.value.length) {
      toast.add({ title: 'Nenhuma conta encontrada', color: 'neutral' })
    }
  } catch {
    toast.add({ title: 'Conta não encontrada', color: 'warning' })
    found.value = []
  } finally {
    searching.value = false
  }
}

async function loadLogs() {
  loadingLogs.value = true
  try {
    const params: Record<string, number> = { page: logsPage.value }
    if (logsAccountId.value) params.account_id = logsAccountId.value
    const res = await $api<Paginated<SupportLog>>('/admin/support/logs', { params })
    logs.value = res.data
    logsTotal.value = res.total
  } catch {
    toast.add({ title: 'Não foi possível carregar os logs', color: 'error' })
  } finally {
    loadingLogs.value = false
  }
}

onMounted(loadLogs)
watch(logsPage, loadLogs)
watch(logsAccountId, () => {
  logsPage.value = 1
  void loadLogs()
})

function actionMeta(action: string) {
  return ACTION_META[action] ?? { label: action, color: 'neutral' as const }
}
</script>

<template>
  <UDashboardPanel id="admin-suporte">
    <template #header>
      <UDashboardNavbar title="Suporte">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UCard>
        <template #header>
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-search" class="size-5 text-dimmed" />
            <h2 class="font-semibold">
              Entrar em uma conta
            </h2>
          </div>
        </template>

        <form class="flex gap-2" @submit.prevent="search">
          <UInput
            v-model="query"
            placeholder="Buscar por nome ou ID da conta..."
            icon="i-lucide-search"
            class="flex-1"
          />
          <UButton label="Buscar" type="submit" :loading="searching" />
        </form>

        <ul v-if="found.length" class="mt-4 divide-y divide-default">
          <li v-for="account in found" :key="account.id" class="flex items-center justify-between gap-3 py-2">
            <div class="flex items-center gap-2 min-w-0">
              <span class="text-sm text-muted">#{{ account.id }}</span>
              <span class="font-medium truncate">{{ account.name }}</span>
              <UBadge :color="account.status === 'active' ? 'success' : 'error'" variant="subtle" size="sm">
                {{ account.status === 'active' ? 'Ativa' : 'Suspensa' }}
              </UBadge>
            </div>
            <UButton
              label="Entrar"
              icon="i-lucide-life-buoy"
              size="sm"
              @click="enterAccount(account)"
            />
          </li>
        </ul>
      </UCard>

      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-scroll-text" class="size-5 text-dimmed" />
              <h2 class="font-semibold">
                Auditoria de acessos
              </h2>
            </div>
            <div class="flex items-center gap-2">
              <UInputNumber
                v-model="logsAccountId"
                :min="1"
                placeholder="Filtrar por conta (ID)"
                class="w-52"
              />
              <UButton
                v-if="logsAccountId"
                label="Limpar"
                color="neutral"
                variant="ghost"
                size="sm"
                @click="logsAccountId = null"
              />
            </div>
          </div>
        </template>

        <UTable
          :data="logs"
          :columns="logColumns"
          :loading="loadingLogs"
          class="shrink-0"
        >
          <template #created-cell="{ row }">
            {{ new Date(row.original.created_at).toLocaleString('pt-BR') }}
          </template>

          <template #actor-cell="{ row }">
            {{ row.original.super_admin?.name ?? '—' }}
          </template>

          <template #account-cell="{ row }">
            {{ row.original.account?.name ?? `#${row.original.id}` }}
          </template>

          <template #action-cell="{ row }">
            <UBadge :color="actionMeta(row.original.action).color" variant="subtle">
              {{ actionMeta(row.original.action).label }}
            </UBadge>
          </template>

          <template #ip-cell="{ row }">
            <span class="text-muted">{{ row.original.ip ?? '—' }}</span>
          </template>
        </UTable>

        <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-4">
          <div class="text-sm text-muted">
            {{ logsTotal }} evento(s) no total.
          </div>

          <UPagination v-model:page="logsPage" :total="logsTotal" :items-per-page="logsPerPage" />
        </div>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
