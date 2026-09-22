<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin']
})

interface AdminPlan {
  id: number
  slug: string
  name: string
  limits?: Record<string, number> | null
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

const plans = ref<AdminPlan[]>([])
const loading = ref(false)
const q = ref('')

const columns: TableColumn<AdminPlan>[] = [
  { accessorKey: 'name', header: 'Plano' },
  { id: 'users', header: 'Usuários' },
  { id: 'clients', header: 'Clientes' },
  { id: 'monitorings', header: 'Monitoramentos' },
  { id: 'actions' }
]

const rows = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return plans.value
  return plans.value.filter(plan => plan.name.toLowerCase().includes(term) || plan.slug.toLowerCase().includes(term))
})

function limitValue(plan: AdminPlan, key: string) {
  const value = plan.limits?.[key]
  return value === undefined || value === null ? null : value
}

async function load() {
  loading.value = true
  try {
    plans.value = await $api<AdminPlan[]>('/admin/plans')
  } catch {
    toast.add({ title: 'Não foi possível carregar os planos', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)

const editOpen = ref(false)
const editSchema = z.object({
  name: z.string().min(2, 'Nome muito curto'),
  users: z.number().int().min(0).nullable(),
  clients: z.number().int().min(0).nullable(),
  monitorings: z.number().int().min(0).nullable()
})
type EditSchema = z.output<typeof editSchema>
const editTarget = ref<AdminPlan | null>(null)
const editState = reactive<Partial<EditSchema>>({ name: '', users: null, clients: null, monitorings: null })
const saving = ref(false)

function openEdit(plan: AdminPlan) {
  editTarget.value = plan
  editState.name = plan.name
  editState.users = plan.limits?.users ?? null
  editState.clients = plan.limits?.clients ?? null
  editState.monitorings = plan.limits?.monitorings ?? null
  editOpen.value = true
}

function planActions(plan: AdminPlan) {
  return [{
    type: 'label' as const,
    label: 'Ações'
  }, {
    label: 'Editar limites',
    icon: 'i-lucide-pencil',
    onSelect: () => openEdit(plan)
  }]
}

async function onSave(event: FormSubmitEvent<EditSchema>) {
  if (!editTarget.value) return
  const limits: Record<string, number> = {}
  if (event.data.users !== null && event.data.users !== undefined) limits.users = event.data.users
  if (event.data.clients !== null && event.data.clients !== undefined) limits.clients = event.data.clients
  if (event.data.monitorings !== null && event.data.monitorings !== undefined) limits.monitorings = event.data.monitorings
  saving.value = true
  try {
    await $api(`/admin/plans/${editTarget.value.id}`, {
      method: 'PUT',
      body: { name: event.data.name, limits }
    })
    toast.add({ title: 'Plano atualizado', description: event.data.name, color: 'success' })
    editOpen.value = false
    await load()
  } catch {
    toast.add({ title: 'Não foi possível atualizar o plano', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <UPageCard
      title="Planos"
      description="Limites de usuários, clientes e monitoramentos."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    />

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }"
    >
      <template #header>
        <UInput
          v-model="q"
          class="max-w-sm"
          icon="i-lucide-search"
          placeholder="Filtrar por nome ou slug..."
        />
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
        <p class="text-muted">
          {{ row.original.slug }}
        </p>
      </template>

      <template #users-header>
        <div class="text-right">
          Usuários
        </div>
      </template>
      <template #clients-header>
        <div class="text-right">
          Clientes
        </div>
      </template>
      <template #monitorings-header>
        <div class="text-right">
          Monitoramentos
        </div>
      </template>

      <template #users-cell="{ row }">
        <div class="text-right">
          <UBadge v-if="limitValue(row.original, 'users') === null" color="neutral" variant="subtle">
            Ilimitado
          </UBadge>
          <span v-else class="font-medium text-highlighted tabular-nums">{{ limitValue(row.original, 'users') }}</span>
        </div>
      </template>
      <template #clients-cell="{ row }">
        <div class="text-right">
          <UBadge v-if="limitValue(row.original, 'clients') === null" color="neutral" variant="subtle">
            Ilimitado
          </UBadge>
          <span v-else class="font-medium text-highlighted tabular-nums">{{ limitValue(row.original, 'clients') }}</span>
        </div>
      </template>
      <template #monitorings-cell="{ row }">
        <div class="text-right">
          <UBadge v-if="limitValue(row.original, 'monitorings') === null" color="neutral" variant="subtle">
            Ilimitado
          </UBadge>
          <span v-else class="font-medium text-highlighted tabular-nums">{{ limitValue(row.original, 'monitorings') }}</span>
        </div>
      </template>

      <template #actions-cell="{ row }">
        <div class="text-right">
          <UDropdownMenu
            :items="planActions(row.original)"
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
          <UIcon name="i-lucide-layers" class="size-6" />
          <span>Nenhum plano encontrado.</span>
        </div>
      </template>
    </UTable>

    <div class="flex items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        {{ rows.length }} de {{ plans.length }} plano(s)
      </div>
    </div>
      </div>
    </UPageCard>
  </div>

  <USlideover
    v-model:open="editOpen"
    title="Editar plano"
    :description="editTarget?.slug"
    :ui="{ footer: 'justify-end' }"
  >
    <template #body>
      <UForm
        id="edit-plan-form"
        :schema="editSchema"
        :state="editState"
        class="space-y-4"
        @submit="onSave"
      >
        <UFormField label="Nome" name="name">
          <UInput v-model="editState.name" class="w-full" />
        </UFormField>
        <UFormField label="Usuários" name="users" hint="Vazio = ilimitado">
          <UInputNumber
            v-model="editState.users"
            :min="0"
            placeholder="Ilimitado"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Clientes" name="clients" hint="Vazio = ilimitado">
          <UInputNumber
            v-model="editState.clients"
            :min="0"
            placeholder="Ilimitado"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Monitoramentos" name="monitorings" hint="Vazio = ilimitado">
          <UInputNumber
            v-model="editState.monitorings"
            :min="0"
            placeholder="Ilimitado"
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
        form="edit-plan-form"
        :loading="saving"
      />
    </template>
  </USlideover>
</template>
