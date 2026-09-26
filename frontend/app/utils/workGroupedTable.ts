import type { GroupingOptions } from '@tanstack/table-core'
import { getGroupedRowModel } from '@tanstack/table-core'

/** Shared TanStack grouping options for Nuxt UI "With grouped rows" tables. */
export function workGroupedTableOptions(): GroupingOptions {
  return {
    groupedColumnMode: 'remove',
    getGroupedRowModel: getGroupedRowModel()
  }
}

export function cascadeLabel(cascade: boolean | null | undefined): string {
  return cascade ? 'Cascata: sim' : 'Cascata: não'
}

export function cascadeBadgeColor(cascade: boolean | null | undefined): 'warning' | 'neutral' {
  return cascade ? 'warning' : 'neutral'
}
