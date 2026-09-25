<script setup lang="ts">
import type { WorkTask } from '~/types/work'
import {
  MAX_VISIBLE_CHIPS,
  buildMonthGrid,
  statusPresentation,
  type MonthCell
} from '~/utils/workCalendar'
import { accessibleDateLabel } from '~/utils/calendarUi'

const props = defineProps<{
  anchorDate: string
  tasksByDay: Map<string, WorkTask[]>
  loading?: boolean
  canDrag?: boolean
  busyId?: number | null
}>()

const emit = defineEmits<{
  'select': [task: WorkTask, event: MouseEvent]
  'select-date': [dateKey: string]
  'drop': [taskId: number, targetDay: string]
}>()

const cells = computed(() => buildMonthGrid(props.anchorDate))
const weeks = computed(() => Array.from({ length: 6 }, (_, index) => cells.value.slice(index * 7, index * 7 + 7)))
const weekdayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

function tasksFor(cell: MonthCell) {
  return props.tasksByDay.get(cell.key) ?? []
}

function visible(cell: MonthCell) {
  return tasksFor(cell).slice(0, MAX_VISIBLE_CHIPS)
}

function overflow(cell: MonthCell) {
  const all = tasksFor(cell)
  return all.length > MAX_VISIBLE_CHIPS ? all.slice(MAX_VISIBLE_CHIPS) : []
}

function onDragStart(event: DragEvent, task: WorkTask) {
  if (!props.canDrag || task.status === 'dismissed' || props.busyId === task.id) {
    event.preventDefault()
    return
  }
  event.dataTransfer?.setData('text/task-id', String(task.id))
  event.dataTransfer!.effectAllowed = 'move'
}

function onDrop(event: DragEvent, dayKey: string) {
  event.preventDefault()
  const raw = event.dataTransfer?.getData('text/task-id')
  const id = raw ? Number(raw) : NaN
  if (!Number.isFinite(id)) return
  emit('drop', id, dayKey)
}

function allowDrop(event: DragEvent) {
  if (!props.canDrag) return
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
}
</script>

<template>
  <div
    class="min-h-0 flex-1 overflow-auto"
    role="grid"
    aria-label="Calendário mensal"
  >
    <div class="flex min-h-full min-w-[48rem] flex-col">
      <div class="grid grid-cols-7 border-b border-default text-left text-xs font-medium text-muted" role="row">
        <div
          v-for="label in weekdayLabels"
          :key="label"
          class="px-2 py-2"
          role="columnheader"
        >
          {{ label }}
        </div>
      </div>

      <div v-if="loading" class="grid min-h-0 flex-1 grid-rows-6">
        <div
          v-for="weekIndex in 6"
          :key="weekIndex"
          class="grid min-h-0 grid-cols-7"
          role="row"
        >
          <div
            v-for="dayIndex in 7"
            :key="`${weekIndex}-${dayIndex}`"
            class="min-h-0 overflow-hidden border-b border-e border-default p-2"
            role="gridcell"
          >
            <USkeleton class="mb-3 h-5 w-6" />
            <USkeleton class="mb-1 h-5 w-full" />
            <USkeleton class="h-5 w-3/4" />
          </div>
        </div>
      </div>

      <div v-else class="grid min-h-0 flex-1 grid-rows-6">
        <div
          v-for="week in weeks"
          :key="week[0]?.key"
          class="grid min-h-0 grid-cols-7"
          role="row"
        >
          <div
            v-for="cell in week"
            :key="cell.key"
            class="relative flex min-h-0 flex-col gap-1 overflow-hidden border-b border-e border-default p-2"
            :class="[
              cell.inMonth ? 'bg-default' : 'bg-elevated/20',
              cell.isToday ? 'bg-primary/5' : ''
            ]"
            role="gridcell"
            :aria-label="accessibleDateLabel(cell.key)"
            :aria-current="cell.isToday ? 'date' : undefined"
            @dragover="allowDrop"
            @drop="onDrop($event, cell.key)"
          >
            <button
              type="button"
              class="mb-0.5 inline-flex size-6 shrink-0 items-center justify-center self-start rounded-full text-xs transition-colors hover:bg-elevated focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
              :class="cell.isToday ? 'bg-primary font-semibold text-inverted hover:bg-primary' : cell.inMonth ? 'text-highlighted' : 'text-muted'"
              :aria-label="`Ir para ${accessibleDateLabel(cell.key)}`"
              @click="emit('select-date', cell.key)"
            >
              {{ cell.day }}
            </button>

            <button
              v-for="task in visible(cell)"
              :key="task.id"
              type="button"
              class="flex w-full min-w-0 items-center gap-1 rounded-md px-1 py-0.5 text-left text-xs transition-colors hover:bg-elevated focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
              :aria-label="`${task.title}, ${statusPresentation(task.status).label}, ${accessibleDateLabel(cell.key)}`"
              :draggable="canDrag && task.status !== 'dismissed'"
              :disabled="busyId === task.id"
              @click="emit('select', task, $event)"
              @dragstart="onDragStart($event, task)"
            >
              <span
                class="size-1.5 shrink-0 rounded-full"
                :class="statusPresentation(task.status).dotClass"
              />
              <span class="truncate text-highlighted">{{ task.title }}</span>
            </button>

            <UPopover v-if="overflow(cell).length" :content="{ align: 'start' }">
              <button
                type="button"
                class="rounded-md px-1 py-0.5 text-left text-xs font-medium text-muted hover:bg-elevated hover:text-highlighted focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
                :aria-label="`Mostrar mais ${overflow(cell).length} tarefas em ${accessibleDateLabel(cell.key)}`"
              >
                +{{ overflow(cell).length }} mais
              </button>
              <template #content>
                <div class="flex max-h-64 w-56 flex-col gap-0.5 overflow-y-auto p-2">
                  <button
                    v-for="task in overflow(cell)"
                    :key="task.id"
                    type="button"
                    class="flex w-full min-w-0 items-center gap-1 rounded-md px-1.5 py-1 text-left text-xs hover:bg-elevated focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
                    :aria-label="`${task.title}, ${statusPresentation(task.status).label}, ${accessibleDateLabel(cell.key)}`"
                    @click="emit('select', task, $event)"
                  >
                    <span
                      class="size-1.5 shrink-0 rounded-full"
                      :class="statusPresentation(task.status).dotClass"
                    />
                    <span class="truncate">{{ task.title }}</span>
                  </button>
                </div>
              </template>
            </UPopover>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
