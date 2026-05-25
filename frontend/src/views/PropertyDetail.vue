<template>
  <div>
    <RouterLink to="/properties" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 mb-6">
      ← {{ product.terminology.properties }}
    </RouterLink>

    <header class="mb-8 flex justify-between items-start">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ property.name }}</h1>
        <p v-if="property.address_line1" class="text-sm text-slate-500 mt-1">
          {{ property.address_line1 }}, {{ property.city }}, {{ property.state }} {{ property.zip }}
        </p>
      </div>
      <button @click="showUnitForm = true"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        + Add {{ product.terminology.unit.toLowerCase() }}
      </button>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <template v-else>
      <div v-if="units.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
          <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold tracking-wider">
            <tr>
              <th class="px-6 py-3">{{ product.terminology.unit }}</th>
              <th v-if="isLandlord" class="px-6 py-3">{{ product.terminology.rent }}</th>
              <th v-if="isLandlord" class="px-6 py-3">Due day</th>
              <th class="px-6 py-3">{{ product.terminology.tenant }}</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm">
            <tr v-for="u in units" :key="u.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4 font-semibold text-slate-900">{{ u.unit_label }}</td>
              <td v-if="isLandlord" class="px-6 py-4 text-slate-700">${{ (u.monthly_rent_cents / 100).toLocaleString() }}</td>
              <td v-if="isLandlord" class="px-6 py-4 text-slate-500">{{ u.due_day }}{{ ordinal(u.due_day) }} of month</td>
              <td class="px-6 py-4">
                <RouterLink v-if="u.tenant_id" :to="`/tenants/${u.tenant_id}`" class="text-blue-600 hover:text-blue-700">
                  {{ u.tenant_name }}
                </RouterLink>
                <span v-else class="text-slate-400 italic">Unassigned</span>
              </td>
              <td class="px-6 py-4">
                <span :class="u.is_active
                  ? 'px-2.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold'
                  : 'px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-semibold'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex gap-2 justify-end">
                  <button @click="openEditUnit(u)"
                    class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">Edit</button>
                  <button @click="removeUnit(u)"
                    class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">Remove</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-50 mb-4">
          <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </div>
        <p class="font-bold text-slate-800 mb-2">Step 2: Add a {{ product.terminology.unit.toLowerCase() }}</p>
        <p class="text-sm text-slate-500 max-w-sm mx-auto mb-6 leading-relaxed">
          Your {{ product.terminology.property.toLowerCase() }} is set up — now add at least one {{ product.terminology.unit.toLowerCase() }}
          so you can assign {{ product.terminology.tenants.toLowerCase() }} and send reminders.
        </p>
        <button @click="showUnitForm = true"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
          + Add {{ product.terminology.unit.toLowerCase() }}
        </button>
      </div>
    </template>

    <!-- Add unit modal -->
    <div v-if="showUnitForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showUnitForm = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Add {{ product.terminology.unit.toLowerCase() }}</h3>
        <form @submit.prevent="saveUnit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ product.terminology.unit }} label</label>
            <input v-model="unitForm.unit_label" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <template v-if="isLandlord">
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Monthly rent ($)</label>
                <input v-model="unitForm.monthly_rent" type="number" min="1" step="0.01" required
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Due day</label>
                <input v-model="unitForm.due_day" type="number" min="1" max="28" required
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Grace days</label>
                <input v-model="unitForm.grace_days" type="number" min="0" max="30"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Late fee type</label>
                <select v-model="unitForm.late_fee_type"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="none">None</option>
                  <option value="flat">Flat ($)</option>
                  <option value="percent">Percent (%)</option>
                </select>
              </div>
              <div v-if="unitForm.late_fee_type !== 'none'">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Late fee value</label>
                <input v-model="unitForm.late_fee_value" type="number" min="0" step="0.01"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
            </div>
          </template>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showUnitForm = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="savingUnit"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ savingUnit ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit unit modal -->
    <div v-if="showEditUnit" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showEditUnit = false">
      <div class="bg-white rounded-2xl p-8 w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Edit {{ product.terminology.unit.toLowerCase() }}</h3>
        <form @submit.prevent="saveEditUnit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ product.terminology.unit }} label</label>
            <input v-model="editUnitForm.unit_label" required
              class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          </div>
          <template v-if="isLandlord">
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Monthly rent ($)</label>
                <input v-model="editUnitForm.monthly_rent" type="number" min="1" step="0.01" required
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Due day</label>
                <input v-model="editUnitForm.due_day" type="number" min="1" max="28" required
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Grace days</label>
                <input v-model="editUnitForm.grace_days" type="number" min="0" max="30"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Late fee type</label>
                <select v-model="editUnitForm.late_fee_type"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="none">None</option>
                  <option value="flat">Flat ($)</option>
                  <option value="percent">Percent (%)</option>
                </select>
              </div>
              <div v-if="editUnitForm.late_fee_type !== 'none'">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Late fee value</label>
                <input v-model="editUnitForm.late_fee_value" type="number" min="0" step="0.01"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
            </div>
          </template>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="showEditUnit = false"
              class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
              Cancel
            </button>
            <button :disabled="savingEditUnit"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg transition-colors">
              {{ savingEditUnit ? 'Saving…' : 'Save changes' }}
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
import { get, post, del } from '../composables/useApi.js'
import { product } from '../composables/useAccount.js'

const route           = useRoute()
const id              = route.params.id
const property        = ref({})
const units           = ref([])
const loading         = ref(true)
const showUnitForm    = ref(false)
const savingUnit      = ref(false)
const showEditUnit    = ref(false)
const savingEditUnit  = ref(false)
const editingUnitId   = ref(null)

const isLandlord = computed(() => product.value.id === 'landlords')

const unitForm     = ref({ unit_label: '', monthly_rent: '', due_day: 1, grace_days: 5, late_fee_type: 'none', late_fee_value: 0 })
const editUnitForm = ref({})

function ordinal(n) {
  const s = ['th', 'st', 'nd', 'rd']
  const v = n % 100
  return s[(v - 20) % 10] || s[v] || s[0]
}

async function load() {
  loading.value = true
  try {
    const data = await get(`/api/properties/${id}`)
    property.value = data
    units.value    = data.units ?? []
  } catch (err) {
    alert('Error loading: ' + err.message)
  } finally { loading.value = false }
}

async function saveUnit() {
  savingUnit.value = true
  try {
    await post('/api/units', { ...unitForm.value, property_id: parseInt(id) })
    showUnitForm.value = false
    unitForm.value = { unit_label: '', monthly_rent: '', due_day: 1, grace_days: 5, late_fee_type: 'none', late_fee_value: 0 }
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally { savingUnit.value = false }
}

function openEditUnit(u) {
  editingUnitId.value = u.id
  editUnitForm.value = {
    unit_label:     u.unit_label,
    monthly_rent:   (u.monthly_rent_cents / 100).toFixed(2),
    due_day:        u.due_day,
    grace_days:     u.grace_days,
    late_fee_type:  u.late_fee_type,
    late_fee_value: u.late_fee_value,
    is_active:      u.is_active,
  }
  showEditUnit.value = true
}

async function saveEditUnit() {
  savingEditUnit.value = true
  try {
    await post(`/api/units/${editingUnitId.value}`, editUnitForm.value)
    showEditUnit.value = false
    load()
  } catch (err) {
    alert('Error saving: ' + err.message)
  } finally { savingEditUnit.value = false }
}

async function removeUnit(u) {
  if (!confirm(`Remove ${product.value.terminology.unit.toLowerCase()} "${u.unit_label}"?`)) return
  try {
    await del(`/api/units/${u.id}`)
    load()
  } catch (err) {
    alert('Error removing: ' + err.message)
  }
}

onMounted(async () => {
  await load()
  if (new URLSearchParams(window.location.search).get('new') === '1') {
    showUnitForm.value = true
    history.replaceState({}, '', window.location.pathname)
  }
})
</script>
