<script setup lang="ts">
import type { NavigationMenuItem, TableColumn } from '@nuxt/ui'
import { sheetBodyClass, sheetTableUi, sheetToolbarUi } from '~/components/data-table/sheet'
import {
  monitoringColumns,
  monitoringCompanies,
  monitoringListPath,
  monitoringStatusFor,
  monitoringStatusPresentation,
  monitoringStatuses,
  type MonitoringCompany,
  type MonitoringPage,
  type MonitoringStatus
} from '~/utils/monitoringNav'

const props = defineProps<{
  page: MonitoringPage
  status: MonitoringStatus | 'all'
}>()

const search = ref('')

interface SheetRow extends MonitoringCompany {
  status: MonitoringStatus
  agency: string
  document: string
  mailbox: string
}

const searched = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('pt-BR')
  return monitoringCompanies.filter((company) => {
    if (!term) return true
    return company.name.toLocaleLowerCase('pt-BR').includes(term) || company.taxId.includes(term)
  })
})

const rows = computed<SheetRow[]>(() => searched.value.flatMap((company) => {
  const status = monitoringStatusFor(company, props.page)
  if (props.status !== 'all' && status !== props.status) return []
  return [{
    ...company,
    status,
    agency: props.page.label,
    document: props.page.label,
    mailbox: props.page.label
  }]
}))

function statusCount(value: MonitoringStatus | 'all') {
  if (value === 'all') return searched.value.length
  return searched.value.filter(company => monitoringStatusFor(company, props.page) === value).length
}

const mobileStatusItems = computed(() => monitoringStatuses.map(item => ({
  label: item.label,
  value: item.value,
  to: monitoringListPath(props.page, item.value),
  count: statusCount(item.value)
})))

const statusTabs = computed<NavigationMenuItem[][]>(() => [[
  ...monitoringStatuses.map(item => ({
    label: item.label,
    icon: item.icon,
    to: monitoringListPath(props.page, item.value),
    exact: true,
    active: props.status === item.value,
    badge: statusCount(item.value)
  }))
]])

const columns = computed<TableColumn<SheetRow>[]>(() =>
  monitoringColumns[props.page.family].map(column => ({
    accessorKey: column.id,
    header: column.header
  }))
)

const detailFields = computed(() =>
  monitoringColumns[props.page.family].filter(column => column.id !== 'name' && column.id !== 'status')
)

function fieldValue(row: SheetRow, id: string) {
  const value = row[id as keyof SheetRow]
  return value == null || value === '' ? '—' : String(value)
}

const hasQuery = computed(() => search.value.trim() !== '' || props.status !== 'all')
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <UDashboardToolbar
      class="hidden min-w-0 md:flex"
      :ui="sheetToolbarUi"
    >
      <template #left>
        <UNavigationMenu
          :items="statusTabs"
          highlight
          class="-mx-1 min-w-0 flex-1"
          :ui="{ root: 'min-w-0', list: 'min-w-0' }"
        />
      </template>
    </UDashboardToolbar>

    <div :class="sheetBodyClass">
      <DataTableStatusChips class="md:hidden" :items="mobileStatusItems" :active="status" />

      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Buscar por nome ou CNPJ"
        class="max-w-md"
        aria-label="Buscar por nome ou CNPJ"
      />

      <UEmpty
        v-if="rows.length === 0"
        :icon="hasQuery ? 'i-lucide-search-x' : 'i-lucide-inbox'"
        :title="hasQuery ? 'Nenhum cliente encontrado' : 'Nenhum cliente nesta lista'"
        :description="hasQuery ? 'Ajuste a busca ou escolha outra situação.' : 'Esta lista ainda não tem clientes.'"
        variant="naked"
      />

      <template v-else>
        <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto md:hidden">
          <UCard v-for="row in rows" :key="row.id" :ui="{ body: 'p-3 sm:p-4' }">
            <div class="flex items-start justify-between gap-3">
              <DataTableIdentity :title="row.name" :meta="row.taxId" />
              <UBadge
                class="shrink-0"
                :color="monitoringStatusPresentation[row.status].color"
                :icon="monitoringStatusPresentation[row.status].icon"
                variant="subtle"
                :label="monitoringStatusPresentation[row.status].label"
              />
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
              <div v-for="field in detailFields" :key="field.id" class="min-w-0">
                <dt class="text-xs text-muted">
                  {{ field.header }}
                </dt>
                <dd class="truncate text-sm text-default tabular-nums">
                  {{ fieldValue(row, field.id) }}
                </dd>
              </div>
            </dl>
          </UCard>
        </div>

        <div class="hidden min-h-0 min-w-0 flex-1 flex-col md:flex">
          <UTable
            sticky
            :data="rows"
            :columns="columns"
            class="h-full min-h-0 w-full flex-1"
            :ui="sheetTableUi"
          >
            <template #name-cell="{ row }">
              <DataTableIdentity :title="row.original.name" :meta="row.original.taxId" />
            </template>

            <template #status-cell="{ row }">
              <UBadge
                class="max-w-full"
                :color="monitoringStatusPresentation[row.original.status].color"
                :icon="monitoringStatusPresentation[row.original.status].icon"
                variant="subtle"
                :label="monitoringStatusPresentation[row.original.status].label"
                :ui="{ base: 'max-w-full', label: 'truncate' }"
              />
            </template>
          </UTable>
        </div>
      </template>
    </div>
  </div>
</template>
