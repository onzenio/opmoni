import type { MemberDirectoryEntry } from '~/types/team'

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

  return { listDirectory }
}
