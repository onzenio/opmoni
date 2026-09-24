<script setup lang="ts">
import { getGroupedRowModel } from '@tanstack/table-core'
import type { ExpandedState } from '@tanstack/table-core'
import type { TableColumn, TableRow } from '@nuxt/ui'
import type { WorkGroupedClient, WorkTask } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const toast = useToast()
const { grouped } = useWork()

const referenceMonth = computed(() => {
  const raw = route.query.reference_month
  return typeof raw === 'string' && /^\d{4}-\d{2}$/.test(raw) ? raw : '2026-03'
})

const { data, status, error, refresh } = await useAsyncData<WorkGroupedClient[]>(
  'work-clientes',
  () => grouped(referenceMonth.value),
  { watch: [referenceMonth] }
)

const groups = computed<WorkGroupedClient[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

interface FlatRow {
  client_id: number
  client_name: string
  process_id: number
  process_name: string
  ratio: number
  id: number
  title: string
  department: string
  due_on: string | null
  priority: string
  status: WorkTask['status']
  order: number
}

const flatTasks = computed<FlatRow[]>(() => {
  const rows: FlatRow[] = []
  for (const group of groups.value) {
    for (const entry of group.processes) {
      for (const task of entry.tasks) {
        rows.push({
          client_id: group.client.id,
          client_name: group.client.name,
          process_id: entry.process.id,
          process_name: entry.process.name,
          ratio: entry.ratio,
          id: task.id,
          title: task.title,
          department: task.department,
          due_on: task.due_on,
          priority: task.priority,
          status: task.status,
          order: task.order
        })
      }
    }
  }
  return rows
})

const nameByClientId = computed(() => {
  const map = new Map<number, string>()
  for (const row of flatTasks.value) {
    if (!map.has(row.client_id)) map.set(row.client_id, row.client_name)
  }
  return map
})

const nameByProcessId = computed(() => {
  const map = new Map<number, string>()
  for (const row of flatTasks.value) {
    if (!map.has(row.process_id)) map.set(row.process_id, row.process_name)
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

const grouping = ref<string[]>(['client_id', 'process_id'])
const expanded = ref<ExpandedState>(true)

const columns: TableColumn<FlatRow>[] = [
  { accessorKey: 'client_id', header: 'Cliente', enableGrouping: true },
  { accessorKey: 'process_id', header: 'Processo', enableGrouping: true },
  {
    accessorKey: 'title',
    header: 'Tarefa',
    aggregatedCell: undefined
  },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'due_on', header: 'Vencimento' }
]

function groupedTitle(row: TableRow<FlatRow>): string {
  const columnId = row.groupingColumnId ?? ''
  if (columnId === 'client_id' || columnId === 'process_id') {
    const id = row.groupingValue as number
    if (columnId === 'client_id') {
      return nameByClientId.value.get(id) ?? `Cliente ${String(id)}`
    }
    return nameByProcessId.value.get(id) ?? `Processo ${String(id)}`
  }
  return ''
}

function leafCount(row: TableRow<FlatRow>): number {
  try {
    return row.getLeafRows().length
  } catch {
    return row.subRows.length
  }
}

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar a visão de clientes', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar os clientes', color: 'error' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-users" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          Clientes
        </h2>
        <span class="shrink-0 text-xs text-muted">{{ referenceMonth }}</span>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar clientes"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar os clientes"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && groups.length === 0" class="flex flex-col gap-3">
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="groups.length === 0"
      icon="i-lucide-users"
      title="Nenhum cliente com rotinas neste mês"
      description="Quando houver processos gerados, eles aparecem aqui agrupados por cliente."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <UCard v-else variant="subtle" :ui="{ body: 'p-0 sm:p-0' }">
      <UTable
        v-model:grouping="grouping"
        v-model:expanded="expanded"
        :data="flatTasks"
        :columns="columns"
        :grouping-options="{ getGroupedRowModel: getGroupedRowModel(), groupedColumnMode: 'remove' }"
        :loading="isLoading"
      >
        <template #title-cell="{ row }: { row: TableRow<FlatRow> }">
          <div class="flex min-w-0 items-center gap-2">
            <template v-if="row.getIsGrouped()">
              <UButton
                :icon="row.getIsExpanded() ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
                color="neutral"
                variant="ghost"
                size="xs"
                :aria-label="row.getIsExpanded() ? 'Recolher' : 'Expandir'"
                @click="row.getToggleExpandedHandler()()"
              />
              <span class="truncate text-sm font-semibold text-highlighted">
                {{ groupedTitle(row) }}
              </span>
              <UBadge color="neutral" variant="subtle" :label="`${leafCount(row)} tarefa(s)`" />
            </template>
            <template v-else>
              <span class="truncate pl-8 text-sm text-highlighted" :title="row.original.title">
                {{ row.original.title }}
              </span>
              <UBadge
                :color="statusPresentation(row.original.status).color"
                variant="subtle"
                :label="statusPresentation(row.original.status).label"
              />
            </template>
          </div>
        </template>

        <template #status-cell="{ row }: { row: TableRow<FlatRow> }">
          <UBadge
            v-if="!row.getIsGrouped()"
            :color="statusPresentation(row.original.status).color"
            variant="subtle"
            :label="statusPresentation(row.original.status).label"
          />
        </template>

        <template #due_on-cell="{ row }: { row: TableRow<FlatRow> }">
          <span v-if="!row.getIsGrouped()" class="text-sm text-muted">
            {{ row.original.due_on ? new Date(`${row.original.due_on}T00:00:00`).toLocaleDateString('pt-BR') : '—' }}
          </span>
        </template>
      </UTable>
    </UCard>
  </div>
</template>
