<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Client } from '~/types/client'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  open: boolean
  client?: Client | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'saved': [client: Client]
}>()

const isOpen = computed({
  get: () => props.open,
  set: value => emit('update:open', value)
})

const { upsertPowerOfAttorney, removePowerOfAttorney } = useClients()
const toast = useToast()

const schema = z.object({
  starts_at: z.string().min(1, 'Informe a data de início').regex(/^\d{4}-\d{2}-\d{2}$/, 'Data inválida (AAAA-MM-DD)'),
  expires_at: z.string().min(1, 'Informe a data de vencimento').regex(/^\d{4}-\d{2}-\d{2}$/, 'Data inválida (AAAA-MM-DD)'),
  notes: z.string().max(2000, 'Observações limitadas a 2000 caracteres').optional()
}).refine(data => data.expires_at >= data.starts_at, {
  message: 'O vencimento deve ser igual ou posterior ao início',
  path: ['expires_at']
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  starts_at: '',
  expires_at: '',
  notes: ''
})

const saving = ref(false)
const removing = ref(false)

watch(() => props.open, (open) => {
  if (!open) return
  const poa = props.client?.ecac_power_of_attorney
  state.starts_at = poa?.starts_at ? poa.starts_at.slice(0, 10) : ''
  state.expires_at = poa?.expires_at ? poa.expires_at.slice(0, 10) : ''
  state.notes = poa?.notes ?? ''
})

async function onSubmit(event: FormSubmitEvent<Schema>) {
  if (!props.client) return
  saving.value = true
  try {
    const saved = await upsertPowerOfAttorney(props.client.id, {
      starts_at: event.data.starts_at,
      expires_at: event.data.expires_at,
      notes: event.data.notes || undefined
    })
    toast.add({ title: 'Procuração registrada', color: 'success' })
    emit('saved', saved)
    isOpen.value = false
  } catch {
    toast.add({ title: 'Não foi possível salvar a procuração', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function onRemove() {
  if (!props.client) return
  removing.value = true
  try {
    await removePowerOfAttorney(props.client.id)
    emit('saved', { ...props.client, ecac_power_of_attorney: null, ecac_power_of_attorney_status: 'missing' })
    toast.add({ title: 'Procuração removida', color: 'success' })
    isOpen.value = false
  } catch {
    toast.add({ title: 'Não foi possível remover a procuração', color: 'error' })
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <UModal
    v-bind="$attrs"
    v-model:open="isOpen"
    title="Procuração e-CAC"
    :description="client ? `Gerenciar procuração de ${client.name}` : 'Gerenciar procuração eletrônica'"
  >
    <template #body>
      <UForm
        id="ecac-poa-form"
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <div class="grid grid-cols-2 gap-4">
          <UFormField label="Início da vigência" name="starts_at">
            <UInput v-model="state.starts_at" type="date" class="w-full" />
          </UFormField>
          <UFormField label="Vencimento" name="expires_at">
            <UInput v-model="state.expires_at" type="date" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="Observações" name="notes" help="Opcional, até 2000 caracteres">
          <UTextarea
            v-model="state.notes"
            :rows="3"
            placeholder="Ex.: procuração outorgada ao escritório"
            class="w-full"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <div class="flex justify-between gap-2">
        <UButton
          v-if="client?.ecac_power_of_attorney"
          label="Remover"
          color="error"
          variant="ghost"
          type="button"
          :loading="removing"
          @click="onRemove"
        />
        <span v-else />
        <div class="flex gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            type="button"
            @click="isOpen = false"
          />
          <UButton
            label="Salvar procuração"
            color="primary"
            variant="solid"
            type="submit"
            form="ecac-poa-form"
            :loading="saving"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>
