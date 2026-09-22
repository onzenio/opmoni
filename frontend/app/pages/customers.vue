<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import { refDebounced } from '@vueuse/core'
import type {
  Client,
  ClientListParams,
  ClientStatus,
  DeadlineStatus,
  TaxRegime
} from '~/types/client'

definePageMeta({ middleware: 'auth' })

const { list, update } = useClients()
const { canManageClients } = useAuth()
const toast = useToast()

const page = ref(1)
const perPage = ref(15)
const search = ref('')
const debouncedSearch = refDebounced(search, 350)
const statusFilter = ref<ClientStatus | 'all'>('all')
const regimeFilter = ref<TaxRegime | 'all'>('all')
const deadlineFilter = ref<DeadlineStatus | 'all'>('all')
const sort = ref<'name' | 'tax_id' | 'status' | 'tax_regime' | 'created_at'>('name')
const direction = ref<'asc' | 'desc'>('asc')

const params = computed<ClientListParams>(() => ({
  page: page.value,
  per_page: perPage.value,
  q: debouncedSearch.value || undefined,
  status: statusFilter.value === 'all' ? undefined : statusFilter.value,
  tax_regime: regimeFilter.value === 'all' ? undefined : regimeFilter.value,
  deadline_status: deadlineFilter.value === 'all' ? undefined : deadlineFilter.value,
  sort: sort.value,
  direction: direction.value
}))

const { data, status, error, refresh } = await useAsyncData(
  'client-portfolio',
  () => list(params.value),
  { watch: [params] }
)

watch([debouncedSearch, statusFilter, regimeFilter, deadlineFilter], () => {
  page.value = 1
})

const rows = computed<Client[]>(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta ?? { current_page: 1, last_page: 1, per_page: perPage.value, total: 0 })
const isLoading = computed(() => status.value === 'pending')
const hasActiveFilters = computed(() =>
  !!debouncedSearch.value || statusFilter.value !== 'all' || regimeFilter.value !== 'all' || deadlineFilter.value !== 'all'
)

function clearFilters() {
  search.value = ''
  statusFilter.value = 'all'
  regimeFilter.value = 'all'
  deadlineFilter.value = 'all'
  page.value = 1
}

const statusOptions = [
  { label: 'Todas as situações', value: 'all' },
  { label: 'Ativo', value: 'active' },
  { label: 'Inativo', value: 'inactive' }
]

const regimeOptions = [
  { label: 'Todos os regimes', value: 'all' },
  { label: 'MEI', value: 'mei' },
  { label: 'Simples Nacional', value: 'simple_national' },
  { label: 'Lucro presumido', value: 'presumed_profit' },
  { label: 'Lucro real', value: 'actual_profit' },
  { label: 'Outro', value: 'other' },
  { label: 'Não aplicável', value: 'not_applicable' }
]

const deadlineOptions = [
  { label: 'Todas as pendências', value: 'all' },
  { label: 'Sem cadastro', value: 'missing' },
  { label: 'Válido', value: 'valid' },
  { label: 'Vence em breve', value: 'expiring' },
  { label: 'Vencido', value: 'expired' }
]

const sortOptions = [
  { label: 'Nome', value: 'name' },
  { label: 'CPF/CNPJ', value: 'tax_id' },
  { label: 'Situação', value: 'status' },
  { label: 'Regime tributário', value: 'tax_regime' },
  { label: 'Cadastro', value: 'created_at' }
]

const statusPresentation: Record<ClientStatus, { label: string, color: 'success' | 'neutral', icon: string }> = {
  active: { label: 'Ativo', color: 'success', icon: 'i-lucide-circle-check' },
  inactive: { label: 'Inativo', color: 'neutral', icon: 'i-lucide-circle-pause' }
}

const regimeLabel: Record<string, string> = {
  mei: 'MEI',
  simple_national: 'Simples Nacional',
  presumed_profit: 'Lucro presumido',
  actual_profit: 'Lucro real',
  other: 'Outro',
  not_applicable: 'Não aplicável'
}

const deadlinePresentation: Record<DeadlineStatus, { label: string, color: 'neutral' | 'success' | 'warning' | 'error', icon: string }> = {
  missing: { label: 'Não cadastrado', color: 'neutral', icon: 'i-lucide-circle-minus' },
  valid: { label: 'Válido', color: 'success', icon: 'i-lucide-circle-check' },
  expiring: { label: 'Vence em breve', color: 'warning', icon: 'i-lucide-clock-alert' },
  expired: { label: 'Vencido', color: 'error', icon: 'i-lucide-circle-alert' }
}

function taxIdLabel(client: Client): string {
  if (!client.tax_id) return 'Não informado'
  return client.person_type === 'individual' ? maskTaxId(client.tax_id) : formatTaxId(client.tax_id)
}

const columns: TableColumn<Client>[] = [
  { accessorKey: 'name', header: 'Cliente' },
  {
    accessorKey: 'tax_id',
    header: 'CPF/CNPJ',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  {
    accessorKey: 'tax_regime',
    header: 'Regime tributário',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  { accessorKey: 'status', header: 'Situação' },
  { id: 'certificate', header: 'Certificado A1' },
  { id: 'ecac_power_of_attorney', header: 'Procuração e-CAC' },
  { id: 'actions', enableHiding: false }
]

const columnVisibility = ref<Record<string, boolean>>({
  name: true,
  tax_id: true,
  tax_regime: true,
  status: true,
  certificate: true,
  ecac_power_of_attorney: true
})

const hideableColumns: { id: string, label: string }[] = [
  { id: 'name', label: 'Cliente' },
  { id: 'tax_id', label: 'CPF/CNPJ' },
  { id: 'tax_regime', label: 'Regime tributário' },
  { id: 'status', label: 'Situação' },
  { id: 'certificate', label: 'Certificado A1' },
  { id: 'ecac_power_of_attorney', label: 'Procuração e-CAC' }
]

const columnMenuItems = computed(() => hideableColumns.map(col => ({
  label: col.label,
  type: 'checkbox' as const,
  checked: columnVisibility.value[col.id] !== false,
  onUpdateChecked(checked: boolean) {
    columnVisibility.value = { ...columnVisibility.value, [col.id]: checked }
  },
  onSelect(e?: Event) {
    e?.preventDefault()
  }
})))

const target = shallowRef<Client | null>(null)
const detailsOpen = ref(false)
const formOpen = ref(false)
const formClient = shallowRef<Client | null>(null)
const certificateOpen = ref(false)
const powerOfAttorneyOpen = ref(false)
const deleteOpen = ref(false)

let lastTrigger: HTMLElement | null = null

function rememberFocus() {
  const activeElement = document.activeElement instanceof HTMLElement ? document.activeElement : null
  if (activeElement?.getAttribute('role') === 'menuitem' && lastTrigger?.isConnected) return
  lastTrigger = activeElement
}

function restoreFocus(event: Event) {
  event.preventDefault()
  lastTrigger?.focus?.()
  lastTrigger = null
}

const overlayContent = { onCloseAutoFocus: restoreFocus }

function openDetails(client: Client) {
  rememberFocus()
  target.value = client
  detailsOpen.value = true
}

function openCreate() {
  rememberFocus()
  target.value = null
  formClient.value = null
  formOpen.value = true
}

function openEdit(client: Client) {
  rememberFocus()
  target.value = client
  formClient.value = client
  formOpen.value = true
}

function openCertificate(client: Client) {
  rememberFocus()
  target.value = client
  certificateOpen.value = true
}

function openPowerOfAttorney(client: Client) {
  rememberFocus()
  target.value = client
  powerOfAttorneyOpen.value = true
}

function openDelete(client: Client) {
  rememberFocus()
  target.value = client
  deleteOpen.value = true
}

async function toggleStatus(client: Client) {
  try {
    await update(client.id, { status: client.status === 'active' ? 'inactive' : 'active' })
    toast.add({ title: client.status === 'active' ? 'Cliente inativado' : 'Cliente ativado', color: 'success' })
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível alterar a situação', color: 'error' })
  }
}

function rowActions(client: Client) {
  const items = [{ label: 'Visualizar', icon: 'i-lucide-eye', onSelect: () => openDetails(client) }]
  if (!canManageClients.value) return items
  return [
    ...items,
    { label: 'Editar', icon: 'i-lucide-pencil', onSelect: () => openEdit(client) },
    { label: 'Certificado A1', icon: 'i-lucide-key-round', onSelect: () => openCertificate(client) },
    { label: 'Procuração e-CAC', icon: 'i-lucide-file-key-2', onSelect: () => openPowerOfAttorney(client) },
    { type: 'separator' as const },
    { label: client.status === 'active' ? 'Inativar' : 'Ativar', icon: 'i-lucide-power', onSelect: () => toggleStatus(client) },
    { label: 'Excluir', icon: 'i-lucide-trash-2', color: 'error' as const, onSelect: () => openDelete(client) }
  ]
}

async function onSaved() {
  target.value = null
  formClient.value = null
  await refresh()
}

async function onDeleted() {
  target.value = null
  await refresh()
}
</script>

<template>
  <UDashboardPanel id="customers">
    <template #header>
      <UDashboardNavbar title="Clientes">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #right>
          <UButton
            v-if="canManageClients"
            label="Novo cliente"
            icon="i-lucide-plus"
            color="primary"
            @click="openCreate"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Buscar por nome, CPF/CNPJ ou email..."
          class="min-w-56 flex-1"
          :disabled="isLoading"
        />

        <USelect
          v-model="statusFilter"
          :items="statusOptions"
          value-key="value"
          placeholder="Situação"
          class="min-w-40"
          :disabled="isLoading"
        />
        <USelect
          v-model="regimeFilter"
          :items="regimeOptions"
          value-key="value"
          placeholder="Regime"
          class="min-w-44"
          :disabled="isLoading"
        />
        <USelect
          v-model="deadlineFilter"
          :items="deadlineOptions"
          value-key="value"
          placeholder="Pendências"
          class="min-w-44"
          :disabled="isLoading"
        />
        <USelect
          v-model="sort"
          :items="sortOptions"
          value-key="value"
          placeholder="Ordenar por"
          class="min-w-40"
          :disabled="isLoading"
        />
        <UButton
          :icon="direction === 'asc' ? 'i-lucide-arrow-up-narrow-wide' : 'i-lucide-arrow-down-wide-narrow'"
          :aria-label="direction === 'asc' ? 'Ordem crescente, alternar para decrescente' : 'Ordem decrescente, alternar para crescente'"
          color="neutral"
          variant="outline"
          :disabled="isLoading"
          @click="direction = direction === 'asc' ? 'desc' : 'asc'"
        />
        <UDropdownMenu :items="columnMenuItems" :content="{ align: 'end' }">
          <UButton
            label="Colunas"
            color="neutral"
            variant="outline"
            trailing-icon="i-lucide-settings-2"
            aria-label="Selecionar colunas visíveis"
            :disabled="isLoading"
          />
        </UDropdownMenu>
      </div>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        title="Não foi possível carregar a carteira"
        description="Verifique sua conexão e tente novamente."
        :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => refresh() }]"
      />

      <template v-else>
        <UTable
          v-model:column-visibility="columnVisibility"
          :data="rows"
          :columns="columns"
          :loading="isLoading"
          :ui="{
            base: 'table-fixed border-separate border-spacing-0',
            thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
            tbody: '[&>tr]:last:[&>td]:border-b-0',
            th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
            td: 'border-b border-default',
            separator: 'h-0'
          }"
        >
          <template #name-cell="{ row }">
            <div class="min-w-0">
              <p class="font-medium text-highlighted truncate">
                {{ row.original.name }}
              </p>
              <p v-if="row.original.trade_name" class="text-sm text-muted truncate">
                {{ row.original.trade_name }}
              </p>
            </div>
          </template>

          <template #tax_id-cell="{ row }">
            <span class="tabular-nums">{{ taxIdLabel(row.original) }}</span>
          </template>

          <template #tax_regime-cell="{ row }">
            {{ row.original.tax_regime ? (regimeLabel[row.original.tax_regime] ?? row.original.tax_regime) : '—' }}
          </template>

          <template #status-cell="{ row }">
            <UBadge
              :color="statusPresentation[row.original.status]?.color ?? 'neutral'"
              :icon="statusPresentation[row.original.status]?.icon"
              variant="subtle"
              :label="statusPresentation[row.original.status]?.label ?? row.original.status"
            />
          </template>

          <template #certificate-cell="{ row }">
            <div class="space-y-0.5">
              <UBadge
                :color="deadlinePresentation[row.original.certificate_status].color"
                :icon="deadlinePresentation[row.original.certificate_status].icon"
                variant="subtle"
                :label="deadlinePresentation[row.original.certificate_status].label"
              />
              <p v-if="row.original.certificate" class="text-xs text-muted tabular-nums">
                até {{ formatDate(row.original.certificate.valid_until) }}
              </p>
            </div>
          </template>

          <template #ecac_power_of_attorney-cell="{ row }">
            <div class="space-y-0.5">
              <UBadge
                :color="deadlinePresentation[row.original.ecac_power_of_attorney_status].color"
                :icon="deadlinePresentation[row.original.ecac_power_of_attorney_status].icon"
                variant="subtle"
                :label="deadlinePresentation[row.original.ecac_power_of_attorney_status].label"
              />
              <p v-if="row.original.ecac_power_of_attorney" class="text-xs text-muted tabular-nums">
                até {{ formatDate(row.original.ecac_power_of_attorney.expires_at) }}
              </p>
            </div>
          </template>

          <template #actions-cell="{ row }">
            <div class="text-right">
              <UDropdownMenu :items="rowActions(row.original)" :content="{ align: 'end' }">
                <UButton
                  icon="i-lucide-ellipsis-vertical"
                  color="neutral"
                  variant="ghost"
                  :aria-label="`Ações para ${row.original.name}`"
                  @click="rememberFocus"
                />
              </UDropdownMenu>
            </div>
          </template>
        </UTable>

        <div
          v-if="!isLoading && meta.total === 0 && !hasActiveFilters"
          class="flex flex-col items-center gap-2 py-12 text-center"
        >
          <UIcon name="i-lucide-users" class="size-8 text-muted" />
          <p class="font-medium text-highlighted">
            Nenhum cliente na carteira
          </p>
          <p class="text-sm text-muted">
            Cadastre o primeiro cliente para começar a gerenciar a carteira.
          </p>
          <UButton
            v-if="canManageClients"
            label="Cadastrar cliente"
            icon="i-lucide-plus"
            color="primary"
            class="mt-2"
            @click="openCreate"
          />
        </div>

        <div
          v-else-if="!isLoading && meta.total === 0"
          class="flex flex-col items-center gap-2 py-12 text-center"
        >
          <UIcon name="i-lucide-search-x" class="size-8 text-muted" />
          <p class="font-medium text-highlighted">
            Nenhum cliente encontrado
          </p>
          <p class="text-sm text-muted">
            Ajuste a busca ou limpe os filtros aplicados.
          </p>
          <UButton
            label="Limpar filtros"
            color="neutral"
            variant="outline"
            class="mt-2"
            @click="clearFilters"
          />
        </div>

        <div v-if="meta.total > 0" class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
          <p class="text-sm text-muted">
            {{ meta.total }} cliente(s) · página {{ meta.current_page }} de {{ meta.last_page }}
          </p>
          <UPagination
            v-model:page="page"
            :items-per-page="meta.per_page"
            :total="meta.total"
          />
        </div>
      </template>
    </template>
  </UDashboardPanel>

  <CustomersClientDetailsSlideover
    v-model:open="detailsOpen"
    :client="target"
    :content="overlayContent"
  />
  <CustomersClientFormSlideover
    v-model:open="formOpen"
    :client="formClient"
    :content="overlayContent"
    @saved="onSaved"
  />
  <CustomersCertificateModal
    v-model:open="certificateOpen"
    :client="target"
    :content="overlayContent"
    @saved="onSaved"
  />
  <CustomersEcacPowerOfAttorneyModal
    v-model:open="powerOfAttorneyOpen"
    :client="target"
    :content="overlayContent"
    @saved="onSaved"
  />
  <CustomersClientDeleteModal
    v-model:open="deleteOpen"
    :client="target"
    :content="overlayContent"
    @deleted="onDeleted"
  />
</template>
