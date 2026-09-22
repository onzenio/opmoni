export default defineNuxtRouteMiddleware(async () => {
  const { fetchMe, user } = useAuth()
  if (!user.value) {
    await fetchMe().catch(() => {})
  }
  if (!user.value) {
    return navigateTo('/login')
  }
})
