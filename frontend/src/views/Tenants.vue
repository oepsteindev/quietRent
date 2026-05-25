<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <h1 class="text-2xl font-bold text-slate-900">{{ product.terminology.tenants }}</h1>
      <button @click="showForm = true"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        + Add {{ product.terminology.tenant.toLowerCase() }}
      </button>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <div v-else-if="tenants.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[560px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
          <tr>
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">{{ product.terminology.unit }}</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Channel</th>
            <th class="px-6 py-3">Reminders</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-medium">
              <RouterLink :to="`/tenants/${t.id}`" class="text-blue-600 hover:text-blue-700">{{ t.full_name }}</RouterLink>
            </td>
            <td class="px-6 py-4 text-slate-500">{{ t.property_name }} – {{ t.unit_label }}</td>
            <td class="px-6 py-4 text-slate-600">{{ t.email }}</td>
            <td class="px-6 py-4 text-slate-600">{{ t.preferred_channel }}</td>
            <td class="px-6 py-4">
              <button @click="togglePause(t)"
                :class="t.reminders_paused
                  ? 'px-2.5 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs font-semibold cursor-pointer hover:bg-amber-100 transition-colors'
                  : 'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold cursor-pointer hover:bg-emerald-100 transition-colors'">
                {{ t.reminders_paused ? 'Paused' : 'Active' }}
              </button>
            </td>
            <td class="px-6 py-4 text-right flex justify-end gap-3">
              <button @click="openEdit(t)"
                class="text-xs font-medium text-slate-500 hover:text-slate-800 transition-colors">
                Edit
              </button>
              <button @click="remove(t)"
                class="text-xs font-medium text-slate-500 hover:text-red-600 transition-colors">
                Remove
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-500 text-sm">
      No {{ product.terminology.tenants.toLowerCase() }} yet.
    </div>

    <!-- Edit modal -->
    <div v-if="showEdit" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showEdit = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Edit {{ product.terminology.tenant.toLowerCase() }}</h3>
        <form @submit.prevent="saveEdit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Full name</label>
            <input v-model="editForm.full_name" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
            <input v-model="editForm.email" type="email" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Phone</label>
            <input v-model="editForm.phone" type="tel"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Preferred contact</label>
            <select v-model="editForm.preferred_channel"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="email">Email only</option>
              <option value="sms">SMS only</option>
              <option value="both">Email + SMS</option>
            </select>
          </div>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showEdit = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="savingEdit"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ savingEdit ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Add {{ product.terminology.tenant.toLowerCase() }}</h3>
        <form @submit.prevent="save" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ product.terminology.unit }}</label>
            <select v-model="form.unit_id" required :disabled="!units.length"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400">
              <option value="">{{ units.length ? `Select a ${product.terminology.unit.toLowerCase()}` : `No ${product.terminology.units.toLowerCase()} yet — add one in ${product.terminology.properties} first` }}</option>
              <option v-for="u in units" :key="u.id" :value="u.id">{{ u.property_name }} – {{ u.unit_label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Full name</label>
            <input v-model="form.full_name" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
            <input v-model="form.email" type="email" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Phone</label>
            <input v-model="form.phone" type="tel"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Preferred contact</label>
            <select v-model="form.preferred_channel"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="email">Email only</option>
              <option value="sms">SMS only</option>
              <option value="both">Email + SMS</option>
            </select>
          </div>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showForm = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="saving"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { get, post, del } from '../composables/useApi.js'
import { product } from '../composables/useAccount.js'

const tenants    = ref([])
const units      = ref([])
const loading    = ref(true)
const showForm   = ref(false)
const saving     = ref(false)
const form       = ref({ unit_id: '', full_name: '', email: '', phone: '', preferred_channel: 'email' })
const showEdit   = ref(false)
const savingEdit = ref(false)
const editTarget = ref(null)
const editForm   = ref({ full_name: '', email: '', phone: '', preferred_channel: 'email' })

async function load() {
  loading.value = true
  try {
    ;[tenants.value, units.value] = await Promise.all([get('/api/tenants'), get('/api/units')])
  } catch (err) {
    alert('Error loading: ' + err.message)
  } finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    await post('/api/tenants', { ...form.value, unit_id: parseInt(form.value.unit_id) })
    showForm.value = false
    form.value = { unit_id: '', full_name: '', email: '', phone: '', preferred_channel: 'email' }
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally { saving.value = false }
}

function openEdit(t) {
  editTarget.value = t
  editForm.value = { full_name: t.full_name, email: t.email, phone: t.phone || '', preferred_channel: t.preferred_channel }
  showEdit.value = true
}

async function saveEdit() {
  savingEdit.value = true
  try {
    await post(`/api/tenants/${editTarget.value.id}`, editForm.value)
    showEdit.value = false
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally { savingEdit.value = false }
}

async function togglePause(t) {
  await post(`/api/tenants/${t.id}/pause`, {})
  load()
}

async function remove(t) {
  if (!confirm(`Remove ${t.full_name}?`)) return
  await del(`/api/tenants/${t.id}`)
  load()
}

onMounted(load)
</script>
