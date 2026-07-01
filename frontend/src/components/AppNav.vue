<template>
  <nav :class="[
    'fixed left-0 top-0 bottom-0 w-52 bg-slate-900 flex flex-col z-40 transition-transform duration-200',
    open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
  ]">
    <div class="px-5 py-4 border-b border-slate-800">
      <span class="text-base font-bold text-white tracking-tight">
        {{ product.shortName }}
        <span class="text-slate-400 text-xs font-normal">{{ product.tagline }}</span>
      </span>
    </div>

    <AccountSwitcher @close="$emit('close')" />

    <ul class="flex-1 py-3 border-t border-slate-800">
      <li v-for="link in product.navigation" :key="link.to">
        <RouterLink
          :to="link.to"
          @click="$emit('close')"
          class="flex items-center px-5 py-2.5 text-sm text-slate-400 hover:text-white hover:bg-slate-800 transition-colors [&.router-link-active]:text-white [&.router-link-active]:bg-slate-800"
        >
          {{ link.label }}
        </RouterLink>
      </li>
      <li v-if="isAdmin">
        <RouterLink
          to="/admin"
          @click="$emit('close')"
          class="flex items-center px-5 py-2.5 text-sm text-amber-400 hover:text-amber-300 hover:bg-slate-800 transition-colors [&.router-link-active]:text-amber-300 [&.router-link-active]:bg-slate-800"
        >
          Admin
        </RouterLink>
      </li>
    </ul>

    <div class="p-4 border-t border-slate-800 space-y-3">
      <button
        @click="logout"
        class="w-full px-3 py-2 text-sm text-slate-400 hover:text-white border border-slate-700 hover:bg-slate-800 rounded-lg transition-colors text-left"
      >
        Log out
      </button>
      <div class="flex gap-3 px-1">
        <a :href="`/html/privacy-${product.id}.html`" target="_blank"
          class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Privacy</a>
        <a :href="`/html/terms-${product.id}.html`" target="_blank"
          class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Terms</a>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { post } from '../composables/useApi'
import { product, isAdmin } from '../composables/useAccount.js'
import AccountSwitcher from './AccountSwitcher.vue'

defineProps({ open: Boolean })
defineEmits(['close'])

const router = useRouter()

async function logout() {
  try {
    await post('/logout', {})
  } catch (e) {
    // ignore — session is cleared server-side regardless
  }
  window.location.href = '/login'
}
</script>
