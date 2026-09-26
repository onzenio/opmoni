<script setup lang="ts">
import type { Row, SortingState } from '@tanstack/table-core'
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
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
  filterWorkProcessosLeaves,
  hasWorkProcessosActiveFilters,
  workProcessosFilterColumns
} from '~/utils/workProcessosFilters'
import {
  isWorkProcessosSelectableLeaf,
  withWorkProcessosLeavesSelected,
  workProcessosLeafSelectionState,
  workProcessosSelectedCount,
  workProcessosTaskIdsFromSelection
} from '~/utils/workProcessosSelection'
import { cascadeBadgeColor, cascadeLabel, workGroupedTableOptions } from '~/utils/workGroupedTable'

definePageMeta({ middleware: 'auth' })

const toast = useToast()
const { grouped, updateTask } = useWork()
const { canManageWork } = useAuth()
const { referenceMonth } = useWorkReferenceMonth()
const { memberOptions } = useDirectory()

const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UCheckbox = resolveComponent('UCheckbox')

const { data, status, error, refresh } = await useAsyncData<WorkGroupedClient[]>(
  'work-processos-grouped',
  () => grouped(referenceMonth.value),
  { watch: [referenceMonth] }
)

const groups = computed<WorkGroupedClient[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')
const desktopTable = useClientMediaQuery('(min-width: 768px)')

/**
 * Leaf = task (or empty placeholder).
 * Grouping: processKey → clientId → leaf
 * (process domain first: same process name across clients shares a root group)
 */
interface ProcessTaskLeaf {
  id: string
  processKey: string
  processId: number
  processName: string
  processStatus: string
  processDueOn: string | null
  processRatio: number
  templateId: number | null
  templateName: string
  cascade: boolean
  clientId: number
  clientName: string
  order: number
  taskId: number | null
  title: string
  status: WorkTaskStatus | null
  department: string
  due_on: string | null
  empty: boolean
}

const allRows = computed<ProcessTaskLeaf[]>(() => {
  const leaves: ProcessTaskLeaf[] = []

  for (const group of groups.value) {
    for (const entry of group.processes) {
      const cascade = Boolean(entry.process.cascade)
      const processKey = entry.process.template
        ? `template-${entry.process.template.id}`
        : `process-${entry.process.id}`
      const templateId = entry.process.template?.id ?? null
      const templateName = entry.process.template?.name ?? ''

      if (entry.tasks.length === 0) {
        leaves.push({
          id: `process-${entry.process.id}-empty`,
          processKey,
          processId: entry.process.id,
          processName: entry.process.name,
          processStatus: entry.process.status,
          processDueOn: entry.process.due_on,
          processRatio: entry.ratio,
          templateId,
          templateName,
          cascade,
          clientId: group.client.id,
          clientName: group.client.name,
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
          processKey,
          processId: entry.process.id,
          processName: entry.process.name,
          processStatus: entry.process.status,
          processDueOn: entry.process.due_on,
          processRatio: entry.ratio,
          templateId,
          templateName,
          cascade,
          clientId: group.client.id,
          clientName: group.client.name,
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

  return leaves.sort((a, b) => {
    if (a.processKey !== b.processKey) return a.processKey.localeCompare(b.processKey, 'pt-BR')
    if (a.clientName !== b.clientName) return a.clientName.localeCompare(b.clientName, 'pt-BR')
    return a.order - b.order
  })
})

const search = ref('')
const filterModels = ref<DataTableFilterModel[]>([])

const rows = computed(() =>
  filterWorkProcessosLeaves(allRows.value, filterModels.value, search.value)
)

const hasActiveFilters = computed(() =>
  hasWorkProcessosActiveFilters(filterModels.value, search.value)
)

const filterColumns = computed(() =>
  workProcessosFilterColumns(allRows.value, filterModels.value)
)

const sorting = ref<SortingState>([])
const rowSelection = ref<Record<string, boolean>>({})
const groupingOptions = ref(workGroupedTableOptions())

const selectedCount = computed(() => workProcessosSelectedCount(rowSelection.value))
const selectedTaskIds = computed(() =>
  workProcessosTaskIdsFromSelection(rowSelection.value, rows.value)
)

const selectableLeafIds = computed(() =>
  rows.value.filter(isWorkProcessosSelectableLeaf).map(leaf => leaf.id)
)

const headerSelectionState = computed(() =>
  workProcessosLeafSelectionState(rowSelection.value, selectableLeafIds.value)
)

function clearSelection() {
  rowSelection.value = {}
}

function clearFilters() {
  filterModels.value = []
  search.value = ''
}

function onFilters(models: DataTableFilterModel[]) {
  filterModels.value = models
}

function onHeaderToggle(value: boolean | 'indeterminate') {
  rowSelection.value = withWorkProcessosLeavesSelected(
    rowSelection.value,
    selectableLeafIds.value,
    value === true
  )
}

function selectableIdsForRow(row: Row<ProcessTaskLeaf>): string[] {
  if (row.getIsGrouped()) {
    return row.getLeafRows()
      .map(leaf => leaf.original)
      .filter(isWorkProcessosSelectableLeaf)
      .map(leaf => leaf.id)
  }
  return isWorkProcessosSelectableLeaf(row.original) ? [row.original.id] : []
}

function onRowSelectToggle(row: Row<ProcessTaskLeaf>, value: boolean | 'indeterminate') {
  const ids = selectableIdsForRow(row)
  if (!ids.length) return
  rowSelection.value = withWorkProcessosLeavesSelected(rowSelection.value, ids, value === true)
}

function setLeafSelected(leafId: string, selected: boolean | 'indeterminate') {
  rowSelection.value = withWorkProcessosLeavesSelected(
    rowSelection.value,
    [leafId],
    selected === true
  )
}

function formatDueOn(value: string | null): string {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

function ratioLabel(ratio: number): string {
  return `${Math.round(ratio * 100)}%`
}

function countLeafTasks(row: { getLeafRows: () => { original: ProcessTaskLeaf }[] }): number {
  return row.getLeafRows().filter(leaf => !leaf.original.empty).length
}

function uniqueClients(row: { getLeafRows: () => { original: ProcessTaskLeaf }[] }): number {
  return new Set(row.getLeafRows().map(leaf => leaf.original.clientId)).size
}

function sortableHeader(label: string, column: { getIsSorted: () => false | 'asc' | 'desc', toggleSorting: (desc?: boolean) => void }) {
  const sorted = column.getIsSorted()
  return h(DataTableSortButton, {
    label,
    sorted: sorted || false,
    onToggle: () => column.toggleSorting(sorted === 'asc')
  })
}

const columns = computed<TableColumn<ProcessTaskLeaf>[]>(() => {
  const cols: TableColumn<ProcessTaskLeaf>[] = []

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
        'ariaLabel': 'Selecionar todas as tarefas filtradas'
      }),
      cell: ({ row }) => {
        const ids = selectableIdsForRow(row)
        if (!ids.length) return null
        return h(UCheckbox, {
          'modelValue': workProcessosLeafSelectionState(rowSelection.value, ids),
          'onUpdate:modelValue': (value: boolean | 'indeterminate') => onRowSelectToggle(row, value),
          'ariaLabel': row.getIsGrouped()
            ? `Selecionar tarefas de ${row.groupingColumnId === 'processKey' ? row.original.processName : row.original.clientName}`
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
    { id: 'processKey', accessorKey: 'processKey', enableSorting: false },
    { id: 'clientId', accessorKey: 'clientId', enableSorting: false },
    {
      accessorKey: 'order',
      enableSorting: true,
      header: ({ column }) => sortableHeader('#', column),
      meta: { class: { th: 'w-14 whitespace-nowrap text-right', td: 'w-14 text-right tabular-nums' } },
      aggregationFn: 'count',
      cell: ({ row }) => {
        if (row.getIsGrouped()) {
          if (row.groupingColumnId === 'processKey') {
            return `${uniqueClients(row)} cliente(s) · ${countLeafTasks(row)} tarefa(s)`
          }
          return `${countLeafTasks(row)}`
        }
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
        if (row.getIsGrouped() && row.groupingColumnId !== 'clientId') return null
        if (!row.getIsGrouped() && row.original.empty) return null

        return h(UButton, {
          'to': `/work/processos/${row.original.processId}`,
          'color': 'neutral',
          'variant': 'ghost',
          'size': 'xs',
          'icon': 'i-lucide-arrow-up-right',
          'aria-label': `Abrir processo ${row.original.processName} · ${row.original.clientName}`
        })
      }
    }
  )

  return cols
})

const rowSelectionOptions = {
  enableRowSelection: (row: Row<ProcessTaskLeaf>) => selectableIdsForRow(row).length > 0
}

/** Mobile: Processo → Cliente → tasks (mirrors desktop hierarchy). */
const mobileGroups = computed(() => {
  const map = new Map<string, {
    processKey: string
    processName: string
    templateName: string
    cascade: boolean
    clients: Map<number, {
      clientId: number
      clientName: string
      processId: number
      processRatio: number
      processStatus: string
      processDueOn: string | null
      tasks: ProcessTaskLeaf[]
    }>
  }>()

  for (const leaf of rows.value) {
    let process = map.get(leaf.processKey)
    if (!process) {
      process = {
        processKey: leaf.processKey,
        processName: leaf.processName,
        templateName: leaf.templateName,
        cascade: leaf.cascade,
        clients: new Map()
      }
      map.set(leaf.processKey, process)
    }
    let client = process.clients.get(leaf.clientId)
    if (!client) {
      client = {
        clientId: leaf.clientId,
        clientName: leaf.clientName,
        processId: leaf.processId,
        processRatio: leaf.processRatio,
        processStatus: leaf.processStatus,
        processDueOn: leaf.processDueOn,
        tasks: []
      }
      process.clients.set(leaf.clientId, client)
    }
    client.tasks.push(leaf)
  }

  return [...map.values()].map(process => ({
    ...process,
    clients: [...process.clients.values()]
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

function isLeafCascadeLocked(leaf: ProcessTaskLeaf): boolean {
  if (!leaf.taskId || !leaf.status) return false
  return lockedIds.value.has(leaf.taskId)
    || isCascadeAdvanceLockedInProcess(leaf.cascade, leaf.processId, leaf.order, allRows.value)
}

function derivedStatusForProcessKey(processKey: string) {
  return derivedProcessStatusForGroup(allRows.value, leaf => leaf.processKey === processKey)
}

function derivedStatusForProcess(processId: number) {
  return derivedProcessStatusForGroup(allRows.value, leaf => leaf.processId === processId)
}

function leavesForProcessKey(processKey: string): ProcessTaskLeaf[] {
  return allRows.value.filter(leaf => leaf.processKey === processKey)
}

function leavesForProcess(processId: number): ProcessTaskLeaf[] {
  return allRows.value.filter(leaf => leaf.processId === processId)
}

function leavesForGroupedRow(row: { groupingColumnId?: string, original: ProcessTaskLeaf }): ProcessTaskLeaf[] {
  return row.groupingColumnId === 'processKey'
    ? leavesForProcessKey(row.original.processKey)
    : leavesForProcess(row.original.processId)
}

function derivedStatusForGroupedRow(row: { groupingColumnId?: string, original: ProcessTaskLeaf }) {
  return row.groupingColumnId === 'processKey'
    ? derivedStatusForProcessKey(row.original.processKey)
    : derivedStatusForProcess(row.original.processId)
}

function taskIdsFromLeaves(leaves: ProcessTaskLeaf[]): number[] {
  return leaves
    .filter(isWorkProcessosSelectableLeaf)
    .map(leaf => leaf.taskId as number)
}

async function setLeafStatus(leaf: ProcessTaskLeaf, next: Exclude<WorkTaskStatus, 'dismissed'>) {
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

async function applyGroupStatus(leaves: ProcessTaskLeaf[], next: Exclude<WorkTaskStatus, 'dismissed'>) {
  const ids = taskIdsFromLeaves(leaves).filter((id) => {
    const leaf = allRows.value.find(row => row.taskId === id)
    return leaf?.status && leaf.status !== next
  })
  await runBulk('Status do grupo', () => ({ status: next }), ids)
}

function openDismissForLeaf(leaf: ProcessTaskLeaf) {
  if (!leaf.taskId) return
  dismissTargetIds.value = [leaf.taskId]
  dismissReason.value = ''
  dismissOpen.value = true
}

function openDismissForLeaves(leaves: ProcessTaskLeaf[]) {
  const ids = taskIdsFromLeaves(leaves)
  if (!ids.length) return
  dismissTargetIds.value = ids
  dismissReason.value = ''
  dismissOpen.value = true
}

const assignItems = computed(() => [
  { label: 'Sem responsável', value: null as number | null },
  ...memberOptions.value.map(option => ({ label: option.label, value: option.value as number | null }))
])

const selectionMenu = computed<DropdownMenuItem[][]>(() => [[
  {
    label: 'Avançar',
    icon: 'i-lucide-arrow-right',
    onSelect: () => { void bulkAdvance() }
  },
  {
    label: 'Atribuir',
    icon: 'i-lucide-user-round',
    children: assignItems.value.map(item => ({
      label: item.label,
      onSelect: () => { void bulkAssign(item.value) }
    }))
  },
  {
    label: 'Dispensar',
    icon: 'i-lucide-circle-minus',
    onSelect: openDismiss
  },
  {
    label: 'Cancelar seleção',
    icon: 'i-lucide-x',
    onSelect: clearSelection
  }
]])

async function runBulk(
  label: string,
  bodyFor: (taskId: number, leaf: ProcessTaskLeaf) => { status?: string, dismissal_reason?: string, assignee_member_id?: number | null } | null,
  ids: number[] = selectedTaskIds.value
) {
  if (!ids.length) return

  const byId = new Map(allRows.value.map(leaf => [leaf.taskId, leaf]))
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
      toast.add({ title: `Nenhuma tarefa atualizada`, color: 'error' })
    }
  } finally {
    bulkBusy.value = false
  }
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
    toast.add({ title: 'Não foi possível atualizar os processos', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar os processos', color: 'error' })
})

watch([filterModels, search, referenceMonth], () => {
  if (selectedCount.value) clearSelection()
})
</script>

<template>
  <div class="relative flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <ClientOnly>
      <WorkToolbarTeleport>
        <div class="flex items-center gap-1">
          <UDropdownMenu
            v-if="canManageWork && selectedCount"
            :items="selectionMenu"
            :content="{ align: 'end' }"
          >
            <UButton
              :label="desktopTable ? 'Seleção' : undefined"
              icon="i-lucide-list-checks"
              color="neutral"
              variant="subtle"
              aria-label="Ações da seleção"
            >
              <template #trailing>
                <UKbd>{{ selectedCount }}</UKbd>
              </template>
            </UButton>
          </UDropdownMenu>
          <UButton
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="ghost"
            aria-label="Atualizar processos"
            :loading="isLoading"
            @click="onRefresh"
          />
        </div>
      </WorkToolbarTeleport>
    </ClientOnly>

    <DataTableFilter
      :columns="filterColumns"
      :model-value="filterModels"
      :disabled="isLoading"
      class="min-w-0"
      @update:model-value="onFilters"
    >
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Buscar processo, modelo, cliente ou tarefa..."
        class="min-w-0 flex-1"
        :disabled="isLoading"
      />
    </DataTableFilter>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar os processos"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <WorkTableSkeleton
      v-else-if="isLoading && groups.length === 0"
      :columns="5"
      :rows="8"
      grouped
    />

    <UEmpty
      v-else-if="groups.length === 0"
      icon="i-lucide-layers"
      title="Nenhum processo neste mês"
      description="Os processos gerados a partir dos modelos aparecem aqui agrupados por processo, cliente e tarefa."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <UEmpty
      v-else-if="rows.length === 0"
      icon="i-lucide-filter-x"
      title="Nenhum resultado com estes filtros"
      description="Ajuste a busca ou limpe os filtros aplicados."
      variant="naked"
      :actions="hasActiveFilters
        ? [{ label: 'Limpar filtros', color: 'neutral', variant: 'outline', onClick: clearFilters }]
        : []"
    />

    <template v-else>
      <!-- Mobile: Processo → Cliente → tarefas -->
      <div
        class="flex min-h-0 flex-1 flex-col gap-4 md:hidden"
        :class="canManageWork && selectedCount ? 'pb-16' : ''"
      >
        <section
          v-for="process in mobileGroups"
          :key="process.processKey"
          class="space-y-2"
        >
          <div class="flex min-w-0 flex-wrap items-center gap-2 px-0.5">
            <strong class="truncate text-highlighted" :title="process.processName">
              {{ process.processName }}
            </strong>
            <UBadge
              :color="cascadeBadgeColor(process.cascade)"
              variant="subtle"
              :label="cascadeLabel(process.cascade)"
            />
            <WorkGroupStatusSelect
              :derived="derivedStatusForProcessKey(process.processKey)"
              :can-manage="canManageWork"
              :loading="bulkBusy"
              @change="(next) => applyGroupStatus(leavesForProcessKey(process.processKey), next)"
              @dismiss="openDismissForLeaves(leavesForProcessKey(process.processKey))"
            />
            <span v-if="process.templateName" class="truncate text-xs text-muted">
              {{ process.templateName }}
            </span>
          </div>

          <div
            v-for="client in process.clients"
            :key="`${process.processKey}-${client.clientId}`"
            class="space-y-2"
          >
            <div class="flex min-w-0 items-center justify-between gap-2 px-0.5">
              <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="truncate font-medium text-highlighted" :title="client.clientName">
                  {{ client.clientName }}
                </span>
                <UBadge
                  color="primary"
                  variant="subtle"
                  :label="ratioLabel(client.processRatio)"
                />
                <WorkGroupStatusSelect
                  :derived="derivedStatusForProcess(client.processId)"
                  :can-manage="canManageWork"
                  :loading="bulkBusy"
                  @change="(next) => applyGroupStatus(leavesForProcess(client.processId), next)"
                  @dismiss="openDismissForLeaves(leavesForProcess(client.processId))"
                />
                <span class="text-xs text-muted">
                  Prazo {{ formatDueOn(client.processDueOn) }}
                </span>
              </div>
              <UButton
                :to="`/work/processos/${client.processId}`"
                color="neutral"
                variant="ghost"
                size="xs"
                icon="i-lucide-arrow-up-right"
                :aria-label="`Abrir processo ${process.processName} · ${client.clientName}`"
              />
            </div>

            <UCard
              v-for="leaf in client.tasks.filter(isWorkProcessosSelectableLeaf)"
              :key="leaf.id"
              variant="subtle"
              :ui="{ body: 'p-3' }"
            >
              <div class="flex items-start gap-3">
                <UCheckbox
                  v-if="canManageWork"
                  :model-value="!!rowSelection[leaf.id]"
                  size="lg"
                  class="mt-0.5"
                  :aria-label="`Selecionar ${leaf.title}`"
                  @update:model-value="setLeafSelected(leaf.id, $event)"
                />
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 font-medium text-highlighted" :title="leaf.title">
                      <span class="mr-1.5 text-muted tabular-nums">#{{ leaf.order }}</span>
                      {{ leaf.title }}
                    </p>
                  </div>
                  <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <WorkTaskStatusSelect
                      v-if="canManageWork && leaf.status"
                      :status="leaf.status"
                      :locked="isLeafCascadeLocked(leaf)"
                      :loading="busyId === leaf.taskId || bulkBusy"
                      class="min-w-36"
                      @change="(next) => setLeafStatus(leaf, next)"
                      @dismiss="openDismissForLeaf(leaf)"
                    />
                    <UBadge
                      v-else-if="leaf.status"
                      :color="statusPresentation(leaf.status).color"
                      variant="subtle"
                      :label="statusPresentation(leaf.status).label"
                    />
                    <span v-if="leaf.department" class="text-xs text-muted">{{ leaf.department }}</span>
                    <span class="text-xs text-muted">{{ formatDueOn(leaf.due_on) }}</span>
                  </div>
                </div>
              </div>
            </UCard>

            <p
              v-if="client.tasks.every(leaf => leaf.empty)"
              class="px-0.5 text-sm text-muted"
            >
              Nenhuma tarefa neste processo
            </p>
          </div>
        </section>
      </div>

      <!-- Desktop grouped table -->
      <UCard
        variant="subtle"
        class="hidden min-w-0 md:block"
        :class="canManageWork && selectedCount ? 'mb-14' : ''"
        :ui="{ body: 'overflow-x-auto p-0 sm:p-0' }"
      >
        <UTable
          v-model:sorting="sorting"
          :data="rows"
          :columns="columns"
          :grouping="['processKey', 'clientId']"
          :grouping-options="groupingOptions"
          :row-selection-options="rowSelectionOptions"
          :loading="isLoading"
          :get-row-id="(row: ProcessTaskLeaf) => row.id"
          :ui="{
            root: 'min-w-full overflow-x-auto',
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

              <div
                v-if="row.groupingColumnId === 'processKey'"
                class="flex min-w-0 flex-wrap items-center gap-2"
              >
                <strong class="truncate text-highlighted" :title="row.original.processName">
                  {{ row.original.processName }}
                </strong>
                <UBadge
                  :color="cascadeBadgeColor(row.original.cascade)"
                  variant="subtle"
                  :label="cascadeLabel(row.original.cascade)"
                />
                <span v-if="row.original.templateName" class="truncate text-xs text-muted">
                  {{ row.original.templateName }}
                </span>
              </div>

              <div
                v-else-if="row.groupingColumnId === 'clientId'"
                class="flex min-w-0 flex-wrap items-center gap-2"
              >
                <span class="truncate font-medium text-highlighted" :title="row.original.clientName">
                  {{ row.original.clientName }}
                </span>
                <UBadge
                  color="primary"
                  variant="subtle"
                  :label="ratioLabel(row.original.processRatio)"
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
      <WorkProcessosSelectionBar
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
