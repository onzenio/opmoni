export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const baseURL = import.meta.server
    ? `${config.apiUrl}/api`
    : `${config.public.apiUrl}/api`

  const api = $fetch.create({
    baseURL,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const headers = new Headers(options.headers as HeadersInit)

      if (import.meta.server) {
        const incoming = useRequestHeaders(['cookie'])
        if (incoming.cookie) headers.set('cookie', incoming.cookie)
        const site = config.public.siteUrl || 'http://localhost:3000'
        headers.set('origin', site)
        headers.set('referer', `${site}/`)
      }

      const token = useCookie('XSRF-TOKEN')
      if (token.value) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(token.value))
      }

      options.headers = headers
    }
  })

  return { provide: { api } }
})
