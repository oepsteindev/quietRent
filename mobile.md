# Mobile Responsiveness Plan

## Current Problems

1. **Sidebar is `fixed w-52` (208px)** — `App.vue` applies `ml-52` to main content. On a 375px phone, only 167px left for content.
2. **No hamburger/toggle** — sidebar is always visible, no way to collapse it on mobile.
3. **Tables overflow** — Rent (7 cols), AppointmentPayments (7 cols), Appointments, Tenants, Properties all have multi-column tables with no scroll container.
4. **`p-8` padding** — 32px each side, further squeezes mobile content.

---

## Files to Change

| File | What changes |
|------|-------------|
| `frontend/src/App.vue` | Add hamburger state, overlay, responsive `ml-0 md:ml-52` |
| `frontend/src/components/AppNav.vue` | Slide-in drawer on mobile, overlay backdrop, close-on-nav |
| `frontend/src/views/Rent.vue` | Wrap table in `overflow-x-auto` |
| `frontend/src/views/AppointmentPayments.vue` | Wrap table in `overflow-x-auto` |
| `frontend/src/views/Appointments.vue` | Wrap table in `overflow-x-auto` |
| `frontend/src/views/Tenants.vue` | Wrap table in `overflow-x-auto` |
| `frontend/src/views/Properties.vue` | Wrap table in `overflow-x-auto` |
| `frontend/src/views/Dashboard.vue` | Check stats grid — likely fine, just verify |

---

## Implementation Plan

### 1. App.vue — Hamburger state + responsive layout

```vue
<template>
  <div>
    <!-- Hamburger (mobile only) -->
    <button v-if="showNav"
      @click="navOpen = !navOpen"
      class="md:hidden fixed top-4 left-4 z-50 w-10 h-10 flex items-center justify-center bg-slate-900 text-white rounded-lg shadow-lg">
      ☰
    </button>

    <!-- Overlay backdrop (mobile) -->
    <div v-if="showNav && navOpen"
      @click="navOpen = false"
      class="md:hidden fixed inset-0 bg-black/40 z-30" />

    <AppNav v-if="showNav" :open="navOpen" @close="navOpen = false" />

    <main :class="showNav
      ? 'md:ml-52 p-4 md:p-8 bg-slate-50 min-h-screen'
      : 'min-h-screen'">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
// add: const navOpen = ref(false)
// close nav on route change
</script>
```

### 2. AppNav.vue — Slide-in drawer on mobile

```vue
<nav :class="[
  'fixed left-0 top-0 bottom-0 w-52 bg-slate-900 flex flex-col z-40 transition-transform duration-200',
  open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
]">
  <!-- existing content unchanged -->
  <!-- add @click="$emit('close')" to each RouterLink so nav auto-closes on mobile tap -->
</nav>
```

Props: `open: Boolean`. Emits: `close`.

### 3. All tables — wrap with scroll div

Every `<table>` that has more than 3 columns:

```html
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
  <table class="w-full text-left border-collapse min-w-[600px]">
    ...
  </table>
</div>
```

`min-w-[600px]` (or appropriate px) keeps columns from collapsing while enabling horizontal scroll on small screens.

### 4. Padding

`App.vue` main: change `p-8` → `p-4 md:p-8`

---

## Tables and their min-widths

| View | Columns | Suggested min-w |
|------|---------|----------------|
| Rent.vue | 7 | 700px |
| AppointmentPayments.vue | 7 | 700px |
| Appointments.vue | ~6 | 640px |
| Tenants.vue | ~5 | 560px |
| Properties.vue | ~4 | 480px |

---

## What NOT to change

- Public pages (Login, Register, ForgotPassword, ResetPassword) — these don't use the sidebar and are already single-column, likely fine.
- Settings.vue — already a single-column stacked layout, should be fine.
- Billing.vue — check but likely fine (cards, not tables).

---

## Build command (after changes)

```bash
docker exec quietrent_node npm run build
```
