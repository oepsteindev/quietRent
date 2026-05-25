<template>
  <div class="max-w-3xl">
    <header class="mb-8 flex justify-between items-center">
      <div>
        <router-link to="/invoices" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
          ← Invoices
        </router-link>
        <h1 class="text-2xl font-bold text-slate-900 mt-1">
          {{ isNew ? 'New Invoice' : invoice.invoice_number }}
        </h1>
      </div>
      <span v-if="!isNew" :class="statusClass(invoice.status)" class="px-3 py-1 rounded-full text-xs font-semibold capitalize">
        {{ invoice.status }}
      </span>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <form v-else @submit.prevent="save" class="space-y-6">

      <!-- Client & Job -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-bold text-slate-700 mb-4">Invoice details</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Client <span class="text-red-400">*</span></label>
            <select v-model="form.client_id" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Select client…</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.full_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Related job <span class="font-normal text-slate-400">(optional)</span></label>
            <select v-model="form.job_id"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">None</option>
              <option v-for="j in jobs" :key="j.id" :value="j.id">{{ j.job_type }} – {{ fmtDate(j.scheduled_at) }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Due date <span class="font-normal text-slate-400">(optional)</span></label>
            <input v-model="form.due_date" type="date"
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Notes <span class="font-normal text-slate-400">(optional — appears on invoice)</span></label>
          <textarea v-model="form.notes" rows="2"
            placeholder="e.g. Payment due within 30 days. Thank you for your business."
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
        </div>
      </div>

      <!-- Line items -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-sm font-bold text-slate-700">Line items</h2>
          <button type="button" @click="addLine"
            class="text-sm text-blue-600 hover:text-blue-700 font-semibold transition-colors">
            + Add line
          </button>
        </div>

        <div v-if="!form.line_items.length" class="text-sm text-slate-400 py-4 text-center">
          No items yet — click "Add line" to start.
        </div>

        <div v-else>
          <!-- Header -->
          <div class="grid gap-2 mb-2" style="grid-template-columns: 1fr 80px 110px 90px 32px;">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Description</span>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Qty</span>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Unit price</span>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Amount</span>
            <span></span>
          </div>

          <!-- Rows -->
          <div v-for="(item, i) in form.line_items" :key="i"
            class="grid gap-2 mb-2 items-center" style="grid-template-columns: 1fr 80px 110px 90px 32px;">
            <input v-model="item.description" placeholder="Service or item description" required
              class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model.number="item.quantity" type="number" min="0.01" step="0.01" required
              class="px-3 py-2 border border-slate-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input v-model.number="item.unit_price_display" type="number" min="0" step="0.01" required
                class="w-full pl-6 pr-3 py-2 border border-slate-200 rounded-lg text-sm text-right focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="text-right text-sm font-semibold text-slate-700">
              {{ fmtAmount(item.quantity * item.unit_price_display) }}
            </div>
            <button type="button" @click="removeLine(i)"
              class="text-slate-300 hover:text-red-400 transition-colors text-lg leading-none">
              ×
            </button>
          </div>

          <!-- Total -->
          <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center gap-6">
              <span class="text-sm font-semibold text-slate-600">Total</span>
              <span class="text-xl font-bold text-slate-900">{{ fmtAmount(grandTotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 justify-between">
        <button v-if="!isNew" type="button" @click="deleteInvoice"
          class="px-4 py-2 text-sm font-semibold text-red-600 hover:text-red-700 transition-colors">
          Delete invoice
        </button>
        <div class="flex gap-3 ml-auto">
          <router-link to="/invoices"
            class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
            Cancel
          </router-link>
          <button type="submit" :disabled="saving"
            class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
            {{ saving ? 'Saving…' : 'Save draft' }}
          </button>
          <button v-if="!isNew" type="button" :disabled="sending" @click="sendInvoice"
            class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 rounded-lg transition-colors">
            {{ sending ? 'Sending…' : (invoice.status === 'sent' ? 'Resend' : 'Send to client') }}
          </button>
        </div>
      </div>

      <p v-if="saved" class="text-xs font-medium text-emerald-600 text-right">Saved.</p>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, post } from '../composables/useApi.js'

const route  = useRoute()
const router = useRouter()

const isNew   = computed(() => route.params.id === undefined || route.path === '/invoices/new')
const loading = ref(true)
const saving  = ref(false)
const sending = ref(false)
const saved   = ref(false)

const invoice = ref({})
const clients = ref([])
const jobs    = ref([])

const form = ref({
  client_id:  '',
  job_id:     '',
  due_date:   '',
  notes:      '',
  line_items: [],
})

const grandTotal = computed(() =>
  form.value.line_items.reduce((sum, item) => sum + (item.quantity || 0) * (item.unit_price_display || 0), 0)
)

function fmtAmount(dollars) {
  return '$' + (dollars || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(dt) {
  return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusClass(status) {
  return {
    draft: 'bg-slate-100 text-slate-600',
    sent:  'bg-blue-50 text-blue-700',
    paid:  'bg-emerald-50 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-500'
}

function addLine() {
  form.value.line_items.push({ description: '', quantity: 1, unit_price_display: 0 })
}

function removeLine(i) {
  form.value.line_items.splice(i, 1)
}

function lineItemsPayload() {
  return form.value.line_items.map(item => ({
    description:      item.description,
    quantity:         item.quantity,
    unit_price_cents: Math.round((item.unit_price_display || 0) * 100),
  }))
}

async function load() {
  loading.value = true
  try {
    const [c, j] = await Promise.all([get('/api/tenants'), get('/api/jobs')])
    clients.value = c
    jobs.value    = j.filter(jb => jb.status === 'scheduled' || jb.status === 'completed')

    if (!isNew.value) {
      invoice.value = await get(`/api/invoices/${route.params.id}`)
      form.value = {
        client_id:  invoice.value.client_id,
        job_id:     invoice.value.job_id ?? '',
        due_date:   invoice.value.due_date ?? '',
        notes:      invoice.value.notes ?? '',
        line_items: (invoice.value.line_items ?? []).map(li => ({
          description:        li.description,
          quantity:           parseFloat(li.quantity),
          unit_price_display: li.unit_price_cents / 100,
        })),
      }
    }
  } catch (err) {
    alert('Error loading: ' + err.message)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const payload = {
      client_id:  form.value.client_id,
      job_id:     form.value.job_id || null,
      due_date:   form.value.due_date || null,
      notes:      form.value.notes || null,
      line_items: lineItemsPayload(),
    }

    if (isNew.value) {
      const res = await post('/api/invoices', payload)
      router.replace(`/invoices/${res.id}`)
    } else {
      await post(`/api/invoices/${route.params.id}`, payload)
      invoice.value = await get(`/api/invoices/${route.params.id}`)
      saved.value = true
      setTimeout(() => { saved.value = false }, 2000)
    }
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally {
    saving.value = false
  }
}

async function sendInvoice() {
  if (!confirm(`Send this invoice to ${invoice.value.client_name}?`)) return
  sending.value = true
  try {
    await post(`/api/invoices/${route.params.id}/send`, {})
    invoice.value = await get(`/api/invoices/${route.params.id}`)
  } catch (err) {
    alert('Error sending: ' + err.message)
  } finally {
    sending.value = false
  }
}

async function deleteInvoice() {
  if (!confirm('Delete this invoice? This cannot be undone.')) return
  try {
    await post(`/api/invoices/${route.params.id}/delete`, {})
    router.push('/invoices')
  } catch (err) {
    alert('Error deleting: ' + err.message)
  }
}

onMounted(load)
</script>
