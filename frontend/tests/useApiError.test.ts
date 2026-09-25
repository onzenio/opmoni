import test from 'node:test'
import assert from 'node:assert/strict'
import { apiMessage, apiFieldErrors } from '../app/composables/useApiError.ts'

test('extrai message e mapeia errors.* do 422', () => {
  const err = { data: { message: 'Falhou.', errors: { name: ['obrigatório.'] } } }
  assert.equal(apiMessage(err), 'Falhou.')
  assert.deepEqual(apiFieldErrors(err), { name: 'obrigatório.' })
})
