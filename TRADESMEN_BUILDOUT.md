# Tradesmen BU Buildout

## Overview
New product vertical for tradesmen (plumbers, electricians, contractors, etc.).  
Core: notify clients about upcoming jobs.  
Add-on (Phase 2): generate and email invoices.

Product ID: `tradesmen`

---

## Data model

### Reused tables (same pattern as hairdressers)
| Table | Tradesmen meaning |
|---|---|
| `accounts` | Tradesman business account |
| `properties` | Company / base location |
| `units` | Individual tradesman (worker) |
| `tenants` | Client |

### New tables
| Table | Purpose |
|---|---|
| `jobs` | Scheduled jobs (mirrors `appointments`) |
| `job_reminders` | Queued/sent reminder rows |
| `job_reminder_rules` | Per-account notification templates |
| `invoices` | Invoice header (Phase 2) |
| `invoice_line_items` | Line items per invoice (Phase 2) |

### Reminder stages
| Stage | When sent |
|---|---|
| `confirmation` | Immediately on booking |
| `reminder_24h` | 24 hours before |
| `reminder_2h` | 2 hours before |
| `completion` | Follow-up same evening after job |

### Template variables
`{client_name}` `{job_type}` `{scheduled_at}` `{address}` `{tradesman_name}`  
`{company_name}` `{estimated_cost}` `{payment_link}` `{contact_phone}` `{business_name}`

---

## Phase 1 — Core BU (Notifications)

### Checklist

#### DB
- [x] `009_tradesmen_vertical.sql` — enum + jobs + job_reminders + job_reminder_rules + seed templates

#### Backend
- [x] `src/Models/Job.php` — `upcomingScheduled()`, `find()`, `create()`, `update()`, `setStatus()`
- [x] `src/Controllers/JobController.php` — CRUD `/api/jobs`
- [x] `src/Controllers/JobReminderRuleController.php` — GET/POST `/api/job-reminder-rules`
- [x] `src/Services/JobReminderDispatcher.php` — `scheduleAll()`, `suppressCanceled()`, `sendDue()`, `sendConfirmation()`
- [x] `public/index.php` — register job routes
- [x] `cron/run.php` — `JobReminderDispatcher::scheduleAll()`, `suppressCanceled()`, `sendDue()`

#### Frontend
- [x] `frontend/src/config/products.js` — `tradesmen` product definition
- [x] `frontend/src/views/Jobs.vue` — job list + add/cancel/complete/no-show
- [x] `frontend/src/views/Settings.vue` — tradesmen branch for job reminder rules
- [x] `frontend/src/router/index.js` — `/jobs` route
- [x] `frontend/src/components/AppNav.vue` — handled via products.js nav config

---

## Phase 2 — Invoicing Add-on

### Checklist

#### DB
- [x] `010_tradesmen_invoices.sql` — invoices + invoice_line_items

#### Backend
- [x] `src/Models/Invoice.php`
- [x] `src/Controllers/InvoiceController.php` — CRUD + send + mark-paid + delete + download
- [x] `src/Services/InvoicePdf.php` — HTML→PDF via Dompdf v3.1
- [x] `Mailer.php` — `sendWithPdf()` + private `sendMail()` refactor
- Note: `invoice_addon` gating deferred — scoped to tradesmen nav for now

#### Frontend
- [x] `frontend/src/views/Invoices.vue` — list with Draft/Sent/Paid tab filters + send/mark-paid/PDF actions
- [x] `frontend/src/views/InvoiceBuilder.vue` — line items with live total, draft save, send to client

---

## Decisions / notes
- Jobs reuse `units` table for tradesmen workers (same FK pattern as stylists → units).
- `completion` stage is seeded but `is_active = 0` by default — user opts in.
- Phase 2 invoicing is gated by `invoice_addon = 1` on `accounts`; add-on can be charged separately or bundled.
- Dompdf installed via `composer require dompdf/dompdf` when Phase 2 starts.
- `contact_phone` column already exists on accounts (added in migration 006 area). No new column needed.

---

## Current status
Phase 1 and Phase 2 complete. Both migrations run against DB.  
Ready to test end-to-end with a tradesmen account.
