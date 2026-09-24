<script setup lang="ts">
import type { WorkProcess } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const toast = useToast()
const { listProcesses } = useWork()

const referenceMonth = computed(() => {
  const raw = route.query.reference_month
  return typeof raw === 'string' && /^\d{4}-\d{2}$/.test(raw) ? raw : undefined
})

const { data, status, error, refresh } = await useAsyncData<WorkProcess[]>(
  'work-processos',
  () => listProcesses({ reference_month: referenceMonth.value }),
  { watch: [referenceMonth] }
)

const processes = computed<WorkProcess[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

function progressPercent(process: WorkProcess): number {
  return Math.round((process.progress?.ratio ?? 0) * 100)
}

function formatMonth(value: string | null): string {
  return value ?? '—'
}

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar os processos', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar os processos', color: 'error' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-layers" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          Processos
        </h2>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar processos"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar os processos"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && processes.length === 0" class="flex flex-col gap-3">
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="processes.length === 0"
      icon="i-lucide-layers"
      title="Nenhum processo neste mês"
      description="Os processos gerados a partir dos modelos aparecem aqui com o progresso das tarefas."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />

    <ul v-else class="flex flex-col gap-3">
      <li v-for="process in processes" :key="process.id">
        <NuxtLink :to="`/work/processos/${process.id}`" class="block">
          <UCard variant="subtle" :ui="{ body: 'p-4' }">
            <div class="flex min-w-0 flex-col gap-3">
              <div class="flex min-w-0 flex-wrap items-center gap-2">
                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-highlighted" :title="process.name">
                  {{ process.name }}
                </p>
                <UBadge color="neutral" variant="subtle" :label="formatMonth(process.reference_month)" />
              </div>
              <p v-if="process.client?.name || process.template?.name" class="truncate text-xs text-muted">
                {{ process.client?.name ?? '' }}{{ process.client?.name && process.template?.name ? ' · ' : '' }}{{ process.template?.name ?? '' }}
              </p>
              <div class="flex items-center gap-3">
                <UProgress :model-value="progressPercent(process)" class="flex-1" />
                <span class="shrink-0 text-xs font-medium text-muted">
                  {{ process.progress ? `${process.progress.done}/${process.progress.total}` : '—' }}
                </span>
              </div>
            </div>
          </UCard>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
