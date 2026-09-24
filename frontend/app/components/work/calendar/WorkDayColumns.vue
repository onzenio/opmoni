<script setup lang="ts">
import type { WorkTask } from '~/types/work'
import { parseDateKey, statusPresentation } from '~/utils/workCalendar'

defineProps<{
  dayKeys: string[]
  tasksByDay: Map<string, WorkTask[]>
  loading?: boolean
  single?: boolean
}>()

const emit = defineEmits<{
  select: [task: WorkTask]
}>()

function dayLabel(key: string) {
  const parsed = parseDateKey(key)
  if (!parsed) return key
  return new Date(parsed.year, parsed.month - 1, parsed.day).toLocaleDateString('pt-BR', {
    weekday: 'short',
    day: '2-digit',
    month: 'short'
  })
}
</script>

<template>
  <div
    class="grid min-h-0 flex-1 gap-2 overflow-auto"
    :class="single ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-7'"
  >
    <div
      v-for="key in dayKeys"
      :key="key"
      class="flex min-h-40 flex-col rounded-lg ring ring-default"
    >
      <div class="border-b border-default px-3 py-2 text-sm font-medium capitalize text-highlighted">
        {{ dayLabel(key) }}
      </div>

      <div v-if="loading" class="space-y-2 p-3">
        <USkeleton class="h-8 w-full" />
        <USkeleton class="h-8 w-3/4" />
      </div>

      <UEmpty
        v-else-if="!(tasksByDay.get(key)?.length)"
        class="my-auto py-6"
        icon="i-lucide-calendar-off"
        title="Sem tarefas"
        description="Nenhuma rotina com vencimento neste dia."
        variant="naked"
      />

      <ul v-else class="flex flex-1 flex-col gap-1 overflow-y-auto p-2">
        <li v-for="task in tasksByDay.get(key)" :key="task.id">
          <button
            type="button"
            class="flex w-full flex-col gap-0.5 rounded-md px-2 py-1.5 text-left hover:bg-elevated"
            @click="emit('select', task)"
          >
            <span class="flex min-w-0 items-center gap-1.5 text-sm font-medium text-highlighted">
              <span
                class="size-1.5 shrink-0 rounded-full"
                :class="statusPresentation(task.status).dotClass"
              />
              <span class="truncate">{{ task.title }}</span>
            </span>
            <span class="truncate pl-3 text-xs text-muted">
              {{ task.process?.client?.name ?? 'Sem cliente' }}
              · {{ task.process?.name ?? 'Processo' }}
            </span>
            <span class="truncate pl-3 text-xs text-muted">
              {{ task.department || 'Sem depto' }}
              · {{ task.priority }}
              <template v-if="task.assignee_member_id"> · #{{ task.assignee_member_id }}</template>
            </span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
