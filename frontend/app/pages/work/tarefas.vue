<script setup lang="ts">
import type { WorkTask } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const toast = useToast()

const { data, status, error, refresh } = await useAsyncData<WorkTask[]>(
  'work-tarefas',
  async () => []
)

const tasks = computed<WorkTask[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar as tarefas', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar as tarefas', color: 'error' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-kanban-square" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          Tarefas
        </h2>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar tarefas"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar as tarefas"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && tasks.length === 0" class="flex flex-col gap-3">
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="tasks.length === 0"
      icon="i-lucide-kanban-square"
      title="Nenhuma tarefa por aqui"
      description="As tarefas das rotinas aparecem aqui organizadas em A fazer, Em progresso, Concluída e Dispensada."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />
  </div>
</template>
