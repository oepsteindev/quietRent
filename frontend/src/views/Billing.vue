<template>
  <div>
    <!-- Success modal -->
    <div v-if="showSuccess" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl p-8 max-w-md text-center shadow-2xl">
        <div class="text-6xl mb-4">✓</div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Payment successful!</h2>
        <p class="text-slate-600 mb-8">Your subscription is now active. You'll be charged on your next billing cycle.</p>
        <button @click="showSuccess = false; loadStatus()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">
          Continue
        </button>
      </div>
    </div>

    <!-- Cancel confirmation modal -->
    <div v-if="showCancelConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl p-8 max-w-md text-center shadow-2xl">
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Cancel subscription?</h2>
        <p class="text-slate-600 mb-6">Your subscription will continue until the end of your billing period ({{ formatDate(billingStatus?.current_period_end) }}), then be canceled.</p>
        <div class="flex gap-3">
          <button @click="showCancelConfirm = false" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-900 font-semibold py-3 rounded-lg">
            Keep subscription
          </button>
          <button @click="confirmCancel" :disabled="canceling" class="flex-1 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-semibold py-3 rounded-lg transition-colors">
            {{ canceling ? 'Canceling…' : 'Cancel' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Canceled modal -->
    <div v-if="showCanceled" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl p-8 max-w-md text-center shadow-2xl">
        <div class="text-6xl mb-4">ℹ</div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Checkout canceled</h2>
        <p class="text-slate-600 mb-8">You canceled checkout. Your plan hasn't changed.</p>
        <button @click="showCanceled = false" class="w-full bg-slate-600 hover:bg-slate-700 text-white font-semibold py-3 rounded-lg">
          Continue
        </button>
      </div>
    </div>

    <header class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Billing</h1>
      <p class="text-sm text-slate-500 font-medium mt-1">Manage your {{ product.shortName }} subscription.</p>
    </header>

    <!-- Current plan banner -->
    <div v-if="billingStatus" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex justify-between items-center">
      <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Current plan</p>
        <div class="flex items-center gap-3">
          <span class="text-lg font-bold text-slate-900">
            {{ currentPlan === 'trial' ? 'Free Trial' : capitalizeFirst(currentPlan) + ' Plan' }}
          </span>
          <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide', {
            'bg-amber-50 text-amber-700': currentPlan === 'trial',
            'bg-emerald-50 text-emerald-700': currentPlan === 'starter',
            'bg-blue-50 text-blue-700': currentPlan === 'pro',
          }]">
            {{ statusLabel }}
          </span>
        </div>
        <p class="text-sm text-slate-500 mt-1">
          {{ planDescription }}
        </p>
      </div>
      <div class="text-right">
        <p v-if="currentPlan === 'trial'" class="text-xs text-slate-400 font-medium">{{ trialDaysLeft }} days left</p>
        <p v-else class="text-xs text-slate-400 font-medium">Renews {{ formatDate(billingStatus.current_period_end) }}</p>
        <button v-if="isSubscribed && !billingStatus.cancel_at_period_end" @click="showCancelConfirm = true" class="text-xs text-red-600 hover:text-red-700 mt-3 font-semibold">
          Cancel subscription
        </button>
        <p v-else-if="billingStatus.cancel_at_period_end" class="text-xs text-red-600 font-semibold mt-2">
          Cancels {{ formatDate(billingStatus.current_period_end) }}
        </p>
      </div>
    </div>

    <div v-if="changePlanError" class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ changePlanError }}</div>

    <!-- Pricing cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

      <!-- Starter -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8 flex flex-col relative">
        <div v-if="currentPlan === 'starter'" class="absolute top-4 right-4">
          <span class="px-2.5 py-0.5 bg-emerald-600 text-white rounded-full text-xs font-bold uppercase tracking-wide">Current plan</span>
        </div>
        <div class="mb-6">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Starter</p>
          <div class="flex items-baseline gap-1">
            <span class="text-4xl font-bold text-slate-900">$19</span>
            <span class="text-sm text-slate-500 font-medium">/ month</span>
          </div>
          <p class="text-sm text-slate-500 mt-2">Perfect for individual landlords with a small portfolio.</p>
        </div>
        <ul class="space-y-3 mb-8 flex-1">
          <li v-for="f in starterFeatures" :key="f" class="flex items-center gap-2.5 text-sm text-slate-700">
            <span class="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
              <span class="text-emerald-600 text-xs font-bold">✓</span>
            </span>
            {{ f }}
          </li>
        </ul>
        <button
          v-if="!isSubscribed"
          @click="subscribe('starter')"
          :disabled="subscribing === 'starter'"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-3 rounded-lg text-sm transition-colors"
        >
          {{ subscribing === 'starter' ? 'Redirecting…' : 'Subscribe — $19/mo' }}
        </button>
        <button
          v-else-if="currentPlan !== 'starter'"
          @click="switchPlan('starter')"
          :disabled="changingPlan === 'starter'"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-3 rounded-lg text-sm transition-colors"
        >
          {{ changingPlan === 'starter' ? 'Switching…' : 'Switch to Starter' }}
        </button>
      </div>

      <!-- Pro -->
      <div class="bg-slate-900 rounded-xl shadow-sm p-8 flex flex-col relative overflow-hidden">
        <div class="absolute top-4 right-4">
          <span v-if="currentPlan === 'pro'" class="px-2.5 py-0.5 bg-emerald-600 text-white rounded-full text-xs font-bold uppercase tracking-wide">Current plan</span>
          <span v-else class="px-2.5 py-0.5 bg-blue-600 text-white rounded-full text-xs font-bold uppercase tracking-wide">Popular</span>
        </div>
        <div class="mb-6">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pro</p>
          <div class="flex items-baseline gap-1">
            <span class="text-4xl font-bold text-white">$49</span>
            <span class="text-sm text-slate-400 font-medium">/ month</span>
          </div>
          <p class="text-sm text-slate-400 mt-2">For growing landlords managing multiple properties.</p>
        </div>
        <ul class="space-y-3 mb-8 flex-1">
          <li v-for="f in proFeatures" :key="f" class="flex items-center gap-2.5 text-sm text-slate-300">
            <span class="w-4 h-4 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
              <span class="text-white text-xs font-bold">✓</span>
            </span>
            {{ f }}
          </li>
        </ul>
        <button
          v-if="!isSubscribed"
          @click="subscribe('pro')"
          :disabled="subscribing === 'pro'"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-3 rounded-lg text-sm transition-colors"
        >
          {{ subscribing === 'pro' ? 'Redirecting…' : 'Subscribe — $49/mo' }}
        </button>
        <button
          v-else-if="currentPlan !== 'pro'"
          @click="switchPlan('pro')"
          :disabled="changingPlan === 'pro'"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-3 rounded-lg text-sm transition-colors"
        >
          {{ changingPlan === 'pro' ? 'Switching…' : 'Switch to Pro' }}
        </button>
      </div>
    </div>

    <!-- Invoice history -->
    <div v-if="isSubscribed && invoices !== null" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
      <h2 class="text-lg font-bold text-slate-900 mb-4">Invoice history</h2>
      <div v-if="invoicesLoading" class="text-center py-8 text-slate-500">
        Loading invoices...
      </div>
      <div v-else-if="invoices.length === 0" class="text-center py-8 text-slate-500">
        No invoices yet
      </div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200">
          <tr class="text-xs font-semibold text-slate-600 uppercase tracking-wider">
            <th class="text-left py-3">Date</th>
            <th class="text-left py-3">Amount</th>
            <th class="text-left py-3">Status</th>
            <th class="text-right py-3">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="invoice in invoices" :key="invoice.id" class="text-slate-700 hover:bg-slate-50">
            <td class="py-3">{{ invoice.date }}</td>
            <td class="py-3">${{ invoice.amount }}</td>
            <td class="py-3">
              <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold', {
                'bg-emerald-50 text-emerald-700': invoice.status === 'paid',
                'bg-amber-50 text-amber-700': invoice.status === 'open',
                'bg-slate-100 text-slate-700': invoice.status === 'draft',
              }]">
                {{ capitalizeFirst(invoice.status) }}
              </span>
            </td>
            <td class="py-3 text-right">
              <a v-if="invoice.pdf_url" :href="invoice.pdf_url" target="_blank" class="text-blue-600 hover:text-blue-700 font-semibold text-xs">
                PDF
              </a>
              <span v-else class="text-slate-400 text-xs">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Stripe not configured notice -->
    <div v-if="stripeNotConfigured" class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex gap-4 items-start">
      <span class="text-amber-500 text-lg mt-0.5">⚠</span>
      <div>
        <p class="text-sm font-semibold text-amber-800 mb-1">Stripe not configured</p>
        <p class="text-sm text-amber-700">
          Add your Stripe keys to <code class="bg-amber-100 px-1.5 py-0.5 rounded text-xs">.env</code> to enable subscriptions:
        </p>
        <pre class="mt-2 bg-amber-100 rounded-lg px-4 py-3 text-xs text-amber-800 font-mono">STRIPE_PUBLISHABLE=pk_live_…
STRIPE_SECRET=sk_live_…
STRIPE_WEBHOOK_SECRET=whsec_…</pre>
      </div>
    </div>

    <!-- FAQ -->
    <div class="mt-8">
      <h2 class="text-base font-bold text-slate-800 mb-4">Frequently asked questions</h2>
      <div class="space-y-3">
        <div v-for="faq in faqs" :key="faq.q" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <p class="text-sm font-semibold text-slate-800 mb-1">{{ faq.q }}</p>
          <p class="text-sm text-slate-500 leading-relaxed">{{ faq.a }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get, post } from '../composables/useApi'
import { product } from '../composables/useAccount.js'

const router = useRouter()
const subscribing           = ref(null)
const stripeNotConfigured   = ref(false)
const showSuccess           = ref(false)
const showCanceled          = ref(false)
const showCancelConfirm     = ref(false)
const canceling             = ref(false)
const changingPlan          = ref(null)
const changePlanError       = ref('')
const billingStatus         = ref(null)
const invoices              = ref(null)
const invoicesLoading       = ref(false)

const starterFeatures = [
  'Up to 10 units',
  'Automated email reminders',
  'Lease & payment tracking',
  'CSV import',
  'Email support',
]

const proFeatures = [
  'Unlimited units',
  'Everything in Starter',
  'SMS reminders',
  'Priority support',
  'Custom reminder templates',
]

const faqs = [
  {
    q: 'Can I cancel anytime?',
    a: 'Yes. You can cancel your subscription at any time from this page. You\'ll keep access until the end of your billing period.',
  },
  {
    q: 'What happens when my trial ends?',
    a: 'Reminders will pause until you subscribe. Your data is always safe and you can subscribe at any time to resume.',
  },
  {
    q: 'Is my payment information secure?',
    a: 'All payments are processed by Stripe, a PCI-compliant payment processor. We never store your card details.',
  },
]

const isSubscribed = computed(() => billingStatus.value?.status === 'active')
const currentPlan = computed(() => billingStatus.value?.plan ?? 'trial')
const statusLabel = computed(() => {
  if (!billingStatus.value) return ''
  if (billingStatus.value.plan === 'trial') return 'Trial'
  if (billingStatus.value.cancel_at_period_end) return 'Canceling'
  return 'Active'
})
const planDescription = computed(() => {
  if (billingStatus.value?.plan === 'trial') {
    return 'Upgrade to keep reminders running after your trial ends.'
  }
  if (billingStatus.value?.cancel_at_period_end) {
    return `Your subscription will end on ${formatDate(billingStatus.value.current_period_end)}.`
  }
  return `Your next billing date is ${formatDate(billingStatus.value?.current_period_end)}.`
})
const trialDaysLeft = computed(() => {
  if (!billingStatus.value?.trial_ends_at) return 0
  const now = new Date()
  const ends = new Date(billingStatus.value.trial_ends_at)
  const days = Math.ceil((ends - now) / (1000 * 60 * 60 * 24))
  return Math.max(0, days)
})

function capitalizeFirst(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
  await checkUrlParams()
  await loadStatus()
  if (isSubscribed.value) {
    await loadInvoices()
  }
})

async function checkUrlParams() {
  const params = new URLSearchParams(window.location.search)
  if (params.has('success')) {
    showSuccess.value = true
    const sessionId = params.get('session_id')
    window.history.replaceState({}, '', '/billing')
    if (sessionId) {
      try {
        await get(`/api/billing/verify-session?session_id=${sessionId}`)
      } catch (e) {
        console.error('Session verify failed:', e)
      }
    }
    setTimeout(() => { showSuccess.value = false }, 15000)
  }
  if (params.has('canceled')) {
    showCanceled.value = true
    window.history.replaceState({}, '', '/billing')
    setTimeout(() => { showCanceled.value = false }, 8000)
  }
}

async function loadStatus() {
  try {
    billingStatus.value = await get('/api/billing/status')
  } catch (err) {
    console.error('Failed to load billing status:', err)
  }
}

async function loadInvoices() {
  invoicesLoading.value = true
  try {
    invoices.value = await get('/api/billing/invoices')
  } catch (err) {
    console.error('Failed to load invoices:', err)
    invoices.value = []
  } finally {
    invoicesLoading.value = false
  }
}

function goToDashboard() {
  router.push('/dashboard')
}

async function subscribe(plan) {
  subscribing.value = plan
  stripeNotConfigured.value = false

  try {
    const { url } = await post('/api/billing/checkout', { plan })
    if (url) {
      window.location.href = url
    }
  } catch (err) {
    const errMsg = err.message?.toLowerCase() || ''
    if (errMsg.includes('stripe') || errMsg.includes('webhook') || errMsg.includes('secret')) {
      stripeNotConfigured.value = true
    } else {
      console.error('Checkout error:', err.message)
    }
  } finally {
    subscribing.value = null
  }
}

async function switchPlan(plan) {
  changingPlan.value = plan
  changePlanError.value = ''
  try {
    await post('/api/billing/change-plan', { plan })
    await loadStatus()
  } catch (err) {
    changePlanError.value = err.message || 'Failed to switch plan. Please try again.'
  } finally {
    changingPlan.value = null
  }
}

async function confirmCancel() {
  canceling.value = true
  try {
    await post('/api/billing/cancel', {})
    showCancelConfirm.value = false
    await loadStatus()
  } catch (err) {
    console.error('Failed to cancel subscription:', err)
  } finally {
    canceling.value = false
  }
}
</script>
