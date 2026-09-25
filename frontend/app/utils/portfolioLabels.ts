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

export const deadlineStatusAppearance: Record<DeadlineStatus, {
  color: 'neutral' | 'success' | 'warning' | 'error'
  icon: string
  iconClass: string
}> = {
  missing: { color: 'neutral', icon: 'i-lucide-circle-minus', iconClass: 'text-muted' },
  valid: { color: 'success', icon: 'i-lucide-circle-check', iconClass: 'text-success' },
  expiring: { color: 'warning', icon: 'i-lucide-clock-alert', iconClass: 'text-warning' },
  expired: { color: 'error', icon: 'i-lucide-circle-alert', iconClass: 'text-error' }
}

export const deadlinePresentation: Record<DeadlineStatus, {
  label: string
  color: 'neutral' | 'success' | 'warning' | 'error'
  icon: string
}> = {
  missing: { label: 'Não cadastrado', color: deadlineStatusAppearance.missing.color, icon: deadlineStatusAppearance.missing.icon },
  valid: { label: 'Válido', color: deadlineStatusAppearance.valid.color, icon: deadlineStatusAppearance.valid.icon },
  expiring: { label: 'Vence em breve', color: deadlineStatusAppearance.expiring.color, icon: deadlineStatusAppearance.expiring.icon },
  expired: { label: 'Vencido', color: deadlineStatusAppearance.expired.color, icon: deadlineStatusAppearance.expired.icon }
}

const deadlineBadgeLabel: Record<DeadlineStatus, string> = {
  missing: 'Sem cadastro',
  valid: 'Válido',
  expiring: 'A vencer',
  expired: 'Vencido'
}

export function deadlineBadgePresentation(status: DeadlineStatus, formattedDate?: string | null) {
  const { color, icon } = deadlineStatusAppearance[status]
  if (status === 'missing' || !formattedDate) {
    return { color, icon, label: deadlineBadgeLabel[status], title: undefined }
  }

  return {
    color,
    icon,
    label: formattedDate,
    title: `${deadlineBadgeLabel[status]} até ${formattedDate}`
  }
}

export function formatPtCount(value: number) {
  return new Intl.NumberFormat('pt-BR').format(value)
}
