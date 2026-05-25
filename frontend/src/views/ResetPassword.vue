<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 w-full max-w-sm">
      <h1 class="text-2xl font-bold text-slate-900 mb-6">Set new password</h1>

      <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
            New password <span class="normal-case font-normal text-slate-400">(min 8 characters)</span>
          </label>
          <input v-model="password" type="password" required minlength="8"
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <button :disabled="loading"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
          {{ loading ? 'Saving…' : 'Update password' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { post } from '../composables/useApi'

const route    = useRoute()
const router   = useRouter()
const password = ref('')
const error    = ref('')
const loading  = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await post('/reset-password', {
      token:    route.query.token ?? '',
      password: password.value,
    })
    router.push('/login')
  } catch (err) {
    error.value = err.message || 'Invalid or expired token.'
  } finally {
    loading.value = false
  }
}
</script>
