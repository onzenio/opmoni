<script setup lang="ts">
defineProps<{
  items: { label: string, value: string, to: string, count?: number }[]
  active: string
}>()
</script>

<template>
  <div class="flex min-w-0 flex-wrap gap-1.5">
    <UButton
      v-for="item in items"
      :key="item.value"
      :label="item.label"
      :to="item.to"
      size="sm"
      :color="active === item.value ? 'primary' : 'neutral'"
      :variant="active === item.value ? 'soft' : 'outline'"
      class="w-[calc(50%-0.1875rem)] min-w-0 justify-between"
      :ui="{ label: 'truncate' }"
      :aria-pressed="active === item.value"
    >
      <template v-if="item.count !== undefined" #trailing>
        <UKbd>{{ item.count }}</UKbd>
      </template>
    </UButton>
  </div>
</template>
