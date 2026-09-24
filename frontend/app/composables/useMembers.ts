import type { MemberDirectoryEntry } from '~/types/team'

export type AccountMemberRole = 'admin' | 'operador' | 'user'

export interface AccountMember {
  id: number
  name: string
  email: string
  role: AccountMemberRole
}

export interface AccountMemberCreatePayload {
  name: string
  email: string
  password: string
  role: AccountMemberRole
}

export interface AccountMemberUpdatePayload {
  role: AccountMemberRole
}

export function useMembers() {
  const { $api } = useNuxtApp()

  function queryOf(params: object) {
    return Object.fromEntries(
      Object.entries(params).flatMap(([key, value]) => {
        if (value === undefined || value === null) return []
        return [[Array.isArray(value) ? `${key}[]` : key, value]]
      })
    )
  }

  async function listDirectory(params: Record<string, unknown> = {}) {
    const response = await $api<{ data: MemberDirectoryEntry[] }>('/account/members/directory', {
      query: queryOf(params)
    })
    return response.data
  }

  async function list() {
    return $api<AccountMember[]>('/account/members')
  }

  async function create(body: AccountMemberCreatePayload) {
    return $api<AccountMember>('/account/members', { method: 'POST', body })
  }

  async function update(id: number, body: AccountMemberUpdatePayload) {
    return $api<AccountMember>(`/account/members/${id}`, { method: 'PATCH', body })
  }

  async function remove(id: number) {
    await $api(`/account/members/${id}`, { method: 'DELETE' })
  }

  return { listDirectory, list, create, update, remove }
}
