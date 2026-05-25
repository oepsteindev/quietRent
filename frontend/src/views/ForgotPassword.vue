<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 w-full max-w-sm">
      <h1 class="text-2xl font-bold text-slate-900 mb-6">Reset your password</h1>

      <div v-if="success" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ success }}</div>
      <div v-if="error"   class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>

      <form v-if="!success" @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
          <input v-model="email" type="email" required
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <button :disabled="loading"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
          {{ loading ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>

      <div class="mt-6 text-center text-sm">
        <RouterLink to="/login" class="text-blue-600 hover:text-blue-700">Back to login</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { post } from '../composables/useApi'

const email   = ref('')
const loading = ref(false)
const success = ref('')
const error   = ref('')

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const res = await post('/forgot-password', { email: email.value })
    success.value = res.message ?? 'If that email exists, a reset link has been sent.'
  } catch (err) {
    error.value = 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
