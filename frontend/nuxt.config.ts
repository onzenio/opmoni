// https://nuxt.com/docs/api/configuration/nuxt-config
// Minimal ambient typing so `process.env` below typechecks without adding @types/node.
declare const process: { env: Record<string, string | undefined> }
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@vueuse/nuxt'
  ],

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  // NUXT_PUBLIC_API_URL sobrescreve este default automaticamente (runtime config).
  runtimeConfig: {
    apiUrl: process.env.NUXT_API_URL ?? process.env.NUXT_PUBLIC_API_URL ?? 'http://localhost:8000',
    public: {
      apiUrl: process.env.NUXT_PUBLIC_API_URL ?? 'http://localhost:8000',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL ?? 'http://localhost:3000'
    }
  },

  routeRules: {
    '/api/**': {
      cors: true
    }
  },

  compatibilityDate: '2026-06-30',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  }
})
