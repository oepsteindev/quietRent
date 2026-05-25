<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 w-full max-w-sm">
      <h1 class="text-2xl font-bold text-slate-900 mb-1">Create your account</h1>
      <p class="text-sm text-slate-500 mb-8">Free 14-day trial. No credit card required.</p>

      <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Your name</label>
          <input v-model="name" type="text" required autocomplete="name"
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
          <input v-model="email" type="email" required autocomplete="email"
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
            Password <span class="normal-case font-normal text-slate-400">(min 8 characters)</span>
          </label>
          <div class="relative">
            <input v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" minlength="8"
              class="w-full px-3 py-2.5 pr-10 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
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
        <button :disabled="loading"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
          {{ loading ? 'Creating account…' : 'Start free trial' }}
        </button>
      </form>

      <div class="mt-6 text-center text-sm text-slate-500">
        Already have an account?
        <RouterLink to="/login" class="text-blue-600 hover:text-blue-700">Sign in</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { post } from '../composables/useApi'
import { product } from '../composables/useAccount.js'

const router       = useRouter()
const name         = ref('')
const email        = ref('')
const password     = ref('')
const error        = ref('')
const loading      = ref(false)
const showPassword = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await post('/register', {
      name:         name.value,
      email:        email.value,
      password:     password.value,
      product_type: product.value.id,
    })
    router.push('/dashboard')
  } catch (err) {
    error.value = err.message || 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
