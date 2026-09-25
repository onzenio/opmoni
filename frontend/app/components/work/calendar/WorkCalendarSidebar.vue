<script setup lang="ts">
import type { DateValue } from '@internationalized/date'
import { CalendarDate } from '@internationalized/date'
import type { WorkTaskStatus, WorkTaskPriority } from '~/types/work'
import { calendarPriorityOptions, countActiveCalendarFilters } from '~/utils/calendarUi'
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

const filtersOpen = ref(false)

const activeFilterCount = computed(() => countActiveCalendarFilters({
  processId: props.processId,
  clientId: props.clientId,
  assigneeId: props.assigneeId,
  department: props.department,
  priority: props.priority
}))

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
  <aside class="flex max-h-[min(45vh,32rem)] w-full shrink-0 flex-col gap-4 overflow-y-auto pe-1 lg:max-h-none lg:w-64 lg:border-e lg:border-default lg:pe-5">
    <UCalendar
      v-model="miniValue"
      as="section"
      locale="pt-BR"
      class="w-full"
    />

    <div class="flex flex-col gap-2 border-t border-default pt-4">
      <p class="text-xs font-medium text-muted">
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

    <UCollapsible v-model:open="filtersOpen" class="border-t border-default pt-2">
      <template #default="{ open }">
        <UButton
          color="neutral"
          variant="ghost"
          class="w-full justify-start px-1.5"
          :trailing-icon="open ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
          :aria-label="open ? 'Recolher filtros' : 'Expandir filtros'"
        >
          <span class="flex min-w-0 flex-1 items-center gap-2 text-left">
            <UIcon name="i-lucide-sliders-horizontal" class="size-4 shrink-0 text-muted" />
            <span>Filtros</span>
            <UBadge
              v-if="activeFilterCount"
              :label="String(activeFilterCount)"
              color="primary"
              variant="subtle"
              size="sm"
            />
          </span>
        </UButton>
      </template>

      <template #content>
        <div class="flex flex-col gap-3 px-1.5 pb-1 pt-3">
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
              :model-value="priority || undefined"
              :items="calendarPriorityOptions"
              value-key="value"
              label-key="label"
              placeholder="Todas"
              class="w-full"
              @update:model-value="emit('update:priority', ($event as WorkTaskPriority | undefined) ?? '')"
            />
          </UFormField>
          <UButton
            label="Limpar filtros"
            color="neutral"
            variant="ghost"
            size="sm"
            class="self-start px-1.5"
            :disabled="activeFilterCount === 0"
            @click="emit('clear')"
          />
        </div>
      </template>
    </UCollapsible>
  </aside>
</template>
