<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin']
})

interface AdminSubscription {
  id: number
  status: 'active' | 'past_due' | 'canceled'
  account?: { id: number, name: string } | null
  plan?: { id: number, slug: string, name: string } | null
}

interface AdminPlan {
  id: number
  slug: string
  name: string
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const STATUS_META: Record<AdminSubscription['status'], { label: string, color: 'success' | 'warning' | 'neutral' }> = {
  active: { label: 'Ativa', color: 'success' },
  past_due: { label: 'Inadimplente', color: 'warning' },
  canceled: { label: 'Cancelada', color: 'neutral' }
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

const subscriptions = ref<AdminSubscription[]>([])
const plans = ref<AdminPlan[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)
const q = ref('')
const statusFilter = ref<'all' | AdminSubscription['status']>('all')

const columns: TableColumn<AdminSubscription>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'account', header: 'Conta' },
  { accessorKey: 'plan', header: 'Plano' },
  { accessorKey: 'status', header: 'Status' },
  { id: 'actions' }
]

const rows = computed(() => {
  const term = q.value.trim().toLowerCase()
  return subscriptions.value.filter((subscription) => {
    const accountName = subscription.account?.name?.toLowerCase() ?? ''
    const planName = subscription.plan?.name?.toLowerCase() ?? ''
    const matchesTerm = !term || accountName.includes(term) || planName.includes(term) || String(subscription.id).includes(term)
    const matchesStatus = statusFilter.value === 'all' || subscription.status === statusFilter.value
    return matchesTerm && matchesStatus
  })
})

async function load() {
  loading.value = true
  try {
    const [subs, planList] = await Promise.all([
      $api<Paginated<AdminSubscription>>('/admin/subscriptions', { params: { page: page.value } }),
      plans.value.length ? Promise.resolve(null) : $api<AdminPlan[]>('/admin/plans')
    ])
    subscriptions.value = subs.data
    total.value = subs.total
    if (planList) plans.value = planList
  } catch {
    toast.add({ title: 'Não foi possível carregar as assinaturas', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(page, load)

const editOpen = ref(false)
const editSchema = z.object({
  plan_id: z.number({ error: 'Escolha um plano' }),
  status: z.enum(['active', 'past_due', 'canceled'])
})
type EditSchema = z.output<typeof editSchema>
const editTarget = ref<AdminSubscription | null>(null)
const editState = reactive<Partial<EditSchema>>({ plan_id: undefined, status: undefined })
const saving = ref(false)

function openEdit(subscription: AdminSubscription) {
  editTarget.value = subscription
  editState.plan_id = subscription.plan?.id
  editState.status = subscription.status
  editOpen.value = true
}

function subscriptionActions(subscription: AdminSubscription) {
  return [{
    type: 'label' as const,
    label: 'Ações'
  }, {
    label: 'Editar',
    icon: 'i-lucide-pencil',
    onSelect: () => openEdit(subscription)
  }]
}

async function onSave(event: FormSubmitEvent<EditSchema>) {
  if (!editTarget.value) return
  saving.value = true
  try {
    await $api(`/admin/subscriptions/${editTarget.value.id}`, {
      method: 'PUT',
      body: { plan_id: event.data.plan_id, status: event.data.status }
    })
    toast.add({ title: 'Assinatura atualizada', color: 'success' })
    editOpen.value = false
    await load()
  } catch {
    toast.add({ title: 'Não foi possível atualizar a assinatura', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <UPageCard
      title="Assinaturas"
      description="Plano e status de cada conta."
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
          <UInput
            v-model="q"
            class="max-w-sm"
            icon="i-lucide-search"
            placeholder="Filtrar por conta ou plano..."
          />

          <USelect
            v-model="statusFilter"
            :items="[
              { label: 'Todas', value: 'all' },
              { label: 'Ativas', value: 'active' },
              { label: 'Inadimplentes', value: 'past_due' },
              { label: 'Canceladas', value: 'canceled' }
            ]"
            :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
            placeholder="Status"
            class="min-w-36"
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
          <template #account-cell="{ row }">
            <p class="font-medium text-highlighted">
              {{ row.original.account?.name ?? `#${row.original.id}` }}
            </p>
          </template>

          <template #plan-cell="{ row }">
            {{ row.original.plan?.name ?? '—' }}
          </template>

          <template #status-cell="{ row }">
            <UBadge :color="STATUS_META[row.original.status].color" variant="subtle">
              {{ STATUS_META[row.original.status].label }}
            </UBadge>
          </template>

          <template #actions-cell="{ row }">
            <div class="text-right">
              <UDropdownMenu
                :items="subscriptionActions(row.original)"
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
              <UIcon name="i-lucide-receipt" class="size-6" />
              <span>Nenhuma assinatura encontrada.</span>
            </div>
          </template>
        </UTable>

        <div class="flex items-center justify-between gap-3 border-t border-default pt-4">
          <div class="text-sm text-muted">
            {{ rows.length }} de {{ total }} assinatura(s)
          </div>

          <div class="flex items-center gap-1.5">
            <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
          </div>
        </div>
      </div>
    </UPageCard>
  </div>

  <USlideover
    v-model:open="editOpen"
    title="Editar assinatura"
    :description="editTarget?.account?.name"
    :ui="{ footer: 'justify-end' }"
  >
    <template #body>
      <UForm
        id="edit-subscription-form"
        :schema="editSchema"
        :state="editState"
        class="space-y-4"
        @submit="onSave"
      >
        <UFormField label="Plano" name="plan_id">
          <USelect
            v-model="editState.plan_id"
            :items="plans.map(p => ({ label: p.name, value: p.id }))"
            placeholder="Escolha um plano"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Status" name="status">
          <USelect
            v-model="editState.status"
            :items="[
              { label: 'Ativa', value: 'active' },
              { label: 'Inadimplente', value: 'past_due' },
              { label: 'Cancelada', value: 'canceled' }
            ]"
            class="w-full"
          />
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
        form="edit-subscription-form"
        :loading="saving"
      />
    </template>
  </USlideover>
</template>
