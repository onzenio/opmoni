export function useSupportMode() {
  const { user, isSuperAdmin, accounts, currentAccount } = useAuth()

  return computed(() => {
    const current = currentAccount.value
    if (!user.value || !isSuperAdmin.value || !current) {
      return false
    }
    return !accounts.value.some(account => account.id === current.id)
  })
}
