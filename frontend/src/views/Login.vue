<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm">

      <!-- Brand -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl mb-4 shadow-sm"
          :style="{ backgroundColor: isPinned() ? product.branding.primaryColor : '#64748b' }">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ product.shortName }}</h1>
        <p class="text-sm text-slate-500 mt-1">
          <template v-if="isPinned()">{{ product.tagline }} — </template>sign in to your account
        </p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">

        <div v-if="error" class="mb-5 flex items-center gap-2.5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          {{ error }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
            <input v-model="email" type="email" required autocomplete="email" placeholder="you@example.com"
              style="appearance: none; -webkit-appearance: none;"
              class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-slate-700">Password</label>
              <RouterLink to="/forgot-password" class="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors">
                Forgot password?
              </RouterLink>
            </div>
            <div class="relative">
            <input v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="••••••••"
              style="appearance: none; -webkit-appearance: none;"
              class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 pr-10 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            <button type="button" @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
              <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
            </button>
          </div>
          </div>

          <button type="submit" :disabled="loading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors shadow-sm">
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>
      </div>

      <p class="text-center text-sm text-slate-500 mt-6">
        Don't have an account?
        <RouterLink to="/register" class="font-medium text-blue-600 hover:text-blue-700 transition-colors">Create one free</RouterLink>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { post } from '../composables/useApi'
import { product, isPinned } from '../composables/useAccount.js'

const router       = useRouter()
const email        = ref('')
const password     = ref('')
const error        = ref('')
const loading      = ref(false)
const showPassword = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await post('/login', {
      email: email.value,
      password: password.value,
    })
    router.push('/dashboard')
  } catch (err) {
    error.value = 'Invalid email or password.'
  } finally {
    loading.value = false
  }
}
</script>
