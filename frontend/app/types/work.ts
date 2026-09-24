export type WorkTaskStatus = 'todo' | 'doing' | 'done' | 'dismissed'
export type WorkTaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface WorkTemplateStep { id: number, title: string, department: string, description: string | null, due_day: number, priority: WorkTaskPriority, order: number, default_assignee_member_id: number | null }
export interface WorkTemplate { id: number, name: string, description: string | null, cascade: boolean, generate_day: number, due_day: number, is_active: boolean, regimes: string[], steps?: WorkTemplateStep[] }
export interface WorkProcess { id: number, name: string, status: string, due_on: string | null, reference_month: string | null, template?: { id: number, name: string }, client?: { id: number, name: string }, progress?: { total: number, done: number, dismissed: number, open: number, ratio: number } }
export interface WorkTask { id: number, title: string, department: string, description: string | null, status: WorkTaskStatus, due_on: string | null, priority: WorkTaskPriority, assignee_member_id: number | null, order: number, process?: { id: number, name: string, client?: { id: number, name: string } } }
export interface WorkGroupedClient { client: { id: number, name: string }, totals: { processes: number, tasks: number }, processes: { process: { id: number, name: string }, ratio: number, tasks: WorkTask[] }[] }
