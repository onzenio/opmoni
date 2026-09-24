export interface AuthAccountLink {
  id: number
  name: string
  role: string
}

export interface AuthCurrentAccount {
  id: number
  name: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  company: string
  size: string
}

interface MeResponse {
  id: number
  name: string
  email: string
  is_super_admin: boolean
  accounts: AuthAccountLink[]
  current_account: AuthCurrentAccount | null
}

export function useAuth() {
  const user = useState<AuthUser | null>('auth.user', () => null)
  const isSuperAdmin = useState<boolean>('auth.is-super-admin', () => false)
  const accounts = useState<AuthAccountLink[]>('auth.accounts', () => [])
  const currentAccount = useState<AuthCurrentAccount | null>('auth.current-account', () => null)
  const currentRole = computed(() => accounts.value.find(account => account.id === currentAccount.value?.id)?.role ?? null)
  const can = (roles: string[]): boolean => isSuperAdmin.value || roles.includes(currentRole.value ?? '')
  const canManageClients = computed(() => can(['admin', 'operador']))
  const canManageWork = computed(() => can(['admin', 'operador']))
  const canManageDepartments = computed(() => can(['admin', 'operador']))

  function applyMe(me: MeResponse) {
    user.value = { id: me.id, name: me.name, email: me.email }
    isSuperAdmin.value = me.is_super_admin
    accounts.value = me.accounts ?? []
    currentAccount.value = me.current_account
  }

  function clearAuth() {
    user.value = null
    isSuperAdmin.value = false
    accounts.value = []
    currentAccount.value = null
  }

  async function ensureCsrf() {
    const config = useRuntimeConfig()
    await $fetch(`${config.public.apiUrl}/sanctum/csrf-cookie`, { credentials: 'include' })
  }

  async function fetchMe() {
    const { $api } = useNuxtApp()
    const me = await $api<MeResponse>('/me')
    applyMe(me)
    return me
  }

  async function login(email: string, password: string) {
    const { $api } = useNuxtApp()
    await ensureCsrf()
    await $api('/login', { method: 'POST', body: { email, password } })
    return fetchMe()
  }

  async function register(payload: RegisterPayload) {
    const { $api } = useNuxtApp()
    await ensureCsrf()
    await $api('/register', { method: 'POST', body: payload })
    return fetchMe()
  }

  async function logout() {
    const { $api } = useNuxtApp()
    await $api('/logout', { method: 'POST' })
    clearAuth()
  }

  async function switchAccount(accountId: number) {
    const { $api } = useNuxtApp()
    await $api('/account/switch', { method: 'POST', body: { account_id: accountId } })
    return fetchMe()
  }

  async function enterSupport(accountId: number) {
    const { $api } = useNuxtApp()
    await $api(`/support/accounts/${accountId}/enter`, { method: 'POST' })
    return fetchMe()
  }

  async function exitSupport() {
    const { $api } = useNuxtApp()
    await $api('/support/exit', { method: 'POST' })
    return fetchMe()
  }

  return { user, isSuperAdmin, accounts, currentAccount, currentRole, can, canManageClients, canManageWork, canManageDepartments, fetchMe, login, register, logout, switchAccount, enterSupport, exitSupport }
}
