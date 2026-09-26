<script setup lang="ts">
import { statusPresentation } from '~/composables/useWorkPresentation'
import type { WorkTaskStatus } from '~/types/work'
import {
  derivedProcessStatusPresentation,
  type DerivedProcessStatus
} from '~/utils/workDerivedStatus'

const props = withDefaults(defineProps<{
  derived: DerivedProcessStatus
  canManage?: boolean
  loading?: boolean
  disabled?: boolean
}>(), {
  canManage: false,
  loading: false,
  disabled: false
})

const emit = defineEmits<{
  change: [status: Exclude<WorkTaskStatus, 'dismissed'>]
  dismiss: []
}>()

const presentation = computed(() => derivedProcessStatusPresentation(props.derived))

const STATUS_VALUES: WorkTaskStatus[] = ['todo', 'doing', 'done', 'dismissed']

const items = computed(() => STATUS_VALUES.map((value) => {
  const item = statusPresentation(value)
  return {
    label: item.label,
    value,
    color: item.color
  }
}))

function onUpdate(value: WorkTaskStatus | undefined) {
  if (!value) return
  if (value === 'dismissed') {
    emit('dismiss')
    return
  }
  emit('change', value)
}
</script>

<template>
  <UBadge
    v-if="!canManage || derived === 'empty'"
    :color="presentation.color"
    variant="subtle"
    :label="presentation.label"
  />
  <USelect
    v-else
    :model-value="undefined"
    :items="items"
    value-key="value"
    label-key="label"
    size="xs"
    variant="soft"
    :color="presentation.color"
    :loading="loading"
    :disabled="disabled || loading"
    :placeholder="presentation.label"
    class="min-w-36"
    :ui="{ base: 'w-full' }"
    aria-label="Status do grupo — aplicar a todas as tarefas"
    @update:model-value="onUpdate"
  />
</template>
