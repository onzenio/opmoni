<script setup lang="ts">
const { currentAccount, exitSupport } = useAuth()
const inSupportMode = useSupportMode()
const toast = useToast()

const leaving = ref(false)

async function leave() {
  leaving.value = true
  try {
    await exitSupport()
    window.location.assign('/')
  } catch {
    toast.add({ title: 'Não foi possível sair do modo suporte', color: 'error' })
    leaving.value = false
  }
}
</script>

<template>
  <div v-if="inSupportMode" class="fixed inset-x-0 top-0 z-[60]">
    <UBanner
      color="warning"
      icon="i-lucide-siren"
      :title="`Acesso de suporte em ${currentAccount?.name ?? ''} — ações auditadas`"
      :actions="[{
        label: 'Sair',
        icon: 'i-lucide-log-out',
        color: 'neutral',
        loading: leaving,
        onClick: leave
      }]"
    />
  </div>
</template>
