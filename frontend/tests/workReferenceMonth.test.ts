import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  formatReferenceMonthLabel,
  parseReferenceMonth,
  shiftReferenceMonth
} from '../app/utils/workReferenceMonth.ts'

describe('reference month', () => {
  it('accepts only real month numbers', () => {
    assert.equal(parseReferenceMonth('2026-01'), '2026-01')
    assert.equal(parseReferenceMonth('2026-12'), '2026-12')
    assert.equal(parseReferenceMonth('2026-00'), null)
    assert.equal(parseReferenceMonth('2026-13'), null)
  })

  it('does not label or shift an invalid month', () => {
    assert.equal(formatReferenceMonthLabel('2026-13'), '2026-13')
    assert.equal(shiftReferenceMonth('2026-00', 1), '2026-00')
  })
})
