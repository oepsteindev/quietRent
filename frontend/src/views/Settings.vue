<template>
  <div>
    <header class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Settings</h1>
    </header>

    <div v-if="loading" class="text-slate-400 text-sm">Loading…</div>

    <template v-else>

      <!-- ── Hairdresser: Disclaimer ────────────────────────────── -->
      <section v-if="isHairdresser" class="mb-8">
        <div class="mb-4">
          <h2 class="text-base font-bold text-slate-800 mb-1">Cancellation / disclaimer</h2>
          <p class="text-sm text-slate-500">This text appears at the bottom of every email you send to clients. Use it for your cancellation policy, late fee notice, etc.</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <textarea v-model="disclaimer" rows="4" @blur="saveAccountSettings"
            placeholder="e.g. Cancellations must be made 24 hours in advance. Late cancellations may be charged 50% of the service fee."
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" />
          <p v-if="accountSaved" class="text-xs font-medium text-emerald-600 mt-2">Saved.</p>
        </div>
      </section>

      <!-- ── Payment link (both product types) ────────────────── -->
      <section class="mb-8">
        <div class="mb-4">
          <h2 class="text-base font-bold text-slate-800 mb-1">Payment link</h2>
          <p class="text-sm text-slate-500">
            {{ isHairdresser || isTradesman ? 'A link where clients can pay you (your own processor — Venmo, Square, etc.).' : 'Your rent payment URL (Venmo, Zelle, PayPal, etc.).' }}
            Optional — leave blank to omit from messages. Use <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{payment_link}</code> in your templates.
          </p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <input v-model="paymentLink" type="url" @blur="saveAccountSettings"
            placeholder="https://venmo.com/u/yourname"
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          <p v-if="accountSaved" class="text-xs font-medium text-emerald-600 mt-2">Saved.</p>
        </div>
      </section>

      <!-- ── Contact phone (both product types) ───────────────── -->
      <section class="mb-8">
        <div class="mb-4">
          <h2 class="text-base font-bold text-slate-800 mb-1">Contact phone</h2>
          <p class="text-sm text-slate-500">
            Your phone number that {{ isHairdresser || isTradesman ? 'clients' : 'tenants' }} can call with questions.
            Optional — leave blank to omit from messages. Use <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{contact_phone}</code> in your templates.
          </p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <input v-model="contactPhone" type="tel" @input="formatPhone" @blur="saveAccountSettings"
            placeholder="(555) 555-5555"
            class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
          <p v-if="accountSaved" class="text-xs font-medium text-emerald-600 mt-2">Saved.</p>
        </div>
      </section>

      <!-- ── Reminder templates ─────────────────────────────────── -->
      <section>
        <div class="mb-6">
          <h2 class="text-base font-bold text-slate-800 mb-1">Reminder templates</h2>
          <p class="text-sm text-slate-500 leading-relaxed">
            Customize the messages sent to your {{ isHairdresser || isTradesman ? 'clients' : 'tenants' }}.
            <template v-if="isHairdresser">
              Variables:
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{client_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{service_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{appointment_at}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{stylist_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{salon_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{fee_amount}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{disclaimer}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{business_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{payment_link}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{contact_phone}</code>
            </template>
            <template v-else-if="isTradesman">
              Variables:
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{client_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{job_type}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{scheduled_at}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{address}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{tradesman_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{company_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{estimated_cost}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{business_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{payment_link}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{contact_phone}</code>
            </template>
            <template v-else>
              Variables:
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{tenant_name}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{unit_label}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{rent_amount}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{due_date}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{late_fee_amount}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{total_due}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{payment_link}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{contact_phone}</code>
              <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{landlord_name}</code>
            </template>
          </p>
        </div>

        <!-- Hairdresser: appointment reminder rules -->
        <template v-if="isHairdresser">
          <div v-for="rule in apptRules" :key="rule.id"
            class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-5">
              <span class="font-semibold text-slate-800 text-sm flex-1">{{ apptStageLabel(rule.stage) }}</span>
              <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                <input type="checkbox" v-model="rule.is_active" @change="saveApptRule(rule)" class="rounded" />
                Active
              </label>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Subject</label>
                <input v-model="rule.subject" @blur="saveApptRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Body</label>
                <textarea v-model="rule.body" rows="7" @blur="saveApptRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" />
              </div>
            </div>
            <p v-if="rule._saved" class="text-xs font-medium text-emerald-600 mt-3">Saved.</p>
          </div>
        </template>

        <!-- Tradesmen: job reminder rules -->
        <template v-else-if="isTradesman">
          <div v-for="rule in jobRules" :key="rule.id"
            class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-5">
              <span class="font-semibold text-slate-800 text-sm flex-1">{{ jobStageLabel(rule.stage) }}</span>
              <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                <input type="checkbox" v-model="rule.is_active" @change="saveJobRule(rule)" class="rounded" />
                Active
              </label>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Subject</label>
                <input v-model="rule.subject" @blur="saveJobRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Body</label>
                <textarea v-model="rule.body" rows="7" @blur="saveJobRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" />
              </div>
            </div>
            <p v-if="rule._saved" class="text-xs font-medium text-emerald-600 mt-3">Saved.</p>
          </div>
        </template>

        <!-- Landlord: rent reminder rules -->
        <template v-else>
          <div v-for="rule in rentRules" :key="rule.id"
            class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-5">
              <span class="font-semibold text-slate-800 text-sm flex-1">{{ rentStageLabel(rule.stage) }}</span>
              <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold">
                Day {{ rule.day_offset >= 0 ? '+' : '' }}{{ rule.day_offset }}
              </span>
              <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                <input type="checkbox" v-model="rule.is_active" @change="saveRentRule(rule)" class="rounded" />
                Active
              </label>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Subject</label>
                <input v-model="rule.subject" @blur="saveRentRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Body</label>
                <textarea v-model="rule.body" rows="6" @blur="saveRentRule(rule)"
                  class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y" />
              </div>
            </div>
            <p v-if="rule._saved" class="text-xs font-medium text-emerald-600 mt-3">Saved.</p>
          </div>
        </template>
      </section>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { get, post } from '../composables/useApi.js'
import { product } from '../composables/useAccount.js'

const loading      = ref(true)
const rentRules    = ref([])
const apptRules    = ref([])
const jobRules     = ref([])
const paymentLink  = ref('')
const contactPhone = ref('')
const disclaimer   = ref('')
const accountSaved = ref(false)

const isHairdresser = computed(() => product.value.id === 'hairdressers')
const isTradesman   = computed(() => product.value.id === 'tradesmen')

const rentStageLabels = {
  pre_due: 'Pre-due reminder (3 days before)',
  due_day: 'Due today reminder',
  late_1:  'Late notice (1 day after)',
  late_5:  'Overdue notice (5 days after)',
}

const apptStageLabelMap = {
  confirmation:  'Booking confirmation (sent immediately)',
  reminder_30h:  '30-hour reminder',
  reminder_2h:   '2-hour reminder',
}

const jobStageLabelMap = {
  confirmation:  'Booking confirmation (sent immediately)',
  reminder_24h:  '24-hour reminder',
  reminder_2h:   '2-hour reminder',
  completion:    'Completion follow-up (opt-in)',
}

function rentStageLabel(s) { return rentStageLabels[s] ?? s }
function apptStageLabel(s) { return apptStageLabelMap[s] ?? s }
function jobStageLabel(s)  { return jobStageLabelMap[s]  ?? s }

function formatPhone(e) {
  const digits = e.target.value.replace(/\D/g, '').slice(0, 10)
  let formatted = digits
  if (digits.length > 6)      formatted = `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`
  else if (digits.length > 3) formatted = `(${digits.slice(0,3)}) ${digits.slice(3)}`
  else if (digits.length > 0) formatted = `(${digits}`
  contactPhone.value = formatted
}

async function load() {
  loading.value = true
  try {
    const [settings, rules, apptRulesData, jobRulesData] = await Promise.all([
      get('/api/account-settings'),
      (!isHairdresser.value && !isTradesman.value) ? get('/api/reminder-rules') : Promise.resolve([]),
      isHairdresser.value ? get('/api/appointment-reminder-rules') : Promise.resolve([]),
      isTradesman.value   ? get('/api/job-reminder-rules')         : Promise.resolve([]),
    ])
    paymentLink.value  = settings.payment_link  ?? ''
    contactPhone.value = settings.contact_phone ?? ''
    disclaimer.value   = settings.disclaimer    ?? ''
    rentRules.value   = rules
    apptRules.value   = apptRulesData
    jobRules.value    = jobRulesData
  } finally {
    loading.value = false
  }
}

async function saveAccountSettings() {
  await post('/api/account-settings', {
    payment_link:  paymentLink.value,
    contact_phone: contactPhone.value,
    disclaimer:    disclaimer.value,
  })
  accountSaved.value = true
  setTimeout(() => { accountSaved.value = false }, 2000)
}

async function saveRentRule(rule) {
  await post(`/api/reminder-rules/${rule.id}`, {
    subject:   rule.subject,
    body:      rule.body,
    is_active: rule.is_active ? 1 : 0,
  })
  rule._saved = true
  setTimeout(() => { rule._saved = false }, 2000)
}

async function saveApptRule(rule) {
  await post(`/api/appointment-reminder-rules/${rule.id}`, {
    subject:   rule.subject,
    body:      rule.body,
    is_active: rule.is_active ? 1 : 0,
  })
  rule._saved = true
  setTimeout(() => { rule._saved = false }, 2000)
}

async function saveJobRule(rule) {
  await post(`/api/job-reminder-rules/${rule.id}`, {
    subject:   rule.subject,
    body:      rule.body,
    is_active: rule.is_active ? 1 : 0,
  })
  rule._saved = true
  setTimeout(() => { rule._saved = false }, 2000)
}

onMounted(load)
</script>
