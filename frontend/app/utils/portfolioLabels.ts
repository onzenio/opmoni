import type { ClientStatus, DeadlineStatus, TaxRegime } from '~/types/client'

export const taxRegimeLabel: Record<TaxRegime, string> = {
  mei: 'MEI',
  simple_national: 'Simples Nacional',
  presumed_profit: 'Lucro presumido',
  actual_profit: 'Lucro real',
  other: 'Outro',
  not_applicable: 'Não se aplica'
}

export const clientStatusLabel: Record<ClientStatus, string> = {
  active: 'Ativo',
  inactive: 'Inativo'
}

export const deadlineStatusLabel: Record<DeadlineStatus, string> = {
  missing: 'Sem cadastro',
  valid: 'Válido',
  expiring: 'Próximo a vencer',
  expired: 'Vencido'
}

export const deadlinePresentation: Record<DeadlineStatus, {
  label: string
  color: 'neutral' | 'success' | 'warning' | 'error'
  icon: string
}> = {
  missing: { label: 'Não cadastrado', color: 'neutral', icon: 'i-lucide-circle-minus' },
  valid: { label: 'Válido', color: 'success', icon: 'i-lucide-circle-check' },
  expiring: { label: 'Vence em breve', color: 'warning', icon: 'i-lucide-clock-alert' },
  expired: { label: 'Vencido', color: 'error', icon: 'i-lucide-circle-alert' }
}

export function formatPtCount(value: number) {
  return new Intl.NumberFormat('pt-BR').format(value)
}
