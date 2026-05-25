<template>
  <div>
    <header class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
      <p class="text-sm text-slate-500 font-medium">{{ currentMonth }}</p>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <template v-else>
      <!-- Stat cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Collected This Month</p>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-bold text-emerald-600">{{ fmt(data.summary.collected) }}</span>
          </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Outstanding This Month</p>
          <span class="text-3xl font-bold text-red-500">{{ fmt(data.summary.outstanding) }}</span>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Late {{ product.terminology.tenants }}</p>
          <span class="text-3xl font-bold" :class="data.summary.late_count > 0 ? 'text-red-500' : 'text-slate-900'">
            {{ data.summary.late_count }}
          </span>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Due in 7 Days</p>
          <span class="text-3xl font-bold text-blue-600">{{ data.summary.upcoming }}</span>
        </div>
      </div>

      <!-- Late charges -->
      <section v-if="data.late_charges.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 class="font-bold text-slate-800">Current &amp; Past Due Charges</h2>
          <RouterLink to="/tenants" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all {{ product.terminology.tenants.toLowerCase() }} →</RouterLink>
        </div>
        <table class="w-full text-left border-collapse">
          <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
            <tr>
              <th class="px-6 py-3">{{ product.terminology.tenant }}</th>
              <th class="px-6 py-3">{{ product.terminology.property }}</th>
              <th class="px-6 py-3">Period</th>
              <th class="px-6 py-3">Due</th>
              <th class="px-6 py-3">Amount</th>
              <th class="px-6 py-3">Late fee</th>
              <th class="px-6 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm">
            <tr v-for="c in data.late_charges" :key="c.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4 font-medium text-slate-900">{{ c.tenant_name }}</td>
              <td class="px-6 py-4 text-slate-500">{{ c.property_name }} – {{ c.unit_label }}</td>
              <td class="px-6 py-4 text-slate-500">{{ formatPeriod(c.period_month) }}</td>
              <td class="px-6 py-4 text-slate-500">{{ c.due_date }}</td>
              <td class="px-6 py-4 font-semibold text-slate-900">{{ fmt(c.amount_cents) }}</td>
              <td class="px-6 py-4 text-slate-500">{{ c.late_fee_cents ? fmt(c.late_fee_cents) : '–' }}</td>
              <td class="px-6 py-4 text-right">
                <button @click="markPaid(c)"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm transition-colors">
                  Mark paid
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <div v-if="!data.late_charges.length && data.property_count === 0"
        class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <p class="text-slate-500 text-sm">No data yet.
          <RouterLink to="/properties" class="text-blue-600 hover:text-blue-700">Add your first {{ product.terminology.property.toLowerCase() }}</RouterLink>
          to get started.
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'
import { setProductType, product } from '../composables/useAccount.js'

const loading = ref(true)
const data    = ref({ summary: {}, late_charges: [], property_count: 0 })

const currentMonth = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })

function fmt(cents) {
  return '$' + ((cents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })
}

function formatPeriod(yearMonth) {
  if (!yearMonth) return '–'
  const [year, month] = yearMonth.split('-')
  const date = new Date(year, parseInt(month) - 1, 1)
  return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
}

async function load() {
  loading.value = true
  try {
    data.value = await get('/api/dashboard')
    setProductType(data.value.product_type)
  } finally { loading.value = false }
}

async function markPaid(charge) {
  await post(`/api/rent/${charge.id}/paid`, {})
  load()
}

onMounted(load)
</script>
