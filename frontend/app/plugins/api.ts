export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const api = $fetch.create({
    baseURL: `${config.public.apiUrl}/api`,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const token = useCookie('XSRF-TOKEN')
      if (token.value) {
        const headers = new Headers(options.headers as HeadersInit)
        headers.set('X-XSRF-TOKEN', decodeURIComponent(token.value))
        options.headers = headers
      }
    }
  })
  return { provide: { api } }
})
