<script setup lang="ts">
import {
  monitoringAttentionCount,
  monitoringCompanies,
  monitoringGroups,
  monitoringListPath
} from '~/utils/monitoringNav'

definePageMeta({ middleware: 'auth' })

function formatCount(value: number) {
  return new Intl.NumberFormat('pt-BR').format(value)
}

const firstPage = monitoringGroups[0]?.pages[0]
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-8 overflow-y-auto p-4 sm:p-6">
    <section class="flex flex-col gap-4">
      <div>
        <h2 class="text-lg font-semibold text-highlighted">
          Empresas
        </h2>
        <p class="text-sm text-muted">
          Clientes acompanhados neste painel.
        </p>
      </div>

      <UPageGrid class="lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-px">
        <MetricCard
          icon="i-lucide-building-2"
          title="Na carteira"
          :to="firstPage ? monitoringListPath(firstPage) : undefined"
          :value="formatCount(monitoringCompanies.length)"
        />
      </UPageGrid>
    </section>

    <section v-for="group in monitoringGroups" :key="group.label" class="flex flex-col gap-4">
      <div class="flex items-start gap-3">
        <UIcon :name="group.icon" class="mt-0.5 size-5 text-muted" />
        <div>
          <h2 class="text-lg font-semibold text-highlighted">
            {{ group.label }}
          </h2>
          <p class="text-sm text-muted">
            {{ group.description }}
          </p>
        </div>
      </div>

      <UPageGrid class="lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-px">
        <MetricCard
          v-for="page in group.pages"
          :key="monitoringListPath(page)"
          :icon="group.icon"
          :title="page.label"
          :to="monitoringListPath(page)"
          :value="formatCount(monitoringAttentionCount(page))"
        />
      </UPageGrid>
    </section>
  </div>
</template>
