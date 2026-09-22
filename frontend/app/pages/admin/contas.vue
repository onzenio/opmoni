<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
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

const { $api } = useNuxtApp()
const toast = useToast()
const { accounts, switchAccount, enterSupport } = useAuth()

const accounts_list = ref<AdminAccount[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)

const columns: TableColumn<AdminAccount>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'name', header: 'Conta' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'plan', header: 'Plano' },
  { accessorKey: 'members', header: 'Membros' },
  { id: 'actions' }
]

async function load() {
  loading.value = true
  try {
    const res = await $api<Paginated<AdminAccount>>('/admin/accounts', { params: { page: page.value } })
    accounts_list.value = res.data
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
  } catch {
    toast.add({ title: 'Não foi possível atualizar a conta', color: 'error' })
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
  <UDashboardPanel id="admin-contas">
    <template #header>
      <UDashboardNavbar title="Contas">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #right>
          <UButton label="Nova conta" icon="i-lucide-plus" @click="createOpen = true" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UTable
        :data="accounts_list"
        :columns="columns"
        :loading="loading"
        class="shrink-0"
      >
        <template #status-cell="{ row }">
          <UBadge
            :color="row.original.status === 'active' ? 'success' : 'error'"
            variant="subtle"
            class="capitalize"
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
              :items="[[{
                label: 'Renomear',
                icon: 'i-lucide-pencil',
                onSelect: () => openRename(row.original)
              }, {
                label: row.original.status === 'active' ? 'Suspender' : 'Reativar',
                icon: row.original.status === 'active' ? 'i-lucide-ban' : 'i-lucide-check-circle',
                color: row.original.status === 'active' ? 'error' as const : undefined,
                onSelect: () => toggleStatus(row.original)
              }, {
                label: 'Entrar em suporte',
                icon: 'i-lucide-life-buoy',
                onSelect: () => enterAccount(row.original)
              }]]"
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
      </UTable>

      <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
        <div class="text-sm text-muted">
          {{ total }} conta(s) no total.
        </div>

        <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
      </div>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="createOpen" title="Nova conta" description="Criar uma conta manualmente">
    <template #body>
      <UForm
        :schema="createSchema"
        :state="createState"
        class="space-y-4"
        @submit="onCreate"
      >
        <UFormField label="Nome" name="name">
          <UInput v-model="createState.name" placeholder="Nome da empresa" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            @click="createOpen = false"
          />
          <UButton
            label="Criar"
            color="primary"
            variant="solid"
            type="submit"
            :loading="creating"
          />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="renameOpen" title="Renomear conta" :description="renameTarget?.name">
    <template #body>
      <UForm
        :schema="renameSchema"
        :state="renameState"
        class="space-y-4"
        @submit="onRename"
      >
        <UFormField label="Nome" name="name">
          <UInput v-model="renameState.name" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            @click="renameOpen = false"
          />
          <UButton
            label="Salvar"
            color="primary"
            variant="solid"
            type="submit"
            :loading="renaming"
          />
        </div>
      </UForm>
    </template>
  </UModal>
</template>
