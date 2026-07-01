<template>
  <div>
    <header class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Admin — Accounts</h1>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading...</div>
    <div v-else-if="error" class="text-red-600 text-sm">{{ error }}</div>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">ID</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Business</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Vertical</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Plan</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Trial ends</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Created</th>
            <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="acc in accounts"
            :key="acc.id"
            class="hover:bg-slate-50 transition-colors"
          >
            <td class="px-4 py-3 text-slate-400 text-xs">{{ acc.id }}</td>
            <td class="px-4 py-3 text-slate-700 font-medium">{{ acc.owner_email || '—' }}</td>
            <td class="px-4 py-3 text-slate-800">{{ acc.name }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ acc.product_type }}</td>

            <!-- Plan -->
            <td class="px-4 py-3">
              <select
                v-model="acc._plan"
                class="text-sm text-slate-800 bg-white border border-slate-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="trial">trial</option>
                <option value="starter">starter</option>
                <option value="pro">pro</option>
              </select>
            </td>

            <!-- Status -->
            <td class="px-4 py-3">
              <select
                v-model="acc._status"
                class="text-sm text-slate-800 bg-white border border-slate-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="trialing">trialing</option>
                <option value="active">active</option>
                <option value="past_due">past_due</option>
                <option value="canceled">canceled</option>
              </select>
            </td>

            <!-- Trial ends -->
            <td class="px-4 py-3">
              <input
                type="date"
                v-model="acc._trial_ends"
                class="text-sm text-slate-800 bg-white border border-slate-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 w-36"
              />
            </td>

            <td class="px-4 py-3 text-slate-400 text-xs">{{ acc.created_at?.slice(0, 10) }}</td>

            <!-- Actions -->
            <td class="px-4 py-3">
              <div class="flex gap-2 flex-wrap">
                <button
                  @click="save(acc)"
                  :disabled="saving === acc.id"
                  class="px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50 transition-colors"
                >
                  Save
                </button>
                <button
                  @click="extendTrial(acc, 14)"
                  class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors border border-slate-200"
                >
                  +14d
                </button>
                <button
                  @click="extendTrial(acc, 30)"
                  class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors border border-slate-200"
                >
                  +30d
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="saveMsg" class="mt-4 text-sm font-medium" :class="saveMsg.startsWith('Error') ? 'text-red-600' : 'text-emerald-600'">
      {{ saveMsg }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'

const accounts = ref([])
const loading  = ref(true)
const error    = ref('')
const saving   = ref(null)
const saveMsg  = ref('')

onMounted(async () => {
  try {
    const data = await get('/api/admin/accounts')
    accounts.value = data.map(a => ({
      ...a,
      _plan:       a.plan,
      _status:     a.subscription_status,
      _trial_ends: a.trial_ends_at ? a.trial_ends_at.slice(0, 10) : '',
    }))
  } catch (e) {
    error.value = 'Failed to load accounts'
  } finally {
    loading.value = false
  }
})

async function save(acc) {
  saving.value = acc.id
  saveMsg.value = ''
  try {
    await post(`/api/admin/accounts/${acc.id}`, {
      plan:                acc._plan,
      subscription_status: acc._status,
      trial_ends_at:       acc._trial_ends || null,
    })
    acc.plan                = acc._plan
    acc.subscription_status = acc._status
    acc.trial_ends_at       = acc._trial_ends
    saveMsg.value = `Account ${acc.id} updated.`
  } catch (e) {
    saveMsg.value = `Error: ${e.message}`
  } finally {
    saving.value = null
  }
}

function extendTrial(acc, days) {
  const base = acc._trial_ends ? new Date(acc._trial_ends) : new Date()
  base.setDate(base.getDate() + days)
  acc._trial_ends = base.toISOString().slice(0, 10)
  acc._plan   = 'trial'
  acc._status = 'trialing'
}
</script>
