import type { WorkGroupedClient, WorkProcess, WorkTask, WorkTemplate } from '~/types/work'

export function useWork() {
  const { $api } = useNuxtApp()
  function queryOf(params: object) {
    return Object.fromEntries(Object.entries(params).flatMap(([key, value]) => {
      if (value === undefined) return []
      return [[Array.isArray(value) ? `${key}[]` : key, value]]
    }))
  }
  async function listTemplates() {
    const res = await $api<{ data: WorkTemplate[] }>('/process-templates')
    return res.data
  }
  async function previewTemplate(id: number) {
    const res = await $api<{ data: { client: { id: number, name: string }, reason: string }[] }>(`/process-templates/${id}/preview`)
    return res.data
  }
  async function generateTemplate(id: number, referenceMonth: string) {
    const res = await $api<{ data: WorkProcess[] }>(`/process-templates/${id}/generate`, { method: 'POST', body: { reference_month: referenceMonth } })
    return res.data
  }
  async function listProcesses(params: { template_id?: number, reference_month?: string, client_id?: number, status?: string } = {}) {
    const res = await $api<{ data: WorkProcess[] }>('/processes', { query: queryOf(params) })
    return res.data
  }
  async function showProcess(id: number) {
    const res = await $api<{ data: WorkProcess }>(`/processes/${id}`)
    return res.data
  }
  async function listTasks(params: {
    process_id?: number
    client_id?: number
    status?: string
    assignee_member_id?: number
    department?: string
    priority?: string
    due_from?: string
    due_to?: string
  } = {}) {
    const res = await $api<{ data: WorkTask[] }>('/tasks', { query: queryOf(params) })
    return res.data
  }
  async function updateTask(id: number, body: { status?: string, dismissal_reason?: string, assignee_member_id?: number | null }) {
    const res = await $api<{ data: WorkTask }>(`/tasks/${id}`, { method: 'PATCH', body })
    return res.data
  }
  async function calendar(from: string, to: string) {
    const res = await $api<{ data: WorkTask[] }>('/work/calendar', { query: { from, to } })
    return res.data
  }
  async function grouped(referenceMonth: string) {
    const res = await $api<{ data: WorkGroupedClient[] }>('/work/grouped', { query: { reference_month: referenceMonth } })
    return res.data
  }
  return { listTemplates, previewTemplate, generateTemplate, listProcesses, showProcess, listTasks, updateTask, calendar, grouped }
}
