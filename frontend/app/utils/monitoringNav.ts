import type { NavigationMenuItem } from '@nuxt/ui'

export type MonitoringStatus = 'pending' | 'expiring' | 'expired' | 'regular'
export type MonitoringFamily = 'obligation' | 'installment' | 'fiscal' | 'mailbox'

export interface MonitoringPage {
  label: string
  icon: string
  segments: readonly string[]
  family: MonitoringFamily
}

export interface MonitoringGroup {
  label: string
  icon: string
  description: string
  pages: readonly MonitoringPage[]
}

export interface MonitoringCompany {
  id: number
  name: string
  taxId: string
  competence: string
  dueOn: string
  installments: string
  balance: string
  issuedOn: string
  unread: number
  lastMessage: string
}

export const monitoringStatuses: {
  value: MonitoringStatus | 'all'
  label: string
  icon: string
}[] = [
  { value: 'all', label: 'Todos', icon: 'i-lucide-list' },
  { value: 'pending', label: 'Pendente', icon: 'i-lucide-clock' },
  { value: 'expiring', label: 'A vencer', icon: 'i-lucide-clock-alert' },
  { value: 'expired', label: 'Vencido', icon: 'i-lucide-circle-alert' },
  { value: 'regular', label: 'Regular', icon: 'i-lucide-circle-check' }
]

const statusBySlug = {
  'pendente': 'pending',
  'a-vencer': 'expiring',
  'vencido': 'expired',
  'regular': 'regular'
} as const

const slugByStatus: Record<MonitoringStatus, keyof typeof statusBySlug> = {
  pending: 'pendente',
  expiring: 'a-vencer',
  expired: 'vencido',
  regular: 'regular'
}

const statusCycle: MonitoringStatus[] = ['expired', 'expiring', 'pending', 'regular']

export const monitoringStatusPresentation: Record<MonitoringStatus, {
  label: string
  color: 'warning' | 'error' | 'success'
  icon: string
}> = {
  pending: { label: 'Pendente', color: 'warning', icon: 'i-lucide-clock' },
  expiring: { label: 'A vencer', color: 'warning', icon: 'i-lucide-clock-alert' },
  expired: { label: 'Vencido', color: 'error', icon: 'i-lucide-circle-alert' },
  regular: { label: 'Regular', color: 'success', icon: 'i-lucide-circle-check' }
}

export const monitoringColumns: Record<MonitoringFamily, { id: string, header: string }[]> = {
  obligation: [
    { id: 'name', header: 'Cliente' },
    { id: 'competence', header: 'Competência' },
    { id: 'status', header: 'Situação' },
    { id: 'dueOn', header: 'Vencimento' }
  ],
  installment: [
    { id: 'name', header: 'Cliente' },
    { id: 'agency', header: 'Órgão' },
    { id: 'installments', header: 'Parcelas' },
    { id: 'status', header: 'Situação' },
    { id: 'balance', header: 'Saldo' }
  ],
  fiscal: [
    { id: 'name', header: 'Cliente' },
    { id: 'document', header: 'Documento' },
    { id: 'status', header: 'Situação' },
    { id: 'issuedOn', header: 'Emissão' }
  ],
  mailbox: [
    { id: 'name', header: 'Cliente' },
    { id: 'mailbox', header: 'Caixa' },
    { id: 'unread', header: 'Não lidas' },
    { id: 'lastMessage', header: 'Última mensagem' }
  ]
}

export const monitoringGroups: readonly MonitoringGroup[] = [
  {
    label: 'Simples Nacional',
    icon: 'i-lucide-store',
    description: 'Apuração do Simples e do MEI.',
    pages: [
      { label: 'PGDAS', icon: 'i-lucide-file-spreadsheet', segments: ['simples', 'pgdas'], family: 'obligation' },
      { label: 'PGMEI', icon: 'i-lucide-store', segments: ['simples', 'pgmei'], family: 'obligation' }
    ]
  },
  {
    label: 'DCTFWeb',
    icon: 'i-lucide-file-spreadsheet',
    description: 'Entregas da DCTFWeb.',
    pages: [
      { label: 'DCTFWeb', icon: 'i-lucide-file-chart-column', segments: ['dctfweb'], family: 'obligation' }
    ]
  },
  {
    label: 'FGTS Digital',
    icon: 'i-lucide-landmark',
    description: 'Entregas do FGTS Digital.',
    pages: [
      { label: 'FGTS Digital', icon: 'i-lucide-landmark', segments: ['fgts-digital'], family: 'obligation' }
    ]
  },
  {
    label: 'Parcelamentos',
    icon: 'i-lucide-calendar-clock',
    description: 'Parcelas em aberto por órgão.',
    pages: [
      { label: 'Simples Nacional', icon: 'i-lucide-store', segments: ['parcelamentos', 'simples-nacional'], family: 'installment' },
      { label: 'PGFN', icon: 'i-lucide-scale', segments: ['parcelamentos', 'pgfn'], family: 'installment' },
      { label: 'Receita Federal', icon: 'i-lucide-building-2', segments: ['parcelamentos', 'receita-federal'], family: 'installment' },
      { label: 'Especiais', icon: 'i-lucide-folder-lock', segments: ['parcelamentos', 'especiais'], family: 'installment' }
    ]
  },
  {
    label: 'Situação Fiscal',
    icon: 'i-lucide-shield-check',
    description: 'Relatório, certidões e comprovantes.',
    pages: [
      { label: 'Relatório Fiscal', icon: 'i-lucide-file-text', segments: ['situacao-fiscal', 'relatorio-fiscal'], family: 'fiscal' },
      { label: 'Certidões', icon: 'i-lucide-badge-check', segments: ['situacao-fiscal', 'certidoes'], family: 'fiscal' },
      { label: 'Comprovantes', icon: 'i-lucide-receipt', segments: ['situacao-fiscal', 'comprovantes'], family: 'fiscal' }
    ]
  },
  {
    label: 'Caixas Postais',
    icon: 'i-lucide-mailbox',
    description: 'Mensagens por caixa.',
    pages: [
      { label: 'e-CAC', icon: 'i-lucide-landmark', segments: ['caixas-postais', 'e-cac'], family: 'mailbox' },
      { label: 'FGTS Digital', icon: 'i-lucide-wallet', segments: ['caixas-postais', 'fgts-digital'], family: 'mailbox' },
      { label: 'DET', icon: 'i-lucide-inbox', segments: ['caixas-postais', 'det'], family: 'mailbox' }
    ]
  },
  {
    label: 'Declarações',
    icon: 'i-lucide-files',
    description: 'Obrigações acessórias da carteira.',
    pages: [
      { label: 'PGDAS', icon: 'i-lucide-file-spreadsheet', segments: ['declaracoes', 'pgdas'], family: 'obligation' },
      { label: 'DCTFWeb', icon: 'i-lucide-file-chart-column', segments: ['declaracoes', 'dctfweb'], family: 'obligation' },
      { label: 'FGTS', icon: 'i-lucide-wallet', segments: ['declaracoes', 'fgts'], family: 'obligation' },
      { label: 'DEFIS', icon: 'i-lucide-file-text', segments: ['declaracoes', 'defis'], family: 'obligation' },
      { label: 'DIRF', icon: 'i-lucide-files', segments: ['declaracoes', 'dirf'], family: 'obligation' }
    ]
  }
]

export const monitoringCompanies: readonly MonitoringCompany[] = [
  { id: 1, name: 'Padaria Estrela do Sul Ltda', taxId: '12.345.678/0001-90', competence: '03/2026', dueOn: '20/04/2026', installments: '8/60', balance: 'R$ 12.480,00', issuedOn: '02/03/2026', unread: 3, lastMessage: 'Guia disponível para pagamento' },
  { id: 2, name: 'Oficina Horizonte ME', taxId: '23.456.789/0001-01', competence: '03/2026', dueOn: '20/04/2026', installments: '14/48', balance: 'R$ 6.230,40', issuedOn: '11/02/2026', unread: 0, lastMessage: 'Parcelamento confirmado' },
  { id: 3, name: 'Mercado Bom Dia Ltda', taxId: '34.567.890/0001-12', competence: '02/2026', dueOn: '20/03/2026', installments: '3/24', balance: 'R$ 2.150,00', issuedOn: '18/01/2026', unread: 5, lastMessage: 'Intimação aguardando ciência' },
  { id: 4, name: 'Clínica Vida Plena Ltda', taxId: '45.678.901/0001-23', competence: '03/2026', dueOn: '20/04/2026', installments: '21/36', balance: 'R$ 18.900,00', issuedOn: '09/03/2026', unread: 1, lastMessage: 'Certidão emitida' },
  { id: 5, name: 'Transportes Serra Azul Ltda', taxId: '56.789.012/0001-34', competence: '01/2026', dueOn: '20/02/2026', installments: '40/60', balance: 'R$ 41.220,15', issuedOn: '28/02/2026', unread: 2, lastMessage: 'Mensagem não lida no e-CAC' },
  { id: 6, name: 'Studio Lume ME', taxId: '67.890.123/0001-45', competence: '03/2026', dueOn: '20/04/2026', installments: '1/12', balance: 'R$ 890,00', issuedOn: '04/03/2026', unread: 0, lastMessage: 'Caixa sem mensagens novas' },
  { id: 7, name: 'Construtora Vale Verde Ltda', taxId: '78.901.234/0001-56', competence: '02/2026', dueOn: '20/03/2026', installments: '11/84', balance: 'R$ 73.400,00', issuedOn: '15/01/2026', unread: 4, lastMessage: 'Comprovante de pagamento recebido' },
  { id: 8, name: 'Farmácia Central do Bairro Ltda', taxId: '89.012.345/0001-67', competence: '03/2026', dueOn: '20/04/2026', installments: '6/18', balance: 'R$ 4.760,80', issuedOn: '22/03/2026', unread: 1, lastMessage: 'DEFIS disponível para entrega' },
  { id: 9, name: 'Escola Pequeno Mundo Ltda', taxId: '90.123.456/0001-78', competence: '03/2026', dueOn: '20/04/2026', installments: '27/60', balance: 'R$ 9.340,00', issuedOn: '01/03/2026', unread: 0, lastMessage: 'Situação fiscal regular' },
  { id: 10, name: 'Gráfica Folha Inteira Ltda', taxId: '10.234.567/0001-89', competence: '02/2026', dueOn: '20/03/2026', installments: '9/36', balance: 'R$ 15.010,50', issuedOn: '19/02/2026', unread: 2, lastMessage: 'FGTS Digital com pendência' }
]

const pageOrder = new Map<string, number>()
const pageByKey = new Map<string, { page: MonitoringPage, group: MonitoringGroup }>()

monitoringGroups.forEach((group) => {
  group.pages.forEach((page) => {
    const key = page.segments.join('/')
    pageOrder.set(key, pageOrder.size)
    pageByKey.set(key, { page, group })
  })
})

export function monitoringPageKey(page: MonitoringPage) {
  return page.segments.join('/')
}

export function monitoringListPath(page: MonitoringPage, status: MonitoringStatus | 'all' = 'all') {
  const base = `/monitoring/${monitoringPageKey(page)}`
  if (status === 'all') return base
  return `${base}/${slugByStatus[status]}`
}

export function monitoringStatusFor(company: MonitoringCompany, page: MonitoringPage): MonitoringStatus {
  const pageIndex = pageOrder.get(monitoringPageKey(page)) ?? 0
  return statusCycle[(company.id + pageIndex) % statusCycle.length] ?? 'regular'
}

export function parseMonitoringSlug(slug: unknown) {
  const source = Array.isArray(slug) ? slug : typeof slug === 'string' ? [slug] : []
  const parts = source.filter((part): part is string => typeof part === 'string' && part !== '')
  if (!parts.length) return null

  let status: MonitoringStatus | 'all' = 'all'
  let body = parts
  const last = parts[parts.length - 1]
  if (last && last in statusBySlug) {
    status = statusBySlug[last as keyof typeof statusBySlug]
    body = parts.slice(0, -1)
  }
  if (!body.length) return null

  const match = pageByKey.get(body.join('/'))
  if (!match) return null
  return { ...match, status }
}

function pageActive(path: string, page: MonitoringPage) {
  const base = monitoringListPath(page)
  return path === base || path.startsWith(`${base}/`)
}

export function monitoringSidebarChildren(path: string): NavigationMenuItem[] {
  const items: NavigationMenuItem[] = [{
    label: 'Painel',
    to: '/monitoring',
    exact: true,
    active: path === '/monitoring'
  }]

  for (const group of monitoringGroups) {
    const first = group.pages[0]
    if (!first) continue

    items.push({
      label: group.label,
      to: monitoringListPath(first),
      active: group.pages.some(page => pageActive(path, page))
    })
  }

  return items
}

export function monitoringAttentionCount(page: MonitoringPage) {
  return monitoringCompanies.filter(company => monitoringStatusFor(company, page) !== 'regular').length
}
