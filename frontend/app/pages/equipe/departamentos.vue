<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { MemberDirectoryEntry, Department, DepartmentColor } from '~/types/team'
import { departmentColorOptions, useDepartments } from '~/composables/useDepartments'
import { useMembers } from '~/composables/useMembers'

definePageMeta({ middleware: 'auth' })

const { list, create, update, remove } = useDepartments()
const { listDirectory } = useMembers()
const { canManageDepartments } = useAuth()
const toast = useToast()

const search = ref('')

const { data, status, error, refresh } = await useAsyncData('equipe-departments', async () => {
  const [departments, members] = await Promise.all([list(), listDirectory()])
  return { departments, members }
})

const loading = computed(() => status.value === 'pending')
const failed = ref(false)

watch(error, (value) => {
  if (value) {
    failed.value = true
    toast.add({ title: 'Não foi possível carregar os departamentos', color: 'error' })
  }
})

async function retry() {
  failed.value = false
  clearError()
  await refresh()
}

const departments = computed<Department[]>(() => data.value?.departments ?? [])
const directory = computed<MemberDirectoryEntry[]>(() => data.value?.members ?? [])

const memberOptions = computed(() => directory.value.map(member => ({
  label: member.name,
  value: member.id
})))

const byId = computed(() => new Map(directory.value.map(member => [member.id, member])))

const memberIdsByDepartment = computed(() => {
  const map = new Map<number, number[]>()
  for (const member of directory.value) {
    for (const department of member.departments) {
      const list = map.get(department.id)
      if (list) list.push(member.id)
      else map.set(department.id, [member.id])
    }
  }
  return map
})

function departmentMembers(department: Department) {
  if (department.members?.length) return department.members
  return (memberIdsByDepartment.value.get(department.id) ?? [])
    .map(id => byId.value.get(id))
    .filter((member): member is MemberDirectoryEntry => member !== undefined)
}

const visibleDepartments = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('pt-BR')
  return [...departments.value]
    .filter((department) => {
      if (!term) return true
      return department.name.toLocaleLowerCase('pt-BR').includes(term)
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))
})

const hasActiveFilters = computed(() => !!search.value.trim())

function clearFilters() {
  search.value = ''
}

function memberCountLabel(department: Department) {
  const count = department.members_count ?? departmentMembers(department).length
  return count === 1 ? '1 membro' : `${count} membros`
}

function menuItems(department: Department): DropdownMenuItem[] {
  return [{
    label: 'Editar',
    icon: 'i-lucide-pencil',
    onSelect: () => openEdit(department)
  }, {
    label: 'Excluir',
    icon: 'i-lucide-trash',
    color: 'error',
    onSelect: () => askDelete(department)
  }]
}

const modalOpen = ref(false)
const editing = ref<Department | null>(null)
const saving = ref(false)
const deleting = ref(false)
const pendingDelete = ref<Department | null>(null)
const deleteOpen = computed({
  get: () => pendingDelete.value != null,
  set: (open: boolean) => {
    if (!open) pendingDelete.value = null
  }
})

const formName = ref('')
const formColor = ref<DepartmentColor>('neutral')
const formMemberIds = ref<number[]>([])

const modalTitle = computed(() => editing.value ? 'Editar departamento' : 'Novo departamento')

function openCreate() {
  editing.value = null
  formName.value = ''
  formColor.value = 'neutral'
  formMemberIds.value = []
  modalOpen.value = true
}

function openEdit(department: Department) {
  editing.value = department
  formName.value = department.name
  formColor.value = department.color
  formMemberIds.value = departmentMembers(department).map(member => member.id)
  modalOpen.value = true
}

async function save() {
  const name = formName.value.trim()
  if (!name || saving.value) return
  saving.value = true
  try {
    if (editing.value) {
      await update(editing.value.id, { name, color: formColor.value, member_ids: [...formMemberIds.value] })
      toast.add({ title: 'Departamento atualizado', color: 'success' })
    } else {
      await create({ name, color: formColor.value, member_ids: [...formMemberIds.value] })
      toast.add({ title: 'Departamento criado', color: 'success' })
    }
    modalOpen.value = false
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível salvar o departamento', color: 'error' })
  } finally {
    saving.value = false
  }
}

function askDelete(department: Department) {
  pendingDelete.value = department
}

async function confirmDelete() {
  if (!pendingDelete.value || deleting.value) return
  deleting.value = true
  try {
    await remove(pendingDelete.value.id)
    toast.add({ title: 'Departamento excluído', color: 'success' })
    pendingDelete.value = null
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível excluir o departamento', color: 'error' })
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <UPageCard
      title="Departamentos"
      description="Organize a equipe em áreas como Fiscal e Pessoal para o Work."
      variant="naked"
      orientation="horizontal"
      class="mb-0"
    >
      <UButton
        v-if="canManageDepartments"
        label="Novo departamento"
        icon="i-lucide-plus"
        color="primary"
        class="w-fit lg:ms-auto"
        :disabled="loading"
        @click="openCreate"
      />
    </UPageCard>

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }"
    >
      <template #header>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Buscar departamentos..."
          class="w-full"
          :disabled="loading"
        />
      </template>

      <div v-if="loading" class="space-y-2 p-4">
        <USkeleton v-for="index in 4" :key="index" class="h-14 w-full rounded-lg" />
      </div>

      <UAlert
        v-else-if="failed || error"
        class="m-4"
        color="error"
        variant="subtle"
        title="Não foi possível carregar os departamentos"
        description="Verifique sua conexão e tente novamente."
        :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => retry() }]"
      />

      <template v-else>
        <UEmpty
          v-if="!departments.length"
          class="py-10"
          icon="i-lucide-network"
          title="Nenhum departamento"
          description="Crie o primeiro departamento para organizar a equipe."
          variant="naked"
          :actions="canManageDepartments ? [{ label: 'Criar departamento', icon: 'i-lucide-plus', onClick: openCreate }] : undefined"
        />

        <UEmpty
          v-else-if="!visibleDepartments.length"
          class="py-10"
          icon="i-lucide-search-x"
          title="Nenhum departamento encontrado"
          description="Ajuste a busca ou limpe o filtro."
          variant="naked"
          :actions="hasActiveFilters ? [{ label: 'Limpar busca', color: 'neutral', variant: 'outline', onClick: clearFilters }] : undefined"
        />

        <ul v-else role="list" class="divide-y divide-default">
          <li
            v-for="department in visibleDepartments"
            :key="department.id"
            class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6"
          >
            <div class="flex min-w-0 flex-1 items-center gap-3">
              <UBadge
                :label="department.name"
                :color="department.color"
                variant="subtle"
              />
              <div class="min-w-0">
                <p class="text-xs text-muted">
                  {{ memberCountLabel(department) }}
                </p>
                <div v-if="departmentMembers(department).length" class="mt-1.5">
                  <UAvatarGroup :max="4" size="xs">
                    <UAvatar
                      v-for="member in departmentMembers(department)"
                      :key="member.id"
                      :alt="member.name"
                    />
                  </UAvatarGroup>
                </div>
                <p v-else class="mt-1 text-xs text-muted">
                  Sem membros vinculados
                </p>
              </div>
            </div>

            <UDropdownMenu
              v-if="canManageDepartments"
              :items="menuItems(department)"
              :content="{ align: 'end' }"
            >
              <UButton
                icon="i-lucide-ellipsis-vertical"
                color="neutral"
                variant="ghost"
                :aria-label="`Ações de ${department.name}`"
              />
            </UDropdownMenu>
          </li>
        </ul>
      </template>
    </UPageCard>

    <UModal
      v-if="canManageDepartments"
      v-model:open="modalOpen"
      :title="modalTitle"
      description="O nome vale para toda a conta. Vincule os membros deste departamento."
    >
      <template #body>
        <form id="department-form" class="space-y-4" @submit.prevent="save">
          <UFormField label="Nome" name="name" required>
            <UInput
              v-model="formName"
              class="w-full"
              maxlength="40"
              placeholder="Ex.: Fiscal"
              autofocus
            />
          </UFormField>
          <UFormField label="Cor" name="color">
            <div class="flex flex-wrap gap-1.5">
              <UButton
                v-for="option in departmentColorOptions"
                :key="option.value"
                :label="option.label"
                :color="option.value"
                :variant="formColor === option.value ? 'solid' : 'outline'"
                size="xs"
                type="button"
                :aria-pressed="formColor === option.value"
                @click="formColor = option.value"
              />
            </div>
          </UFormField>
          <UFormField label="Membros" name="member_ids" description="Selecione um ou mais membros.">
            <USelectMenu
              v-model="formMemberIds"
              :items="memberOptions"
              value-key="value"
              label-key="label"
              multiple
              placeholder="Selecionar membros"
              class="w-full"
            />
          </UFormField>
        </form>
      </template>
      <template #footer="{ close }">
        <UButton
          label="Cancelar"
          color="neutral"
          variant="outline"
          @click="close"
        />
        <UButton
          type="submit"
          form="department-form"
          :label="editing ? 'Salvar' : 'Criar'"
          :loading="saving"
          :disabled="!formName.trim()"
        />
      </template>
    </UModal>

    <UModal
      v-if="canManageDepartments"
      v-model:open="deleteOpen"
      title="Excluir departamento"
      :description="pendingDelete ? `Excluir ${pendingDelete.name} e remover os vínculos com os membros. Essa ação não pode ser desfeita.` : 'Excluir departamento.'"
    >
      <template #footer="{ close }">
        <UButton
          label="Cancelar"
          color="neutral"
          variant="outline"
          @click="close"
        />
        <UButton
          label="Excluir"
          color="error"
          :loading="deleting"
          @click="confirmDelete"
        />
      </template>
    </UModal>
  </div>
</template>
