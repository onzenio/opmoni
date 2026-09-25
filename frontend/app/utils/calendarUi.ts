import type { WorkTaskPriority } from '~/types/work'

export interface CalendarFilterSelection {
  processId: number | null
  clientId: number | null
  assigneeId: number | null
  department: string
  priority: string
}

export const calendarPriorityOptions: { label: string, value: WorkTaskPriority }[] = [
  { label: 'Baixa', value: 'low' },
  { label: 'Média', value: 'medium' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' }
]

export function countActiveCalendarFilters(selection: CalendarFilterSelection) {
  return [
    selection.processId,
    selection.clientId,
    selection.assigneeId,
    selection.department.trim(),
    selection.priority
  ].filter(Boolean).length
}

export function accessibleDateLabel(dateKey: string, locale = 'pt-BR') {
  const date = new Date(`${dateKey}T00:00:00`)
  if (Number.isNaN(date.getTime())) return dateKey

  return new Intl.DateTimeFormat(locale, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}
