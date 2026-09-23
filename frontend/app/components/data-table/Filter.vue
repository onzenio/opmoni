<script setup lang="ts">
import type { ChipProps, CommandPaletteGroup, CommandPaletteItem, DropdownMenuItem } from '@nuxt/ui'
import {
  columnType,
  defaultFilterOperator,
  determineNewOperator,
  operatorChoices,
  operatorDetail,
  operatorLabel,
  type DataTableFilterColumn,
  type DataTableFilterModel,
  type DataTableFilterOperator,
  type DataTableFilterOption
} from './filter-model'

export type {
  DataTableColumnType,
  DataTableFilterColumn,
  DataTableFilterModel,
  DataTableFilterOperator,
  DataTableFilterOption
} from './filter-model'

type FilterCommand = CommandPaletteItem & {
  columnId: string
  value?: string
}

const valueItems = new Map<string, FilterCommand>()

const props = defineProps<{
  columns: DataTableFilterColumn[]
  modelValue: DataTableFilterModel[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: DataTableFilterModel[]]
}>()

const open = ref(false)
const query = ref('')
const paletteKey = ref(0)
const operatorPref = ref<Record<string, DataTableFilterOperator>>({})
const isMobile = useMediaQuery('(max-width: 767px)')

function applied(columnId: string) {
  return props.modelValue.find(filter => filter.columnId === columnId)
}

function valuesOf(columnId: string) {
  return applied(columnId)?.values ?? []
}

function columnOf(columnId: string) {
  return props.columns.find(column => column.id === columnId)
}

function replace(columnId: string, next: DataTableFilterModel | null) {
  const rest = props.modelValue.filter(filter => filter.columnId !== columnId)
  emit('update:modelValue', next ? [...rest, next] : rest)
}

function currentOperator(column: DataTableFilterColumn) {
  return applied(column.id)?.operator ?? operatorPref.value[column.id] ?? defaultFilterOperator(columnType(column))
}

function toggleValue(columnId: string, value: string) {
  const column = columnOf(columnId)
  if (!column) return
  const current = valuesOf(columnId).map(String)
  const next = current.includes(value) ? current.filter(item => item !== value) : [...current, value]
  if (!next.length) {
    replace(columnId, null)
    return
  }
  const operator = determineNewOperator(columnType(column), current, next, currentOperator(column))
  operatorPref.value = { ...operatorPref.value, [columnId]: operator }
  replace(columnId, { columnId, type: columnType(column), operator, values: next })
}

function choose(event: Event, columnId: string, value: string) {
  event.preventDefault()
  toggleValue(columnId, value)
}

function setOperator(column: DataTableFilterColumn, operator: DataTableFilterOperator) {
  const type = columnType(column)
  operatorPref.value = { ...operatorPref.value, [column.id]: operator }
  const values = operatorDetail(type, operator).target === 'single'
    ? valuesOf(column.id).slice(0, 1)
    : valuesOf(column.id)
  if (!values.length) return
  replace(column.id, { columnId: column.id, type, operator, values })
}

function chip(color?: DataTableFilterOption['color']): ChipProps | undefined {
  return color ? { color } : undefined
}

function valueItem(columnId: string, option: DataTableFilterOption, prefix?: string): FilterCommand {
  const key = `${prefix ?? ''}:${columnId}:${option.value}`
  const active = valuesOf(columnId).map(String).includes(option.value)
  const existing = valueItems.get(key)
  if (existing) {
    existing.active = active
    existing.label = option.label
    existing.chip = chip(option.color)
    return existing
  }
  const item: FilterCommand = {
    label: option.label,
    prefix,
    columnId,
    value: option.value,
    slot: 'value',
    chip: chip(option.color),
    active,
    onSelect: (event: Event) => choose(event, columnId, option.value)
  }
  valueItems.set(key, item)
  return item
}

function valueLabel(column: DataTableFilterColumn, value: string | number) {
  return column.options?.find(option => option.value === String(value))?.label ?? String(value)
}

function valueColor(column: DataTableFilterColumn, value: string | number) {
  return column.options?.find(option => option.value === String(value))?.color
}

const pills = computed(() => props.modelValue.flatMap((filter) => {
  const column = columnOf(filter.columnId)
  if (!column || !filter.values.length) return []
  return [{ filter, column }]
}))

const groups = computed<CommandPaletteGroup[]>(() => {
  const listed = props.columns.filter((column) => {
    const type = columnType(column)
    return (type === 'option' || type === 'multiOption') && (column.options?.length ?? 0) > 0
  })

  return [
    {
      id: 'columns',
      items: listed.map(column => ({
        label: column.label,
        icon: column.icon,
        placeholder: 'Buscar valor...',
        children: (column.options ?? []).map(option => valueItem(column.id, option))
      }))
    },
    {
      id: 'matches',
      ignoreFilter: true,
      postFilter: (search, items) => {
        const term = search.trim().toLocaleLowerCase('pt-BR')
        if (term.length < 2) return []
        return items.filter(item =>
          item.label?.toLocaleLowerCase('pt-BR').includes(term)
          || item.prefix?.toLocaleLowerCase('pt-BR').includes(term)
        )
      },
      items: listed.flatMap(column =>
        (column.options ?? []).map(option => valueItem(column.id, option, column.label))
      )
    }
  ]
})

function valueGroups(column: DataTableFilterColumn): CommandPaletteGroup[] {
  return [{
    id: column.id,
    items: (column.options ?? []).map(option => valueItem(column.id, option))
  }]
}

function operatorItems(column: DataTableFilterColumn): DropdownMenuItem[][] {
  return [operatorChoices(columnType(column)).map(operator => ({
    label: operator.label,
    icon: operator.value === applied(column.id)?.operator ? 'i-lucide-check' : undefined,
    onSelect: () => setOperator(column, operator.value)
  }))]
}

function clearFilters() {
  emit('update:modelValue', [])
  operatorPref.value = {}
}

watch(open, (isOpen) => {
  if (isOpen) return
  query.value = ''
  paletteKey.value++
})
</script>

<template>
  <div class="flex w-full min-w-0 flex-col gap-1.5">
    <div class="flex w-full min-w-0 items-center gap-1.5">
      <slot />

      <UDrawer
        v-if="isMobile"
        v-model:open="open"
        title="Filtros"
        :handle="true"
        :ui="{ content: 'max-h-[calc(100dvh-1rem)]' }"
      >
        <UButton
          icon="i-lucide-list-filter"
          color="neutral"
          variant="outline"
          :disabled="disabled"
          class="shrink-0"
          aria-label="Filtros"
        />
        <template #body>
          <DataTableFilterMenu
            :key="paletteKey"
            v-model:search-term="query"
            :groups="groups"
            placeholder="Buscar campo ou valor..."
          />
        </template>
      </UDrawer>

      <UPopover
        v-else
        v-model:open="open"
        :content="{ align: 'start', side: 'bottom' }"
        :ui="{ content: 'w-fit p-0' }"
      >
        <UButton
          icon="i-lucide-list-filter"
          color="neutral"
          variant="outline"
          :disabled="disabled"
          class="shrink-0"
          aria-label="Filtros"
        />
        <template #content>
          <DataTableFilterMenu
            :key="paletteKey"
            v-model:search-term="query"
            :groups="groups"
            placeholder="Buscar campo ou valor..."
          />
        </template>
      </UPopover>

      <slot name="trailing" />
    </div>

    <div v-if="pills.length" class="flex items-start justify-between gap-1.5">
      <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
        <UFieldGroup v-for="{ filter, column } in pills" :key="filter.columnId" class="w-fit shrink-0">
          <UButton
            :icon="column.icon"
            :label="column.label"
            color="neutral"
            variant="outline"
            class="pointer-events-none"
            tabindex="-1"
          />

          <UDropdownMenu :items="operatorItems(column)" :content="{ align: 'start' }">
            <UButton
              :label="operatorLabel(columnType(column), filter.operator)"
              color="neutral"
              variant="outline"
              :disabled="disabled"
            />
          </UDropdownMenu>

          <UPopover :content="{ align: 'start' }" :ui="{ content: 'w-fit p-0' }">
            <UButton color="neutral" variant="outline" :disabled="disabled">
              <UBadge
                v-if="filter.values.length === 1"
                :label="valueLabel(column, filter.values[0]!)"
                :color="valueColor(column, filter.values[0]!) ?? 'neutral'"
                variant="subtle"
              />
              <UBadge
                v-else
                :label="`${filter.values.length} selecionados`"
                color="neutral"
                variant="subtle"
              />
            </UButton>
            <template #content>
              <DataTableFilterMenu :groups="valueGroups(column)" placeholder="Buscar valor..." />
            </template>
          </UPopover>

          <UButton
            icon="i-lucide-x"
            color="neutral"
            variant="outline"
            :disabled="disabled"
            :aria-label="`Remover filtro ${column.label}`"
            @click="replace(column.id, null)"
          />
        </UFieldGroup>
      </div>

      <UButton
        label="Limpar"
        icon="i-lucide-filter-x"
        color="error"
        variant="ghost"
        class="shrink-0"
        :disabled="disabled"
        @click="clearFilters"
      />
    </div>
  </div>
</template>
