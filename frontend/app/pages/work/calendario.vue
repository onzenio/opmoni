<script setup lang="ts">
import type { WorkTask, WorkTaskPriority, WorkTaskStatus } from '~/types/work'
import { useClients } from '~/composables/useClients'
import { useDepartments } from '~/composables/useDepartments'
import { useMembers } from '~/composables/useMembers'
import {
  type CalendarView,
  groupTasksByDay,
  monthLabel,
  parseCalendarQuery,
  periodRange,
  shiftPeriod,
  todayKey,
  weekKeys
} from '~/utils/workCalendar'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { calendar, updateTask, listProcesses } = useWork()
const { list: listClients } = useClients()
const { list: listDepartments } = useDepartments()
const { listDirectory } = useMembers()
const { canManageWork } = useAuth()

const initial = parseCalendarQuery(route.query.view, route.query.date)
const view = ref<CalendarView>(initial.view)
const focusDate = ref(initial.date)

const statusVisible = ref<Record<WorkTaskStatus, boolean>>({
  todo: true,
  doing: true,
  done: true,
  dismissed: true
})

const processId = ref<number | null>(null)
const clientId = ref<number | null>(null)
const assigneeId = ref<number | null>(null)
const department = ref('')
const priority = ref<WorkTaskPriority | ''>('')

const range = computed(() => periodRange(view.value, focusDate.value))
const rangeKey = computed(() => JSON.stringify([
  range.value.from,
  range.value.to,
  processId.value,
  clientId.value,
  assigneeId.value,
  department.value,
  priority.value
]))

const { data, status, error, refresh } = await useAsyncData(
  'work-calendar',
  () => calendar(range.value.from, range.value.to, {
    process_id: processId.value ?? undefined,
    client_id: clientId.value ?? undefined,
    assignee_member_id: assigneeId.value ?? undefined,
    department: department.value || undefined,
    priority: priority.value || undefined
  }),
  { watch: [rangeKey] }
)

const tasks = computed<WorkTask[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

const filteredTasks = computed(() => tasks.value.filter(task => statusVisible.value[task.status]))
const tasksByDay = computed(() => groupTasksByDay(filteredTasks.value))

const title = computed(() => {
  if (view.value === 'month') return monthLabel(focusDate.value)
  if (view.value === 'week') {
    const keys = weekKeys(focusDate.value)
    return `Semana · ${keys[0]} → ${keys[6]}`
  }
  return new Date(`${focusDate.value}T00:00:00`).toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

const dayColumnKeys = computed(() => {
  if (view.value === 'day') return [focusDate.value]
  if (view.value === 'week') return weekKeys(focusDate.value)
  return []
})

const viewItems = [
  { label: 'Dia', value: 'day' as const },
  { label: 'Semana', value: 'week' as const },
  { label: 'Mês', value: 'month' as const }
]

function syncQuery() {
  router.replace({
    query: {
      ...route.query,
      view: view.value,
      date: focusDate.value
    }
  })
}

watch([view, focusDate], syncQuery, { immediate: true })

watch(
  () => [route.query.view, route.query.date] as const,
  ([qView, qDate]) => {
    const parsed = parseCalendarQuery(qView, qDate)
    if (parsed.view !== view.value) view.value = parsed.view
    if (parsed.date !== focusDate.value) focusDate.value = parsed.date
  }
)

function goToday() {
  focusDate.value = todayKey()
}

function goPrev() {
  focusDate.value = shiftPeriod(view.value, focusDate.value, -1)
}

function goNext() {
  focusDate.value = shiftPeriod(view.value, focusDate.value, 1)
}

function onMiniDate(date: string) {
  focusDate.value = date
}

function clearFilters() {
  processId.value = null
  clientId.value = null
  assigneeId.value = null
  department.value = ''
  priority.value = ''
  statusVisible.value = { todo: true, doing: true, done: true, dismissed: true }
}

const { data: filterSources } = await useAsyncData('work-calendar-filters', async () => {
  const month = focusDate.value.slice(0, 7)
  const [processes, clientsRes, members, departments] = await Promise.all([
    listProcesses({ reference_month: month }).catch(() => []),
    listClients({ per_page: 100, sort: 'name', direction: 'asc' }).catch(() => ({ data: [] as { id: number, name: string }[] })),
    listDirectory().catch(() => []),
    listDepartments().catch(() => [])
  ])
  return {
    processes,
    clients: clientsRes.data ?? [],
    members,
    departments
  }
}, { watch: [() => focusDate.value.slice(0, 7)] })

const processOptions = computed(() => (filterSources.value?.processes ?? []).map(p => ({
  label: p.name,
  value: p.id
})))

const clientOptions = computed(() => (filterSources.value?.clients ?? []).map(c => ({
  label: c.name,
  value: c.id
})))

const assigneeOptions = computed(() => (filterSources.value?.members ?? []).map(m => ({
  label: m.name,
  value: m.id
})))

const departmentOptions = computed(() => (filterSources.value?.departments ?? []).map(d => ({
  label: d.name,
  value: d.name
})))

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar o calendário', color: 'error' })
})

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar o calendário', color: 'error' })
  }
}

function apiMessage(error: unknown): string | undefined {
  if (!error || typeof error !== 'object') return undefined
  const data = (error as { data?: { message?: string }, response?: { _data?: { message?: string } } }).data
  const nested = (error as { response?: { _data?: { message?: string } } }).response?._data
  const message = data?.message ?? nested?.message
  return typeof message === 'string' && message.length > 0 ? message : undefined
}

function apiStatus(error: unknown): number | undefined {
  if (!error || typeof error !== 'object') return undefined
  const record = error as {
    status?: number
    statusCode?: number
    response?: { status?: number }
    data?: { status?: number }
  }
  const code = record.status ?? record.statusCode ?? record.response?.status ?? record.data?.status
  return typeof code === 'number' ? code : undefined
}

const selectedTask = ref<WorkTask | null>(null)
const popoverOpen = ref(false)
const busyId = ref<number | null>(null)
const optimistic = ref(new Map<number, string>())

const displayByDay = computed(() => {
  const base = new Map(tasksByDay.value)
  for (const [id, day] of optimistic.value) {
    for (const [key, list] of base) {
      const next = list.filter(t => t.id !== id)
      if (next.length) base.set(key, next)
      else base.delete(key)
    }
    const task = tasks.value.find(t => t.id === id)
    if (!task) continue
    const list = base.get(day) ?? []
    base.set(day, [...list, { ...task, due_on: day }].sort((a, b) => a.title.localeCompare(b.title, 'pt-BR')))
  }
  return base
})

function openTask(task: WorkTask) {
  selectedTask.value = task
  popoverOpen.value = true
}

async function advance(task: WorkTask) {
  const next = task.status === 'todo' ? 'doing' : task.status === 'doing' ? 'done' : null
  if (!next) return
  busyId.value = task.id
  try {
    await updateTask(task.id, { status: next })
    await refresh()
    toast.add({ title: next === 'done' ? 'Tarefa concluída' : 'Tarefa em progresso', color: 'success' })
  } catch (error: unknown) {
    if (apiStatus(error) === 422) {
      toast.add({ title: 'Avanço bloqueado', description: apiMessage(error) ?? 'Aguardando etapas anteriores.', color: 'warning' })
    } else {
      toast.add({ title: 'Não foi possível avançar a tarefa', description: apiMessage(error), color: 'error' })
    }
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
    await refresh()
    toast.add({ title: 'Tarefa retornada', color: 'success' })
  } catch (error: unknown) {
    toast.add({ title: 'Não foi possível retornar a tarefa', description: apiMessage(error), color: 'error' })
  } finally {
    busyId.value = null
  }
}

async function assign(task: WorkTask, memberId: number | null) {
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

async function dismiss(task: WorkTask, reason: string) {
  busyId.value = task.id
  try {
    await updateTask(task.id, { status: 'dismissed', dismissal_reason: reason })
    popoverOpen.value = false
    await refresh()
    toast.add({ title: 'Tarefa dispensada', color: 'success' })
  } catch (error: unknown) {
    toast.add({ title: 'Não foi possível dispensar a tarefa', description: apiMessage(error), color: 'error' })
  } finally {
    busyId.value = null
  }
}

async function onDrop(taskId: number, targetDay: string) {
  if (!canManageWork.value) return
  const task = tasks.value.find(t => t.id === taskId)
  if (!task || task.status === 'dismissed') return
  if (task.due_on === targetDay) return
  if (busyId.value === taskId) return

  const nextMap = new Map(optimistic.value)
  nextMap.set(taskId, targetDay)
  optimistic.value = nextMap
  busyId.value = taskId

  try {
    await updateTask(taskId, { due_on: targetDay })
    await refresh()
  } catch (error: unknown) {
    toast.add({
      title: 'Não foi possível reagendar',
      description: apiMessage(error),
      color: 'error'
    })
  } finally {
    const cleared = new Map(optimistic.value)
    cleared.delete(taskId)
    optimistic.value = cleared
    busyId.value = null
  }
}

function isEditableTarget(target: EventTarget | null) {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

function onKeydown(event: KeyboardEvent) {
  if (isEditableTarget(event.target)) return
  if (event.key === 't' || event.key === 'T') {
    event.preventDefault()
    goToday()
    return
  }
  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    goPrev()
  }
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    goNext()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-hidden p-3 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-calendar-days" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted capitalize sm:text-lg">
          {{ title }}
        </h2>
      </div>

      <UTabs
        :model-value="view"
        :items="viewItems"
        :content="false"
        size="sm"
        class="w-auto"
        @update:model-value="(value) => { view = value as CalendarView }"
      />

      <UButton
        icon="i-lucide-chevron-left"
        color="neutral"
        variant="outline"
        aria-label="Período anterior"
        :disabled="isLoading"
        @click="goPrev"
      />
      <UButton
        label="Hoje"
        color="neutral"
        variant="outline"
        :disabled="isLoading"
        @click="goToday"
      />
      <UButton
        icon="i-lucide-chevron-right"
        color="neutral"
        variant="outline"
        aria-label="Próximo período"
        :disabled="isLoading"
        @click="goNext"
      />
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:flex-row">
      <WorkCalendarSidebar
        :model-date="focusDate"
        :status-visible="statusVisible"
        :process-id="processId"
        :client-id="clientId"
        :assignee-id="assigneeId"
        :department="department"
        :priority="priority"
        :process-options="processOptions"
        :client-options="clientOptions"
        :assignee-options="assigneeOptions"
        :department-options="departmentOptions"
        @update:model-date="onMiniDate"
        @update:status-visible="statusVisible = $event"
        @update:process-id="processId = $event"
        @update:client-id="clientId = $event"
        @update:assignee-id="assigneeId = $event"
        @update:department="department = $event"
        @update:priority="priority = $event"
        @clear="clearFilters"
      />

      <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg ring ring-default">
        <UAlert
          v-if="error"
          class="m-3"
          color="error"
          variant="subtle"
          title="Não foi possível carregar o calendário"
          description="Verifique a conexão e tente novamente."
          :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
        />

        <UEmpty
          v-else-if="!isLoading && !filteredTasks.length"
          class="my-auto py-16"
          icon="i-lucide-calendar-days"
          title="Nenhuma tarefa com prazo"
          description="Rotinas geradas com vencimento aparecem aqui."
          variant="naked"
        />

        <WorkMonthGrid
          v-else-if="view === 'month'"
          :anchor-date="focusDate"
          :tasks-by-day="displayByDay"
          :loading="isLoading"
          :can-drag="canManageWork"
          :busy-id="busyId"
          @select="openTask"
          @drop="onDrop"
        />

        <WorkDayColumns
          v-else
          :day-keys="dayColumnKeys"
          :tasks-by-day="displayByDay"
          :loading="isLoading"
          :single="view === 'day'"
          @select="openTask"
        />
      </div>
    </div>

    <WorkCalendarTaskPopover
      v-model:open="popoverOpen"
      :task="selectedTask"
      :can-manage="canManageWork"
      :busy="busyId === selectedTask?.id"
      :member-options="assigneeOptions"
      @advance="advance"
      @back="moveBack"
      @assign="assign"
      @dismiss="dismiss"
    />
  </div>
</template>
