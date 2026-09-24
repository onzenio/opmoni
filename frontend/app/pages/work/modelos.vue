<script setup lang="ts">
import type { WorkTemplate } from '~/types/work'

definePageMeta({ middleware: 'auth' })

const toast = useToast()

const { data, status, error, refresh } = await useAsyncData<WorkTemplate[]>(
  'work-modelos',
  async () => []
)

const templates = computed<WorkTemplate[]>(() => data.value ?? [])
const isLoading = computed(() => status.value === 'pending')

async function onRefresh() {
  try {
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível atualizar os modelos', color: 'error' })
  }
}

watch(error, (value) => {
  if (value) toast.add({ title: 'Não foi possível carregar os modelos', color: 'error' })
})
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-3 sm:gap-5 sm:p-4 lg:p-5">
    <header class="flex min-w-0 flex-wrap items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-2.5">
        <UIcon name="i-lucide-shapes" class="size-5 shrink-0 text-primary" />
        <h2 class="truncate text-base font-semibold tracking-tight text-highlighted sm:text-lg">
          Modelos
        </h2>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Atualizar modelos"
        :loading="isLoading"
        @click="onRefresh"
      />
    </header>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Não foi possível carregar os modelos"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => onRefresh() }]"
    />

    <div v-else-if="isLoading && templates.length === 0" class="flex flex-col gap-3">
      <USkeleton v-for="index in 4" :key="index" class="h-24 w-full rounded-xl" />
    </div>

    <UEmpty
      v-else-if="templates.length === 0"
      icon="i-lucide-shapes"
      title="Nenhum modelo cadastrado"
      description="Os modelos definem as rotinas mensais que geram um processo por cliente."
      variant="naked"
      :actions="[{ label: 'Atualizar', icon: 'i-lucide-refresh-cw', onClick: () => onRefresh() }]"
    />
  </div>
</template>
