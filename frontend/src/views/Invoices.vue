<template>
  <div>
    <header class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Invoices</h1>
        <p class="text-sm text-slate-500 mt-1">Create and send invoices to your clients</p>
      </div>
      <router-link to="/invoices/new"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        + New invoice
      </router-link>
    </header>

    <!-- Status filter tabs -->
    <div class="flex gap-2 mb-6">
      <button v-for="tab in tabs" :key="tab.value"
        @click="filterStatus = tab.value; load()"
        :class="filterStatus === tab.value
          ? 'bg-blue-600 text-white'
          : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <div v-else-if="!invoices.length" class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-500 text-sm">
      No invoices yet.
      <router-link to="/invoices/new" class="text-blue-600 hover:text-blue-700 underline ml-1">Create one</router-link>
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
          <tr>
            <th class="px-6 py-3">Invoice #</th>
            <th class="px-6 py-3">Client</th>
            <th class="px-6 py-3">Due Date</th>
            <th class="px-6 py-3">Total</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
          <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4">
              <router-link :to="`/invoices/${inv.id}`" class="font-mono text-blue-600 hover:text-blue-700 font-semibold">
                {{ inv.invoice_number }}
              </router-link>
            </td>
            <td class="px-6 py-4 font-medium text-slate-900">{{ inv.client_name }}</td>
            <td class="px-6 py-4 text-slate-600">{{ inv.due_date ? fmtDate(inv.due_date) : '—' }}</td>
            <td class="px-6 py-4 font-semibold text-slate-900">{{ fmt(inv.total_cents) }}</td>
            <td class="px-6 py-4">
              <span :class="statusClass(inv.status)" class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize">
                {{ inv.status }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex gap-2 justify-end">
                <router-link :to="`/invoices/${inv.id}`"
                  class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg transition-colors">
                  Edit
                </router-link>
                <button v-if="inv.status !== 'paid'" @click="send(inv)"
                  class="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition-colors">
                  {{ inv.status === 'sent' ? 'Resend' : 'Send' }}
                </button>
                <button v-if="inv.status === 'sent'" @click="markPaid(inv)"
                  class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors">
                  Mark paid
                </button>
                <a :href="`/api/invoices/${inv.id}/download`" target="_blank"
                  class="px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-700 transition-colors">
                  PDF
                </a>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'

const loading      = ref(true)
const invoices     = ref([])
const filterStatus = ref('')

const tabs = [
  { label: 'All',   value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Sent',  value: 'sent' },
  { label: 'Paid',  value: 'paid' },
]

function fmt(cents) {
  return '$' + ((cents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })
}

function fmtDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusClass(status) {
  return {
    draft: 'bg-slate-100 text-slate-600',
    sent:  'bg-blue-50 text-blue-700',
    paid:  'bg-emerald-50 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-500'
}

async function load() {
  loading.value = true
  try {
    const qs = filterStatus.value ? `?status=${filterStatus.value}` : ''
    invoices.value = await get('/api/invoices' + qs)
  } catch (err) {
    alert('Error loading invoices: ' + err.message)
  } finally {
    loading.value = false
  }
}

async function send(inv) {
  if (!confirm(`Send invoice ${inv.invoice_number} to ${inv.client_name} (${inv.client_email})?`)) return
  try {
    await post(`/api/invoices/${inv.id}/send`, {})
    load()
  } catch (err) {
    alert('Error sending invoice: ' + err.message)
  }
}

async function markPaid(inv) {
  if (!confirm(`Mark invoice ${inv.invoice_number} as paid?`)) return
  try {
    await post(`/api/invoices/${inv.id}/mark-paid`, {})
    load()
  } catch (err) {
    alert('Error: ' + err.message)
  }
}

onMounted(load)
</script>
