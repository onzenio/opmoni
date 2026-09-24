<script setup lang="ts">
import type { MemberDirectoryEntry, Department, DepartmentColor } from '~/types/team'
import { departmentColorOptions, useDepartments } from '~/composables/useDepartments'
import { useMembers } from '~/composables/useMembers'

definePageMeta({ middleware: 'auth' })

const { list, create, update, remove } = useDepartments()
const { listDirectory } = useMembers()
const { canManageClients } = useAuth()
const toast = useToast()

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

function memberName(id: number) {
  return byId.value.get(id)?.name ?? 'Membro removido'
}

const ordered = computed(() => [...departments.value].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')))

const groups = computed(() => {
  const byLetter = new Map<string, Department[]>()
  for (const department of ordered.value) {
    const key = department.name.trim().charAt(0).toLocaleUpperCase('pt-BR') || '#'
    const list = byLetter.get(key)
    if (list) list.push(department)
    else byLetter.set(key, [department])
  }
  return [...byLetter.entries()]
    .sort(([a], [b]) => a.localeCompare(b, 'pt-BR'))
    .map(([letter, items]) => ({ letter, items }))
})

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

const modalOpen = ref(false)
const editing = ref<Department | null>(null)
const saving = ref(false)
const deleting = ref(false)
const pendingDelete = ref<Department | null>(null)

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
    toast.add({
      title: editing.value ? 'Não foi possível atualizar o departamento' : 'Não foi possível criar o departamento',
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}

function askDelete(department: Department) {
  pendingDelete.value = department
}

const deleteOpen = computed({
  get: () => pendingDelete.value !== null,
  set: (value) => {
    if (!value) pendingDelete.value = null
  }
})

async function confirmDelete() {
  const department = pendingDelete.value
  if (!department || deleting.value) return
  deleting.value = true
  try {
    await remove(department.id)
    pendingDelete.value = null
    toast.add({ title: 'Departamento excluído', color: 'success' })
    await refresh()
  } catch {
    toast.add({ title: 'Não foi possível excluir o departamento', color: 'error' })
  } finally {
    deleting.value = false
  }
}

function memberCountLabel(department: Department) {
  const count = department.members_count ?? departmentMembers(department).length
  return count === 1 ? '1 membro' : `${count} membros`
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 sm:p-6">
    <div v-if="canManageClients" class="flex justify-end">
      <UButton
        label="Novo departamento"
        icon="i-lucide-plus"
        color="primary"
        :disabled="loading"
        @click="openCreate"
      />
    </div>

    <div v-if="loading" class="space-y-2">
      <USkeleton v-for="index in 4" :key="index" class="h-20 w-full rounded-lg" />
    </div>

    <UAlert
      v-else-if="failed || error"
      color="error"
      variant="subtle"
      title="Não foi possível carregar os departamentos"
      description="Verifique sua conexão e tente novamente."
      :actions="[{ label: 'Tentar novamente', color: 'error', variant: 'solid', onClick: () => retry() }]"
    />

    <template v-else>
      <UEmpty
        v-if="!departments.length"
        icon="i-lucide-building-2"
        title="Nenhum departamento"
        description="Crie o primeiro departamento para organizar a equipe."
        variant="naked"
        :actions="canManageClients ? [{ label: 'Criar departamento', icon: 'i-lucide-plus', onClick: openCreate }] : undefined"
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
          <li v-for="department in group.items" :key="department.id" class="bg-default px-3 py-2.5">
            <div class="flex items-center gap-2">
              <UBadge :label="department.name" :color="department.color" variant="subtle" />
              <span class="text-xs text-muted">{{ memberCountLabel(department) }}</span>
              <span class="flex-1" />
              <template v-if="canManageClients">
                <UButton
                  icon="i-lucide-pencil"
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  :aria-label="`Editar ${department.name}`"
                  @click="openEdit(department)"
                />
                <UButton
                  icon="i-lucide-trash"
                  color="error"
                  variant="ghost"
                  size="xs"
                  :aria-label="`Excluir ${department.name}`"
                  @click="askDelete(department)"
                />
              </template>
            </div>
            <div v-if="departmentMembers(department).length" class="mt-2 flex flex-wrap gap-1.5">
              <UChip
                v-for="member in departmentMembers(department)"
                :key="member.id"
                :text="member.name"
              >
                <UAvatar :alt="member.name" size="xs" />
              </UChip>
              <span class="sr-only">
                {{ departmentMembers(department).map(member => memberName(member.id)).join(', ') }}
              </span>
            </div>
            <p v-else class="mt-1.5 text-xs text-muted">
              Sem membros vinculados
            </p>
          </li>
        </ul>
      </section>
    </template>

    <UModal
      v-if="canManageClients"
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
      v-if="canManageClients"
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
