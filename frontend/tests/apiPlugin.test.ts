import assert from 'node:assert/strict'
import { it } from 'node:test'

it('captures Nuxt composables while the API plugin context is active', async () => {
  let contextActive = true
  let cookieReads = 0
  let onRequest: ((context: { options: { headers?: HeadersInit } }) => void) | undefined
  const xsrfCookie: { value: string | null } = { value: null }

  Object.assign(globalThis, {
    defineNuxtPlugin: (factory: () => unknown) => factory,
    useRuntimeConfig: () => ({
      apiUrl: 'http://backend:8000',
      public: {
        apiUrl: 'http://localhost:8000',
        siteUrl: 'http://localhost:3000'
      }
    }),
    useCookie: () => {
      assert.equal(contextActive, true, 'useCookie must run during plugin setup')
      cookieReads++

      return xsrfCookie
    },
    $fetch: {
      create: (options: { onRequest: typeof onRequest }) => {
        onRequest = options.onRequest

        return () => undefined
      }
    }
  })

  const plugin = (await import('../app/plugins/api.ts')).default
  const result = plugin()

  assert.ok(result)
  assert.equal(cookieReads, 1)
  assert.ok(onRequest)

  contextActive = false
  xsrfCookie.value = 'csrf-token'
  const options: { headers?: HeadersInit } = {}
  onRequest({ options })

  assert.equal(new Headers(options.headers).get('X-XSRF-TOKEN'), 'csrf-token')
})
