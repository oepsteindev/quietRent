<template>
  <div>
    <!-- Hamburger (mobile only) -->
    <button v-if="showNav"
      @click="navOpen = !navOpen"
      class="md:hidden fixed top-4 left-4 z-50 w-10 h-10 flex items-center justify-center bg-slate-900 text-white rounded-lg shadow-lg">
      ☰
    </button>

    <!-- Overlay backdrop (mobile) -->
    <div v-if="showNav && navOpen"
      @click="navOpen = false"
      class="md:hidden fixed inset-0 bg-black/40 z-30" />

    <AppNav v-if="showNav" :open="navOpen" @close="navOpen = false" />

    <main :class="showNav
      ? 'md:ml-52 p-4 md:p-8 bg-slate-50 min-h-screen'
      : 'min-h-screen'">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { setCsrf } from './composables/useApi.js'
import AppNav from './components/AppNav.vue'

const route = useRoute()
const router = useRouter()
const routerReady = ref(false)
router.isReady().then(() => { routerReady.value = true })

const publicRoutes = ['/login', '/register', '/forgot-password', '/reset-password']
const showNav = computed(() => routerReady.value && !publicRoutes.includes(route.path))

const navOpen = ref(false)

watch(() => route.path, () => { navOpen.value = false })

onMounted(async () => {
  try {
    const res = await fetch('/api/csrf', { credentials: 'include' })
    if (res.ok) {
      const data = await res.json()
      if (data.csrf) setCsrf(data.csrf)
    }
  } catch (e) {
    console.warn('Failed to fetch CSRF token:', e.message)
  }
})
</script>
