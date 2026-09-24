<script setup lang="ts">
import type { MemberDirectoryEntry } from '~/types/team'
import { useDepartments } from '~/composables/useDepartments'
import { useMembers } from '~/composables/useMembers'

definePageMeta({ middleware: 'auth' })

const { list } = useDepartments()
const { listDirectory } = useMembers()
const toast = useToast()
const { canManageClients } = useAuth()

const search = ref('')
const departmentFilter = ref<number | null>(null)

const { data, status, error, refresh } = await useAsyncData('equipe-directory', async () => {
  const [members, departments] = await Promise.all([listDirectory(), list()])
  return { members, departments }
})

const loading = computed(() => status.value === 'pending')

const failed = ref(false)

watch(error, (value) => {
  if (value) {
    failed.value = true
    toast.add({ title: 'Não foi possível carregar a equipe', color: 'error' })
  }
})

async function retry() {
  failed.value = false
  clearError()
  await refresh()
}

const members = computed<MemberDirectoryEntry[]>(() => data.value?.members ?? [])

const departmentOptions = computed(() => (data.value?.departments ?? []).map(department => ({
  label: department.name,
  value: department.id
})))

const visibleMembers = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('pt-BR')
  const departmentId = departmentFilter.value
  return members.value.filter((member) => {
    if (departmentId != null && !member.departments.some(department => department.id === departmentId)) return false
    if (!term) return true
    return member.name.toLocaleLowerCase('pt-BR').includes(term)
      || member.role.toLocaleLowerCase('pt-BR').includes(term)
  })
})

const hasActiveFilters = computed(() => !!search.value.trim() || departmentFilter.value != null)

function clearFilters() {
  search.value = ''
  departmentFilter.value = null
}

function groupKey(member: MemberDirectoryEntry) {
  const first = member.name.trim().charAt(0).toLocaleUpperCase('pt-BR')
  return first || '#'
}

const groups = computed(() => {
  const byLetter = new Map<string, MemberDirectoryEntry[]>()
  for (const member of visibleMembers.value) {
    const key = groupKey(member)
    const list = byLetter.get(key)
    if (list) list.push(member)
    else byLetter.set(key, [member])
  }
  return [...byLetter.entries()]
    .sort(([a], [b]) => a.localeCompare(b, 'pt-BR'))
    .map(([letter, items]) => ({
      letter,
      items: [...items].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))
    }))
})

const rolePresentation: Record<string, { label: string, color: 'primary' | 'info' | 'neutral', icon: string }> = {
  admin: { label: 'Admin', color: 'primary', icon: 'i-lucide-shield-check' },
  operador: { label: 'Operador', color: 'info', icon: 'i-lucide-briefcase' },
  user: { label: 'Usuário', color: 'neutral', icon: 'i-lucide-user' }
}

function roleOf(member: MemberDirectoryEntry) {
  return rolePresentation[member.role] ?? { label: member.role, color: 'neutral' as const, icon: 'i-lucide-user' }
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 sm:p-6">
    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Buscar por nome ou papel..."
        class="min-w-0 flex-1"
        :disabled="loading"
      />
      <USelectMenu
        v-model="departmentFilter"
        :items="departmentOptions"
        value-key="value"
        label-key="label"
        placeholder="Filtrar por departamento"
        clear
        class="w-full sm:w-64"
        :disabled="loading"
      />
    </div>

    <div v-if="loading" class="space-y-2">
      <USkeleton v-for="index in 5" :key="index" class="h-14 w-full rounded-lg" />
    </div>

    <UAlert
      v-else-if="failed || error"
      color="error"
      variant="subtle"
      title="Não foi possível carregar a equipe"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => retry() }]"
    />

    <template v-else>
      <UEmpty
        v-if="!members.length"
        icon="i-lucide-users"
        title="Nenhum membro na equipe"
        description="Os membros da conta aparecem aqui automaticamente."
        variant="naked"
      />

      <UEmpty
        v-else-if="!visibleMembers.length"
        icon="i-lucide-search-x"
        title="Nenhum membro encontrado"
        description="Ajuste a busca ou limpe o filtro de departamento."
        variant="naked"
        :actions="hasActiveFilters ? [{ label: 'Limpar filtros', color: 'neutral', variant: 'outline', onClick: clearFilters }] : undefined"
      />

      <section
        v-for="group in groups"
        v-else
        :key="group.letter"
        class="flex flex-col gap-2"
      >
        <h2 class="text-xs font-semibold tracking-wider text-muted uppercase">
          {{ group.letter }}
        </h2>
        <ul class="divide-y divide-default overflow-hidden rounded-lg ring ring-default">
          <li v-for="member in group.items" :key="member.id" class="flex items-center gap-3 bg-default px-3 py-2.5">
            <UAvatar :alt="member.name" size="md" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-highlighted">
                {{ member.name }}
              </p>
              <div v-if="member.departments.length" class="mt-1 flex flex-wrap gap-1">
                <UBadge
                  v-for="department in member.departments"
                  :key="department.id"
                  :label="department.name"
                  :color="department.color"
                  variant="subtle"
                  size="xs"
                />
              </div>
              <p v-else class="mt-0.5 text-xs text-muted">
                Sem departamento
              </p>
            </div>
            <UBadge
              :label="roleOf(member).label"
              :color="roleOf(member).color"
              :icon="roleOf(member).icon"
              variant="subtle"
            />
          </li>
        </ul>
      </section>

      <p v-if="canManageClients && members.length" class="text-xs text-muted">
        Para gerenciar convites e papéis, acesse Configurações.
      </p>
    </template>
  </div>
</template>
