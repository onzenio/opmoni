<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

definePageMeta({
  middleware: ['auth', 'super-admin'],
  layout: 'admin'
})

interface AdminUser {
  id: number
  name: string
  email: string
  is_super_admin: boolean
  accountLinks?: { role: string, account?: { id: number, name: string } | null }[]
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const { $api } = useNuxtApp()
const toast = useToast()

const users = ref<AdminUser[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)

const columns: TableColumn<AdminUser>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'name', header: 'Nome' },
  { accessorKey: 'email', header: 'Email' },
  { accessorKey: 'type', header: 'Tipo' },
  { accessorKey: 'accounts', header: 'Contas' }
]

async function load() {
  loading.value = true
  try {
    const res = await $api<Paginated<AdminUser>>('/admin/users', { params: { page: page.value } })
    users.value = res.data
    total.value = res.total
  } catch {
    toast.add({ title: 'Não foi possível carregar os usuários', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(page, load)
</script>

<template>
  <UDashboardPanel id="admin-usuarios">
    <template #header>
      <UDashboardNavbar title="Usuários">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UTable
        :data="users"
        :columns="columns"
        :loading="loading"
        class="shrink-0"
      >
        <template #type-cell="{ row }">
          <UBadge v-if="row.original.is_super_admin" color="primary" variant="subtle">
            Super admin
          </UBadge>
          <span v-else class="text-muted">Usuário</span>
        </template>

        <template #accounts-cell="{ row }">
          <div v-if="row.original.accountLinks?.length" class="flex flex-wrap gap-1.5">
            <UBadge
              v-for="link in row.original.accountLinks"
              :key="`${link.account?.id}-${link.role}`"
              color="neutral"
              variant="subtle"
            >
              {{ link.account?.name ?? '—' }} · {{ link.role }}
            </UBadge>
          </div>
          <span v-else class="text-muted">—</span>
        </template>
      </UTable>

      <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
        <div class="text-sm text-muted">
          {{ total }} usuário(s) no total.
        </div>

        <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
      </div>
    </template>
  </UDashboardPanel>
</template>
