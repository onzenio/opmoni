<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
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

const { $api } = useNuxtApp()
const toast = useToast()

const subscriptions = ref<AdminSubscription[]>([])
const plans = ref<AdminPlan[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)

const columns: TableColumn<AdminSubscription>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'account', header: 'Conta' },
  { accessorKey: 'plan', header: 'Plano' },
  { accessorKey: 'status', header: 'Status' },
  { id: 'actions' }
]

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
  <UDashboardPanel id="admin-assinaturas">
    <template #header>
      <UDashboardNavbar title="Assinaturas">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UTable
        :data="subscriptions"
        :columns="columns"
        :loading="loading"
        class="shrink-0"
      >
        <template #account-cell="{ row }">
          {{ row.original.account?.name ?? `#${row.original.id}` }}
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
            <UButton
              label="Trocar plano / status"
              icon="i-lucide-pencil"
              color="neutral"
              variant="outline"
              size="sm"
              @click="openEdit(row.original)"
            />
          </div>
        </template>
      </UTable>

      <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
        <div class="text-sm text-muted">
          {{ total }} assinatura(s) no total.
        </div>

        <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
      </div>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="editOpen" title="Editar assinatura" :description="editTarget?.account?.name">
    <template #body>
      <UForm
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
        <div class="flex justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            @click="editOpen = false"
          />
          <UButton
            label="Salvar"
            color="primary"
            variant="solid"
            type="submit"
            :loading="saving"
          />
        </div>
      </UForm>
    </template>
  </UModal>
</template>
