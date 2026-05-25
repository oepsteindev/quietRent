# quietNotify — Remaining Work & Implementation Plans

This document is written for Claude Haiku to act on. Each section describes a self-contained task with enough context to implement without reading the full conversation history.

---

## Already Fixed (context only)

- `public/cron.php` — was calling `ReminderDispatcher::dispatch()` (private). Fixed to call the full sequence: `BillingEngine::generateMonth()`, `BillingEngine::applyLateFees()`, `ReminderDispatcher::scheduleAll()`, `ReminderDispatcher::suppressPaid()`, `ReminderDispatcher::sendDue()`.
- `src/Models/Tenant.php` — `sms_gateway` fully removed from `create()` and `update()` SQL.
- `src/Services/CsvImporter.php` — `sms_gateway` removed from `Tenant::create()` call.
- `src/Services/SMS.php` — `sendViaGateway()` (legacy email-to-SMS carrier gateway) deleted. Only `send()` via Twilio remains.
- `src/Services/ReminderDispatcher.php` — unused `Tenant` import removed; `sms_gateway` removed from SELECT; `scheduleAll()` and `sendDue()` filter out canceled/expired accounts via `subscription_status IN ('trialing', 'active')` and `trial_ends_at > NOW()`.
- `src/Controllers/BillingController.php` — `changePlan()` fixed (Stripe archived inline products); `current_period_end` fixed (moved to `items->data[0]` in newer Stripe SDK).
- `src/Services/Mailer.php` — hardcoded fallback from-address changed from `noreply@example.com` to `support@getquietnotify.com`.
- `src/Controllers/AuthController.php` — password reset URL now uses `Env::get('APP_URL')` instead of `$_ENV` directly.
- `frontend/src/views/TenantDetail.vue` — "SMS carrier" info card removed; carrier dropdown removed from edit modal; `CARRIERS` constant and `carrierLabel()` function removed; `sms_gateway` removed from `openEdit()`.
- `test_checkout.php` and `test_stripe_checkout.php` deleted.
- All rent payment provider tracking (cash/check/Zelle) removed — app is notification-only. Stripe is only used for landlord SaaS subscriptions.
- Frontend rebuilt successfully.

---

## Remaining Tasks

### 1. Activate Twilio SMS *(human step — waiting on Twilio verification)*

All SMS code is complete. The Twilio PHP SDK is installed. No code changes needed.

**Steps when credentials arrive:**
1. Add to `.env`:
   ```
   TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_FROM=+1XXXXXXXXXX
   ```
2. Restart the PHP container: `docker restart quietrent_app`
3. Test from the container:
   ```bash
   docker exec quietrent_app php -r "
   define('BASE_PATH', '/var/www/html');
   require BASE_PATH . '/src/Core/Autoloader.php';
   require BASE_PATH . '/vendor/autoload.php';
   use QuietRent\Core\{Autoloader, Env};
   use QuietRent\Services\SMS;
   Autoloader::register(BASE_PATH);
   Env::load(BASE_PATH . '/.env');
   \$result = SMS::send('+15005550006', 'Test reminder from quietNotify');
   echo \$result ? 'SMS sent OK' : 'SMS failed (check error_log)';
   "
   ```
   (Use Twilio magic test number `+15005550006` — free, no charge.)

---

### 2. Drop sms_gateway DB column *(run after Twilio confirmed working)*

The `sms_gateway` column on the `tenants` table is no longer written to or read. Drop it when ready:

```bash
docker exec quietrent_db mysql -u root -p"${MYSQL_ROOT_PASSWORD}" quietrent \
  -e "ALTER TABLE tenants DROP COLUMN IF EXISTS sms_gateway;"
```

Or run the migration file that's already written: `database/migrations/004_drop_sms_gateway.sql`

---

## Architecture Reference

**Stack:**
- PHP 8.x, no framework (custom Router, DB, Auth, Response in `src/Core/`)
- Vue 3 SPA with Vite (`frontend/`)
- MySQL via Docker (`quietrent_db`)
- PHP-FPM container: `quietrent_app` — run commands with `docker exec quietrent_app php ...`
- Node/Vite container: `quietrent_node` — rebuild frontend with `docker exec quietrent_node sh -c "cd /var/www/html/frontend && npm run build"`

**Key files:**
- Routes: `public/index.php` (all API + SPA routes)
- Cron (shell): `cron/run.php`
- Cron (webhook): `public/cron.php`
- Email: `src/Services/Mailer.php`
- SMS: `src/Services/SMS.php`
- Reminder scheduling + sending: `src/Services/ReminderDispatcher.php`
- Subscription billing: `src/Controllers/BillingController.php`
- Tenant model: `src/Models/Tenant.php`

**Accounts table key columns:**
- `subscription_status`: `trialing` | `active` | `canceled` | `past_due`
- `trial_ends_at`: datetime
- `stripe_subscription_id`, `stripe_customer_id`, `plan`

**Reminders table key columns:**
- `status`: `pending` | `sent` | `failed` | `suppressed`
- `channel`: `email` | `sms`
- `stage`: `pre_due` | `due_day` | `late_1` | `late_5`
- `scheduled_at`: datetime (cron checks `<= NOW()`)
