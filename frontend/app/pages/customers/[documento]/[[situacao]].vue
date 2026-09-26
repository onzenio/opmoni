<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem, TableColumn } from '@nuxt/ui'
import type { ComponentPublicInstance } from 'vue'
import { refDebounced, useInfiniteScroll } from '@vueuse/core'
import type { DataTableFilterColumn, DataTableFilterModel, DataTableFilterOperator } from '~/components/data-table/Filter.vue'
import {
  clientSelectionCount,
  emptyClientSelection,
  headerCheckboxState,
  isClientInSelection,
  withClientSelected,
  type ClientSelection
} from '~/utils/clientSelection'
import type {
  Client,
  ClientListParams,
  ClientSheet,
  ClientStatus,
  ClientPortfolioView,
  DeadlineStatus,
  TaxRegime
} from '~/types/client'
import DataTableSortButton from '~/components/data-table/SortButton.vue'
import { sheetBodyClass, sheetTableUi, sheetToolbarUi } from '~/components/data-table/sheet'
import { formatDate } from '~/utils'
import { customerDetailPath, customerListPath, parseCustomerList } from '~/utils/customerRoutes'
import { pinnedDocumentFilter, singleValueFacets, tagFacets } from '~/utils/portfolioFilters'
import { deadlineStatusAppearance } from '~/utils/portfolioLabels'

const UCheckbox = resolveComponent('UCheckbox')

definePageMeta({ middleware: 'auth' })

const { list, show, portfolioSummary, update, listTags, createSelection } = useClients()
const { canManageClients } = useAuth()
const toast = useToast()

const search = ref('')
const debouncedSearch = refDebounced(search, 350)
const statusFilter = ref<ClientStatus[]>([])
const regimeFilter = ref<TaxRegime[]>([])
const tagFilter = ref<number[]>([])
const certificateFilter = ref<DeadlineStatus[]>([])
const poaFilter = ref<DeadlineStatus[]>([])
const filterOperator = ref<Partial<Record<string, DataTableFilterOperator>>>({})
const route = useRoute()
const listing = computed(() => parseCustomerList(route.params.documento, route.params.situacao))
if (!listing.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada' })
}

watch(listing, (value) => {
  if (!value) {
    showError(createError({ statusCode: 404, statusMessage: 'Página não encontrada' }))
    return
  }
  const pinned = pinnedDocumentFilter(value)
  if (pinned === 'certificate') certificateFilter.value = []
  if (pinned === 'poa') poaFilter.value = []
})

const documentTab = computed(() => listing.value?.document ?? 'certificate')
const documentStatus = computed(() => listing.value?.status ?? 'all')
const view = computed<ClientPortfolioView | 'all'>(() =>
  documentStatus.value === 'all' ? 'all' : `${documentTab.value}_${documentStatus.value}`
)

const sort = ref<ClientListParams['sort']>('name')
const direction = ref<'asc' | 'desc'>('asc')

const columnFilters = computed(() => {
  const pinned = listing.value ? pinnedDocumentFilter(listing.value) : null
  return {
    status: statusFilter.value.length ? statusFilter.value : undefined,
    tax_regime: regimeFilter.value.length ? regimeFilter.value : undefined,
    tag_id: tagFilter.value.length ? tagFilter.value : undefined,
    certificate_status: pinned === 'certificate' || !certificateFilter.value.length ? undefined : certificateFilter.value,
    poa_status: pinned === 'poa' || !poaFilter.value.length ? undefined : poaFilter.value
  }
})

const params = computed<ClientListParams>(() => ({
  sheet: 1,
  q: debouncedSearch.value || undefined,
  ...columnFilters.value,
  view: view.value === 'all' ? undefined : view.value,
  sort: sort.value,
  direction: direction.value
}))

const listKey = computed(() => JSON.stringify([
  route.params.documento,
  route.params.situacao ?? '',
  params.value
]))

const { data, status, error, refresh } = await useAsyncData(
  listKey,
  () => list(params.value)
)

const page = ref(1)
const rows = ref<ClientSheet[]>([])
const matchingTotal = ref(0)
const listMode = ref<'sheet' | 'paged'>('sheet')
const loadingMore = ref(false)
const mobileScrollTop = ref(0)
let listGeneration = 0

watch(data, (value) => {
  listGeneration += 1
  rows.value = value?.data ?? []
  matchingTotal.value = value?.meta?.total ?? rows.value.length
  listMode.value = value?.meta?.mode === 'paged' ? 'paged' : 'sheet'
  page.value = value?.meta?.current_page ?? 1
  mobileScrollTop.value = 0
}, { immediate: true })

async function loadMore() {
  if (listMode.value !== 'paged' || loadingMore.value || status.value === 'pending') return
  if (rows.value.length >= matchingTotal.value) return

  const generation = listGeneration
  const nextPage = page.value + 1
  loadingMore.value = true
  try {
    const response = await list({ ...params.value, page: nextPage })
    if (generation !== listGeneration) return
    if (!response.data.length) {
      matchingTotal.value = rows.value.length
      return
    }
    const seen = new Set(rows.value.map(client => client.id))
    rows.value = [...rows.value, ...response.data.filter(client => !seen.has(client.id))]
    matchingTotal.value = response.meta?.total ?? matchingTotal.value
    page.value = nextPage
  } catch {
    toast.add({ title: 'Não foi possível carregar mais clientes', color: 'error' })
  } finally {
    loadingMore.value = false
  }
}

const table = useTemplateRef<ComponentPublicInstance>('table')
const columnVisibility = ref<Record<string, boolean>>({})

const hideableColumns = [
  { id: 'name', label: 'Nome/Razão social' },
  { id: 'tags', label: 'Tags' },
  { id: 'tax_regime', label: 'Regime' },
  { id: 'status', label: 'Situação' },
  { id: 'certificate', label: 'Cert. A1' },
  { id: 'ecac_power_of_attorney', label: 'e-CAC' }
]

const mobileList = useTemplateRef<HTMLElement>('mobileList')
const desktopTable = useClientMediaQuery('(min-width: 768px)')
const mobileRowStride = 300
const mobileViewport = ref(640)
const mobileWindow = computed(() => {
  const start = Math.max(0, Math.floor(mobileScrollTop.value / mobileRowStride) - 2)
  const count = Math.ceil(mobileViewport.value / mobileRowStride) + 5
  const end = Math.min(rows.value.length, start + count)
  return {
    items: rows.value.slice(start, end),
    offset: start * mobileRowStride,
    height: rows.value.length * mobileRowStride
  }
})

function onMobileScroll(event: Event) {
  const element = event.target
  if (!(element instanceof HTMLElement)) return
  mobileScrollTop.value = element.scrollTop
  mobileViewport.value = element.clientHeight
}

onMounted(() => {
  useInfiniteScroll(
    computed(() => desktopTable.value ? table.value?.$el ?? null : mobileList.value),
    () => loadMore(),
    {
      distance: 200,
      canLoadMore: () => listMode.value === 'paged' && status.value !== 'pending' && !loadingMore.value && rows.value.length < matchingTotal.value
    }
  )
})

const summaryParams = computed(() => ({
  q: debouncedSearch.value || undefined,
  ...columnFilters.value
}))

const { data: summary } = await useAsyncData(
  'client-portfolio-summary',
  () => portfolioSummary(summaryParams.value),
  { watch: [summaryParams] }
)

const total = computed(() => matchingTotal.value)
const isLoading = computed(() => status.value === 'pending')
const hasActiveFilters = computed(() =>
  !!debouncedSearch.value
  || statusFilter.value.length > 0
  || regimeFilter.value.length > 0
  || tagFilter.value.length > 0
  || certificateFilter.value.length > 0
  || poaFilter.value.length > 0
  || view.value !== 'all'
)

const { data: tagCatalog } = await useAsyncData('client-tag-catalog', () => listTags())

const statusOptions = [
  { label: 'Ativo', value: 'active', color: 'success' as const },
  { label: 'Inativo', value: 'inactive', color: 'neutral' as const }
]

const regimeOptions = [
  { label: 'MEI', value: 'mei', color: 'info' as const },
  { label: 'Simples Nacional', value: 'simple_national', color: 'info' as const },
  { label: 'Lucro presumido', value: 'presumed_profit', color: 'info' as const },
  { label: 'Lucro real', value: 'actual_profit', color: 'info' as const },
  { label: 'Outro', value: 'other', color: 'info' as const },
  { label: 'Não aplicável', value: 'not_applicable', color: 'info' as const }
]

const documentStateOptions = [
  { label: 'Sem cadastro', value: 'missing', color: deadlineStatusAppearance.missing.color },
  { label: 'Válido', value: 'valid', color: deadlineStatusAppearance.valid.color },
  { label: 'A vencer', value: 'expiring', color: deadlineStatusAppearance.expiring.color },
  { label: 'Vencido', value: 'expired', color: deadlineStatusAppearance.expired.color }
]

const canFacet = computed(() =>
  listMode.value === 'sheet'
  && status.value === 'success'
  && rows.value.length > 0
  && rows.value.length === matchingTotal.value
)

function facetChoices<T extends { value: string }>(
  choices: readonly T[],
  selected: readonly string[],
  present: readonly string[],
  minimum: number
) {
  if (selected.length > 0 || !canFacet.value) return [...choices]
  const allowed = new Set(present)
  const next = choices.filter(choice => allowed.has(choice.value))
  return next.length < minimum ? null : next
}

const filterColumns = computed(() => {
  const pinned = listing.value ? pinnedDocumentFilter(listing.value) : null
  const columns: DataTableFilterColumn[] = []

  const tags = facetChoices(
    (tagCatalog.value?.data ?? []).map(tag => ({
      label: tag.name,
      value: String(tag.id),
      color: tag.color
    })),
    tagFilter.value.map(String),
    tagFacets(rows.value).map(String),
    1
  )
  if (tags?.length) {
    columns.push({ id: 'tag', label: 'Tags', icon: 'i-lucide-tags', options: tags })
  }

  const regimes = facetChoices(
    regimeOptions,
    regimeFilter.value,
    singleValueFacets(rows.value.map(client => client.tax_regime)),
    2
  )
  if (regimes) {
    columns.push({ id: 'regime', label: 'Regime', icon: 'i-lucide-scale', options: regimes })
  }

  const situations = facetChoices(
    statusOptions,
    statusFilter.value,
    singleValueFacets(rows.value.map(client => client.status)),
    2
  )
  if (situations) {
    columns.push({ id: 'status', label: 'Situação', icon: 'i-lucide-circle-dot', options: situations })
  }

  if (pinned !== 'certificate') {
    const certificates = facetChoices(
      documentStateOptions,
      certificateFilter.value,
      singleValueFacets(rows.value.map(client => client.certificate_status)),
      2
    )
    if (certificates) {
      columns.push({ id: 'certificate', label: 'Cert. A1', icon: 'i-lucide-key-round', options: certificates })
    }
  }

  if (pinned !== 'poa') {
    const powers = facetChoices(
      documentStateOptions,
      poaFilter.value,
      singleValueFacets(rows.value.map(client => client.ecac_power_of_attorney_status)),
      2
    )
    if (powers) {
      columns.push({ id: 'poa', label: 'e-CAC', icon: 'i-lucide-file-key-2', options: powers })
    }
  }

  return columns
})

function optionModel(columnId: string, values: string[]): DataTableFilterModel | undefined {
  if (!values.length) return undefined
  return {
    columnId,
    operator: filterOperator.value[columnId] ?? (values.length > 1 ? 'is any of' : 'is'),
    values
  }
}

const filterModels = computed<DataTableFilterModel[]>(() => {
  const pinned = listing.value ? pinnedDocumentFilter(listing.value) : null
  return [
    optionModel('tag', tagFilter.value.map(String)),
    optionModel('regime', regimeFilter.value),
    optionModel('status', statusFilter.value),
    optionModel('certificate', pinned === 'certificate' ? [] : certificateFilter.value),
    optionModel('poa', pinned === 'poa' ? [] : poaFilter.value)
  ].filter(model => model !== undefined)
})

const activeFilterCount = computed(() => filterModels.value.length)

function onFilters(models: DataTableFilterModel[]) {
  const valuesOf = (id: string) => models.find(filter => filter.columnId === id)?.values ?? []
  tagFilter.value = valuesOf('tag').map(Number)
  regimeFilter.value = valuesOf('regime') as TaxRegime[]
  statusFilter.value = valuesOf('status') as ClientStatus[]
  certificateFilter.value = valuesOf('certificate') as DeadlineStatus[]
  poaFilter.value = valuesOf('poa') as DeadlineStatus[]
  filterOperator.value = Object.fromEntries(models.map(model => [model.columnId, model.operator]))
}

function applySavedFilter(preset: { q: string, filters: DataTableFilterModel[] }) {
  search.value = preset.q
  onFilters(preset.filters)
}

function clearAppliedFilters() {
  onFilters([])
}

function clearSearch() {
  search.value = ''
}

type SortKey = ClientListParams['sort']

function toggleSort(key: SortKey) {
  if (sort.value === key) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = key
    direction.value = 'asc'
  }
}

function sortableHeader(label: string, key: SortKey) {
  return h(DataTableSortButton, {
    label,
    sorted: sort.value === key ? direction.value : false,
    onToggle: () => toggleSort(key)
  })
}

const selection = ref<ClientSelection>(emptyClientSelection())
const snapshotIds = shallowRef(new Set<number>())
const selectingAll = ref(false)
const selectedCount = computed(() => clientSelectionCount(selection.value))

function inSnapshot(id: number) {
  return snapshotIds.value.has(id)
}

function isClientSelected(id: number) {
  return isClientInSelection(selection.value, id, inSnapshot(id))
}

function setClientSelected(id: number, selected: boolean | 'indeterminate') {
  selection.value = withClientSelected(selection.value, id, !!selected, inSnapshot(id))
  if (selection.value.mode === 'explicit' && selection.value.ids.length === 0) snapshotIds.value = new Set()
}

function clearSelection() {
  selection.value = emptyClientSelection()
  snapshotIds.value = new Set()
}

async function selectAllMatching() {
  if (selectingAll.value || matchingTotal.value === 0) return
  selectingAll.value = true
  try {
    const snapshot = await createSelection({
      q: debouncedSearch.value || undefined,
      ...columnFilters.value,
      view: view.value === 'all' ? undefined : view.value
    })
    snapshotIds.value = new Set(snapshot.data.ids)
    selection.value = {
      mode: 'all_matching',
      id: snapshot.data.id,
      total: snapshot.data.count,
      excluded: [],
      included: []
    }
  } catch {
    toast.add({ title: 'Não foi possível selecionar os clientes filtrados', color: 'error' })
  } finally {
    selectingAll.value = false
  }
}

const rowSelection = computed({
  get: () => Object.fromEntries(
    rows.value.filter(client => isClientSelected(client.id)).map(client => [String(client.id), true])
  ),
  set: (value: Record<string, boolean>) => {
    const changes = rows.value.filter(client => !!value[String(client.id)] !== isClientSelected(client.id))
    const [client] = changes
    if (changes.length !== 1 || !client) return
    setClientSelected(client.id, !!value[String(client.id)])
  }
})

const headerState = computed(() => headerCheckboxState(selection.value, matchingTotal.value))

async function onHeaderToggle(value: boolean | 'indeterminate') {
  if (value !== true) {
    clearSelection()
    return
  }
  await selectAllMatching()
}

const tagAssignment = computed(() => {
  const current = selection.value
  if (current.mode === 'all_matching') {
    return {
      selection_id: current.id,
      excluded_ids: [...current.excluded],
      ids: [...current.included]
    }
  }
  return { ids: [...current.ids] }
})

const tagsOpen = ref(false)
const tagsIntent = ref<'selection' | 'catalog'>('selection')
const tagsFocusCreate = ref(false)

function openTags(intent: 'selection' | 'catalog', focusCreate = false) {
  tagsIntent.value = intent
  tagsFocusCreate.value = focusCreate
  tagsOpen.value = true
}

const selectionMenu = computed<DropdownMenuItem[][]>(() => [[
  ...(canManageClients.value
    ? [{
        label: 'Tags',
        icon: 'i-lucide-tags',
        onSelect: () => openTags('selection')
      }]
    : []),
  {
    label: 'Exportar seleção',
    icon: 'i-lucide-file-spreadsheet',
    onSelect: () => exportClients(true)
  },
  {
    label: 'Cancelar seleção',
    icon: 'i-lucide-x',
    onSelect: clearSelection
  }
]])

const tagMenuItems = [[{
  label: 'Criar tag',
  icon: 'i-lucide-plus',
  onSelect: () => openTags('catalog', true)
}, {
  label: 'Gerenciar tags',
  icon: 'i-lucide-library',
  onSelect: () => openTags('catalog')
}]] satisfies DropdownMenuItem[][]

watch([debouncedSearch, statusFilter, regimeFilter, tagFilter, certificateFilter, poaFilter, view], (_value, previous) => {
  if (!previous || selectedCount.value === 0) return
  clearSelection()
})

function regimeText(client: ClientSheet) {
  return client.tax_regime ? (regimeLabel[client.tax_regime] ?? client.tax_regime) : '—'
}

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

function taxIdLabel(client: ClientSheet): string {
  if (!client.tax_id) return 'Não informado'
  return client.person_type === 'individual' ? maskTaxId(client.tax_id) : formatTaxId(client.tax_id)
}

function choiceLabel(options: readonly { label: string, value: string }[], value: string | null | undefined) {
  return options.find(option => option.value === value)?.label ?? ''
}

function csvCell(value: string) {
  return /[;"\n\r]/.test(value) ? `"${value.replaceAll('"', '""')}"` : value
}

function exportClients(onlySelection = false) {
  const source = onlySelection ? rows.value.filter(client => isClientSelected(client.id)) : rows.value
  if (!source.length) {
    toast.add({ title: 'Nada para exportar', color: 'warning' })
    return
  }

  const visible = (id: string) => columnVisibility.value[id] !== false
  const fields: { header: string, value: (client: ClientSheet) => string }[] = []
  if (visible('name')) {
    fields.push(
      { header: 'Nome/Razão social', value: client => client.name },
      { header: 'CPF/CNPJ', value: client => taxIdLabel(client) }
    )
  }
  if (visible('tags')) {
    fields.push({ header: 'Tags', value: client => client.tags?.map(tag => tag.name).join(', ') ?? '' })
  }
  if (visible('tax_regime')) {
    fields.push({ header: 'Regime', value: client => client.tax_regime ? (regimeLabel[client.tax_regime] ?? client.tax_regime) : '' })
  }
  if (visible('status')) {
    fields.push({ header: 'Situação', value: client => statusPresentation[client.status]?.label ?? client.status })
  }
  if (visible('certificate')) {
    fields.push(
      { header: 'Cert. A1', value: client => choiceLabel(documentStateOptions, client.certificate_status) },
      { header: 'Validade do certificado', value: client => client.certificate?.valid_until ? formatDate(client.certificate.valid_until) : '' }
    )
  }
  if (visible('ecac_power_of_attorney')) {
    fields.push(
      { header: 'e-CAC', value: client => choiceLabel(documentStateOptions, client.ecac_power_of_attorney_status) },
      { header: 'Validade da procuração', value: client => client.ecac_power_of_attorney?.expires_at ? formatDate(client.ecac_power_of_attorney.expires_at) : '' }
    )
  }
  if (!fields.length) {
    toast.add({ title: 'Nenhuma coluna visível para exportar', color: 'warning' })
    return
  }

  const lines = [
    fields.map(field => field.header),
    ...source.map(client => fields.map(field => field.value(client)))
  ]
  const body = lines.map(line => line.map(csvCell).join(';')).join('\r\n')
  const blob = new Blob([`\uFEFF${body}`], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `clientes-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)

  if (onlySelection && source.length < selectedCount.value) {
    toast.add({
      title: 'Exportamos os clientes já carregados',
      description: 'A seleção inclui clientes que ainda não estão na tabela.',
      color: 'warning'
    })
    return
  }
  toast.add({ title: onlySelection ? 'Seleção exportada' : 'Tabela exportada', color: 'success' })
}

const columns = computed<TableColumn<ClientSheet>[]>(() => {
  const cols: TableColumn<ClientSheet>[] = []

  if (canManageClients.value) {
    cols.push({
      id: 'select',
      enableHiding: false,
      meta: { class: { th: 'w-10', td: 'w-10' } },
      header: () => h(UCheckbox, {
        'modelValue': headerState.value,
        'disabled': selectingAll.value || matchingTotal.value === 0,
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => {
          void onHeaderToggle(value)
        },
        'ariaLabel': 'Selecionar todos os clientes filtrados'
      }),
      cell: ({ row }) => h(UCheckbox, {
        'modelValue': row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'ariaLabel': `Selecionar ${row.original.name}`
      })
    })
  }

  cols.push(
    {
      accessorKey: 'name',
      header: () => sortableHeader('Nome/Razão social', 'name'),
      meta: { class: { th: 'min-w-52 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      id: 'tags',
      header: 'Tags',
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      accessorKey: 'tax_regime',
      header: () => sortableHeader('Regime', 'tax_regime'),
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      accessorKey: 'status',
      header: () => sortableHeader('Situação', 'status'),
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      id: 'certificate',
      header: () => sortableHeader('Cert. A1', 'certificate'),
      meta: { class: { th: 'min-w-28 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      id: 'ecac_power_of_attorney',
      header: () => sortableHeader('e-CAC', 'poa'),
      meta: { class: { th: 'min-w-24 whitespace-nowrap', td: 'max-w-0' } }
    },
    {
      id: 'actions',
      enableHiding: false,
      meta: { class: { th: 'w-12', td: 'w-12' } }
    }
  )

  return cols
})

const target = shallowRef<Client | null>(null)
const formOpen = ref(false)
const certificateOpen = ref(false)
const powerOfAttorneyOpen = ref(false)
const deleteOpen = ref(false)
const deleteTarget = shallowRef<ClientSheet | null>(null)

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

async function loadClient(id: number) {
  try {
    return await show(id)
  } catch {
    toast.add({ title: 'Não foi possível abrir o cliente', color: 'error' })
    return null
  }
}

function openDetails(client: ClientSheet) {
  return navigateTo(customerDetailPath(client.id))
}

function openCreate() {
  rememberFocus()
  formOpen.value = true
}

const createRequest = useState('customers-create', () => 0)
watch(createRequest, (value, previous) => {
  if (value === previous) return
  openCreate()
})

function openEdit(client: ClientSheet) {
  return navigateTo(customerDetailPath(client.id))
}

async function openCertificate(client: ClientSheet) {
  rememberFocus()
  const full = await loadClient(client.id)
  if (!full) return
  target.value = full
  certificateOpen.value = true
}

async function openPowerOfAttorney(client: ClientSheet) {
  rememberFocus()
  const full = await loadClient(client.id)
  if (!full) return
  target.value = full
  powerOfAttorneyOpen.value = true
}

function openDelete(client: ClientSheet) {
  rememberFocus()
  deleteTarget.value = client
  deleteOpen.value = true
}

async function toggleStatus(client: ClientSheet) {
  try {
    await update(client.id, { status: client.status === 'active' ? 'inactive' : 'active' })
    toast.add({ title: client.status === 'active' ? 'Cliente inativado' : 'Cliente ativado', color: 'success' })
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível alterar a situação', color: 'error' })
  }
}

function rowActions(client: ClientSheet) {
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
  await refresh()
}

async function onDeleted() {
  deleteTarget.value = null
  await refresh()
}

const statusChoices: { label: string, value: DeadlineStatus | 'all', icon: string, iconClass?: string }[] = [
  { label: 'Todos', value: 'all', icon: 'i-lucide-building-2' },
  { label: 'A vencer', value: 'expiring', icon: deadlineStatusAppearance.expiring.icon, iconClass: deadlineStatusAppearance.expiring.iconClass },
  { label: 'Vencido', value: 'expired', icon: deadlineStatusAppearance.expired.icon, iconClass: deadlineStatusAppearance.expired.iconClass },
  { label: 'Válido', value: 'valid', icon: deadlineStatusAppearance.valid.icon, iconClass: deadlineStatusAppearance.valid.iconClass },
  { label: 'Sem cadastro', value: 'missing', icon: deadlineStatusAppearance.missing.icon, iconClass: deadlineStatusAppearance.missing.iconClass }
]

const statusTabs = computed<NavigationMenuItem[][]>(() => {
  const counts = summary.value
  const bucket = counts?.[documentTab.value]

  return [statusChoices.map(item => ({
    label: item.label,
    icon: item.icon,
    iconClass: item.iconClass,
    to: customerListPath(documentTab.value, item.value),
    exact: true,
    active: documentStatus.value === item.value,
    badge: item.value === 'all' ? counts?.total : bucket?.[item.value]
  }))]
})

function statusCount(value: DeadlineStatus | 'all') {
  const counts = summary.value
  if (!counts) return undefined
  return value === 'all' ? counts.total : counts[documentTab.value][value]
}

const mobileStatusItems = computed(() => statusChoices.map(item => ({
  label: item.label,
  value: item.value,
  to: customerListPath(documentTab.value, item.value),
  count: statusCount(item.value)
})))
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <UDashboardToolbar
      class="hidden min-w-0 md:flex"
      :ui="sheetToolbarUi"
    >
      <template #left>
        <div class="flex min-w-0 flex-1 items-center gap-1.5">
          <UNavigationMenu
            :items="statusTabs"
            highlight
            class="-mx-1 min-w-0 flex-1"
            :ui="{ root: 'min-w-0', list: 'min-w-0' }"
          >
            <template #item-leading="{ item }">
              <UIcon
                v-if="item.icon"
                :name="item.icon"
                class="size-5 shrink-0"
                :class="item.iconClass"
              />
            </template>
          </UNavigationMenu>
          <CustomersSavedFilters
            v-if="documentStatus === 'all'"
            :search="search"
            :filters="filterModels"
            @apply="applySavedFilter"
          />
        </div>
      </template>
      <template #right>
        <UDropdownMenu
          v-if="canManageClients"
          :items="tagMenuItems"
          :content="{ align: 'end' }"
        >
          <UButton
            label="Tags"
            icon="i-lucide-tags"
            trailing-icon="i-lucide-chevron-down"
            color="neutral"
            variant="ghost"
            aria-label="Opções de tags"
          />
        </UDropdownMenu>
      </template>
    </UDashboardToolbar>

    <div :class="sheetBodyClass">
      <div class="flex items-start gap-2 md:hidden">
        <DataTableStatusChips class="flex-1" :items="mobileStatusItems" :active="documentStatus" />
        <CustomersSavedFilters
          v-if="documentStatus === 'all'"
          compact
          :search="search"
          :filters="filterModels"
          @apply="applySavedFilter"
        />
        <UDropdownMenu
          v-if="canManageClients"
          :items="tagMenuItems"
          :content="{ align: 'end' }"
        >
          <UButton
            icon="i-lucide-tags"
            color="neutral"
            variant="outline"
            aria-label="Opções de tags"
          />
        </UDropdownMenu>
      </div>

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
          placeholder="Buscar por nome, CPF/CNPJ ou email..."
          class="min-w-0 flex-1"
          :disabled="isLoading"
        />
        <template #trailing>
          <div class="ml-auto flex shrink-0 items-center gap-1.5">
            <UDropdownMenu
              v-if="selectedCount"
              :items="selectionMenu"
              :content="{ align: 'end' }"
            >
              <UButton
                :label="desktopTable ? 'Seleção' : undefined"
                icon="i-lucide-list-checks"
                color="neutral"
                variant="subtle"
                class="shrink-0"
                aria-label="Ações da seleção"
              >
                <template #trailing>
                  <UKbd>{{ selectedCount }}</UKbd>
                </template>
              </UButton>
            </UDropdownMenu>

            <UButton
              :label="desktopTable ? 'Exportar' : undefined"
              icon="i-lucide-file-spreadsheet"
              color="neutral"
              variant="outline"
              class="shrink-0"
              aria-label="Exportar para Excel"
              :disabled="isLoading || !rows.length"
              @click="exportClients(false)"
            />

            <DataTableColumnMenu v-model="columnVisibility" :columns="hideableColumns" />
          </div>
        </template>
      </DataTableFilter>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        title="Não foi possível carregar a carteira"
        description="Verifique sua conexão e tente novamente."
        :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => refresh() }]"
      />

      <template v-else>
        <div v-if="isLoading" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto md:hidden">
          <USkeleton v-for="index in 4" :key="index" class="h-52 w-full rounded-lg" />
        </div>

        <div v-else-if="rows.length" class="flex min-h-0 flex-1 flex-col gap-3 md:hidden">
          <div
            ref="mobileList"
            class="min-h-0 flex-1 overflow-y-auto"
            :class="canManageClients && selectedCount ? 'pb-16' : ''"
            @scroll.passive="onMobileScroll"
          >
            <div class="relative" :style="{ height: `${mobileWindow.height}px` }">
              <div class="absolute inset-x-0 flex flex-col gap-3" :style="{ top: `${mobileWindow.offset}px` }">
                <UCard
                  v-for="client in mobileWindow.items"
                  :key="client.id"
                  variant="subtle"
                  :ui="{ root: 'overflow-visible', body: 'p-4' }"
                >
                  <div class="flex items-start gap-3">
                    <UCheckbox
                      v-if="canManageClients"
                      :model-value="isClientSelected(client.id)"
                      size="lg"
                      class="mt-0.5"
                      :ui="{ base: 'rounded-full' }"
                      :aria-label="`Selecionar ${client.name}`"
                      @update:model-value="setClientSelected(client.id, $event)"
                    />
                    <DataTableIdentity
                      class="flex-1"
                      :title="client.name"
                      :meta="taxIdLabel(client)"
                      :truncate="false"
                    >
                      <CustomersClientTags class="mt-1" :tags="client.tags" />
                    </DataTableIdentity>
                    <UDropdownMenu :items="rowActions(client)" :content="{ align: 'end' }">
                      <UButton
                        icon="i-lucide-ellipsis-vertical"
                        color="neutral"
                        variant="ghost"
                        :aria-label="`Ações para ${client.name}`"
                        @click="rememberFocus"
                      />
                    </UDropdownMenu>
                  </div>

                  <div class="mt-3 flex items-center justify-between gap-3">
                    <UBadge
                      color="neutral"
                      variant="subtle"
                      class="min-w-0"
                      :label="regimeText(client)"
                      :ui="{ base: 'min-w-0 max-w-[70%] whitespace-normal', label: 'whitespace-normal text-left' }"
                    />
                    <UBadge
                      class="shrink-0"
                      :color="statusPresentation[client.status]?.color ?? 'neutral'"
                      :icon="statusPresentation[client.status]?.icon"
                      variant="subtle"
                      :label="statusPresentation[client.status]?.label ?? client.status"
                    />
                  </div>

                  <USeparator class="my-3" />

                  <UCollapsible default-open>
                    <template #default="{ open }">
                      <UButton
                        color="neutral"
                        variant="ghost"
                        class="w-full px-1"
                        :trailing-icon="open ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                        :aria-label="open ? 'Recolher certificado A1 e e-CAC' : 'Expandir certificado A1 e e-CAC'"
                      >
                        <span class="flex min-w-0 flex-1 items-center gap-2 text-left">
                          <UIcon name="i-lucide-badge-check" class="size-4 shrink-0 text-muted" />
                          <span class="break-words">Certificado A1 e e-CAC</span>
                        </span>
                      </UButton>
                    </template>
                    <template #content>
                      <div class="mt-2 flex flex-col gap-2.5 rounded-lg bg-default p-3 ring ring-default">
                        <div class="flex items-center justify-between gap-3">
                          <p class="text-sm text-muted">
                            Certificado A1
                          </p>
                          <CustomersDocumentStatus
                            :status="client.certificate_status"
                            :value="client.certificate?.valid_until"
                            kind="certificate"
                            :actionable="canManageClients"
                            @action="openCertificate(client)"
                          />
                        </div>
                        <div class="flex items-center justify-between gap-3">
                          <p class="text-sm text-muted">
                            e-CAC
                          </p>
                          <CustomersDocumentStatus
                            :status="client.ecac_power_of_attorney_status"
                            :value="client.ecac_power_of_attorney?.expires_at"
                            kind="poa"
                            :actionable="canManageClients"
                            @action="openPowerOfAttorney(client)"
                          />
                        </div>
                      </div>
                    </template>
                  </UCollapsible>
                </UCard>
              </div>
            </div>
          </div>
        </div>

        <div
          class="hidden min-h-0 min-w-0 flex-1 flex-col md:flex"
          :class="canManageClients && selectedCount ? 'pb-16' : ''"
        >
          <UTable
            ref="table"
            v-model:row-selection="rowSelection"
            v-model:column-visibility="columnVisibility"
            sticky
            :virtualize="{ estimateSize: 53, overscan: 16 }"
            :watch-options="{ deep: false }"
            :get-row-id="(row: ClientSheet) => String(row.id)"
            :data="rows"
            :columns="columns"
            :loading="isLoading || loadingMore"
            class="h-full min-h-0 w-full flex-1"
            :ui="sheetTableUi"
          >
            <template #name-cell="{ row }">
              <DataTableIdentity :title="row.original.name" :meta="taxIdLabel(row.original)" />
            </template>

            <template #tags-cell="{ row }">
              <CustomersClientTags v-if="row.original.tags?.length" collapse :tags="row.original.tags" />
            </template>

            <template #tax_regime-cell="{ row }">
              <span class="block truncate" :title="row.original.tax_regime ? (regimeLabel[row.original.tax_regime] ?? row.original.tax_regime) : undefined">
                {{ row.original.tax_regime ? (regimeLabel[row.original.tax_regime] ?? row.original.tax_regime) : '—' }}
              </span>
            </template>

            <template #status-cell="{ row }">
              <UBadge
                class="max-w-full"
                :color="statusPresentation[row.original.status]?.color ?? 'neutral'"
                :icon="statusPresentation[row.original.status]?.icon"
                variant="subtle"
                :label="statusPresentation[row.original.status]?.label ?? row.original.status"
                :ui="{ base: 'max-w-full', label: 'truncate' }"
              />
            </template>

            <template #certificate-cell="{ row }">
              <CustomersDocumentStatus
                :status="row.original.certificate_status"
                :value="row.original.certificate?.valid_until"
                kind="certificate"
                :actionable="canManageClients"
                @action="openCertificate(row.original)"
              />
            </template>

            <template #ecac_power_of_attorney-cell="{ row }">
              <CustomersDocumentStatus
                :status="row.original.ecac_power_of_attorney_status"
                :value="row.original.ecac_power_of_attorney?.expires_at"
                kind="poa"
                :actionable="canManageClients"
                @action="openPowerOfAttorney(row.original)"
              />
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
        </div>

        <UEmpty
          v-if="!isLoading && total === 0 && !hasActiveFilters"
          icon="i-lucide-users"
          title="Nenhum cliente na carteira"
          description="Cadastre o primeiro cliente para começar a gerenciar a carteira."
          variant="naked"
          :actions="canManageClients ? [{
            label: 'Cadastrar cliente',
            icon: 'i-lucide-plus',
            onClick: openCreate
          }] : undefined"
        />

        <UEmpty
          v-else-if="!isLoading && total === 0"
          icon="i-lucide-search-x"
          title="Nenhum cliente encontrado"
          description="Ajuste a busca ou limpe os filtros aplicados."
          variant="naked"
          :actions="[
            ...(activeFilterCount ? [{
              label: 'Limpar filtros',
              color: 'neutral' as const,
              variant: 'outline' as const,
              onClick: clearAppliedFilters
            }] : []),
            ...(search ? [{
              label: 'Limpar busca',
              color: 'neutral' as const,
              variant: 'outline' as const,
              onClick: clearSearch
            }] : [])
          ]"
        />
      </template>

      <Transition
        enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <CustomersSelectionBar
          v-if="canManageClients && selectedCount"
          class="absolute bottom-3 left-1/2 z-20 w-max max-w-[calc(100%-1.5rem)] -translate-x-1/2"
          :count="selectedCount"
          :disabled="isLoading || selectingAll"
          @clear="clearSelection"
          @tags="openTags('selection')"
        />
      </Transition>
    </div>

    <CustomersClientCreateModal
      v-model:open="formOpen"
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
    <CustomersTagsModal
      v-if="canManageClients"
      v-model:open="tagsOpen"
      :count="selectedCount"
      :assignment="tagAssignment"
      :intent="tagsIntent"
      :focus-create="tagsFocusCreate"
      @applied="refresh"
      @changed="refresh"
    />

    <CustomersClientDeleteModal
      v-model:open="deleteOpen"
      :client="deleteTarget"
      :content="overlayContent"
      @deleted="onDeleted"
    />
  </div>
</template>
