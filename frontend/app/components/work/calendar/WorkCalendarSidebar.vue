<script setup lang="ts">
import type { DateValue } from '@internationalized/date'
import { CalendarDate } from '@internationalized/date'
import type { WorkTaskStatus, WorkTaskPriority } from '~/types/work'
import { parseDateKey, statusPresentation } from '~/utils/workCalendar'

const props = defineProps<{
  modelDate: string
  statusVisible: Record<WorkTaskStatus, boolean>
  processId: number | null
  clientId: number | null
  assigneeId: number | null
  department: string
  priority: WorkTaskPriority | ''
  processOptions: { label: string, value: number }[]
  clientOptions: { label: string, value: number }[]
  assigneeOptions: { label: string, value: number }[]
  departmentOptions: { label: string, value: string }[]
}>()

const emit = defineEmits<{
  'update:modelDate': [value: string]
  'update:statusVisible': [value: Record<WorkTaskStatus, boolean>]
  'update:processId': [value: number | null]
  'update:clientId': [value: number | null]
  'update:assigneeId': [value: number | null]
  'update:department': [value: string]
  'update:priority': [value: WorkTaskPriority | '']
  'clear': []
}>()

const statusItems: { key: WorkTaskStatus, label: string }[] = [
  { key: 'todo', label: statusPresentation('todo').label },
  { key: 'doing', label: statusPresentation('doing').label },
  { key: 'done', label: statusPresentation('done').label },
  { key: 'dismissed', label: statusPresentation('dismissed').label }
]

const priorityItems: { label: string, value: WorkTaskPriority | '' }[] = [
  { label: 'Todas', value: '' },
  { label: 'Baixa', value: 'low' },
  { label: 'Média', value: 'medium' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' }
]

const miniValue = computed({
  get(): DateValue | undefined {
    const parsed = parseDateKey(props.modelDate)
    if (!parsed) return undefined
    return new CalendarDate(parsed.year, parsed.month, parsed.day)
  },
  set(value: DateValue | undefined) {
    if (!value || !('day' in value)) return
    const date = value as { year: number, month: number, day: number }
    emit('update:modelDate', `${date.year}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`)
  }
})

function toggleStatus(key: WorkTaskStatus, on: boolean) {
  emit('update:statusVisible', { ...props.statusVisible, [key]: on })
}
</script>

<template>
  <aside class="flex w-full shrink-0 flex-col gap-4 lg:w-64">
    <UCalendar
      v-model="miniValue"
      class="w-full rounded-lg p-2 ring ring-default"
    />

    <div class="flex flex-col gap-2">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">
        Status
      </p>
      <label
        v-for="item in statusItems"
        :key="item.key"
        class="flex items-center gap-2 text-sm text-highlighted"
      >
        <UCheckbox
          :model-value="statusVisible[item.key]"
          @update:model-value="(value) => toggleStatus(item.key, Boolean(value))"
        />
        <span
          class="size-1.5 rounded-full"
          :class="statusPresentation(item.key).dotClass"
        />
        {{ item.label }}
      </label>
    </div>

    <div class="flex flex-col gap-3">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">
        Filtros
      </p>
      <UFormField label="Processo">
        <USelectMenu
          :model-value="processId"
          :items="processOptions"
          value-key="value"
          label-key="label"
          placeholder="Todos"
          clear
          class="w-full"
          @update:model-value="emit('update:processId', ($event as number | null) ?? null)"
        />
      </UFormField>
      <UFormField label="Cliente">
        <USelectMenu
          :model-value="clientId"
          :items="clientOptions"
          value-key="value"
          label-key="label"
          placeholder="Todos"
          clear
          class="w-full"
          @update:model-value="emit('update:clientId', ($event as number | null) ?? null)"
        />
      </UFormField>
      <UFormField label="Responsável">
        <USelectMenu
          :model-value="assigneeId"
          :items="assigneeOptions"
          value-key="value"
          label-key="label"
          placeholder="Todos"
          clear
          class="w-full"
          @update:model-value="emit('update:assigneeId', ($event as number | null) ?? null)"
        />
      </UFormField>
      <UFormField label="Departamento">
        <USelectMenu
          :model-value="department || undefined"
          :items="departmentOptions"
          value-key="value"
          label-key="label"
          placeholder="Todos"
          clear
          class="w-full"
          @update:model-value="emit('update:department', typeof $event === 'string' ? $event : '')"
        />
      </UFormField>
      <UFormField label="Prioridade">
        <USelect
          :model-value="priority"
          :items="priorityItems"
          value-key="value"
          label-key="label"
          class="w-full"
          @update:model-value="emit('update:priority', $event as WorkTaskPriority | '')"
        />
      </UFormField>
      <UButton
        label="Limpar filtros"
        color="neutral"
        variant="ghost"
        size="sm"
        class="self-start"
        @click="emit('clear')"
      />
    </div>
  </aside>
</template>
