import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isCalendarRoute } from '../app/utils/workNav.ts'

describe('Work navigation', () => {
  it('identifies only the calendar route for the clean shell', () => {
    assert.equal(isCalendarRoute('/work/calendario'), true)
    assert.equal(isCalendarRoute('/work/calendario?view=month'), true)
    assert.equal(isCalendarRoute('/work/tarefas'), false)
    assert.equal(isCalendarRoute('/work/calendario-extra'), false)
  })
})
