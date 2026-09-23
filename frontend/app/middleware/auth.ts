function unauthenticated(error: unknown) {
  if (!error || typeof error !== 'object') return false
  const failure = error as { status?: number, statusCode?: number }
  const status = Number(failure.statusCode ?? failure.status)
  return status === 401 || status === 419
}

export default defineNuxtRouteMiddleware(async () => {
  const { fetchMe, user } = useAuth()
  if (user.value) return
  try {
    await fetchMe()
  } catch (error) {
    if (unauthenticated(error)) return navigateTo('/login')
  }
})
