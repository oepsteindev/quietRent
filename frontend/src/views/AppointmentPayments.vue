<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <h1 class="text-2xl font-bold text-slate-900">Payments</h1>
      <div class="flex items-center gap-3">
        <button @click="prevMonth"
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-bold shadow-sm transition-colors">
          ‹
        </button>
        <span class="text-sm font-semibold text-slate-700 min-w-24 text-center">{{ monthLabel }}</span>
        <button @click="nextMonth"
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-bold shadow-sm transition-colors">
          ›
        </button>
      </div>
    </header>

    <!-- Summary strip -->
    <div v-if="!loading && rows.length" class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Collected</p>
        <p class="text-xl font-bold text-emerald-600">${{ collected }}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Outstanding</p>
        <p class="text-xl font-bold text-amber-600">${{ outstanding }}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Waived</p>
        <p class="text-xl font-bold text-slate-400">${{ waived }}</p>
      </div>
    </div>

    <!-- Status filters -->
    <div class="flex gap-2 mb-6 flex-wrap">
      <button v-for="s in statuses" :key="s.value"
        @click="filter = s.value"
        :class="filter === s.value
          ? 'px-4 py-1.5 rounded-full text-xs font-semibold bg-slate-900 text-white border border-slate-900'
          : 'px-4 py-1.5 rounded-full text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors'">
        {{ s.label }}
      </button>
    </div>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <div v-else-if="filtered.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[700px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
          <tr>
            <th class="px-6 py-3">Client</th>
            <th class="px-6 py-3">Service</th>
            <th class="px-6 py-3">Stylist</th>
            <th class="px-6 py-3">Date</th>
            <th class="px-6 py-3">Fee</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="r in filtered" :key="r.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-medium">
              <RouterLink :to="`/tenants/${r.client_id}`" class="text-blue-600 hover:text-blue-700">{{ r.client_name }}</RouterLink>
            </td>
            <td class="px-6 py-4 text-slate-600">{{ r.service_name }}</td>
            <td class="px-6 py-4 text-slate-500">{{ r.stylist_name }}</td>
            <td class="px-6 py-4 text-slate-500">{{ formatDate(r.appointment_at) }}</td>
            <td class="px-6 py-4 font-semibold text-slate-900">
              {{ r.fee_cents ? '$' + (r.fee_cents / 100).toLocaleString() : '—' }}
            </td>
            <td class="px-6 py-4">
              <span :class="statusClass(r.payment_status)">{{ statusLabel(r.payment_status) }}</span>
            </td>
            <td class="px-6 py-4 text-right">
              <div v-if="r.payment_status === 'unpaid' && r.fee_cents" class="flex gap-2 justify-end">
                <button @click="markPaid(r)"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-colors">
                  Mark paid
                </button>
                <button @click="waive(r)"
                  class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                  Waive
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-400 text-sm">
      No appointments for this period.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'

const rows    = ref([])
const loading = ref(true)
const filter  = ref('all')
const month   = ref(new Date().toISOString().slice(0, 7))

const statuses = [
  { value: 'all',    label: 'All' },
  { value: 'unpaid', label: 'Unpaid' },
  { value: 'paid',   label: 'Paid' },
  { value: 'waived', label: 'Waived' },
]

const filtered = computed(() =>
  filter.value === 'all' ? rows.value : rows.value.filter(r => r.payment_status === filter.value)
)

const collected  = computed(() => fmt(rows.value.filter(r => r.payment_status === 'paid').reduce((s, r) => s + r.fee_cents, 0)))
const outstanding = computed(() => fmt(rows.value.filter(r => r.payment_status === 'unpaid').reduce((s, r) => s + r.fee_cents, 0)))
const waived      = computed(() => fmt(rows.value.filter(r => r.payment_status === 'waived').reduce((s, r) => s + r.fee_cents, 0)))

function fmt(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const monthLabel = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  return new Date(y, m - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' })
})

function statusClass(s) {
  const map = {
    paid:   'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold',
    unpaid: 'px-2.5 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs font-semibold',
    waived: 'px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold',
  }
  return map[s] ?? map.unpaid
}

function statusLabel(s) {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function formatDate(dt) {
  return new Date(dt).toLocaleDateString('default', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

function prevMonth() {
  const [y, m] = month.value.split('-').map(Number)
  const d = new Date(y, m - 2, 1)
  month.value = d.toISOString().slice(0, 7)
  load()
}

function nextMonth() {
  const [y, m] = month.value.split('-').map(Number)
  const d = new Date(y, m, 1)
  month.value = d.toISOString().slice(0, 7)
  load()
}

async function load() {
  loading.value = true
  try { rows.value = await get(`/api/appointment-payments?month=${month.value}`) }
  catch (err) { alert('Error loading payments: ' + err.message) }
  finally { loading.value = false }
}

async function markPaid(r) {
  try {
    await post(`/api/appointment-payments/${r.id}/paid`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

async function waive(r) {
  if (!confirm('Waive fee for this appointment?')) return
  try {
    await post(`/api/appointment-payments/${r.id}/waive`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

onMounted(load)
</script>
