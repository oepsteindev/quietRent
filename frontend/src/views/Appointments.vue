<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Appointments</h1>
        <p class="text-sm text-slate-500 mt-1">Manage upcoming and past appointments</p>
      </div>
      <button @click="openAdd"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        + Add appointment
      </button>
    </header>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="filterDate" type="date"
        class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      <select v-model="filterStatus"
        class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All statuses</option>
        <option value="scheduled">Scheduled</option>
        <option value="completed">Completed</option>
        <option value="canceled">Canceled</option>
        <option value="no_show">No-show</option>
      </select>
      <button @click="load" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg transition-colors">
        Filter
      </button>
      <button @click="clearFilters" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
        Clear
      </button>
    </div>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <div v-else-if="!appointments.length" class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-500 text-sm">
      No appointments found.
      <button @click="openAdd" class="text-blue-600 hover:text-blue-700 underline ml-1">Add one</button>
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
          <tr>
            <th class="px-6 py-3">Date &amp; Time</th>
            <th class="px-6 py-3">Client</th>
            <th class="px-6 py-3">Service</th>
            <th class="px-6 py-3">Stylist</th>
            <th class="px-6 py-3">Fee</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="appt in appointments" :key="appt.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">{{ formatDate(appt.appointment_at) }}</td>
            <td class="px-6 py-4 font-medium text-slate-900">{{ appt.client_name }}</td>
            <td class="px-6 py-4 text-slate-700">{{ appt.service_name }}</td>
            <td class="px-6 py-4 text-slate-500">{{ appt.stylist_name }}</td>
            <td class="px-6 py-4 text-slate-700">{{ fmt(appt.fee_cents) }}</td>
            <td class="px-6 py-4">
              <span :class="statusClass(appt.status)" class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize">
                {{ appt.status.replace('_', '-') }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div v-if="appt.status === 'scheduled'" class="flex gap-2 justify-end">
                <button @click="complete(appt)"
                  class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors">
                  Complete
                </button>
                <button @click="noShow(appt)"
                  class="px-3 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition-colors">
                  No-show
                </button>
                <button @click="cancel(appt)"
                  class="px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-colors">
                  Cancel
                </button>
              </div>
              <span v-else class="text-slate-400 text-xs">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">{{ editId ? 'Edit appointment' : 'New appointment' }}</h3>
        <form @submit.prevent="save" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Client</label>
              <select v-model="form.client_id" required
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select client…</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.full_name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Stylist / Station</label>
              <select v-model="form.stylist_id" required
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select stylist…</option>
                <option v-for="u in stylists" :key="u.id" :value="u.id">{{ u.unit_label }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Service</label>
            <input v-model="form.service_name" required placeholder="e.g. Haircut, Color, Blowout"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Date &amp; Time</label>
              <input v-model="form.appointment_at" type="datetime-local" required
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Duration (min)</label>
              <input v-model.number="form.duration_minutes" type="number" min="15" max="480" step="15"
                class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fee ($)</label>
            <input v-model.number="feeDisplay" type="number" min="0" step="0.01" placeholder="0.00"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Notes <span class="normal-case font-normal text-slate-400">(optional)</span></label>
            <textarea v-model="form.notes" rows="2"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
          </div>

          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showForm = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="saving"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ saving ? 'Saving…' : 'Save appointment' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'

const loading      = ref(true)
const appointments = ref([])
const clients      = ref([])
const stylists     = ref([])

const filterDate   = ref('')
const filterStatus = ref('')

const showForm  = ref(false)
const saving    = ref(false)
const editId    = ref(null)
const feeDisplay = ref(0)

const form = ref({
  client_id:        '',
  stylist_id:       '',
  service_name:     '',
  appointment_at:   '',
  duration_minutes: 60,
  notes:            '',
})

function fmt(cents) {
  return '$' + ((cents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })
}

function formatDate(dt) {
  return new Date(dt).toLocaleString('en-US', {
    weekday: 'short', month: 'short', day: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
}

function statusClass(status) {
  return {
    scheduled: 'bg-blue-50 text-blue-700',
    completed: 'bg-emerald-50 text-emerald-700',
    canceled:  'bg-slate-100 text-slate-500',
    no_show:   'bg-amber-50 text-amber-700',
  }[status] ?? 'bg-slate-100 text-slate-500'
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filterDate.value)   params.set('date',   filterDate.value)
    if (filterStatus.value) params.set('status', filterStatus.value)
    const qs = params.toString() ? '?' + params.toString() : ''
    appointments.value = await get('/api/appointments' + qs)
  } catch (err) {
    alert('Error loading appointments: ' + err.message)
  } finally {
    loading.value = false
  }
}

async function loadSelectData() {
  const [c, u] = await Promise.all([
    get('/api/tenants'),
    get('/api/units'),
  ])
  clients.value  = c
  stylists.value = u
}

function clearFilters() {
  filterDate.value   = ''
  filterStatus.value = ''
  load()
}

function openAdd() {
  editId.value = null
  feeDisplay.value = 0
  form.value = {
    client_id:        '',
    stylist_id:       '',
    service_name:     '',
    appointment_at:   '',
    duration_minutes: 60,
    notes:            '',
  }
  showForm.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      fee_cents: Math.round(feeDisplay.value * 100),
    }
    if (editId.value) {
      await post(`/api/appointments/${editId.value}`, payload)
    } else {
      await post('/api/appointments', payload)
    }
    showForm.value = false
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally {
    saving.value = false
  }
}

async function complete(appt) {
  if (!confirm(`Mark "${appt.service_name}" for ${appt.client_name} as completed?`)) return
  try {
    await post(`/api/appointments/${appt.id}/complete`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

async function cancel(appt) {
  if (!confirm(`Cancel this appointment with ${appt.client_name}?`)) return
  try {
    await post(`/api/appointments/${appt.id}/cancel`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

async function noShow(appt) {
  if (!confirm(`Mark ${appt.client_name} as a no-show?`)) return
  try {
    await post(`/api/appointments/${appt.id}/no-show`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

onMounted(async () => {
  await Promise.all([load(), loadSelectData()])
})
</script>
