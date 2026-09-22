<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Client, ClientUpdatePayload, ClientWritePayload, CnpjPreview, CnpjRefreshPreview } from '~/types/client'

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

const isEditing = computed(() => !!props.client)

const { lookupCnpj, create, update, refreshPreview, refreshCnpj } = useClients()
const toast = useToast()

const companySchema = z.object({
  person_type: z.literal('company'),
  tax_id: z.string().min(14, 'Informe um CNPJ válido'),
  status: z.enum(['active', 'inactive']),
  tax_regime: z.enum(['mei', 'simple_national', 'presumed_profit', 'actual_profit', 'other']),
  email: z.email('Email inválido').or(z.literal('')).optional(),
  phone: z.string().max(20, 'Telefone muito longo').optional()
})

const individualSchema = z.object({
  person_type: z.literal('individual'),
  tax_id: z.string().min(11, 'Informe um CPF válido'),
  name: z.string().min(2, 'Informe o nome completo').max(255, 'Nome muito longo'),
  status: z.enum(['active', 'inactive']),
  tax_regime: z.literal('not_applicable'),
  email: z.email('Email inválido').or(z.literal('')).optional(),
  phone: z.string().max(20, 'Telefone muito longo').optional(),
  street_type: z.string().max(40).optional(),
  street: z.string().max(255).optional(),
  address_number: z.string().max(30).optional(),
  address_complement: z.string().max(255).optional(),
  district: z.string().max(255).optional(),
  postal_code: z.string().max(9).optional(),
  city: z.string().max(255).optional(),
  state: z.string().length(2, 'UF deve ter 2 letras').optional()
})

const schema = z.discriminatedUnion('person_type', [companySchema, individualSchema])
type Schema = z.output<typeof schema>

interface ClientFormState {
  person_type: 'company' | 'individual'
  tax_id: string
  name: string
  status: 'active' | 'inactive'
  tax_regime: string
  email: string
  phone: string
  street_type: string
  street: string
  address_number: string
  address_complement: string
  district: string
  postal_code: string
  city: string
  state: string
}

const state = reactive<ClientFormState>({
  person_type: 'company',
  tax_id: '',
  name: '',
  status: 'active',
  tax_regime: 'presumed_profit',
  email: '',
  phone: '',
  street_type: '',
  street: '',
  address_number: '',
  address_complement: '',
  district: '',
  postal_code: '',
  city: '',
  state: ''
})

const step = ref<1 | 2>(1)
const preview = ref<CnpjPreview | null>(null)
const lookingUp = ref(false)
const submitting = ref(false)
const refreshing = ref(false)
const refreshData = ref<CnpjRefreshPreview | null>(null)
const confirmingRefresh = ref(false)

const regimeLocked = computed(() => {
  if (preview.value?.mei) return 'mei' as const
  if (preview.value?.simple_national) return 'simple_national' as const
  if (isEditing.value && (props.client?.tax_regime === 'mei' || props.client?.tax_regime === 'simple_national'))
    return props.client.tax_regime as 'mei' | 'simple_national'
  return null
})

const regimeOptions = computed(() => {
  if (regimeLocked.value === 'mei') return [{ label: 'MEI', value: 'mei' }]
  if (regimeLocked.value === 'simple_national') return [{ label: 'Simples Nacional', value: 'simple_national' }]
  return [
    { label: 'Presumido', value: 'presumed_profit' },
    { label: 'Real', value: 'actual_profit' },
    { label: 'Outro', value: 'other' }
  ]
})

const statusOptions = [
  { label: 'Ativo', value: 'active' },
  { label: 'Inativo', value: 'inactive' }
]

const personTypeOptions = [
  { label: 'Pessoa jurídica (CNPJ)', value: 'company' },
  { label: 'Pessoa física (CPF)', value: 'individual' }
]

const canLookup = computed(() =>
  state.person_type === 'company'
  && typeof state.tax_id === 'string'
  && state.tax_id.replace(/\D/g, '').length === 14
)

function resetForm() {
  state.person_type = 'company'
  state.tax_id = ''
  state.name = ''
  state.status = 'active'
  state.tax_regime = 'presumed_profit'
  state.email = ''
  state.phone = ''
  state.street_type = ''
  state.street = ''
  state.address_number = ''
  state.address_complement = ''
  state.district = ''
  state.postal_code = ''
  state.city = ''
  state.state = ''
  step.value = 1
  preview.value = null
  refreshData.value = null
  confirmingRefresh.value = false
}

watch(() => props.open, (open) => {
  if (!open) {
    resetForm()
    return
  }
  if (props.client) {
    const c = props.client
    state.person_type = c.person_type ?? 'company'
    state.tax_id = c.tax_id ?? ''
    state.name = c.name ?? ''
    state.status = c.status ?? 'active'
    state.tax_regime = c.tax_regime ?? (state.person_type === 'individual' ? 'not_applicable' : 'presumed_profit')
    state.email = c.email ?? ''
    state.phone = c.phone ?? ''
    state.street_type = c.address?.street_type ?? ''
    state.street = c.address?.street ?? ''
    state.address_number = c.address?.number ?? ''
    state.address_complement = c.address?.complement ?? ''
    state.district = c.address?.district ?? ''
    state.postal_code = c.address?.postal_code ?? ''
    state.city = c.address?.city ?? ''
    state.state = c.address?.state ?? ''
    step.value = 2
    preview.value = null
  } else {
    resetForm()
  }
})

watch(() => state.person_type, (type) => {
  if (type === 'individual') {
    state.tax_regime = 'not_applicable'
    step.value = 2
    preview.value = null
  } else {
    state.tax_regime = 'presumed_profit'
    if (!props.client) {
      step.value = 1
      preview.value = null
    }
  }
})

async function onLookup() {
  if (typeof state.tax_id !== 'string') return
  lookingUp.value = true
  try {
    const data = await lookupCnpj(state.tax_id)
    preview.value = data
    const locked = data.mei ? 'mei' : data.simple_national ? 'simple_national' : null
    state.tax_regime = locked ?? 'presumed_profit'
    state.email = data.email ?? state.email
    state.phone = data.phone ?? state.phone
    step.value = 2
    toast.add({ title: 'CNPJ localizado', description: data.name, color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível consultar o CNPJ', description: 'Confira o número e tente novamente.', color: 'error' })
  } finally {
    lookingUp.value = false
  }
}

function toUpdatePayload(data: ClientWritePayload): ClientUpdatePayload {
  if (data.person_type === 'company') {
    return {
      status: data.status,
      tax_regime: data.tax_regime,
      email: data.email,
      phone: data.phone
    }
  }

  return {
    name: data.name,
    status: data.status,
    tax_regime: 'not_applicable',
    email: data.email,
    phone: data.phone,
    street_type: data.street_type,
    street: data.street,
    address_number: data.address_number,
    address_complement: data.address_complement,
    district: data.district,
    postal_code: data.postal_code,
    city: data.city,
    state: data.state
  }
}

async function onSubmit(event: FormSubmitEvent<Schema>) {
  submitting.value = true
  try {
    const saved = props.client
      ? await update(props.client.id, toUpdatePayload(event.data as ClientWritePayload))
      : await create(event.data as ClientWritePayload)
    toast.add({ title: props.client ? 'Cliente atualizado' : 'Cliente cadastrado', color: 'success' })
    emit('saved', saved)
    isOpen.value = false
  } catch {
    toast.add({ title: 'Não foi possível salvar o cliente', color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function onRefreshPreview() {
  if (!props.client) return
  refreshing.value = true
  try {
    refreshData.value = await refreshPreview(props.client.id)
    confirmingRefresh.value = true
  } catch {
    toast.add({ title: 'Não foi possível consultar a Receita', color: 'error' })
  } finally {
    refreshing.value = false
  }
}

async function onConfirmRefresh() {
  if (!props.client) return
  refreshing.value = true
  try {
    const saved = await refreshCnpj(props.client.id)
    toast.add({ title: 'Dados atualizados pela Receita', color: 'success' })
    emit('saved', saved)
    confirmingRefresh.value = false
    refreshData.value = null
    isOpen.value = false
  } catch {
    toast.add({ title: 'Não foi possível aplicar a atualização', color: 'error' })
  } finally {
    refreshing.value = false
  }
}

const refreshEntries = computed(() => {
  if (!refreshData.value) return []
  return Object.entries(refreshData.value.changes).map(([field, change]) => ({ field, ...change as { from: unknown, to: unknown } }))
})
</script>

<template>
  <USlideover
    v-model:open="isOpen"
    :title="isEditing ? 'Editar cliente' : 'Novo cliente'"
    description="Informe o documento e confirme os dados cadastrais"
  >
    <template #body>
      <div class="space-y-4">
        <UFormField label="Tipo de pessoa" name="person_type">
          <USelect
            v-model="state.person_type"
            :items="personTypeOptions"
            value-key="value"
            class="w-full"
            :disabled="isEditing"
          />
        </UFormField>

        <!-- Etapa 1: documento (somente CNPJ novo) -->
        <template v-if="step === 1 && state.person_type === 'company' && !isEditing">
          <UFormField label="CNPJ" name="tax_id" help="Somente números">
            <UInput v-model="state.tax_id" placeholder="00.000.000/0000-00" class="w-full" />
          </UFormField>
          <UButton
            label="Consultar CNPJ"
            icon="i-lucide-search"
            color="primary"
            :loading="lookingUp"
            :disabled="!canLookup"
            @click="onLookup"
          />
        </template>

        <!-- Etapa 2: formulário -->
        <template v-else>
          <UAlert
            v-if="preview"
            color="success"
            variant="subtle"
            :title="preview.name"
            :description="`${preview.trade_name ?? ''} · ${preview.registration_status ?? 'Situação desconhecida'} · ${preview.primary_activity_description ?? ''}`.trim()"
          />

          <UCard v-if="preview" variant="subtle">
            <template #header>
              <span class="text-sm font-medium">Dados da Receita</span>
            </template>
            <dl class="space-y-1 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Razão social
                </dt>
                <dd class="text-right font-medium">
                  {{ preview.name }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Fantasia
                </dt>
                <dd class="text-right">
                  {{ preview.trade_name ?? '—' }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Situação
                </dt>
                <dd class="text-right">
                  {{ preview.registration_status ?? '—' }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Atividade
                </dt>
                <dd class="text-right">
                  {{ preview.primary_activity_description ?? '—' }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Endereço
                </dt>
                <dd class="text-right">
                  {{ [preview.street, preview.address_number, preview.city, preview.state].filter(Boolean).join(', ') || '—' }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-muted">
                  Atualizado em
                </dt>
                <dd class="text-right">
                  {{ preview.looked_up_at }}
                </dd>
              </div>
            </dl>
          </UCard>

          <UForm
            :schema="schema"
            :state="state as unknown as Partial<Schema>"
            class="space-y-4"
            @submit="onSubmit"
          >
            <UFormField
              v-if="isEditing"
              label="Documento"
              name="tax_id"
            >
              <UInput v-model="state.tax_id" class="w-full" disabled />
            </UFormField>

            <UFormField
              v-if="state.person_type === 'individual'"
              label="Nome completo"
              name="name"
            >
              <UInput v-model="state.name" placeholder="Nome do cliente" class="w-full" />
            </UFormField>

            <div class="grid grid-cols-2 gap-4">
              <UFormField label="Situação" name="status">
                <USelect
                  v-model="state.status"
                  :items="statusOptions"
                  value-key="value"
                  class="w-full"
                />
              </UFormField>
              <UFormField
                v-if="state.person_type === 'company'"
                label="Regime tributário"
                name="tax_regime"
                :help="regimeLocked ? 'Definido pela Receita (MEI/Simples)' : undefined"
              >
                <USelect
                  v-model="state.tax_regime"
                  :items="regimeOptions"
                  value-key="value"
                  class="w-full"
                  :disabled="!!regimeLocked"
                />
              </UFormField>
              <UFormField
                v-else
                label="Regime tributário"
                name="tax_regime"
              >
                <UInput value="Não aplicável" class="w-full" disabled />
              </UFormField>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <UFormField label="Email" name="email">
                <UInput
                  v-model="state.email"
                  type="email"
                  placeholder="contato@empresa.com"
                  class="w-full"
                />
              </UFormField>
              <UFormField label="Telefone" name="phone">
                <UInput v-model="state.phone" placeholder="(00) 00000-0000" class="w-full" />
              </UFormField>
            </div>

            <template v-if="state.person_type === 'individual'">
              <div class="grid grid-cols-3 gap-4">
                <UFormField label="Tipo logradouro" name="street_type">
                  <UInput v-model="state.street_type" class="w-full" />
                </UFormField>
                <UFormField label="Logradouro" name="street" class="col-span-2">
                  <UInput v-model="state.street" class="w-full" />
                </UFormField>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <UFormField label="Número" name="address_number">
                  <UInput v-model="state.address_number" class="w-full" />
                </UFormField>
                <UFormField label="Complemento" name="address_complement">
                  <UInput v-model="state.address_complement" class="w-full" />
                </UFormField>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <UFormField label="Bairro" name="district">
                  <UInput v-model="state.district" class="w-full" />
                </UFormField>
                <UFormField label="CEP" name="postal_code">
                  <UInput v-model="state.postal_code" class="w-full" />
                </UFormField>
              </div>
              <div class="grid grid-cols-3 gap-4">
                <UFormField label="Cidade" name="city" class="col-span-2">
                  <UInput v-model="state.city" class="w-full" />
                </UFormField>
                <UFormField label="UF" name="state">
                  <UInput v-model="state.state" maxlength="2" class="w-full" />
                </UFormField>
              </div>
            </template>

            <div class="flex justify-end gap-2 pt-2">
              <UButton
                v-if="!isEditing && state.person_type === 'company'"
                label="Voltar"
                color="neutral"
                variant="subtle"
                type="button"
                @click="step = 1"
              />
              <UButton
                :label="isEditing ? 'Salvar alterações' : 'Cadastrar cliente'"
                color="primary"
                variant="solid"
                type="submit"
                :loading="submitting"
              />
            </div>
          </UForm>

          <!-- Refresh confirmado (edição de CNPJ) -->
          <template v-if="isEditing && props.client?.person_type === 'company'">
            <USeparator />
            <div class="space-y-2">
              <UButton
                label="Atualizar pela Receita"
                icon="i-lucide-refresh-cw"
                color="neutral"
                variant="outline"
                type="button"
                :loading="refreshing"
                @click="onRefreshPreview"
              />
              <UAlert
                v-if="confirmingRefresh && refreshData"
                color="warning"
                variant="subtle"
                title="Alterações encontradas na Receita"
                description="Confira abaixo e confirme para aplicar."
              />
              <ul v-if="confirmingRefresh && refreshEntries.length" class="space-y-1 text-sm">
                <li v-for="entry in refreshEntries" :key="entry.field" class="flex justify-between gap-4">
                  <span class="text-muted">{{ entry.field }}</span>
                  <span>{{ String(entry.from ?? '—') }} → {{ String(entry.to ?? '—') }}</span>
                </li>
              </ul>
              <p v-if="confirmingRefresh && !refreshEntries.length" class="text-sm text-muted">
                Nenhuma alteração encontrada.
              </p>
              <div v-if="confirmingRefresh" class="flex justify-end gap-2">
                <UButton
                  label="Descartar"
                  color="neutral"
                  variant="subtle"
                  type="button"
                  @click="confirmingRefresh = false; refreshData = null"
                />
                <UButton
                  label="Confirmar atualização"
                  color="warning"
                  variant="solid"
                  type="button"
                  :loading="refreshing"
                  :disabled="!refreshEntries.length"
                  @click="onConfirmRefresh"
                />
              </div>
            </div>
          </template>
        </template>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          label="Fechar"
          color="neutral"
          variant="subtle"
          type="button"
          @click="isOpen = false"
        />
      </div>
    </template>
  </USlideover>
</template>
