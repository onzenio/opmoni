function unauthenticated(error: unknown) {
  if (!error || typeof error !== 'object') return false
  const failure = error as { status?: number, statusCode?: number }
  const status = Number(failure.statusCode ?? failure.status)
  return status === 401 || status === 419
}

function forbidden(error: unknown) {
  if (!error || typeof error !== 'object') return false
  const failure = error as { status?: number, statusCode?: number }
  const status = Number(failure.statusCode ?? failure.status)
  return status === 403
}

export default defineNuxtRouteMiddleware(async () => {
  const { fetchMe, user } = useAuth()
  if (user.value) return
  try {
    await fetchMe()
  } catch (error) {
    if (unauthenticated(error)) return navigateTo('/login')
    if (forbidden(error)) {
      const toast = useToast()
      toast.add({ title: 'Acesso negado', description: 'Você não tem permissão para acessar esta área.', color: 'error' })
      return navigateTo('/')
    }
    throw error
  }
})
