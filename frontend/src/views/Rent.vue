<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <h1 class="text-2xl font-bold text-slate-900">Rent</h1>
      <div class="flex items-center gap-3">
        <button @click="prevMonth"
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-bold shadow-sm transition-colors">
          ‹
        </button>
        <span class="text-sm font-semibold text-slate-700 min-w-24 text-center">{{ month }}</span>
        <button @click="nextMonth"
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-bold shadow-sm transition-colors">
          ›
        </button>
      </div>
    </header>

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
            <th class="px-6 py-3">Tenant</th>
            <th class="px-6 py-3">Unit</th>
            <th class="px-6 py-3">Due</th>
            <th class="px-6 py-3">Rent</th>
            <th class="px-6 py-3">Late fee</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Alerts sent</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="c in filtered" :key="c.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-medium">
              <RouterLink :to="`/tenants/${c.tenant_id}`" class="text-blue-600 hover:text-blue-700">{{ c.tenant_name }}</RouterLink>
            </td>
            <td class="px-6 py-4 text-slate-500">{{ c.property_name }} – {{ c.unit_label }}</td>
            <td class="px-6 py-4 text-slate-500">{{ c.due_date }}</td>
            <td class="px-6 py-4 font-semibold text-slate-900">${{ (c.amount_cents / 100).toLocaleString() }}</td>
            <td class="px-6 py-4 text-slate-500">{{ c.late_fee_cents ? '$' + (c.late_fee_cents / 100).toLocaleString() : '–' }}</td>
            <td class="px-6 py-4">
              <span :class="statusClass(c.status)">{{ c.status }}</span>
            </td>
            <td class="px-6 py-4">
              <div v-if="c.alerts_sent && c.alerts_sent.length" class="flex flex-col gap-1">
                <div v-for="a in c.alerts_sent" :key="a.stage + a.channel"
                  class="flex items-center gap-1.5 text-xs text-slate-600">
                  <span :class="a.status === 'sent' ? 'w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0' : 'w-1.5 h-1.5 rounded-full bg-red-400 shrink-0'"></span>
                  <span class="font-medium text-slate-700">{{ stageLabel(a.stage) }}</span>
                  <span class="text-slate-400">·</span>
                  <span class="capitalize">{{ a.channel }}</span>
                  <span class="text-slate-400">·</span>
                  <span class="text-slate-500">{{ formatSentAt(a.sent_at) }}</span>
                </div>
              </div>
              <span v-else class="text-slate-300 text-xs">–</span>
            </td>
            <td class="px-6 py-4 text-right">
              <div v-if="c.status !== 'paid' && c.status !== 'waived'" class="flex gap-2 justify-end">
                <button @click="markPaid(c)"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-colors">
                  Mark paid
                </button>
                <button @click="waive(c)"
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
      No charges for this period.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'

const charges = ref([])
const loading = ref(true)
const filter  = ref('all')
const month   = ref(new Date().toISOString().slice(0, 7))

const statuses = [
  { value: 'all',      label: 'All' },
  { value: 'upcoming', label: 'Upcoming' },
  { value: 'due',      label: 'Due' },
  { value: 'late',     label: 'Late' },
  { value: 'paid',     label: 'Paid' },
]

const filtered = computed(() =>
  filter.value === 'all' ? charges.value : charges.value.filter(c => c.status === filter.value)
)

function statusClass(status) {
  const map = {
    paid:     'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold capitalize',
    due:      'px-2.5 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs font-semibold capitalize',
    late:     'px-2.5 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold capitalize',
    upcoming: 'px-2.5 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold capitalize',
    waived:   'px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold capitalize',
  }
  return map[status] ?? 'px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold capitalize'
}

const stageLabels = {
  pre_due: 'Pre-due',
  due_day: 'Due day',
  late_1:  '1-day late',
  late_5:  '5-day late',
}

function stageLabel(stage) {
  return stageLabels[stage] ?? stage
}

function formatSentAt(sentAt) {
  if (!sentAt) return ''
  const d = new Date(sentAt.replace(' ', 'T') + 'Z')
  return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
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
  try { charges.value = await get(`/api/rent?month=${month.value}`) }
  catch (err) { alert('Error loading rent charges: ' + err.message) }
  finally { loading.value = false }
}

async function markPaid(c) {
  try {
    await post(`/api/rent/${c.id}/paid`, {})
    load()
  } catch (err) {
    alert('Error marking paid: ' + err.message)
  }
}

async function waive(c) {
  if (!confirm('Waive this charge?')) return
  try {
    await post(`/api/rent/${c.id}/waive`, {})
    load()
  } catch (err) {
    alert('Error waiving charge: ' + err.message)
  }
}

onMounted(load)
</script>
