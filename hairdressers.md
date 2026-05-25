# quietNotify — Hairdresser Vertical Implementation

All code complete as of 2026-04-29. Frontend built successfully (45 modules, 1.01s).

## Database
- [x] `database/migrations/005_hairdressers_vertical.sql` — created (run manually when ready)

## New Backend
- [x] `src/Models/Appointment.php`
- [x] `src/Controllers/AppointmentController.php`
- [x] `src/Controllers/AppointmentReminderRuleController.php`
- [x] `src/Services/AppointmentReminderDispatcher.php`

## Modified Backend (additive only — existing logic untouched)
- [x] `src/Models/Account.php` — product_type param in create(), seedAppointmentReminderRules()
- [x] `src/Controllers/AuthController.php` — reads product_type, seeds appt rules for hairdressers
- [x] `src/Controllers/DashboardController.php` — returns product_type in API response
- [x] `public/index.php` — appointment + appointment-reminder-rules routes added
- [x] `public/cron.php` — AppointmentReminderDispatcher added to cron pipeline

## New Frontend
- [x] `frontend/src/composables/useAccount.js` — reactive product/setProductType composable
- [x] `frontend/src/views/Appointments.vue` — full appointment management UI

## Modified Frontend
- [x] `frontend/src/config/products.js` — hairdressers entry added; getCurrentProduct() accepts id param
- [x] `frontend/src/router/index.js` — /appointments route added
- [x] `frontend/src/components/AppNav.vue` — uses reactive useAccount.js composable
- [x] `frontend/src/views/Dashboard.vue` — calls setProductType() after dashboard load
- [x] `frontend/src/views/Register.vue` — sends product_type in register POST body

## Build
- [x] `docker exec quietrent_node sh -c "cd /var/www/html/frontend && npm run build"` ✓ 45 modules

---

## How to Activate Hairdresser Vertical

### Step 1 — Run migration (once, when ready)
```bash
docker exec quietrent_db mysql -u root -p"${MYSQL_ROOT_PASSWORD}" quietrent \
  < database/migrations/005_hairdressers_vertical.sql
```

### Step 2 — Deploy as hairdressers (optional build-time flag)
Add to `frontend/.env` or `frontend/.env.production`:
```
VITE_PRODUCT_ID=hairdressers
```
Then rebuild: `docker exec quietrent_node sh -c "cd /var/www/html/frontend && npm run build"`

If you skip this, the UI defaults to landlord labels but the backend works for all verticals.

### Step 3 — Register a hairdresser account
Any account registered while `VITE_PRODUCT_ID=hairdressers` will:
- Have `product_type='hairdressers'` stored in the DB
- Get appointment reminder rule templates seeded automatically

### Step 4 — Setup structure
- Properties → Add a Salon (with address)
- Units → Add Stylists/Stations under the salon (unit_label = "Sarah", "Chair 2", etc.)
- Tenants → Add Clients (with email/phone)
- Appointments → Book appointments linking client + stylist + date/time + service

### Cron
The existing cron URL handles everything — appointment reminders are scheduled and sent automatically in the same hourly run as rent reminders.

---

## Architecture Notes
- Existing landlord code is UNTOUCHED (additive only)
- `appointments` table is separate from `rent_charges`
- `appointment_reminders` / `appointment_reminder_rules` are separate tables (no ENUM surgery)
- Property/Unit/Tenant reused: Salon → Stylist/Station → Client
- product_type returned from /api/dashboard → stored in reactive composable → AppNav adapts

## Template Variables (hairdresser reminders)
```
{client_name}       tenant.full_name
{service_name}      appointments.service_name
{appointment_at}    formatted: "Tuesday, May 6 at 2:00 PM"
{stylist_name}      units.unit_label
{salon_name}        properties.name
{fee_amount}        formatted dollar amount
{business_name}     accounts.name
```

## Reminder Stages
| Stage         | When sent                  |
|---------------|----------------------------|
| reminder_48h  | 48 hours before appointment |
| reminder_2h   | 2 hours before appointment  |

Templates are seeded at registration and editable via /api/appointment-reminder-rules.
