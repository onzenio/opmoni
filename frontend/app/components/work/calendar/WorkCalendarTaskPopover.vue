<script setup lang="ts">
import type { WorkTask } from '~/types/work'
import { statusPresentation } from '~/utils/workCalendar'

const props = defineProps<{
  task: WorkTask | null
  open: boolean
  canManage: boolean
  busy?: boolean
  memberOptions: { label: string, value: number }[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'advance': [task: WorkTask]
  'back': [task: WorkTask]
  'assign': [task: WorkTask, memberId: number | null]
  'dismiss': [task: WorkTask, reason: string]
}>()

const dismissOpen = ref(false)
const dismissReason = ref('')

watch(() => props.open, (value) => {
  if (!value) {
    dismissOpen.value = false
    dismissReason.value = ''
  }
})

const canAdvance = computed(() => props.task && (props.task.status === 'todo' || props.task.status === 'doing'))
const canBack = computed(() => props.task && (props.task.status === 'doing' || props.task.status === 'done'))
const canDismiss = computed(() => props.task && props.task.status !== 'dismissed')

function formatDue(due: string | null) {
  if (!due) return 'Sem prazo'
  return new Date(`${due}T00:00:00`).toLocaleDateString('pt-BR')
}

function confirmDismiss() {
  if (!props.task) return
  if (!dismissReason.value.trim()) return
  emit('dismiss', props.task, dismissReason.value.trim())
  dismissOpen.value = false
  dismissReason.value = ''
}
</script>

<template>
  <UModal
    :open="open"
    :title="task?.title ?? 'Tarefa'"
    :description="task ? `${task.process?.client?.name ?? 'Sem cliente'} · ${task.process?.name ?? 'Processo'}` : undefined"
    @update:open="emit('update:open', $event)"
  >
    <template v-if="task" #body>
      <div class="space-y-3 text-sm">
        <div class="flex flex-wrap items-center gap-2">
          <UBadge
            :label="statusPresentation(task.status).label"
            :color="statusPresentation(task.status).color"
            variant="subtle"
          />
          <UBadge :label="task.priority" color="neutral" variant="outline" />
        </div>
        <p class="text-muted">
          Departamento: <span class="text-highlighted">{{ task.department || '—' }}</span>
        </p>
        <p class="text-muted">
          Vencimento: <span class="text-highlighted">{{ formatDue(task.due_on) }}</span>
        </p>
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
        <p v-else class="text-muted">
          Responsável: <span class="text-highlighted">{{ task.assignee_member_id ?? '—' }}</span>
        </p>
        <NuxtLink
          :to="`/work/processos/${task.process?.id}`"
          class="inline-flex items-center gap-1 text-primary hover:underline"
        >
          <UIcon name="i-lucide-external-link" class="size-3.5" />
          Abrir processo
        </NuxtLink>
      </div>
    </template>
    <template v-if="task && canManage" #footer="{ close }">
      <div class="flex w-full flex-wrap items-center gap-2">
        <UButton
          v-if="canBack"
          label="Retornar"
          color="neutral"
          variant="outline"
          :loading="busy"
          @click="emit('back', task)"
        />
        <UButton
          v-if="canAdvance"
          :label="task.status === 'todo' ? 'Avançar' : 'Concluir'"
          color="primary"
          :loading="busy"
          @click="emit('advance', task)"
        />
        <UButton
          v-if="canDismiss"
          label="Dispensar"
          color="neutral"
          variant="ghost"
          :disabled="busy"
          @click="dismissOpen = true"
        />
        <span class="flex-1" />
        <UButton
          label="Fechar"
          color="neutral"
          variant="soft"
          @click="close"
        />
      </div>
    </template>
  </UModal>

  <UModal
    v-model:open="dismissOpen"
    title="Dispensar tarefa"
    description="Informe o motivo. Essa ação registra a dispensa na rotina."
  >
    <template #body>
      <UFormField label="Motivo" required>
        <UTextarea
          v-model="dismissReason"
          :rows="3"
          class="w-full"
          autofocus
        />
      </UFormField>
    </template>
    <template #footer="{ close }">
      <UButton
        label="Cancelar"
        color="neutral"
        variant="outline"
        @click="close"
      />
      <UButton
        label="Confirmar"
        color="error"
        :disabled="!dismissReason.trim()"
        :loading="busy"
        @click="confirmDismiss"
      />
    </template>
  </UModal>
</template>
