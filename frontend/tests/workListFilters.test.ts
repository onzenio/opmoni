import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import type { DataTableFilterModel } from '../app/components/data-table/filter-model.ts'
import type { WorkGroupedClient, WorkTask } from '../app/types/work.ts'
import {
  filterWorkProcessosLeaves,
  workProcessosFilterColumns
} from '../app/utils/workProcessosFilters.ts'
import { filterWorkTasks, tasksForWorkScope } from '../app/utils/workTarefasFilters.ts'

function task(id: number, dueOn: string | null): WorkTask {
  return {
    id,
    title: `Tarefa ${id}`,
    department: 'Fiscal',
    description: null,
    status: 'todo',
    due_on: dueOn,
    priority: 'medium',
    assignee_member_id: null,
    order: id
  }
}

describe('Work task filters', () => {
  it('keeps only tasks inside an inclusive due-date range', () => {
    const tasks = [
      task(1, '2026-09-09'),
      task(2, '2026-09-10'),
      task(3, '2026-09-20'),
      task(4, '2026-09-21'),
      task(5, null)
    ]

    const filtered = filterWorkTasks(tasks, [], [], '', {
      from: '2026-09-10',
      to: '2026-09-20'
    })

    assert.deepEqual(filtered.map(item => item.id), [2, 3])
  })
})

describe('Work task scope', () => {
  it('keeps scoped undated tasks in their competence and separates unscoped undated tasks', () => {
    const scopedTask = task(3, null)
    const groups: WorkGroupedClient[] = [{
      client: { id: 1, name: 'Alpha' },
      totals: { processes: 1, tasks: 1 },
      processes: [{
        process: { id: 10, name: 'Rotina', status: 'open', due_on: null, reference_month: '2026-09', template: null },
        totals: { tasks: 1, done: 0, dismissed: 0, open: 1 },
        progress: { total: 1, done: 0, dismissed: 0, open: 1, ratio: 0 },
        ratio: 0,
        tasks: [scopedTask]
      }]
    }]
    const unscoped = [task(1, '2026-09-20'), task(2, null)]

    assert.deepEqual(tasksForWorkScope(groups, unscoped, 'month').map(item => item.id), [3, 1])
    assert.deepEqual(tasksForWorkScope(groups, unscoped, 'undated').map(item => item.id), [2])
  })
})

describe('Work process filters', () => {
  const leaves = [
    {
      id: '1',
      processKey: 'template-11',
      processId: 101,
      processName: 'Folha',
      processStatus: 'open',
      processDueOn: '2026-09-20',
      templateId: 11,
      templateName: 'Folha mensal',
      clientId: 1,
      clientName: 'Alpha',
      cascade: true,
      title: 'Conferir folha',
      status: 'todo' as const,
      department: 'Pessoal',
      due_on: '2026-09-10',
      empty: false,
      taskId: 1
    },
    {
      id: '2',
      processKey: 'template-22',
      processId: 202,
      processName: 'Fiscal',
      processStatus: 'done',
      processDueOn: '2026-09-25',
      templateId: 22,
      templateName: 'Fiscal mensal',
      clientId: 2,
      clientName: 'Beta',
      cascade: false,
      title: 'Transmitir obrigação',
      status: 'done' as const,
      department: 'Fiscal',
      due_on: '2026-09-18',
      empty: false,
      taskId: 2
    }
  ]

  it('exposes template and process-status facets from grouped process metadata', () => {
    const columns = workProcessosFilterColumns(leaves, [])

    assert.deepEqual(
      columns.find(column => column.id === 'template')?.options?.map(option => option.value),
      ['22', '11']
    )
    assert.deepEqual(
      columns.find(column => column.id === 'processStatus')?.options?.map(option => option.value),
      ['open', 'done']
    )
  })

  it('filters process rows by template and process status independently of task status', () => {
    const filters: DataTableFilterModel[] = [
      { columnId: 'template', type: 'option', operator: 'is', values: ['11'] },
      { columnId: 'processStatus', type: 'option', operator: 'is', values: ['open'] }
    ]

    assert.deepEqual(
      filterWorkProcessosLeaves(leaves, filters, '').map(leaf => leaf.id),
      ['1']
    )
  })
})
