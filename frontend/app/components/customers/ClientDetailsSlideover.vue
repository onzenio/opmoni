<script setup lang="ts">
import type { Client, DeadlineStatus } from '~/types/client'

const props = defineProps<{
  open: boolean
  client?: Client | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const isOpen = computed({
  get: () => props.open,
  set: value => emit('update:open', value)
})

const deadlinePresentation: Record<DeadlineStatus, { label: string, color: 'neutral' | 'success' | 'warning' | 'error', icon: string }> = {
  missing: { label: 'Não cadastrado', color: 'neutral', icon: 'i-lucide-circle-minus' },
  valid: { label: 'Válido', color: 'success', icon: 'i-lucide-circle-check' },
  expiring: { label: 'Vence em breve', color: 'warning', icon: 'i-lucide-clock-alert' },
  expired: { label: 'Vencido', color: 'error', icon: 'i-lucide-circle-alert' }
}

const statusLabel: Record<string, string> = {
  active: 'Ativo',
  inactive: 'Inativo'
}

const regimeLabel: Record<string, string> = {
  mei: 'MEI',
  simple_national: 'Simples Nacional',
  presumed_profit: 'Lucro presumido',
  actual_profit: 'Lucro real',
  other: 'Outro',
  not_applicable: 'Não aplicável'
}

const addressLine = computed(() => {
  const a = props.client?.address
  if (!a) return '—'
  return [a.street, a.number, a.district, a.city, a.state, a.postal_code].filter(Boolean).join(', ') || '—'
})
</script>

<template>
  <USlideover
    v-model:open="isOpen"
    title="Detalhes do cliente"
    :description="client?.name ?? 'Informações cadastrais e acessos fiscais'"
  >
    <template #body>
      <div v-if="client" class="space-y-6">
        <section class="space-y-2">
          <h3 class="text-sm font-semibold">
            Cadastro
          </h3>
          <dl class="space-y-1 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Nome / Razão social
              </dt>
              <dd class="text-right font-medium">
                {{ client.name }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Nome fantasia
              </dt>
              <dd class="text-right">
                {{ client.trade_name ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Documento
              </dt>
              <dd class="text-right">
                {{ client.tax_id ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Situação
              </dt>
              <dd class="text-right">
                {{ statusLabel[client.status] ?? client.status }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Regime tributário
              </dt>
              <dd class="text-right">
                {{ client.tax_regime ? (regimeLabel[client.tax_regime] ?? client.tax_regime) : '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Situação Receita
              </dt>
              <dd class="text-right">
                {{ client.registration_status ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Atividade principal
              </dt>
              <dd class="text-right">
                {{ client.primary_activity?.description ?? '—' }}
              </dd>
            </div>
          </dl>
        </section>

        <USeparator />

        <section class="space-y-2">
          <h3 class="text-sm font-semibold">
            Contato e endereço
          </h3>
          <dl class="space-y-1 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Email
              </dt>
              <dd class="text-right">
                {{ client.email ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Telefone
              </dt>
              <dd class="text-right">
                {{ client.phone ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">
                Endereço
              </dt>
              <dd class="text-right">
                {{ addressLine }}
              </dd>
            </div>
          </dl>
        </section>

        <USeparator />

        <section class="space-y-2">
          <h3 class="text-sm font-semibold">
            Acessos fiscais
          </h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm text-muted">Certificado A1</span>
              <UBadge
                :color="deadlinePresentation[client.certificate_status].color"
                variant="subtle"
                :icon="deadlinePresentation[client.certificate_status].icon"
                :label="deadlinePresentation[client.certificate_status].label"
              />
            </div>
            <p v-if="client.certificate" class="text-sm text-muted">
              {{ client.certificate.subject }} · válido até {{ client.certificate.valid_until }}
            </p>
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm text-muted">Procuração e-CAC</span>
              <UBadge
                :color="deadlinePresentation[client.ecac_power_of_attorney_status].color"
                variant="subtle"
                :icon="deadlinePresentation[client.ecac_power_of_attorney_status].icon"
                :label="deadlinePresentation[client.ecac_power_of_attorney_status].label"
              />
            </div>
            <p v-if="client.ecac_power_of_attorney" class="text-sm text-muted">
              Vigência: {{ client.ecac_power_of_attorney.starts_at }} → {{ client.ecac_power_of_attorney.expires_at }}
            </p>
          </div>
        </section>
      </div>
      <p v-else class="text-sm text-muted">
        Nenhum cliente selecionado.
      </p>
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
