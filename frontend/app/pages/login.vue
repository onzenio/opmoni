<script setup lang="ts">
import * as z from 'zod'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'

definePageMeta({
  layout: 'auth'
})

const toast = useToast()
const { fetchMe, login, user } = useAuth()

if (!user.value && import.meta.client) {
  await fetchMe().catch(() => {})
}
if (user.value) {
  await navigateTo('/')
}

const fields: AuthFormField[] = [{
  name: 'email',
  type: 'email',
  label: 'Email',
  placeholder: 'voce@empresa.com',
  required: true
}, {
  name: 'password',
  label: 'Senha',
  type: 'password',
  placeholder: 'Sua senha',
  required: true
}, {
  name: 'remember',
  label: 'Lembrar de mim',
  type: 'checkbox'
}]

const providers = [{
  label: 'Google',
  icon: 'i-simple-icons-google',
  onClick: () => {
    toast.add({ title: 'Login social ainda não configurado', color: 'neutral' })
  }
}, {
  label: 'GitHub',
  icon: 'i-simple-icons-github',
  onClick: () => {
    toast.add({ title: 'Login social ainda não configurado', color: 'neutral' })
  }
}]

const schema = z.object({
  email: z.email('Email inválido'),
  password: z.string('Senha é obrigatória').min(8, 'Mínimo de 8 caracteres')
})

type Schema = z.output<typeof schema>

const loading = ref(false)

async function onSubmit(event: FormSubmitEvent<Schema>) {
  loading.value = true
  try {
    await login(event.data.email, event.data.password)
    toast.add({ title: `Bem-vindo de volta, ${event.data.email}!`, color: 'success' })
    await navigateTo('/')
  } catch {
    toast.add({ title: 'Não foi possível entrar', description: 'Verifique seu email e senha.', color: 'error' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center p-4">
    <UPageCard class="w-full max-w-md">
      <UAuthForm
        :schema="schema"
        :fields="fields"
        :providers="providers"
        :loading="loading"
        title="Entrar no opmoni"
        description="Acesse sua conta para continuar."
        icon="i-lucide-lock"
        @submit="onSubmit"
      >
        <template #password-hint>
          <ULink to="/login" class="font-medium text-primary">Esqueci a senha</ULink>
        </template>
        <template #footer>
          Não tem conta? <ULink to="/onboarding" class="font-medium text-primary">Comece pelo onboarding</ULink>.
        </template>
      </UAuthForm>
    </UPageCard>
  </div>
</template>
