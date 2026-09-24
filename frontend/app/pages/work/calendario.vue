<script setup lang="ts">
import type { DateValue } from '@internationalized/date'
import { CalendarDate, getLocalTimeZone, today } from '@internationalized/date'
import type { WorkTask } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const { calendar } = useWork()
const toast = useToast()

function firstDayOfMonth(year: number, month: number): string {
  return `${year}-${String(month).padStart(2, '0')}-01`
}

function lastDayOfMonth(year: number, month: number): string {
  const last = new Date(year, month, 0).getDate()
  return `${year}-${String(month).padStart(2, '0')}-${String(last).padStart(2, '0')}`
}

const now = new Date()
const currentYear = now.getFullYear()
const currentMonth = now.getMonth() + 1

const from = ref(firstDayOfMonth(currentYear, currentMonth))
const to = ref(lastDayOfMonth(currentYear, currentMonth))

const rangeKey = computed(() => JSON.stringify([from.value, to.value]))

const { data, status, error, refresh } = await useAsyncData(
  'work-calendar',
  () => calendar(from.value, to.value),
  { watch: [rangeKey] }
)

const tasks = computed<WorkTask[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

const byDay = computed(() => {
  const map = new Map<string, WorkTask[]>()
  for (const task of tasks.value) {
    if (!task.due_on) continue
    const list = map.get(task.due_on) ?? []
    list.push(task)
    map.set(task.due_on, list)
  }
  return map
})

function statusPresentation(taskStatus: WorkTask['status']): { label: string, color: 'info' | 'warning' | 'success' | 'neutral' } {
  switch (taskStatus) {
    case 'todo': return { label: 'A fazer', color: 'info' }
    case 'doing': return { label: 'Em progresso', color: 'warning' }
    case 'done': return { label: 'Concluída', color: 'success' }
    case 'dismissed': return { label: 'Dispensada', color: 'neutral' }
  }
}

function toKey(date: { year: number, month: number, day: number }): string {
  return `${date.year}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`
}

function formatDayLabel(key: string): string {
  return new Date(`${key}T00:00:00`).toLocaleDateString('pt-BR')
}

const visibleMonth = shallowRef<DateValue>(today(getLocalTimeZone()))
const selectedValue = shallowRef<DateValue | undefined>(undefined)
const selectedKey = ref<string | null>(null)

watch(selectedValue, (value) => {
  selectedKey.value = value && 'day' in value ? toKey(value as { year: number, month: number, day: number }) : null
})

const selectedTasks = computed<WorkTask[]>(() => {
  if (!selectedKey.value) return []
  return byDay.value.get(selectedKey.value) ?? []
})

function applyMonth(year: number, month: number) {
  from.value = firstDayOfMonth(year, month)
  to.value = lastDayOfMonth(year, month)
  visibleMonth.value = new CalendarDate(year, month, 1)
  selectedValue.value = undefined
}

function shiftMonth(delta: -1 | 1) {
  const anchor = new Date(`${from.value}T00:00:00`)
  anchor.setDate(1)
  anchor.setMonth(anchor.getMonth() + delta)
  applyMonth(anchor.getFullYear(), anchor.getMonth() + 1)
}

function onPlaceholderUpdate(value: unknown) {
  if (!value || typeof value !== 'object') return
  const date = value as { year: number, month: number }
  if (typeof date.year !== 'number' || typeof date.month !== 'number') return
  if (firstDayOfMonth(date.year, date.month) === from.value) return
  applyMonth(date.year, date.month)
}

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar o calendário', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar o calendário', color: 'error' })
})

const monthLabel = computed(() => {
  const parts = from.value.split('-')
  const year = Number(parts[0] ?? NaN)
  const month = Number(parts[1] ?? NaN)
  if (!Number.isInteger(year) || !Number.isInteger(month)) return 'Calendário'
  return new Date(year, month - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-calendar-days" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted capitalize sm:text-lg">
          {{ monthLabel }}
        </h2>
      </div>
      <UButton
        icon="i-lucide-chevron-left"
        color="neutral"
        variant="outline"
        aria-label="Mês anterior"
        :disabled="isLoading"
        @click="shiftMonth(-1)"
      />
      <UButton
        icon="i-lucide-chevron-right"
        color="neutral"
        variant="outline"
        aria-label="Próximo mês"
        :disabled="isLoading"
        @click="shiftMonth(1)"
      />
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar calendário"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar o calendário"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && tasks.length === 0" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
      <USkeleton class="h-96 w-full rounded-xl" />
      <USkeleton class="h-96 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="!isLoading && tasks.length === 0"
      icon="i-lucide-calendar-x"
      title="Nenhuma tarefa com vencimento neste mês"
      description="Quando houver rotinas geradas com data de vencimento, elas aparecem aqui."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <div v-else class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
      <UCard variant="subtle" :ui="{ body: 'p-2 sm:p-3' }">
        <UCalendar
          v-model="selectedValue"
          :placeholder="visibleMonth"
          size="lg"
          @update:placeholder="onPlaceholderUpdate"
        >
          <template #day="{ day }">
            <div class="flex min-h-7 flex-col items-center gap-0.5 px-0.5 py-0.5">
              <span class="text-xs leading-none">{{ day.day }}</span>
              <div class="flex flex-wrap items-center justify-center gap-0.5">
                <span
                  v-for="task in (byDay.get(toKey(day)) ?? []).slice(0, 4)"
                  :key="task.id"
                  :title="task.title"
                  :class="[
                    'size-1.5 rounded-full',
                    task.status === 'todo' ? 'bg-info' : task.status === 'doing' ? 'bg-warning' : task.status === 'done' ? 'bg-success' : 'bg-muted'
                  ]"
                />
              </div>
            </div>
          </template>
        </UCalendar>
      </UCard>

      <UCard variant="subtle" :ui="{ body: 'p-4' }">
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-list-checks" class="size-4 shrink-0 text-muted" />
            <h3 class="text-sm font-semibold text-highlighted">
              {{ selectedKey ? `Tarefas de ${formatDayLabel(selectedKey)}` : 'Selecione um dia' }}
            </h3>
          </div>
          <p v-if="!selectedKey" class="text-sm text-muted">
            Toque em um dia do calendário para ver as tarefas com vencimento.
          </p>
          <UEmpty
            v-else-if="selectedTasks.length === 0"
            icon="i-lucide-calendar-check"
            title="Nenhuma tarefa neste dia"
            description="Não há vencimentos para a data selecionada."
            variant="naked"
          />
          <ul v-else class="flex flex-col gap-2">
            <li
              v-for="task in selectedTasks"
              :key="task.id"
              class="flex min-w-0 items-start gap-2 rounded-lg bg-default p-2.5 ring ring-default"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-highlighted" :title="task.title">
                  {{ task.title }}
                </p>
                <p v-if="task.process?.client?.name || task.process?.name" class="truncate text-xs text-muted">
                  {{ task.process?.client?.name ?? '' }}{{ task.process?.client?.name && task.process?.name ? ' · ' : '' }}{{ task.process?.name ?? '' }}
                </p>
              </div>
              <UBadge
                :color="statusPresentation(task.status).color"
                variant="subtle"
                :label="statusPresentation(task.status).label"
              />
            </li>
          </ul>
        </div>
      </UCard>
    </div>
  </div>
</template>
