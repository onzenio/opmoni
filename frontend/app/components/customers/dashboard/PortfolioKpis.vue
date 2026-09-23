<script setup lang="ts">
import type { ClientPortfolioSummary } from '~/types/client'
import { customerListPath } from '~/utils/customerRoutes'
import { formatPtCount } from '~/utils/portfolioLabels'

defineProps<{
  summary: ClientPortfolioSummary | null
  loading?: boolean
}>()

function attention(summary: ClientPortfolioSummary | null, document: 'certificate' | 'poa') {
  if (!summary) return 0
  return summary[document].expired + summary[document].expiring
}
</script>

<template>
  <UPageGrid class="gap-3 sm:gap-3 lg:grid-cols-4 lg:gap-px">
    <MetricCard
      icon="i-lucide-building-2"
      title="Total"
      to="/customers/certificados"
      :loading="loading"
      :value="formatPtCount(summary?.total ?? 0)"
    />
    <MetricCard
      icon="i-lucide-circle-check"
      title="Ativos"
      to="/customers/certificados"
      :loading="loading"
      :value="formatPtCount(summary?.active ?? 0)"
      value-class="text-success"
    />
    <MetricCard
      icon="i-lucide-key-round"
      title="Certificados"
      :to="customerListPath('certificate', 'expiring')"
      :loading="loading"
      :value="formatPtCount(attention(summary, 'certificate'))"
      :value-class="attention(summary, 'certificate') > 0 ? 'text-warning' : 'text-highlighted'"
    />
    <MetricCard
      icon="i-lucide-file-key-2"
      title="Procurações"
      :to="customerListPath('poa', 'expiring')"
      :loading="loading"
      :value="formatPtCount(attention(summary, 'poa'))"
      :value-class="attention(summary, 'poa') > 0 ? 'text-warning' : 'text-highlighted'"
    />
  </UPageGrid>
</template>
