<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ product.terminology.properties }}</h1>
      </div>
      <button @click="() => { editingId = null; form = { name: '', address_line1: '', city: '', state: '', zip: '' }; showForm = true; }"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        + Add {{ product.terminology.property.toLowerCase() }}
      </button>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <div v-else-if="properties.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[480px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
          <tr>
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Address</th>
            <th class="px-6 py-3">{{ product.terminology.units }}</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="p in properties" :key="p.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-medium">
              <RouterLink :to="`/properties/${p.id}`" class="text-blue-600 hover:text-blue-700">{{ p.name }}</RouterLink>
            </td>
            <td class="px-6 py-4 text-slate-500">{{ p.address_line1 }}, {{ p.city }}, {{ p.state }}</td>
            <td class="px-6 py-4 text-slate-700">{{ p.unit_count }}</td>
            <td class="px-6 py-4">
              <span :class="p.is_active
                ? 'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold'
                : 'px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold'">
                {{ p.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex gap-2 justify-end">
                <button @click="openEdit(p)"
                  class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                  Edit
                </button>
                <button @click="remove(p)"
                  class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-500 text-sm">
      No {{ product.terminology.properties.toLowerCase() }} yet.
    </div>

    <!-- Add/Edit modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">{{ editingId ? 'Edit' : 'Add' }} {{ product.terminology.property.toLowerCase() }}</h3>
        <form @submit.prevent="save" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ product.terminology.property }} name</label>
            <input v-model="form.name" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Address</label>
            <input v-model="form.address_line1" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">City</label>
              <input v-model="form.city" required
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">State</label>
              <input v-model="form.state" required maxlength="2"
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">ZIP</label>
              <input v-model="form.zip" required
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
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

const properties = ref([])
const loading    = ref(true)
const showForm   = ref(false)
const saving     = ref(false)
const editingId  = ref(null)
const form       = ref({ name: '', address_line1: '', city: '', state: '', zip: '' })

async function load() {
  loading.value = true
  try { properties.value = await get('/api/properties') }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    if (editingId.value) {
      await post(`/api/properties/${editingId.value}`, form.value)
    } else {
      const res = await post('/api/properties', form.value)
      window.location.href = `/properties/${res.id}?new=1`
    }
    showForm.value = false
    editingId.value = null
    form.value = { name: '', address_line1: '', city: '', state: '', zip: '' }
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally { saving.value = false }
}

function openEdit(p) {
  editingId.value = p.id
  form.value = {
    name: p.name,
    address_line1: p.address_line1,
    city: p.city,
    state: p.state,
    zip: p.zip,
  }
  showForm.value = true
}

async function remove(p) {
  if (!confirm(`Remove "${p.name}"? This will delete all associated ${product.value.terminology.units.toLowerCase()} and ${product.value.terminology.tenants.toLowerCase()}.`)) return
  await del(`/api/properties/${p.id}`)
  load()
}

onMounted(load)
</script>
