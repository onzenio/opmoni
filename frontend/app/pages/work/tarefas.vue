<script setup lang="ts">
import type { WorkTask } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const toast = useToast()
const { $api } = useNuxtApp()
const { listTasks, updateTask } = useWork()
const { canManageClients } = useAuth()

type ColumnKey = WorkTask['status']

interface MemberOption {
  label: string
  value: number
}

interface TaskFilters {
  processId: string
  clientId: string
  assigneeId: string
  department: string
  priority: string
  dueFrom: string
  dueTo: string
}

const filters = reactive<TaskFilters>({
  processId: '',
  clientId: '',
  assigneeId: '',
  department: '',
  priority: '',
  dueFrom: '',
  dueTo: ''
})

const viewMode = ref<'board' | 'table'>('board')

const filterQuery = computed(() => ({
  process_id: filters.processId ? Number(filters.processId) : undefined,
  client_id: filters.clientId ? Number(filters.clientId) : undefined,
  assignee_member_id: filters.assigneeId ? Number(filters.assigneeId) : undefined,
  department: filters.department || undefined,
  priority: filters.priority || undefined,
  due_from: filters.dueFrom || undefined,
  due_to: filters.dueTo || undefined
}))

const filterKey = computed(() => JSON.stringify(filterQuery.value))

const { data, status, error, refresh } = await useAsyncData<WorkTask[]>(
  'work-tarefas',
  () => listTasks(filterQuery.value),
  { watch: [filterKey] }
)

const tasks = computed<WorkTask[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

const { data: members } = await useAsyncData(
  'work-members',
  async () => {
    if (!canManageClients.value) return [] as { id: number, name: string }[]
    const res = await $api<{ data?: { id: number, name: string }[] } | { id: number, name: string }[]>('/account/members')
    return Array.isArray(res) ? res : (res.data ?? [])
  },
  { default: () => [] as { id: number, name: string }[] }
)

const memberOptions = computed<MemberOption[]>(() =>
  (members.value ?? []).map(member => ({ label: member.name, value: member.id }))
)

function memberName(memberId: number | null): string {
  if (memberId === null) return 'Sem responsável'
  return memberOptions.value.find(option => option.value === memberId)?.label ?? `Membro ${String(memberId)}`
}

const columns = [
  { key: 'todo' as ColumnKey, title: 'A fazer', icon: 'i-lucide-circle' },
  { key: 'doing' as ColumnKey, title: 'Em progresso', icon: 'i-lucide-loader' },
  { key: 'done' as ColumnKey, title: 'Concluída', icon: 'i-lucide-circle-check' },
  { key: 'dismissed' as ColumnKey, title: 'Dispensada', icon: 'i-lucide-circle-minus' }
]

const tasksByColumn = computed<Record<ColumnKey, WorkTask[]>>(() => {
  const board: Record<ColumnKey, WorkTask[]> = { todo: [], doing: [], done: [], dismissed: [] }
  for (const task of tasks.value) board[task.status].push(task)
  return board
})

function statusPresentation(taskStatus: WorkTask['status']): { label: string, color: 'info' | 'warning' | 'success' | 'neutral' } {
  switch (taskStatus) {
    case 'todo': return { label: 'A fazer', color: 'info' }
    case 'doing': return { label: 'Em progresso', color: 'warning' }
    case 'done': return { label: 'Concluída', color: 'success' }
    case 'dismissed': return { label: 'Dispensada', color: 'neutral' }
  }
}

function priorityPresentation(priority: WorkTask['priority']): { label: string, color: 'neutral' | 'info' | 'warning' | 'error' } {
  switch (priority) {
    case 'low': return { label: 'Baixa', color: 'neutral' }
    case 'medium': return { label: 'Média', color: 'info' }
    case 'high': return { label: 'Alta', color: 'warning' }
    case 'urgent': return { label: 'Urgente', color: 'error' }
  }
}

function formatDueOn(value: string | null): string {
  if (!value) return 'Sem vencimento'
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

function processLabel(task: WorkTask): string {
  return task.process?.name ?? 'Processo avulso'
}

const lockedIds = ref<Set<number>>(new Set())

function markLocked(taskId: number) {
  lockedIds.value = new Set(lockedIds.value).add(taskId)
}

function clearLocked(taskId: number) {
  const next = new Set(lockedIds.value)
  next.delete(taskId)
  lockedIds.value = next
}

function apiMessage(error: unknown): string | undefined {
  if (!error || typeof error !== 'object') return undefined
  const data = (error as { data?: { message?: string }, response?: { _data?: { message?: string } } }).data
  const nested = (error as { response?: { _data?: { message?: string } } }).response?._data
  const message = data?.message ?? nested?.message
  return typeof message === 'string' && message.length > 0 ? message : undefined
}

const busyId = ref<number | null>(null)

async function advance(task: WorkTask) {
  const next = task.status === 'todo' ? 'doing' : task.status === 'doing' ? 'done' : null
  if (!next) return
  busyId.value = task.id
  try {
    await updateTask(task.id, { status: next })
    clearLocked(task.id)
    await refresh()
    toast.add({ title: next === 'done' ? 'Tarefa concluída' : 'Tarefa em progresso', color: 'success' })
  } catch (error: unknown) {
    markLocked(task.id)
    toast.add({ title: 'Avanço bloqueado pela cascata', description: apiMessage(error) ?? 'Aguardando etapas anteriores.', color: 'warning' })
  } finally {
    busyId.value = null
  }
}

async function moveBack(task: WorkTask) {
  const previous = task.status === 'doing' ? 'todo' : task.status === 'done' ? 'doing' : null
  if (!previous) return
  busyId.value = task.id
  try {
    await updateTask(task.id, { status: previous })
    clearLocked(task.id)
    await refresh()
    toast.add({ title: 'Tarefa retornada', color: 'success' })
  } catch (error: unknown) {
    toast.add({ title: 'Não foi possível retornar a tarefa', description: apiMessage(error), color: 'error' })
  } finally {
    busyId.value = null
  }
}

const dismissOpen = ref(false)
const dismissTarget = ref<WorkTask | null>(null)
const dismissReason = ref('')
const dismissing = ref(false)

function openDismiss(task: WorkTask) {
  dismissTarget.value = task
  dismissReason.value = ''
  dismissOpen.value = true
}

async function dismissWithReason(task: WorkTask, reason: string) {
  if (!reason.trim()) {
    toast.add({ title: 'Informe o motivo da dispensa', color: 'error' })
    return
  }
  dismissing.value = true
  try {
    await updateTask(task.id, { status: 'dismissed', dismissal_reason: reason.trim() })
    clearLocked(task.id)
    dismissOpen.value = false
    dismissTarget.value = null
    await refresh()
    toast.add({ title: 'Tarefa dispensada', color: 'success' })
  } catch (error: unknown) {
    toast.add({ title: 'Não foi possível dispensar a tarefa', description: apiMessage(error), color: 'error' })
  } finally {
    dismissing.value = false
  }
}

async function onConfirmDismiss() {
  if (!dismissTarget.value) return
  await dismissWithReason(dismissTarget.value, dismissReason.value)
}

async function assign(task: WorkTask, memberId: number | null) {
  if (task.assignee_member_id === memberId) return
  busyId.value = task.id
  try {
    await updateTask(task.id, { assignee_member_id: memberId })
    await refresh()
    toast.add({ title: memberId === null ? 'Responsável removido' : 'Responsável atualizado', color: 'success' })
  } catch (error: unknown) {
    toast.add({ title: 'Não foi possível atribuir o responsável', description: apiMessage(error), color: 'error' })
  } finally {
    busyId.value = null
  }
}

function assigneeModel(task: WorkTask): number | null {
  return task.assignee_member_id
}

function filteredBy(status: ColumnKey): WorkTask[] {
  return tasksByColumn.value[status]
}

function clearFilters() {
  filters.processId = ''
  filters.clientId = ''
  filters.assigneeId = ''
  filters.department = ''
  filters.priority = ''
  filters.dueFrom = ''
  filters.dueTo = ''
}

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar as tarefas', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar as tarefas', color: 'error' })
})

watch(dismissOpen, (open) => {
  if (!open) {
    dismissTarget.value = null
    dismissReason.value = ''
  }
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-kanban-square" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          Tarefas
        </h2>
      </div>
      <div class="flex shrink-0 items-center gap-1 rounded-lg bg-elevated p-1" role="group" aria-label="Alternar visualização">
        <UButton
          icon="i-lucide-kanban-square"
          size="xs"
          :color="viewMode === 'board' ? 'primary' : 'neutral'"
          :variant="viewMode === 'board' ? 'solid' : 'ghost'"
          aria-label="Ver como quadro"
          @click="viewMode = 'board'"
        />
        <UButton
          icon="i-lucide-table"
          size="xs"
          :color="viewMode === 'table' ? 'primary' : 'neutral'"
          :variant="viewMode === 'table' ? 'solid' : 'ghost'"
          aria-label="Ver como tabela"
          @click="viewMode = 'table'"
        />
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar tarefas"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UCard variant="subtle" :ui="{ body: 'p-3 sm:p-4' }">
      <div class="grid min-w-0 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <UFormField label="Processo (ID)" name="process_id">
          <UInput
            v-model="filters.processId"
            type="number"
            min="1"
            placeholder="Todos"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Cliente (ID)" name="client_id">
          <UInput
            v-model="filters.clientId"
            type="number"
            min="1"
            placeholder="Todos"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Departamento" name="department">
          <UInput v-model="filters.department" placeholder="Ex.: Fiscal" class="w-full" />
        </UFormField>
        <UFormField label="Prioridade" name="priority">
          <USelect
            v-model="filters.priority"
            :items="[
              { label: 'Todas', value: '' },
              { label: 'Baixa', value: 'low' },
              { label: 'Média', value: 'medium' },
              { label: 'Alta', value: 'high' },
              { label: 'Urgente', value: 'urgent' }
            ]"
            placeholder="Todas"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Vencimento de" name="due_from">
          <UInput v-model="filters.dueFrom" type="date" class="w-full" />
        </UFormField>
        <UFormField label="Vencimento até" name="due_to">
          <UInput v-model="filters.dueTo" type="date" class="w-full" />
        </UFormField>
        <UFormField v-if="canManageClients" label="Responsável" name="assignee">
          <USelect
            v-model="filters.assigneeId"
            :items="[{ label: 'Todos', value: '' }, ...memberOptions.map(option => ({ label: option.label, value: String(option.value) }))]"
            placeholder="Todos"
            class="w-full"
          />
        </UFormField>
        <div class="flex items-end">
          <UButton
            label="Limpar filtros"
            color="neutral"
            variant="subtle"
            icon="i-lucide-x"
            @click="clearFilters"
          />
        </div>
      </div>
    </UCard>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar as tarefas"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && tasks.length === 0" class="flex flex-col gap-3">
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="tasks.length === 0"
      icon="i-lucide-kanban-square"
      title="Nenhuma tarefa por aqui"
      description="As tarefas das rotinas aparecem aqui organizadas em A fazer, Em progresso, Concluída e Dispensada."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <template v-else-if="viewMode === 'table'">
      <UCard variant="subtle" :ui="{ body: 'p-0 sm:p-0' }">
        <UTable
          :data="tasks"
          :columns="[
            { accessorKey: 'title', header: 'Tarefa' },
            { accessorKey: 'status', header: 'Status' },
            { accessorKey: 'due_on', header: 'Vencimento' },
            { accessorKey: 'department', header: 'Depto.' }
          ]"
        >
          <template #title-cell="{ row }">
            <div class="flex min-w-0 flex-col">
              <span class="truncate text-sm font-medium text-highlighted" :title="row.original.title">
                {{ row.original.title }}
              </span>
              <span v-if="row.original.process?.client?.name || row.original.process?.name" class="truncate text-xs text-muted">
                {{ row.original.process?.client?.name ?? '' }}{{ row.original.process?.client?.name && row.original.process?.name ? ' · ' : '' }}{{ row.original.process?.name ?? '' }}
              </span>
            </div>
          </template>
          <template #status-cell="{ row }">
            <UBadge
              :color="statusPresentation(row.original.status).color"
              variant="subtle"
              :label="statusPresentation(row.original.status).label"
            />
          </template>
          <template #due_on-cell="{ row }">
            <span class="text-sm text-muted">{{ formatDueOn(row.original.due_on) }}</span>
          </template>
          <template #department-cell="{ row }">
            <span class="text-sm text-muted">{{ row.original.department }}</span>
          </template>
        </UTable>
      </UCard>
    </template>

    <div v-else class="grid min-w-0 items-start gap-3 xl:grid-cols-4 lg:grid-cols-2">
      <section
        v-for="column in columns"
        :key="column.key"
        :aria-label="column.title"
        class="flex min-w-0 flex-col gap-2 rounded-xl bg-elevated/60 p-2 ring-1 ring-default"
      >
        <header class="flex items-center gap-2 px-1 pt-1">
          <UIcon :name="column.icon" class="size-4 shrink-0 text-muted" />
          <h3 class="min-w-0 flex-1 truncate text-sm font-semibold text-highlighted">
            {{ column.title }}
          </h3>
          <UBadge color="neutral" variant="subtle" :label="String(filteredBy(column.key).length)" />
        </header>

        <p v-if="filteredBy(column.key).length === 0" class="rounded-lg bg-default px-3 py-4 text-center text-xs text-muted ring ring-default">
          Nenhuma tarefa
        </p>

        <article
          v-for="task in filteredBy(column.key)"
          :key="task.id"
          class="flex min-w-0 flex-col gap-2 rounded-xl bg-default p-3 ring ring-default"
        >
          <div class="flex min-w-0 items-start gap-2">
            <p class="min-w-0 flex-1 truncate text-sm font-medium text-highlighted" :title="task.title">
              {{ task.title }}
            </p>
            <UBadge
              :color="priorityPresentation(task.priority).color"
              variant="subtle"
              :label="priorityPresentation(task.priority).label"
            />
          </div>

          <p class="truncate text-xs text-muted" :title="processLabel(task)">
            {{ processLabel(task) }}
          </p>
          <p v-if="task.process?.client?.name" class="truncate text-xs text-muted">
            {{ task.process.client.name }}
          </p>

          <div class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <span class="inline-flex items-center gap-1">
              <UIcon name="i-lucide-calendar" class="size-3.5 shrink-0" />
              {{ formatDueOn(task.due_on) }}
            </span>
            <UBadge color="neutral" variant="outline" :label="task.department" />
          </div>
          <p class="text-xs text-muted">
            Responsável: {{ memberName(task.assignee_member_id) }}
          </p>

          <p v-if="lockedIds.has(task.id)" class="flex items-center gap-1.5 text-xs text-warning">
            <UIcon name="i-lucide-lock" class="size-3.5 shrink-0" />
            Aguardando etapas anteriores — pode ser bloqueado se a cascata estiver ativa.
          </p>

          <USelectMenu
            v-if="canManageClients"
            :model-value="assigneeModel(task)"
            :items="[{ label: 'Sem responsável', value: null }, ...memberOptions]"
            value-key="value"
            label-key="label"
            placeholder="Atribuir responsável"
            :search-input="{ placeholder: 'Buscar membro...' }"
            :loading="busyId === task.id"
            :disabled="busyId === task.id"
            class="w-full"
            @update:model-value="(value: number | null) => assign(task, value)"
          />

          <div v-if="canManageClients" class="flex flex-wrap gap-1.5">
            <UButton
              v-if="task.status === 'todo' || task.status === 'doing'"
              :label="task.status === 'todo' ? 'Avançar' : 'Concluir'"
              icon="i-lucide-arrow-right"
              size="xs"
              color="primary"
              :loading="busyId === task.id"
              :disabled="busyId === task.id"
              @click="advance(task)"
            />
            <UButton
              v-if="task.status === 'doing' || task.status === 'done'"
              label="Retornar"
              icon="i-lucide-arrow-left"
              size="xs"
              color="neutral"
              variant="outline"
              :loading="busyId === task.id"
              :disabled="busyId === task.id"
              @click="moveBack(task)"
            />
            <UButton
              v-if="task.status !== 'dismissed'"
              label="Dispensar"
              icon="i-lucide-circle-minus"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="busyId === task.id"
              @click="openDismiss(task)"
            />
          </div>
        </article>
      </section>
    </div>

    <UModal
      v-model:open="dismissOpen"
      title="Dispensar tarefa"
      :description="dismissTarget ? `Informar o motivo da dispensa de ${dismissTarget.title}` : 'Informar o motivo da dispensa'"
    >
      <template #body>
        <UFormField
          label="Motivo da dispensa"
          name="dismissal_reason"
          required
          help="O motivo é obrigatório e fica registrado na tarefa."
        >
          <UTextarea
            v-model="dismissReason"
            :rows="4"
            placeholder="Ex.: sem movimento no mês"
            class="w-full"
          />
        </UFormField>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            @click="dismissOpen = false"
          />
          <UButton
            label="Dispensar"
            color="warning"
            variant="solid"
            icon="i-lucide-circle-minus"
            :loading="dismissing"
            :disabled="dismissing || !dismissReason.trim()"
            @click="onConfirmDismiss"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
