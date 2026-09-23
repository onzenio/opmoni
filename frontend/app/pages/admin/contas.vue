<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin']
})

interface AdminAccount {
  id: number
  name: string
  status: 'active' | 'suspended'
  members_count?: number
  subscription?: {
    id: number
    status: string
    plan?: { id: number, slug: string, name: string } | null
  } | null
  created_at: string
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
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

const accountsList = ref<AdminAccount[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)
const q = ref('')
const statusFilter = ref<'all' | 'active' | 'suspended'>('all')

const columns: TableColumn<AdminAccount>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'name', header: 'Conta' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'plan', header: 'Plano' },
  { accessorKey: 'members', header: 'Membros' },
  { id: 'actions' }
]

const rows = computed(() => {
  const term = q.value.trim().toLowerCase()
  return accountsList.value.filter((account) => {
    const matchesTerm = !term || account.name.toLowerCase().includes(term) || String(account.id).includes(term)
    const matchesStatus = statusFilter.value === 'all' || account.status === statusFilter.value
    return matchesTerm && matchesStatus
  })
})

async function load() {
  loading.value = true
  try {
    const res = await $api<Paginated<AdminAccount>>('/admin/accounts', { params: { page: page.value } })
    accountsList.value = res.data
    total.value = res.total
  } catch {
    toast.add({ title: 'Não foi possível carregar as contas', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(page, load)

function isOwnAccount(id: number) {
  return accounts.value.some(a => a.id === id)
}

async function toggleStatus(account: AdminAccount) {
  const status = account.status === 'active' ? 'suspended' : 'active'
  try {
    await $api(`/admin/accounts/${account.id}`, { method: 'PATCH', body: { status } })
    toast.add({
      title: status === 'suspended' ? 'Conta suspensa' : 'Conta reativada',
      description: account.name,
      color: status === 'suspended' ? 'warning' : 'success'
    })
    await load()
    return true
  } catch {
    toast.add({ title: 'Não foi possível atualizar a conta', color: 'error' })
    return false
  }
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

const confirmOpen = ref(false)
const confirmTarget = ref<AdminAccount | null>(null)
const confirming = ref(false)

function openStatusConfirm(account: AdminAccount) {
  confirmTarget.value = account
  confirmOpen.value = true
}

async function confirmStatus() {
  if (!confirmTarget.value) return
  confirming.value = true
  try {
    const ok = await toggleStatus(confirmTarget.value)
    if (ok) confirmOpen.value = false
  } finally {
    confirming.value = false
  }
}

function accountActions(account: AdminAccount) {
  return [{
    type: 'label' as const,
    label: 'Ações'
  }, {
    label: 'Renomear',
    icon: 'i-lucide-pencil',
    onSelect: () => openRename(account)
  }, {
    label: 'Entrar em suporte',
    icon: 'i-lucide-life-buoy',
    onSelect: () => enterAccount(account)
  }, {
    type: 'separator' as const
  }, {
    label: account.status === 'active' ? 'Suspender' : 'Reativar',
    icon: account.status === 'active' ? 'i-lucide-ban' : 'i-lucide-circle-check',
    color: account.status === 'active' ? 'error' as const : undefined,
    onSelect: () => openStatusConfirm(account)
  }]
}

const createOpen = ref(false)
const createSchema = z.object({
  name: z.string().min(2, 'Nome muito curto')
})
type CreateSchema = z.output<typeof createSchema>
const createState = reactive<Partial<CreateSchema>>({ name: '' })
const creating = ref(false)

async function onCreate(event: FormSubmitEvent<CreateSchema>) {
  creating.value = true
  try {
    await $api('/admin/accounts', { method: 'POST', body: { name: event.data.name } })
    toast.add({ title: 'Conta criada', description: event.data.name, color: 'success' })
    createOpen.value = false
    createState.name = ''
    page.value = 1
    await load()
  } catch {
    toast.add({ title: 'Não foi possível criar a conta', color: 'error' })
  } finally {
    creating.value = false
  }
}

const renameOpen = ref(false)
const renameSchema = z.object({
  name: z.string().min(2, 'Nome muito curto')
})
type RenameSchema = z.output<typeof renameSchema>
const renameTarget = ref<AdminAccount | null>(null)
const renameState = reactive<Partial<RenameSchema>>({ name: '' })
const renaming = ref(false)

function openRename(account: AdminAccount) {
  renameTarget.value = account
  renameState.name = account.name
  renameOpen.value = true
}

async function onRename(event: FormSubmitEvent<RenameSchema>) {
  if (!renameTarget.value) return
  renaming.value = true
  try {
    await $api(`/admin/accounts/${renameTarget.value.id}`, { method: 'PATCH', body: { name: event.data.name } })
    toast.add({ title: 'Conta renomeada', description: event.data.name, color: 'success' })
    renameOpen.value = false
    await load()
  } catch {
    toast.add({ title: 'Não foi possível renomear a conta', color: 'error' })
  } finally {
    renaming.value = false
  }
}
</script>

<template>
  <div>
    <UPageCard
      title="Contas"
      description="Empresas que usam o opmoni."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        label="Nova conta"
        icon="i-lucide-plus"
        color="neutral"
        class="w-fit lg:ms-auto"
        @click="createOpen = true"
      />
    </UPageCard>

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }"
    >
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-1.5">
          <UInput
            v-model="q"
            class="max-w-sm"
            icon="i-lucide-search"
            placeholder="Filtrar por nome ou ID..."
          />

          <USelect
            v-model="statusFilter"
            :items="[
              { label: 'Todas', value: 'all' },
              { label: 'Ativas', value: 'active' },
              { label: 'Suspensas', value: 'suspended' }
            ]"
            :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
            placeholder="Status"
            class="min-w-28"
          />
        </div>
      </template>

      <div class="flex flex-col gap-4 p-4 sm:p-6">
        <UTable
          :data="rows"
          :columns="columns"
          :loading="loading"
          class="shrink-0"
          :ui="tableUi"
        >
          <template #name-cell="{ row }">
            <p class="font-medium text-highlighted">
              {{ row.original.name }}
            </p>
          </template>

          <template #status-cell="{ row }">
            <UBadge
              :color="row.original.status === 'active' ? 'success' : 'error'"
              variant="subtle"
            >
              {{ row.original.status === 'active' ? 'Ativa' : 'Suspensa' }}
            </UBadge>
          </template>

          <template #plan-cell="{ row }">
            {{ row.original.subscription?.plan?.name ?? '—' }}
          </template>

          <template #members-cell="{ row }">
            {{ row.original.members_count ?? '—' }}
          </template>

          <template #actions-cell="{ row }">
            <div class="text-right">
              <UDropdownMenu
                :items="accountActions(row.original)"
                :content="{ align: 'end' }"
              >
                <UButton
                  icon="i-lucide-ellipsis-vertical"
                  color="neutral"
                  variant="ghost"
                  class="ml-auto"
                />
              </UDropdownMenu>
            </div>
          </template>

          <template #empty>
            <div class="flex flex-col items-center justify-center gap-2 py-8 text-sm text-muted">
              <UIcon name="i-lucide-building-2" class="size-6" />
              <span>Nenhuma conta encontrada.</span>
            </div>
          </template>
        </UTable>

        <div class="flex items-center justify-between gap-3 border-t border-default pt-4">
          <div class="text-sm text-muted">
            {{ rows.length }} de {{ total }} conta(s)
          </div>

          <div class="flex items-center gap-1.5">
            <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
          </div>
        </div>
      </div>
    </UPageCard>
  </div>

  <UModal
    v-model:open="createOpen"
    title="Nova conta"
    description="Criar uma conta manualmente."
  >
    <template #body>
      <UForm
        id="create-account-form"
        :schema="createSchema"
        :state="createState"
        class="space-y-4"
        @submit="onCreate"
      >
        <UFormField label="Nome" name="name">
          <UInput v-model="createState.name" placeholder="Nome da empresa" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer="{ close }">
      <UButton
        label="Cancelar"
        color="neutral"
        variant="outline"
        @click="close"
      />
      <UButton
        label="Criar"
        type="submit"
        form="create-account-form"
        :loading="creating"
      />
    </template>
  </UModal>

  <USlideover
    v-model:open="renameOpen"
    title="Renomear conta"
    :description="renameTarget?.name"
    :ui="{ footer: 'justify-end' }"
  >
    <template #body>
      <UForm
        id="rename-account-form"
        :schema="renameSchema"
        :state="renameState"
        class="space-y-4"
        @submit="onRename"
      >
        <UFormField label="Nome" name="name">
          <UInput v-model="renameState.name" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer="{ close }">
      <UButton
        label="Cancelar"
        color="neutral"
        variant="outline"
        @click="close"
      />
      <UButton
        label="Salvar"
        type="submit"
        form="rename-account-form"
        :loading="renaming"
      />
    </template>
  </USlideover>

  <UModal
    v-model:open="confirmOpen"
    :title="confirmTarget?.status === 'active' ? 'Suspender conta' : 'Reativar conta'"
    :description="confirmTarget?.status === 'active' ? `${confirmTarget?.name} deixa de acessar o app até ser reativada.` : `${confirmTarget?.name} volta a acessar o app.`"
  >
    <template #footer="{ close }">
      <UButton
        label="Cancelar"
        color="neutral"
        variant="outline"
        @click="close"
      />
      <UButton
        :label="confirmTarget?.status === 'active' ? 'Suspender' : 'Reativar'"
        :color="confirmTarget?.status === 'active' ? 'error' : 'primary'"
        :loading="confirming"
        @click="confirmStatus"
      />
    </template>
  </UModal>
</template>
