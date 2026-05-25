<template>
  <div>
    <header class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Import from CSV</h1>
      <p class="text-sm text-slate-500 font-medium mt-1">Upload a CSV to bulk-add properties, units, and tenants.</p>
    </header>

    <!-- Format guide -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-4">
      <h2 class="text-sm font-bold text-slate-800 mb-3">Required columns</h2>
      <code class="block bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 mb-3">
        property_name, unit_label, monthly_rent, tenant_name, tenant_email
      </code>
      <p class="text-xs text-slate-500">
        Optional: address_line1, city, state, zip, due_day, tenant_phone, preferred_channel
      </p>
      <a :href="sampleCsvUrl" download="sample.csv"
        class="inline-block mt-4 text-sm font-medium text-blue-600 hover:text-blue-700">
        Download sample CSV →
      </a>
    </div>

    <!-- Upload -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-4 flex items-center gap-4">
      <input type="file" accept=".csv" ref="fileInput" @change="onFile"
        class="text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 file:cursor-pointer" />
      <button @click="doImport" :disabled="!file || importing"
        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors whitespace-nowrap">
        {{ importing ? 'Importing…' : 'Import' }}
      </button>
    </div>

    <!-- Results -->
    <div v-if="result" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-bold text-slate-800">Import results</h2>
      </div>
      <div class="divide-y divide-slate-100">
        <div class="px-6 py-4 flex justify-between text-sm">
          <span class="text-slate-600">Properties created</span>
          <strong class="text-slate-900">{{ result.created.properties }}</strong>
        </div>
        <div class="px-6 py-4 flex justify-between text-sm">
          <span class="text-slate-600">Units created</span>
          <strong class="text-slate-900">{{ result.created.units }}</strong>
        </div>
        <div class="px-6 py-4 flex justify-between text-sm">
          <span class="text-slate-600">Tenants created</span>
          <strong class="text-slate-900">{{ result.created.tenants }}</strong>
        </div>
      </div>
      <div v-if="result.errors?.length" class="px-6 py-4 border-t border-slate-100">
        <p class="text-xs font-semibold text-red-600 uppercase tracking-wider mb-2">Warnings / skipped rows</p>
        <ul class="space-y-1">
          <li v-for="e in result.errors" :key="e" class="text-xs text-red-600">{{ e }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const file      = ref(null)
const fileInput = ref(null)
const importing = ref(false)
const result    = ref(null)

const sampleCsvUrl = 'data:text/csv;charset=utf-8,' + encodeURIComponent(
  'property_name,unit_label,monthly_rent,due_day,tenant_name,tenant_email,tenant_phone,preferred_channel\n' +
  '123 Main St,Apt 1,1200,1,Jane Smith,jane@example.com,5551234567,email\n' +
  '123 Main St,Apt 2,1100,1,Bob Jones,bob@example.com,,email\n'
)

function onFile(e) {
  file.value = e.target.files[0] ?? null
}

async function doImport() {
  if (!file.value) return
  importing.value = true
  result.value    = null

  const form = new FormData()
  form.append('csv', file.value)
  form.append('_csrf', '')

  const res = await fetch('/api/import', {
    method: 'POST',
    credentials: 'include',
    body: form,
  })

  if (res.status === 302) {
    window.location.href = '/login'
    return
  }

  result.value    = await res.json()
  importing.value = false
  fileInput.value.value = ''
  file.value = null
}
</script>
