<script setup lang="ts">
const props = withDefaults(defineProps<{
  icon: string
  title: string
  to?: string
  loading?: boolean
  value?: string | number
  valueClass?: string
  /** `brand` keeps the template's green icon. `quiet` is the neutral portfolio tile. */
  tone?: 'brand' | 'quiet'
}>(), {
  loading: false,
  valueClass: 'text-highlighted',
  tone: 'quiet'
})

const cardUi = computed(() => ({
  container: 'gap-y-1.5',
  wrapper: 'items-start',
  leading: props.tone === 'brand'
    ? 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col'
    : 'p-2.5 rounded-full bg-elevated ring ring-inset ring-default flex-col',
  title: props.tone === 'brand'
    ? 'font-normal text-muted text-xs uppercase'
    : 'font-normal text-muted text-sm'
}))
</script>

<template>
  <UPageCard
    :icon="icon"
    :title="title"
    :to="to"
    variant="subtle"
    :ui="cardUi"
    class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1"
  >
    <slot>
      <span class="text-2xl font-semibold tabular-nums" :class="valueClass">
        <USkeleton v-if="loading" class="h-8 w-12" />
        <template v-else>{{ value }}</template>
      </span>
    </slot>
  </UPageCard>
</template>
