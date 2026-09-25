<script setup lang="ts">
import type { WorkTask } from '~/types/work'
import { statusPresentation } from '~/utils/workCalendar'

const props = defineProps<{
  task: WorkTask
  canManage: boolean
  busy?: boolean
  memberOptions: { label: string, value: number }[]
  showClose?: boolean
  showTitle?: boolean
}>()

const emit = defineEmits<{
  'advance': [task: WorkTask]
  'back': [task: WorkTask]
  'assign': [task: WorkTask, memberId: number | null]
  'request-dismiss': [task: WorkTask]
  'close': []
}>()

const canAdvance = computed(() => props.task.status === 'todo' || props.task.status === 'doing')
const canBack = computed(() => props.task.status === 'doing' || props.task.status === 'done')
const canDismiss = computed(() => props.task.status !== 'dismissed')

function formatDue(due: string | null) {
  if (!due) return 'Sem prazo'
  return new Date(`${due}T00:00:00`).toLocaleDateString('pt-BR')
}
</script>

<template>
  <div class="flex flex-col gap-4 p-4">
    <div class="min-w-0">
      <h2 v-if="showTitle !== false" class="truncate text-base font-semibold text-highlighted">
        {{ task.title }}
      </h2>
      <p class="mt-1 truncate text-sm text-muted">
        {{ task.process?.client?.name ?? 'Sem cliente' }} · {{ task.process?.name ?? 'Processo' }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <UBadge
        :label="statusPresentation(task.status).label"
        :color="statusPresentation(task.status).color"
        variant="subtle"
      />
      <UBadge :label="task.priority" color="neutral" variant="outline" />
    </div>

    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 text-sm">
      <dt class="text-muted">
        Departamento
      </dt>
      <dd class="truncate text-right text-highlighted">
        {{ task.department || '—' }}
      </dd>
      <dt class="text-muted">
        Vencimento
      </dt>
      <dd class="text-right text-highlighted">
        {{ formatDue(task.due_on) }}
      </dd>
    </dl>

    <UFormField v-if="canManage" label="Responsável">
      <USelectMenu
        :model-value="task.assignee_member_id"
        :items="memberOptions"
        value-key="value"
        label-key="label"
        placeholder="Sem responsável"
        clear
        class="w-full"
        :disabled="busy"
        @update:model-value="emit('assign', task, ($event as number | null) ?? null)"
      />
    </UFormField>
    <p v-else class="text-sm text-muted">
      Responsável: <span class="text-highlighted">{{ task.assignee_member_id ?? '—' }}</span>
    </p>

    <NuxtLink
      :to="`/work/processos/${task.process?.id}`"
      class="inline-flex items-center gap-1 text-sm text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <UIcon name="i-lucide-external-link" class="size-3.5" />
      Abrir processo
    </NuxtLink>

    <div v-if="canManage" class="flex flex-wrap items-center gap-2 border-t border-default pt-3">
      <UButton
        v-if="canBack"
        label="Retornar"
        color="neutral"
        variant="outline"
        size="sm"
        :loading="busy"
        @click="emit('back', task)"
      />
      <UButton
        v-if="canAdvance"
        :label="task.status === 'todo' ? 'Avançar' : 'Concluir'"
        color="primary"
        size="sm"
        :loading="busy"
        @click="emit('advance', task)"
      />
      <UButton
        v-if="canDismiss"
        label="Dispensar"
        color="neutral"
        variant="ghost"
        size="sm"
        :disabled="busy"
        @click="emit('request-dismiss', task)"
      />
      <UButton
        v-if="showClose"
        label="Fechar"
        color="neutral"
        variant="soft"
        size="sm"
        class="ms-auto"
        @click="emit('close')"
      />
    </div>
  </div>
</template>
