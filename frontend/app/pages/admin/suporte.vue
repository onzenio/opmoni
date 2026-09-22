<script setup lang="ts">
import type { TableColumn, TabsItem } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin']
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

const tableUi = {
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
}

const { $api } = useNuxtApp()
const toast = useToast()
const { accounts, switchAccount, enterSupport } = useAuth()

const tab = ref('entrar')
const tabs = [{
  label: 'Entrar',
  value: 'entrar',
  icon: 'i-lucide-life-buoy'
}, {
  label: 'Auditoria',
  value: 'auditoria',
  icon: 'i-lucide-scroll-text'
}] satisfies TabsItem[]

const query = ref('')
const found = ref<AdminAccount[]>([])
const searching = ref(false)

const logs = ref<SupportLog[]>([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsPerPage = 15
const accountQuery = ref('')
const loadingLogs = ref(false)

const logsAccountId = computed(() => {
  const value = Number(accountQuery.value)
  return accountQuery.value && Number.isInteger(value) && value > 0 ? value : null
})

const logColumns: TableColumn<SupportLog>[] = [
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
      const lower = term.toLowerCase()
      const matches: AdminAccount[] = []
      let page = 1
      let lastPage = 1
      do {
        const res = await $api<Paginated<AdminAccount>>('/admin/accounts', { params: { page } })
        lastPage = res.last_page
        matches.push(...res.data.filter(a => a.name.toLowerCase().includes(lower)))
        page += 1
      } while (page <= lastPage)
      found.value = matches
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
  if (logsPage.value === 1) {
    void loadLogs()
    return
  }
  logsPage.value = 1
})

function actionMeta(action: string) {
  return ACTION_META[action] ?? { label: action, color: 'neutral' as const }
}
</script>

<template>
  <div>
    <UPageCard
      title="Suporte"
      description="Entre numa conta ou consulte a auditoria."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    />

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }"
    >
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-1.5">
          <UTabs v-model="tab" :items="tabs" :content="false" />

          <div class="flex flex-wrap items-center gap-1.5">
            <UInput
              v-if="tab === 'entrar'"
              v-model="query"
              class="max-w-sm"
              icon="i-lucide-search"
              placeholder="Nome ou ID da conta"
              @keydown.enter.prevent="search"
            />
            <UInput
              v-else
              v-model="accountQuery"
              class="max-w-sm"
              icon="i-lucide-search"
              placeholder="Filtrar por ID da conta..."
            />
            <UButton
              v-if="tab === 'entrar'"
              label="Buscar"
              color="neutral"
              :loading="searching"
              @click="search"
            />
          </div>
        </div>
      </template>

      <ul v-if="tab === 'entrar' && found.length" role="list" class="divide-y divide-default">
        <li
          v-for="account in found"
          :key="account.id"
          class="flex items-center justify-between gap-3 py-3 px-4 sm:px-6"
        >
          <div class="min-w-0">
            <p class="font-medium text-highlighted truncate">
              {{ account.name }}
            </p>
            <p class="text-sm text-muted">
              #{{ account.id }}
            </p>
          </div>

          <div class="flex items-center gap-3">
            <UBadge :color="account.status === 'active' ? 'success' : 'error'" variant="subtle">
              {{ account.status === 'active' ? 'Ativa' : 'Suspensa' }}
            </UBadge>
            <UButton
              icon="i-lucide-life-buoy"
              color="neutral"
              variant="ghost"
              @click="enterAccount(account)"
            />
          </div>
        </li>
      </ul>
      <div v-else-if="tab === 'entrar'" class="flex flex-col items-center justify-center gap-2 py-10 text-sm text-muted">
        <UIcon name="i-lucide-search" class="size-6" />
        <span>Busque uma conta pelo nome ou pelo ID para entrar em suporte.</span>
      </div>

      <div v-else class="flex flex-col gap-4 p-4 sm:p-6">
    <UTable
      :data="logs"
      :columns="logColumns"
      :loading="loadingLogs"
      class="shrink-0"
      :ui="tableUi"
    >
      <template #created-cell="{ row }">
        {{ new Date(row.original.created_at).toLocaleString('pt-BR') }}
      </template>

      <template #actor-cell="{ row }">
        <p class="font-medium text-highlighted">
          {{ row.original.super_admin?.name ?? '—' }}
        </p>
      </template>

      <template #account-cell="{ row }">
        {{ row.original.account?.name ?? '—' }}
      </template>

      <template #action-cell="{ row }">
        <UBadge :color="actionMeta(row.original.action).color" variant="subtle">
          {{ actionMeta(row.original.action).label }}
        </UBadge>
      </template>

      <template #ip-cell="{ row }">
        <span class="text-muted">{{ row.original.ip ?? '—' }}</span>
      </template>

      <template #empty>
        <div class="flex flex-col items-center justify-center gap-2 py-8 text-sm text-muted">
          <UIcon name="i-lucide-scroll-text" class="size-6" />
          <span>Nenhum evento encontrado.</span>
        </div>
      </template>
    </UTable>

    <div class="flex items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        {{ logsTotal }} evento(s)
      </div>

      <div class="flex items-center gap-1.5">
        <UPagination v-model:page="logsPage" :total="logsTotal" :items-per-page="logsPerPage" />
      </div>
    </div>
      </div>
    </UPageCard>
  </div>
</template>
