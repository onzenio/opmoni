<script setup lang="ts">
import type { AccordionItem } from '@nuxt/ui'
import type { WorkProcess, WorkTask } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const toast = useToast()
const { showProcess } = useWork()

const processId = computed(() => Number(route.params.id))

const { data, status, error, refresh } = await useAsyncData<WorkProcess & { tasks?: WorkTask[] }>(
  'work-processo-detalhe',
  () => showProcess(processId.value),
  { watch: [processId] }
)

const process = computed(() => data.value ?? null)
const isLoading = computed(() => status.value === 'pending')

const tasks = computed<WorkTask[]>(() => {
  const raw = data.value as (WorkProcess & { tasks?: WorkTask[] }) | null
  return [...(raw?.tasks ?? [])].sort((a, b) => {
    if (!a.due_on && !b.due_on) return a.order - b.order
    if (!a.due_on) return 1
    if (!b.due_on) return -1
    return a.due_on === b.due_on ? a.order - b.order : (a.due_on < b.due_on ? -1 : 1)
  })
})

const progress = computed(() => process.value?.progress ?? { total: 0, done: 0, dismissed: 0, open: 0, ratio: 0 })
const progressPercent = computed(() => Math.round(progress.value.ratio * 100))

function statusPresentation(taskStatus: WorkTask['status']): { label: string, color: 'info' | 'warning' | 'success' | 'neutral', icon: string } {
  switch (taskStatus) {
    case 'todo': return { label: 'A fazer', color: 'info', icon: 'i-lucide-circle' }
    case 'doing': return { label: 'Em progresso', color: 'warning', icon: 'i-lucide-loader' }
    case 'done': return { label: 'Concluída', color: 'success', icon: 'i-lucide-circle-check' }
    case 'dismissed': return { label: 'Dispensada', color: 'neutral', icon: 'i-lucide-circle-minus' }
  }
}

function isLocked(index: number): boolean {
  for (let i = 0; i < index; i++) {
    const previous = tasks.value[i]
    if (previous && previous.status !== 'done' && previous.status !== 'dismissed') return true
  }
  return false
}

const timelineItems = computed(() => tasks.value.map(task => ({
  title: task.title,
  description: statusPresentation(task.status).label,
  icon: statusPresentation(task.status).icon,
  date: task.due_on ? new Date(`${task.due_on}T00:00:00`).toLocaleDateString('pt-BR') : undefined
})))

const accordionItems = computed<AccordionItem[]>(() => tasks.value.map((task, index) => ({
  label: task.title,
  value: String(task.id),
  disabled: false,
  task,
  locked: isLocked(index)
})))

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar o processo', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar o processo', color: 'error' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <UButton
        icon="i-lucide-arrow-left"
        color="neutral"
        variant="ghost"
        aria-label="Voltar para processos"
        to="/work/processos"
      />
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-layers" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          {{ process?.name ?? 'Processo' }}
        </h2>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar processo"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar o processo"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && !process" class="flex flex-col gap-3">
      <USkeleton class="h-32 w-full rounded-xl" />
      <USkeleton class="h-64 w-full rounded-xl" />
    </div>

    <template v-else-if="process">
      <UCard variant="subtle" :ui="{ body: 'p-4' }">
        <div class="flex min-w-0 flex-col gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p v-if="process.client?.name" class="min-w-0 flex-1 truncate text-sm text-muted">
              {{ process.client.name }}{{ process.template?.name ? ` · ${process.template.name}` : '' }}
            </p>
            <UBadge
              v-if="process.reference_month"
              color="neutral"
              variant="subtle"
              :label="process.reference_month"
            />
          </div>
          <div class="flex items-center gap-3">
            <UProgress :model-value="progressPercent" class="flex-1" />
            <span class="shrink-0 text-xs font-medium text-muted">
              {{ progress.done }}/{{ progress.total }} ({{ progressPercent }}%)
            </span>
          </div>
          <p v-if="progress.dismissed > 0" class="text-xs text-muted">
            {{ progress.dismissed }} dispensada(s)
          </p>
        </div>
      </UCard>

      <div class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <UCard variant="subtle" :ui="{ body: 'p-4' }">
          <template #header>
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-list-ordered" class="size-4 shrink-0 text-muted" />
              <h3 class="text-sm font-semibold text-highlighted">
                Etapas
              </h3>
            </div>
          </template>
          <UTimeline v-if="timelineItems.length > 0" :items="timelineItems" />
          <UEmpty
            v-else
            icon="i-lucide-list-checks"
            title="Nenhuma etapa"
            description="Este processo ainda não tem tarefas."
            variant="naked"
          />
        </UCard>

        <UCard variant="subtle" :ui="{ body: 'p-4' }">
          <template #header>
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-kanban-square" class="size-4 shrink-0 text-muted" />
              <h3 class="text-sm font-semibold text-highlighted">
                Tarefas
              </h3>
            </div>
          </template>
          <UAccordion v-if="accordionItems.length > 0" :items="accordionItems" type="multiple">
            <template #content="{ item }">
              <div class="flex min-w-0 flex-col gap-2 px-1 pb-1">
                <div class="flex flex-wrap items-center gap-2">
                  <UBadge
                    :color="statusPresentation((item as unknown as { task: WorkTask }).task.status).color"
                    variant="subtle"
                    :label="statusPresentation((item as unknown as { task: WorkTask }).task.status).label"
                  />
                  <UBadge
                    v-if="(item as unknown as { locked: boolean }).locked"
                    color="warning"
                    variant="subtle"
                    label="Bloqueada pela cascata"
                  >
                    <template #leading>
                      <UIcon name="i-lucide-lock" class="size-3" />
                    </template>
                  </UBadge>
                </div>
                <UTooltip
                  v-if="(item as unknown as { locked: boolean }).locked"
                  text="Etapa anterior pendente bloqueia o avanço (cascata)"
                >
                  <p class="flex items-center gap-1.5 text-xs text-muted">
                    <UIcon name="i-lucide-lock" class="size-3.5 shrink-0" />
                    Aguardando conclusão das etapas anteriores.
                  </p>
                </UTooltip>
                <p v-if="(item as unknown as { task: WorkTask }).task.description" class="text-sm text-muted">
                  {{ (item as unknown as { task: WorkTask }).task.description }}
                </p>
                <p class="text-xs text-muted">
                  Vencimento: {{ (item as unknown as { task: WorkTask }).task.due_on ? new Date(`${(item as unknown as { task: WorkTask }).task.due_on}T00:00:00`).toLocaleDateString('pt-BR') : 'sem prazo' }}
                  · Ordem {{ (item as unknown as { task: WorkTask }).task.order }}
                </p>
              </div>
            </template>
          </UAccordion>
          <UEmpty
            v-else
            icon="i-lucide-list-checks"
            title="Nenhuma tarefa"
            description="Este processo ainda não tem tarefas."
            variant="naked"
          />
        </UCard>
      </div>
    </template>
  </div>
</template>
