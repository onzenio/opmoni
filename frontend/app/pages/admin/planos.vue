<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
})

interface AdminPlan {
  id: number
  slug: string
  name: string
  limits?: Record<string, number> | null
}

const { $api } = useNuxtApp()
const toast = useToast()

const plans = ref<AdminPlan[]>([])
const loading = ref(false)

const columns: TableColumn<AdminPlan>[] = [
  { accessorKey: 'slug', header: 'Slug' },
  { accessorKey: 'name', header: 'Nome' },
  { accessorKey: 'limits', header: 'Limites (usuários / clientes / monitoramentos)' },
  { id: 'actions' }
]

function limitLabel(plan: AdminPlan, key: string) {
  const value = plan.limits?.[key]
  return value === undefined || value === null ? '∞' : String(value)
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
  <UDashboardPanel id="admin-planos">
    <template #header>
      <UDashboardNavbar title="Planos">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UTable
        :data="plans"
        :columns="columns"
        :loading="loading"
        class="shrink-0"
      >
        <template #limits-cell="{ row }">
          <div class="flex gap-1.5">
            <UBadge color="neutral" variant="subtle">
              usuários: {{ limitLabel(row.original, 'users') }}
            </UBadge>
            <UBadge color="neutral" variant="subtle">
              clientes: {{ limitLabel(row.original, 'clients') }}
            </UBadge>
            <UBadge color="neutral" variant="subtle">
              monitoramentos: {{ limitLabel(row.original, 'monitorings') }}
            </UBadge>
          </div>
        </template>

        <template #actions-cell="{ row }">
          <div class="text-right">
            <UButton
              label="Editar limites"
              icon="i-lucide-pencil"
              color="neutral"
              variant="outline"
              size="sm"
              @click="openEdit(row.original)"
            />
          </div>
        </template>
      </UTable>

      <p class="text-sm text-muted">
        Limite vazio significa ilimitado. Novos limites valem para as próximas criações.
      </p>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="editOpen" title="Editar plano" :description="editTarget?.slug">
    <template #body>
      <UForm
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
