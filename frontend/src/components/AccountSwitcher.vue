<template>
  <div class="relative" ref="el">
    <button
      @click="open = !open"
      class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-slate-800 transition-colors"
    >
      <div class="min-w-0">
        <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold leading-none mb-1">Business</p>
        <p class="text-sm text-white font-medium truncate">{{ currentName }}</p>
      </div>
      <span class="text-slate-400 ml-2 flex-shrink-0 text-xs">{{ open ? '▲' : '▼' }}</span>
    </button>

    <div v-if="open" class="absolute left-0 right-0 top-full bg-slate-800 border border-slate-700 rounded-b-lg shadow-xl z-50 max-h-64 overflow-y-auto">
      <button
        v-for="a in accounts"
        :key="a.id"
        @click="switchTo(a)"
        :class="[
          'w-full flex items-center gap-2 px-5 py-3 text-sm text-left transition-colors',
          a.is_current ? 'text-white bg-slate-700' : 'text-slate-300 hover:bg-slate-700 hover:text-white'
        ]"
      >
        <span class="flex-1 truncate">{{ a.name }}</span>
        <span class="text-xs text-slate-500 flex-shrink-0 capitalize">{{ a.product_type }}</span>
        <span v-if="a.is_current" class="text-blue-400 flex-shrink-0">✓</span>
      </button>

      <div class="border-t border-slate-700">
        <button
          @click="showCreate = true; open = false"
          class="w-full flex items-center gap-2 px-5 py-3 text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
        >
          <span class="text-blue-400">+</span> Add business
        </button>
      </div>
    </div>
  </div>

  <!-- Create new business modal -->
  <Teleport to="body">
    <div v-if="showCreate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCreate = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-sm shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-5">Add a new business</h3>
        <form @submit.prevent="createBusiness" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Business name</label>
            <input v-model="newName" required autofocus
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Business type</label>
            <select v-model="newType"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="landlords">Property management (Landlord)</option>
              <option value="hairdressers">Hair salon / Hairdresser</option>
            </select>
          </div>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showCreate = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="creating"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ creating ? 'Creating…' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { get, post } from '../composables/useApi.js'
import { setProductType } from '../composables/useAccount.js'

const emit = defineEmits(['close'])

const router     = useRouter()
const el         = ref(null)
const open       = ref(false)
const showCreate = ref(false)
const creating   = ref(false)
const accounts   = ref([])
const newName    = ref('')
const newType    = ref('landlords')

const currentName = computed(() => accounts.value.find(a => a.is_current)?.name ?? 'My Business')

async function load() {
  try { accounts.value = await get('/api/accounts') } catch (_) {}
}

async function switchTo(a) {
  if (a.is_current) { open.value = false; return }
  try {
    const res = await post('/api/switch-account', { account_id: a.id })
    setProductType(res.product_type)
    open.value = false
    emit('close')
    window.location.href = '/dashboard'
  } catch (err) {
    alert('Error switching: ' + err.message)
  }
}

async function createBusiness() {
  if (!newName.value.trim()) return
  creating.value = true
  try {
    const res = await post('/api/accounts', { name: newName.value.trim(), product_type: newType.value })
    setProductType(res.product_type)
    showCreate.value = false
    newName.value = ''
    emit('close')
    window.location.href = '/dashboard'
  } catch (err) {
    alert('Error creating business: ' + err.message)
  } finally {
    creating.value = false
  }
}

function onClickOutside(e) {
  if (el.value && !el.value.contains(e.target)) open.value = false
}

onMounted(() => {
  load()
  document.addEventListener('click', onClickOutside)
})
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>
