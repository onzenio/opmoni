<script setup lang="ts">
import type { Row, SortingState } from '@tanstack/table-core'
import type { TableColumn } from '@nuxt/ui'
import { h, resolveComponent } from 'vue'
import type { DataTableFilterModel } from '~/components/data-table/Filter.vue'
import DataTableSortButton from '~/components/data-table/SortButton.vue'
import WorkToolbarTeleport from '~/components/work/WorkToolbarTeleport'
import WorkGroupStatusSelect from '~/components/work/WorkGroupStatusSelect.vue'
import WorkTaskStatusSelect from '~/components/work/WorkTaskStatusSelect.vue'
import { apiMessage, apiStatus } from '~/composables/useApiError'
import { statusPresentation } from '~/composables/useWorkPresentation'
import type { WorkGroupedClient, WorkTaskStatus } from '~/types/work'
import {
  derivedProcessStatusForGroup,
  isCascadeAdvanceLockedInProcess
} from '~/utils/workDerivedStatus'
import {
  filterWorkClientesLeaves,
  hasWorkClientesActiveFilters,
  workClientesFilterColumns
} from '~/utils/workClientesFilters'
import {
  isWorkClientesSelectableLeaf,
  withWorkClientesLeavesSelected,
  workClientesLeafSelectionState,
  workClientesSelectedCount,
  workClientesTaskIdsFromSelection
} from '~/utils/workClientesSelection'
import { cascadeBadgeColor, cascadeLabel, workGroupedTableOptions } from '~/utils/workGroupedTable'

definePageMeta({ middleware: 'auth' })

const toast = useToast()
const { grouped, updateTask } = useWork()
const { canManageWork } = useAuth()
const { referenceMonth } = useWorkReferenceMonth()
const { memberOptions, error: membersError } = useDirectory()

const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UCheckbox = resolveComponent('UCheckbox')

const { data, status, error, refresh } = await useAsyncData<WorkGroupedClient[]>(
  'work-clientes',
  () => grouped(referenceMonth.value),
  { watch: [referenceMonth] }
)

const groups = computed<WorkGroupedClient[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

/** Flat leaf: one per task (or placeholder when a process has no tasks). */
interface ClientTaskLeaf {
  id: string
  clientId: number
  clientName: string
  processId: number
  processName: string
  processRatio: number
  cascade: boolean
  order: number
  taskId: number | null
  title: string
  status: WorkTaskStatus | null
  department: string
  due_on: string | null
  empty: boolean
}

const rows = computed<ClientTaskLeaf[]>(() => {
  const leaves: ClientTaskLeaf[] = []

  for (const group of groups.value) {
    for (const entry of group.processes) {
      const cascade = Boolean(entry.process.cascade)

      if (entry.tasks.length === 0) {
        leaves.push({
          id: `process-${entry.process.id}-empty`,
          clientId: group.client.id,
          clientName: group.client.name,
          processId: entry.process.id,
          processName: entry.process.name,
          processRatio: entry.ratio,
          cascade,
          order: 0,
          taskId: null,
          title: '',
          status: null,
          department: '',
          due_on: null,
          empty: true
        })
        continue
      }

      const sortedTasks = [...entry.tasks].sort((a, b) => a.order - b.order)

      for (const task of sortedTasks) {
        leaves.push({
          id: String(task.id),
          clientId: group.client.id,
          clientName: group.client.name,
          processId: entry.process.id,
          processName: entry.process.name,
          processRatio: entry.ratio,
          cascade,
          order: task.order,
          taskId: task.id,
          title: task.title,
          status: task.status,
          department: task.department,
          due_on: task.due_on,
          empty: false
        })
      }
    }
  }

  return leaves
})

const search = ref('')
const filterModels = ref<DataTableFilterModel[]>([])

const filteredRows = computed(() =>
  filterWorkClientesLeaves(rows.value, filterModels.value, search.value)
)

const hasActiveFilters = computed(() =>
  hasWorkClientesActiveFilters(filterModels.value, search.value)
)

const filterColumns = computed(() =>
  workClientesFilterColumns(rows.value, filterModels.value)
)

const sorting = ref<SortingState>([])
const rowSelection = ref<Record<string, boolean>>({})
const groupingOptions = ref(workGroupedTableOptions())

const selectedCount = computed(() => workClientesSelectedCount(rowSelection.value))
const selectedTaskIds = computed(() =>
  workClientesTaskIdsFromSelection(rowSelection.value, filteredRows.value)
)

const selectableLeafIds = computed(() =>
  filteredRows.value.filter(isWorkClientesSelectableLeaf).map(leaf => leaf.id)
)

const headerSelectionState = computed(() =>
  workClientesLeafSelectionState(rowSelection.value, selectableLeafIds.value)
)

function clearSelection() {
  rowSelection.value = {}
}

function clearFilters() {
  filterModels.value = []
  search.value = ''
}

function onHeaderToggle(value: boolean | 'indeterminate') {
  rowSelection.value = withWorkClientesLeavesSelected(
    rowSelection.value,
    selectableLeafIds.value,
    value === true
  )
}

function selectableIdsForRow(row: Row<ClientTaskLeaf>): string[] {
  if (row.getIsGrouped()) {
    return row.getLeafRows()
      .map((leaf: Row<ClientTaskLeaf>) => leaf.original)
      .filter(isWorkClientesSelectableLeaf)
      .map((leaf: ClientTaskLeaf) => leaf.id)
  }
  return isWorkClientesSelectableLeaf(row.original) ? [row.original.id] : []
}

function onRowSelectToggle(row: Row<ClientTaskLeaf>, value: boolean | 'indeterminate') {
  const ids = selectableIdsForRow(row)
  if (!ids.length) return
  rowSelection.value = withWorkClientesLeavesSelected(rowSelection.value, ids, value === true)
}

function formatDueOn(value: string | null): string {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

function ratioLabel(ratio: number): string {
  return `${Math.round(ratio * 100)}%`
}

function countLeafTasks(row: { getLeafRows: () => { original: ClientTaskLeaf }[] }): number {
  return row.getLeafRows().filter(leaf => !leaf.original.empty).length
}

function sortableHeader(label: string, column: { getIsSorted: () => false | 'asc' | 'desc', toggleSorting: (desc?: boolean) => void }) {
  const sorted = column.getIsSorted()
  return h(DataTableSortButton, {
    label,
    sorted: sorted || false,
    onToggle: () => column.toggleSorting(sorted === 'asc')
  })
}

const columns = computed<TableColumn<ClientTaskLeaf>[]>(() => {
  const cols: TableColumn<ClientTaskLeaf>[] = []

  if (canManageWork.value) {
    cols.push({
      id: 'select',
      enableSorting: false,
      enableHiding: false,
      meta: { class: { th: 'w-10', td: 'w-10' } },
      header: () => h(UCheckbox, {
        'modelValue': headerSelectionState.value,
        'disabled': selectableLeafIds.value.length === 0,
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => onHeaderToggle(value),
        'ariaLabel': 'Selecionar todas as tarefas visíveis'
      }),
      cell: ({ row }) => {
        const ids = selectableIdsForRow(row)
        if (!ids.length) return null
        return h(UCheckbox, {
          'modelValue': workClientesLeafSelectionState(rowSelection.value, ids),
          'onUpdate:modelValue': (value: boolean | 'indeterminate') => onRowSelectToggle(row, value),
          'ariaLabel': row.getIsGrouped()
            ? `Selecionar tarefas de ${row.groupingColumnId === 'clientId' ? row.original.clientName : row.original.processName}`
            : `Selecionar ${row.original.title}`
        })
      }
    })
  }

  cols.push(
    {
      id: 'item',
      header: 'Item',
      enableSorting: false,
      meta: { class: { th: 'min-w-48 whitespace-nowrap', td: 'min-w-48' } }
    },
    { id: 'clientId', accessorKey: 'clientId', enableSorting: false },
    { id: 'processId', accessorKey: 'processId', enableSorting: false },
    {
      accessorKey: 'order',
      enableSorting: true,
      header: ({ column }) => sortableHeader('#', column),
      meta: { class: { th: 'w-14 whitespace-nowrap text-right', td: 'w-14 text-right tabular-nums' } },
      aggregationFn: 'count',
      cell: ({ row }) => {
        if (row.getIsGrouped()) return `${countLeafTasks(row)}`
        if (row.original.empty) return '—'
        return String(row.original.order)
      }
    },
    {
      accessorKey: 'title',
      enableSorting: true,
      header: ({ column }) => sortableHeader('Tarefa', column),
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'min-w-28' } },
      aggregationFn: 'count',
      cell: ({ row }) => {
        if (row.getIsGrouped()) return `${countLeafTasks(row)} tarefa(s)`
        if (row.original.empty) return 'Nenhuma tarefa neste processo'
        return row.original.title
      }
    },
    {
      accessorKey: 'status',
      enableSorting: true,
      header: ({ column }) => sortableHeader('Status', column),
      meta: { class: { th: 'min-w-36 whitespace-nowrap', td: 'min-w-40' } },
      sortingFn: (a, b) => {
        const left = a.original.status ?? ''
        const right = b.original.status ?? ''
        return left.localeCompare(right)
      }
    },
    {
      accessorKey: 'department',
      enableSorting: true,
      header: ({ column }) => sortableHeader('Depto.', column),
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'min-w-28' } },
      cell: ({ row }) => {
        if (row.getIsGrouped() || row.original.empty) return null
        return row.original.department
      }
    },
    {
      accessorKey: 'due_on',
      enableSorting: true,
      header: ({ column }) => sortableHeader('Vencimento', column),
      meta: { class: { th: 'min-w-36 whitespace-nowrap', td: 'min-w-32 whitespace-nowrap' } },
      cell: ({ row }) => {
        if (row.getIsGrouped() || row.original.empty) return null
        return formatDueOn(row.original.due_on)
      }
    },
    {
      id: 'actions',
      enableSorting: false,
      header: '',
      meta: { class: { th: 'w-12', td: 'w-12' } },
      cell: ({ row }) => {
        if (row.getIsGrouped() && row.groupingColumnId !== 'processId') return null
        if (!row.getIsGrouped() && row.original.empty) return null

        return h(UButton, {
          'to': `/work/processos/${row.original.processId}`,
          'color': 'neutral',
          'variant': 'ghost',
          'size': 'xs',
          'icon': 'i-lucide-arrow-up-right',
          'aria-label': `Abrir processo ${row.original.processName}`
        })
      }
    }
  )

  return cols
})

const rowSelectionOptions = {
  enableRowSelection: (row: Row<ClientTaskLeaf>) => selectableIdsForRow(row).length > 0
}

const mobileGroups = computed(() => {
  const map = new Map<number, {
    clientId: number
    clientName: string
    processes: Map<number, {
      processId: number
      processName: string
      processRatio: number
      cascade: boolean
      tasks: ClientTaskLeaf[]
    }>
  }>()

  for (const leaf of filteredRows.value) {
    let client = map.get(leaf.clientId)
    if (!client) {
      client = { clientId: leaf.clientId, clientName: leaf.clientName, processes: new Map() }
      map.set(leaf.clientId, client)
    }
    let process = client.processes.get(leaf.processId)
    if (!process) {
      process = {
        processId: leaf.processId,
        processName: leaf.processName,
        processRatio: leaf.processRatio,
        cascade: leaf.cascade,
        tasks: []
      }
      client.processes.set(leaf.processId, process)
    }
    process.tasks.push(leaf)
  }

  return [...map.values()].map(client => ({
    ...client,
    processes: [...client.processes.values()]
  }))
})

const bulkBusy = ref(false)
const busyId = ref<number | null>(null)
const lockedIds = ref<Set<number>>(new Set())
const dismissOpen = ref(false)
const dismissReason = ref('')
const dismissTargetIds = ref<number[] | null>(null)

function markLocked(taskId: number) {
  lockedIds.value = new Set(lockedIds.value).add(taskId)
}

function clearLocked(taskId: number) {
  const next = new Set(lockedIds.value)
  next.delete(taskId)
  lockedIds.value = next
}

function isLeafCascadeLocked(leaf: ClientTaskLeaf): boolean {
  if (!leaf.taskId || !leaf.status) return false
  return lockedIds.value.has(leaf.taskId)
    || isCascadeAdvanceLockedInProcess(leaf.cascade, leaf.processId, leaf.order, rows.value)
}

function derivedStatusForClient(clientId: number) {
  return derivedProcessStatusForGroup(rows.value, leaf => leaf.clientId === clientId)
}

function derivedStatusForProcess(processId: number) {
  return derivedProcessStatusForGroup(rows.value, leaf => leaf.processId === processId)
}

function leavesForClient(clientId: number): ClientTaskLeaf[] {
  return rows.value.filter(leaf => leaf.clientId === clientId)
}

function leavesForProcess(processId: number): ClientTaskLeaf[] {
  return rows.value.filter(leaf => leaf.processId === processId)
}

function leavesForGroupedRow(row: { groupingColumnId?: string, original: ClientTaskLeaf }): ClientTaskLeaf[] {
  return row.groupingColumnId === 'clientId'
    ? leavesForClient(row.original.clientId)
    : leavesForProcess(row.original.processId)
}

function derivedStatusForGroupedRow(row: { groupingColumnId?: string, original: ClientTaskLeaf }) {
  return row.groupingColumnId === 'clientId'
    ? derivedStatusForClient(row.original.clientId)
    : derivedStatusForProcess(row.original.processId)
}

function taskIdsFromLeaves(leaves: ClientTaskLeaf[]): number[] {
  return leaves
    .filter(isWorkClientesSelectableLeaf)
    .map(leaf => leaf.taskId as number)
}

const assignItems = computed(() => [
  { label: 'Sem responsável', value: null as number | null },
  ...memberOptions.value.map(option => ({ label: option.label, value: option.value as number | null }))
])

async function runBulk(
  label: string,
  bodyFor: (taskId: number, leaf: ClientTaskLeaf) => { status?: string, dismissal_reason?: string, assignee_member_id?: number | null } | null,
  ids: number[] = selectedTaskIds.value
) {
  if (!ids.length) return

  const byId = new Map(rows.value.map(leaf => [leaf.taskId, leaf]))
  bulkBusy.value = true
  let ok = 0
  let failed = 0
  let locked = 0

  try {
    for (const taskId of ids) {
      const leaf = byId.get(taskId)
      if (!leaf) continue
      const body = bodyFor(taskId, leaf)
      if (!body) continue
      try {
        await updateTask(taskId, body)
        clearLocked(taskId)
        ok += 1
      } catch (err: unknown) {
        if (apiStatus(err) === 422) {
          markLocked(taskId)
          locked += 1
          toast.add({
            title: `Bloqueada #${taskId}`,
            description: apiMessage(err) ?? 'Aguardando etapas anteriores.',
            color: 'warning'
          })
        } else {
          failed += 1
          toast.add({
            title: `Falha em #${taskId}`,
            description: apiMessage(err) ?? 'Não foi possível atualizar a tarefa.',
            color: 'error'
          })
        }
      }
    }

    await refresh()
    clearSelection()

    if (ok && !failed && !locked) {
      toast.add({ title: `${label}: ${ok} tarefa(s)`, color: 'success' })
    } else if (ok) {
      toast.add({
        title: `${label}: ${ok} ok${failed ? `, ${failed} erro(s)` : ''}${locked ? `, ${locked} bloqueada(s)` : ''}`,
        color: 'warning'
      })
    } else if (failed || locked) {
      toast.add({ title: 'Nenhuma tarefa atualizada', color: 'error' })
    }
  } finally {
    bulkBusy.value = false
  }
}

async function setLeafStatus(leaf: ClientTaskLeaf, next: Exclude<WorkTaskStatus, 'dismissed'>) {
  if (!leaf.taskId || !leaf.status || leaf.status === next) return
  busyId.value = leaf.taskId
  try {
    await updateTask(leaf.taskId, { status: next })
    clearLocked(leaf.taskId)
    await refresh()
    const titles: Record<Exclude<WorkTaskStatus, 'dismissed'>, string> = {
      todo: 'Tarefa marcada como A fazer',
      doing: 'Tarefa em progresso',
      done: 'Tarefa concluída'
    }
    toast.add({ title: titles[next], color: 'success' })
  } catch (err: unknown) {
    if (apiStatus(err) === 422) {
      markLocked(leaf.taskId)
      toast.add({
        title: 'Avanço bloqueado',
        description: apiMessage(err) ?? 'Aguardando etapas anteriores.',
        color: 'warning'
      })
    } else {
      toast.add({
        title: 'Não foi possível atualizar o status',
        description: apiMessage(err),
        color: 'error'
      })
    }
  } finally {
    busyId.value = null
  }
}

async function applyGroupStatus(leaves: ClientTaskLeaf[], next: Exclude<WorkTaskStatus, 'dismissed'>) {
  const ids = taskIdsFromLeaves(leaves).filter((id) => {
    const leaf = rows.value.find(row => row.taskId === id)
    return leaf?.status && leaf.status !== next
  })
  await runBulk('Status do grupo', () => ({ status: next }), ids)
}

function openDismissForLeaf(leaf: ClientTaskLeaf) {
  if (!leaf.taskId) return
  dismissTargetIds.value = [leaf.taskId]
  dismissReason.value = ''
  dismissOpen.value = true
}

function openDismissForLeaves(leaves: ClientTaskLeaf[]) {
  const ids = taskIdsFromLeaves(leaves)
  if (!ids.length) return
  dismissTargetIds.value = ids
  dismissReason.value = ''
  dismissOpen.value = true
}

async function bulkAdvance() {
  await runBulk('Status avançado', (_id, leaf) => {
    if (!leaf.status) return null
    const next = leaf.status === 'todo' ? 'doing' : leaf.status === 'doing' ? 'done' : null
    return next ? { status: next } : null
  })
}

async function bulkAssign(memberId: number | null) {
  await runBulk('Responsável atualizado', () => ({ assignee_member_id: memberId }))
}

async function bulkDismiss() {
  const reason = dismissReason.value.trim()
  if (!reason) {
    toast.add({ title: 'Informe o motivo da dispensa', color: 'error' })
    return
  }
  const ids = dismissTargetIds.value ?? selectedTaskIds.value
  dismissOpen.value = false
  dismissTargetIds.value = null
  await runBulk('Tarefas dispensadas', (_id, leaf) => {
    if (leaf.status === 'dismissed') return null
    return { status: 'dismissed', dismissal_reason: reason }
  }, ids)
  dismissReason.value = ''
}

function openDismiss() {
  dismissTargetIds.value = null
  dismissReason.value = ''
  dismissOpen.value = true
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

watch(membersError, (value) => {
  if (value) {
    toast.add({
      title: 'Não foi possível carregar os responsáveis',
      description: 'A atribuição em massa pode ficar limitada.',
      color: 'warning'
    })
  }
})

watch([filterModels, search, referenceMonth], () => {
  if (selectedCount.value) clearSelection()
})

watch(dismissOpen, (open) => {
  if (!open) dismissReason.value = ''
})
</script>

<template>
  <div class="relative flex min-h-0 min-w-0 flex-1 flex-col gap-3 overflow-hidden p-3 sm:gap-4 sm:p-4 lg:p-5">
    <ClientOnly>
      <WorkToolbarTeleport>
        <UButton
          icon="i-lucide-refresh-cw"
          color="neutral"
          variant="ghost"
          aria-label="Atualizar clientes"
          :loading="isLoading"
          @click="onRefresh"
        />
      </WorkToolbarTeleport>
    </ClientOnly>

    <DataTableFilter
      :columns="filterColumns"
      :model-value="filterModels"
      :disabled="isLoading"
      class="min-w-0 shrink-0"
      @update:model-value="filterModels = $event"
    >
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Buscar cliente, processo ou tarefa..."
        class="min-w-0 flex-1"
        :disabled="isLoading"
      />
    </DataTableFilter>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar os clientes"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <WorkTableSkeleton
      v-else-if="isLoading && groups.length === 0"
      :columns="5"
      :rows="8"
      grouped
      class="min-h-0 flex-1"
    />

    <UEmpty
      v-else-if="groups.length === 0"
      icon="i-lucide-users"
      title="Nenhum cliente com rotinas neste mês"
      description="Quando houver processos gerados, eles aparecem aqui agrupados por cliente."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <UEmpty
      v-else-if="filteredRows.length === 0"
      icon="i-lucide-search-x"
      title="Nenhuma tarefa encontrada"
      description="Ajuste a busca ou limpe os filtros aplicados."
      variant="naked"
      :actions="hasActiveFilters
        ? [{ label: 'Limpar filtros', icon: 'i-lucide-filter-x', color: 'neutral', variant: 'outline', onClick: () => clearFilters() }]
        : [{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <template v-else>
      <!-- Mobile card fallback -->
      <div
        class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto md:hidden"
        :class="canManageWork && selectedCount ? 'pb-16' : ''"
      >
        <UCard
          v-for="client in mobileGroups"
          :key="client.clientId"
          variant="subtle"
          :ui="{ body: 'space-y-3 p-4' }"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
              <p class="min-w-0 truncate text-sm font-semibold text-highlighted" :title="client.clientName">
                {{ client.clientName }}
              </p>
              <WorkGroupStatusSelect
                :derived="derivedStatusForClient(client.clientId)"
                :can-manage="canManageWork"
                :loading="bulkBusy"
                @change="(next) => applyGroupStatus(leavesForClient(client.clientId), next)"
                @dismiss="openDismissForLeaves(leavesForClient(client.clientId))"
              />
            </div>
            <UCheckbox
              v-if="canManageWork"
              :model-value="workClientesLeafSelectionState(
                rowSelection,
                client.processes.flatMap(p => p.tasks).filter(isWorkClientesSelectableLeaf).map(t => t.id)
              )"
              size="lg"
              :ui="{ base: 'rounded-full' }"
              :aria-label="`Selecionar tarefas de ${client.clientName}`"
              @update:model-value="rowSelection = withWorkClientesLeavesSelected(
                rowSelection,
                client.processes.flatMap(p => p.tasks).filter(isWorkClientesSelectableLeaf).map(t => t.id),
                $event === true
              )"
            />
          </div>

          <div
            v-for="process in client.processes"
            :key="process.processId"
            class="space-y-2 rounded-lg bg-default p-3 ring ring-default"
          >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
              <UCheckbox
                v-if="canManageWork"
                :model-value="workClientesLeafSelectionState(
                  rowSelection,
                  process.tasks.filter(isWorkClientesSelectableLeaf).map(t => t.id)
                )"
                :aria-label="`Selecionar tarefas de ${process.processName}`"
                @update:model-value="rowSelection = withWorkClientesLeavesSelected(
                  rowSelection,
                  process.tasks.filter(isWorkClientesSelectableLeaf).map(t => t.id),
                  $event === true
                )"
              />
              <span class="min-w-0 flex-1 truncate text-sm font-medium text-highlighted" :title="process.processName">
                {{ process.processName }}
              </span>
              <UBadge color="primary" variant="subtle" :label="ratioLabel(process.processRatio)" />
              <WorkGroupStatusSelect
                :derived="derivedStatusForProcess(process.processId)"
                :can-manage="canManageWork"
                :loading="bulkBusy"
                @change="(next) => applyGroupStatus(leavesForProcess(process.processId), next)"
                @dismiss="openDismissForLeaves(leavesForProcess(process.processId))"
              />
              <UBadge
                :color="cascadeBadgeColor(process.cascade)"
                variant="subtle"
                :label="cascadeLabel(process.cascade)"
              />
              <UButton
                :to="`/work/processos/${process.processId}`"
                color="neutral"
                variant="ghost"
                size="xs"
                icon="i-lucide-arrow-up-right"
                :aria-label="`Abrir processo ${process.processName}`"
              />
            </div>

            <div
              v-for="task in process.tasks"
              :key="task.id"
              class="flex items-start gap-2 border-t border-default pt-2 first:border-t-0 first:pt-0"
            >
              <UCheckbox
                v-if="canManageWork && isWorkClientesSelectableLeaf(task)"
                :model-value="!!rowSelection[task.id]"
                class="mt-0.5"
                :aria-label="`Selecionar ${task.title}`"
                @update:model-value="rowSelection = withWorkClientesLeavesSelected(rowSelection, [task.id], $event === true)"
              />
              <div class="min-w-0 flex-1 space-y-1">
                <template v-if="task.empty">
                  <p class="text-sm text-muted">
                    Nenhuma tarefa neste processo
                  </p>
                </template>
                <template v-else>
                  <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 text-sm text-highlighted">
                      <span class="me-1.5 tabular-nums text-muted">#{{ task.order }}</span>
                      {{ task.title }}
                    </p>
                    <WorkTaskStatusSelect
                      v-if="canManageWork && task.status"
                      :status="task.status"
                      :locked="isLeafCascadeLocked(task)"
                      :loading="busyId === task.taskId || bulkBusy"
                      class="shrink-0"
                      @change="(next) => setLeafStatus(task, next)"
                      @dismiss="openDismissForLeaf(task)"
                    />
                    <UBadge
                      v-else-if="task.status"
                      :color="statusPresentation(task.status).color"
                      variant="subtle"
                      :label="statusPresentation(task.status).label"
                      class="shrink-0"
                    />
                  </div>
                  <p class="text-xs text-muted">
                    {{ task.department || '—' }} · {{ formatDueOn(task.due_on) }}
                  </p>
                </template>
              </div>
            </div>
          </div>
        </UCard>
      </div>

      <!-- Desktop grouped table -->
      <UCard
        class="hidden min-h-0 min-w-0 flex-1 flex-col overflow-hidden md:flex"
        :class="canManageWork && selectedCount ? 'pb-16' : ''"
        variant="subtle"
        :ui="{ body: 'flex min-h-0 flex-1 flex-col overflow-auto p-0 sm:p-0', root: 'flex min-h-0 flex-1 flex-col' }"
      >
        <UTable
          v-model:sorting="sorting"
          v-model:row-selection="rowSelection"
          :data="filteredRows"
          :columns="columns"
          :grouping="['clientId', 'processId']"
          :grouping-options="groupingOptions"
          :row-selection-options="rowSelectionOptions"
          :loading="isLoading"
          :get-row-id="(row: ClientTaskLeaf) => row.id"
          sticky
          class="min-h-0 flex-1"
          :ui="{
            root: 'min-h-0 min-w-full overflow-auto',
            base: 'min-w-max',
            th: 'whitespace-nowrap',
            td: 'empty:p-0'
          }"
        >
          <template #item-cell="{ row }">
            <div v-if="row.getIsGrouped()" class="flex items-center">
              <span
                class="inline-block"
                :style="{ width: `calc(${row.depth} * 1rem)` }"
              />

              <UButton
                variant="outline"
                color="neutral"
                class="mr-2"
                size="xs"
                :icon="row.getIsExpanded() ? 'i-lucide-minus' : 'i-lucide-plus'"
                :aria-label="row.getIsExpanded() ? 'Recolher' : 'Expandir'"
                @click="row.toggleExpanded()"
              />

              <strong
                v-if="row.groupingColumnId === 'clientId'"
                class="truncate text-highlighted"
                :title="row.original.clientName"
              >
                {{ row.original.clientName }}
              </strong>

              <div
                v-else-if="row.groupingColumnId === 'processId'"
                class="flex min-w-0 flex-wrap items-center gap-2"
              >
                <span class="truncate font-medium text-highlighted" :title="row.original.processName">
                  {{ row.original.processName }}
                </span>
                <UBadge
                  color="primary"
                  variant="subtle"
                  :label="ratioLabel(row.original.processRatio)"
                />
                <UBadge
                  :color="cascadeBadgeColor(row.original.cascade)"
                  variant="subtle"
                  :label="cascadeLabel(row.original.cascade)"
                />
              </div>
            </div>
            <div v-else class="flex items-center">
              <span
                class="inline-block"
                :style="{ width: `calc(${row.depth} * 1rem)` }"
              />
            </div>
          </template>

          <template #status-cell="{ row }">
            <WorkGroupStatusSelect
              v-if="row.getIsGrouped()"
              :derived="derivedStatusForGroupedRow(row)"
              :can-manage="canManageWork"
              :loading="bulkBusy"
              @change="(next) => applyGroupStatus(leavesForGroupedRow(row), next)"
              @dismiss="openDismissForLeaves(leavesForGroupedRow(row))"
            />
            <span v-else-if="row.original.empty" class="text-muted">—</span>
            <WorkTaskStatusSelect
              v-else-if="canManageWork && row.original.status"
              :status="row.original.status"
              :locked="isLeafCascadeLocked(row.original)"
              :loading="busyId === row.original.taskId || bulkBusy"
              @change="(next) => setLeafStatus(row.original, next)"
              @dismiss="openDismissForLeaf(row.original)"
            />
            <UBadge
              v-else-if="row.original.status"
              :color="statusPresentation(row.original.status).color"
              variant="subtle"
              :label="statusPresentation(row.original.status).label"
            />
          </template>
        </UTable>
      </UCard>
    </template>

    <Transition
      enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <WorkClientesSelectionBar
        v-if="canManageWork && selectedCount"
        class="absolute bottom-3 left-1/2 z-20 w-max max-w-[calc(100%-1.5rem)] -translate-x-1/2"
        :count="selectedCount"
        :disabled="isLoading || bulkBusy"
        :member-items="assignItems"
        @clear="clearSelection"
        @advance="bulkAdvance"
        @dismiss="openDismiss"
        @assign="bulkAssign"
      />
    </Transition>

    <UModal
      v-model:open="dismissOpen"
      :title="(dismissTargetIds?.length ?? 0) === 1 ? 'Dispensar tarefa' : 'Dispensar tarefas'"
      :description="dismissTargetIds
        ? `${dismissTargetIds.length} tarefa(s) serão marcadas como dispensadas.`
        : `${selectedCount} tarefa(s) serão marcadas como dispensadas.`"
    >
      <template #body>
        <UFormField label="Motivo" name="dismissal_reason" required>
          <UTextarea
            v-model="dismissReason"
            :rows="3"
            autoresize
            placeholder="Descreva o motivo da dispensa..."
          />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="ghost"
            @click="dismissOpen = false"
          />
          <UButton
            label="Dispensar"
            color="error"
            :loading="bulkBusy"
            :disabled="bulkBusy || !dismissReason.trim()"
            @click="bulkDismiss"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
