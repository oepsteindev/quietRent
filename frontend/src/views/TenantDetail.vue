<template>
  <div>
    <RouterLink to="/tenants" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 mb-6">
      ← {{ product.terminology.tenants }}
    </RouterLink>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <template v-else>
      <header class="mb-8 flex justify-between items-start">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">{{ tenant.full_name }}</h1>
          <p class="text-sm text-slate-500 mt-1">{{ tenant.property_name }} – {{ tenant.unit_label }}</p>
        </div>
        <div class="flex gap-2">
          <button @click="openEdit"
            class="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg shadow-sm transition-colors">
            Edit
          </button>
          <button @click="togglePause"
            :class="tenant.reminders_paused
              ? 'px-4 py-2 text-sm font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg shadow-sm transition-colors'
              : 'px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg shadow-sm transition-colors'">
            {{ tenant.reminders_paused ? 'Resume reminders' : 'Pause reminders' }}
          </button>
        </div>
      </header>

      <!-- Info grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</p>
          <p class="text-sm text-slate-800 font-medium break-all">{{ tenant.email }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Phone</p>
          <p class="text-sm text-slate-800 font-medium">{{ tenant.phone || '–' }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Preferred contact</p>
          <p class="text-sm text-slate-800 font-medium capitalize">{{ tenant.preferred_channel }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Reminders</p>
          <span :class="tenant.reminders_paused
            ? 'px-2.5 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs font-semibold'
            : 'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold'">
            {{ tenant.reminders_paused ? 'Paused' : 'Active' }}
          </span>
        </div>
      </div>

      <!-- Lease / Rental section (landlords only) -->
      <section v-if="isLandlord" class="mb-8">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-base font-bold text-slate-800">Lease / Rental</h2>
          <button v-if="!activeLease" @click="showLeaseForm = true"
            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-colors">
            + Add lease / rental
          </button>
        </div>

        <div v-if="activeLease" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-center">
          <div class="flex gap-8">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Start date</p>
              <p class="text-sm font-medium text-slate-800">{{ activeLease.start_date }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">End date</p>
              <p class="text-sm font-medium text-slate-800">{{ activeLease.end_date || 'Month-to-month' }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</p>
              <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold">Active</span>
            </div>
          </div>
          <button @click="endLease(activeLease)"
            class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
            End lease
          </button>
        </div>

        <div v-else-if="!showLeaseForm" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-sm text-slate-500">
          No active lease or rental.
          <button @click="showLeaseForm = true" class="text-blue-600 hover:text-blue-700 underline ml-1">Add one</button>
          to start billing.
        </div>

        <div v-if="showLeaseForm" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mt-3">
          <form @submit.prevent="saveLease" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Start date</label>
                <input v-model="leaseForm.start_date" type="date" required
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  End date <span class="normal-case font-normal text-slate-400">(blank = month-to-month)</span>
                </label>
                <input v-model="leaseForm.end_date" type="date"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
            </div>
            <div class="flex gap-3 justify-end">
              <button type="button" @click="showLeaseForm = false"
                class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                Cancel
              </button>
              <button :disabled="savingLease"
                class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
                {{ savingLease ? 'Saving…' : 'Save lease / rental' }}
              </button>
            </div>
          </form>
        </div>

        <div v-if="endedLeases.length" class="mt-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Past leases</p>
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
                <tr>
                  <th class="px-6 py-3">Start</th>
                  <th class="px-6 py-3">End</th>
                  <th class="px-6 py-3">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-sm">
                <tr v-for="l in endedLeases" :key="l.id" class="hover:bg-slate-50 transition-colors">
                  <td class="px-6 py-4 text-slate-700">{{ l.start_date }}</td>
                  <td class="px-6 py-4 text-slate-700">{{ l.end_date || '–' }}</td>
                  <td class="px-6 py-4">
                    <span class="px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold">Ended</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </template>

    <!-- Edit tenant modal -->
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
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { get, post } from '../composables/useApi.js'
import { product } from '../composables/useAccount.js'

const route    = useRoute()
const id       = route.params.id
const tenant   = ref({})
const leases   = ref([])
const loading  = ref(true)
const showEdit = ref(false)
const saving   = ref(false)
const editForm = ref({})

const showLeaseForm = ref(false)
const savingLease   = ref(false)
const leaseForm     = ref({ start_date: '', end_date: '' })

const isLandlord  = computed(() => product.value.id === 'landlords')
const activeLease = computed(() => leases.value.find(l => l.status === 'active') ?? null)
const endedLeases = computed(() => leases.value.filter(l => l.status !== 'active'))

async function load() {
  loading.value = true
  try {
    ;[tenant.value, leases.value] = await Promise.all([
      get(`/api/tenants/${id}`),
      get(`/api/leases/tenant/${id}`),
    ])
  } catch (err) {
    alert('Error loading: ' + err.message)
  } finally { loading.value = false }
}

function openEdit() {
  editForm.value = {
    full_name:         tenant.value.full_name,
    email:             tenant.value.email,
    phone:             tenant.value.phone || '',
    preferred_channel: tenant.value.preferred_channel,
  }
  showEdit.value = true
}

async function saveEdit() {
  saving.value = true
  try {
    await post(`/api/tenants/${id}`, editForm.value)
    showEdit.value = false
    load()
  } catch (err) {
    alert('Error saving tenant: ' + err.message)
  } finally { saving.value = false }
}

async function togglePause() {
  try {
    await post(`/api/tenants/${id}/pause`, {})
    load()
  } catch (err) {
    alert('Error toggling pause: ' + err.message)
  }
}

async function saveLease() {
  savingLease.value = true
  try {
    await post('/api/leases', {
      tenant_id:  parseInt(id),
      unit_id:    tenant.value.unit_id,
      start_date: leaseForm.value.start_date,
      end_date:   leaseForm.value.end_date || null,
    })
    showLeaseForm.value = false
    leaseForm.value = { start_date: '', end_date: '' }
    load()
  } catch (err) {
    alert('Error saving lease: ' + err.message)
  } finally { savingLease.value = false }
}

async function endLease(lease) {
  if (!confirm('End this lease / rental?')) return
  try {
    await post(`/api/leases/${lease.id}/end`, {})
    load()
  } catch (err) {
    alert('Error ending lease: ' + err.message)
  }
}

onMounted(load)
</script>
