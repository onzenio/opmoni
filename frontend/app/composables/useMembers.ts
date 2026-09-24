import type { MemberDirectoryEntry } from '~/types/team'
import { queryOf } from './useApiQuery'

export function useMembers() {
  const { $api } = useNuxtApp()

  async function listDirectory(params: Record<string, unknown> = {}) {
    const response = await $api<{ data?: MemberDirectoryEntry[] } | MemberDirectoryEntry[]>('/account/members/directory', {
      query: queryOf(params)
    })
    return Array.isArray(response) ? response : (response.data ?? [])
  }

  return { listDirectory }
}
