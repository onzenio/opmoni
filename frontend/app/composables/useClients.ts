import type {
  Client,
  ClientListParams,
  ClientUpdatePayload,
  ClientWritePayload,
  CnpjPreview,
  CnpjRefreshPreview,
  PaginatedResponse,
  PowerOfAttorneyPayload
} from '~/types/client'

export function useClients() {
  const { $api } = useNuxtApp()

  async function list(params: ClientListParams) {
    return $api<PaginatedResponse<Client>>('/clients', { query: params })
  }

  async function lookupCnpj(cnpj: string) {
    const response = await $api<{ data: CnpjPreview }>('/clients/cnpj-lookup', {
      method: 'POST', body: { cnpj }
    })
    return response.data
  }

  async function create(payload: ClientWritePayload) {
    const response = await $api<{ data: Client }>('/clients', { method: 'POST', body: payload })
    return response.data
  }

  async function update(id: number, payload: ClientUpdatePayload) {
    const response = await $api<{ data: Client }>(`/clients/${id}`, { method: 'PATCH', body: payload })
    return response.data
  }

  async function refreshPreview(id: number) {
    const response = await $api<{ data: CnpjRefreshPreview }>(`/clients/${id}/cnpj-refresh-preview`, { method: 'POST' })
    return response.data
  }

  async function refreshCnpj(id: number) {
    const response = await $api<{ data: Client }>(`/clients/${id}/cnpj-refresh`, { method: 'POST' })
    return response.data
  }

  async function remove(id: number) {
    await $api(`/clients/${id}`, { method: 'DELETE' })
  }

  async function uploadCertificate(id: number, file: File, password: string) {
    const body = new FormData()
    body.append('certificate', file)
    body.append('password', password)
    const response = await $api<{ data: Client }>(`/clients/${id}/certificate`, { method: 'POST', body })
    return response.data
  }

  async function removeCertificate(id: number) {
    await $api(`/clients/${id}/certificate`, { method: 'DELETE' })
  }

  async function upsertPowerOfAttorney(id: number, payload: PowerOfAttorneyPayload) {
    const response = await $api<{ data: Client }>(`/clients/${id}/ecac-power-of-attorney`, { method: 'PUT', body: payload })
    return response.data
  }

  async function removePowerOfAttorney(id: number) {
    await $api(`/clients/${id}/ecac-power-of-attorney`, { method: 'DELETE' })
  }

  return { list, lookupCnpj, create, update, refreshPreview, refreshCnpj, remove, uploadCertificate, removeCertificate, upsertPowerOfAttorney, removePowerOfAttorney }
}
