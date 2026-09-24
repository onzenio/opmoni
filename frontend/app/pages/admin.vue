<script setup lang="ts">
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'super-admin']
})
</script>

<template>
  <UDashboardPanel id="admin" :ui="{ body: 'min-h-0 flex-1 gap-0 overflow-hidden p-4 sm:p-6' }">
    <template #body>
      <NuxtPage />
    </template>
  </UDashboardPanel>
</template>
