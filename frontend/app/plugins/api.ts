export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const incoming = import.meta.server ? useRequestHeaders(['cookie']) : null
  const xsrfToken = useCookie<string | null>('XSRF-TOKEN')
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
        if (incoming?.cookie) headers.set('cookie', incoming.cookie)
        const site = config.public.siteUrl || 'http://localhost:3000'
        headers.set('origin', site)
        headers.set('referer', `${site}/`)
      }

      if (xsrfToken.value) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfToken.value))
      }

      options.headers = headers
    }
  })

  return { provide: { api } }
})
