<script setup lang="ts">
import { statusPresentation } from '~/composables/useWorkPresentation'
import type { WorkTaskStatus } from '~/types/work'

const props = withDefaults(defineProps<{
  status: WorkTaskStatus
  locked?: boolean
  loading?: boolean
  disabled?: boolean
}>(), {
  locked: false,
  loading: false,
  disabled: false
})

const emit = defineEmits<{
  change: [status: Exclude<WorkTaskStatus, 'dismissed'>]
  dismiss: []
}>()

const STATUS_VALUES: WorkTaskStatus[] = ['todo', 'doing', 'done', 'dismissed']

const items = computed(() => STATUS_VALUES.map((value) => {
  const presentation = statusPresentation(value)
  const cascadeBlocked = props.locked && value !== 'todo' && value !== props.status
  return {
    label: presentation.label,
    value,
    color: presentation.color,
    disabled: cascadeBlocked
  }
}))

function onUpdate(value: WorkTaskStatus | undefined) {
  if (!value || value === props.status) return
  if (value === 'dismissed') {
    emit('dismiss')
    return
  }
  emit('change', value)
}
</script>

<template>
  <USelect
    :model-value="status"
    :items="items"
    value-key="value"
    label-key="label"
    size="xs"
    variant="soft"
    :loading="loading"
    :disabled="disabled || loading"
    class="min-w-36"
    :ui="{ base: 'w-full' }"
    aria-label="Status da tarefa"
    @update:model-value="onUpdate"
  />
</template>
