<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

defineProps<{
  collapsed?: boolean
}>()

const { accounts, currentAccount, switchAccount } = useAuth()
const toast = useToast()

async function selectAccount(id: number) {
  if (id === currentAccount.value?.id) {
    return
  }
  try {
    await switchAccount(id)
    window.location.assign('/')
  } catch {
    toast.add({ title: 'Não foi possível trocar de conta', color: 'error' })
  }
}

const items = computed<DropdownMenuItem[][]>(() => [
  accounts.value.map(account => ({
    label: account.name,
    icon: account.id === currentAccount.value?.id ? 'i-lucide-check' : 'i-lucide-building-2',
    onSelect() {
      void selectAccount(account.id)
    }
  }))
])
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'center', collisionPadding: 12 }"
    :ui="{ content: collapsed ? 'w-40' : 'w-(--reka-dropdown-menu-trigger-width)' }"
  >
    <UButton
      icon="i-lucide-building-2"
      :label="collapsed ? undefined : (currentAccount?.name ?? 'Contas')"
      trailing-icon="i-lucide-chevrons-up-down"
      color="neutral"
      variant="ghost"
      block
      :square="collapsed"
      class="data-[state=open]:bg-elevated"
      :class="[!collapsed && 'py-2']"
      :ui="{
        trailingIcon: 'text-dimmed'
      }"
    />
  </UDropdownMenu>
</template>
