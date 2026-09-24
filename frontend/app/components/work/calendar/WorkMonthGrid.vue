<script setup lang="ts">
import type { WorkTask } from '~/types/work'
import {
  MAX_VISIBLE_CHIPS,
  buildMonthGrid,
  statusPresentation,
  type MonthCell
} from '~/utils/workCalendar'

const props = defineProps<{
  anchorDate: string
  tasksByDay: Map<string, WorkTask[]>
  loading?: boolean
  canDrag?: boolean
  busyId?: number | null
}>()

const emit = defineEmits<{
  select: [task: WorkTask]
  drop: [taskId: number, targetDay: string]
}>()

const cells = computed(() => buildMonthGrid(props.anchorDate))
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
  <div class="flex min-h-0 flex-1 flex-col">
    <div class="grid grid-cols-7 border-b border-default text-center text-xs font-medium text-muted">
      <div v-for="label in weekdayLabels" :key="label" class="px-1 py-2">
        {{ label }}
      </div>
    </div>

    <div v-if="loading" class="grid min-h-0 flex-1 grid-cols-7 grid-rows-6">
      <div
        v-for="index in 42"
        :key="index"
        class="border-b border-e border-default p-1"
      >
        <USkeleton class="mb-2 h-4 w-6" />
        <USkeleton class="mb-1 h-5 w-full" />
        <USkeleton class="h-5 w-3/4" />
      </div>
    </div>

    <div
      v-else
      class="grid min-h-0 flex-1 grid-cols-7 grid-rows-6"
    >
      <div
        v-for="cell in cells"
        :key="cell.key"
        class="relative flex min-h-24 flex-col gap-0.5 border-b border-e border-default p-1"
        :class="[
          cell.inMonth ? 'bg-default' : 'bg-elevated/40',
          cell.isToday ? 'ring-1 ring-inset ring-primary' : ''
        ]"
        @dragover="allowDrop"
        @drop="onDrop($event, cell.key)"
      >
        <span
          class="mb-0.5 inline-flex size-6 items-center justify-center rounded-full text-xs"
          :class="cell.isToday ? 'bg-primary text-inverted font-semibold' : cell.inMonth ? 'text-highlighted' : 'text-muted'"
        >
          {{ cell.day }}
        </span>

        <button
          v-for="task in visible(cell)"
          :key="task.id"
          type="button"
          class="flex w-full min-w-0 items-center gap-1 rounded-md px-1 py-0.5 text-left text-xs hover:bg-elevated"
          :draggable="canDrag && task.status !== 'dismissed'"
          :disabled="busyId === task.id"
          @click="emit('select', task)"
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
            class="rounded-md px-1 py-0.5 text-left text-xs font-medium text-muted hover:bg-elevated hover:text-highlighted"
          >
            +{{ overflow(cell).length }} more
          </button>
          <template #content>
            <div class="flex max-h-64 w-56 flex-col gap-0.5 overflow-y-auto p-2">
              <button
                v-for="task in overflow(cell)"
                :key="task.id"
                type="button"
                class="flex w-full min-w-0 items-center gap-1 rounded-md px-1.5 py-1 text-left text-xs hover:bg-elevated"
                @click="emit('select', task)"
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
</template>
