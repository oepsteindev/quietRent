# quietNotify

A lightweight notification and workflow automation platform for small business operators. The first product is **quietNotify for Landlords** — automated rent reminders and online payment tracking for small landlords.

---

## Stack

| Layer    | Tech                        |
|----------|-----------------------------|
| Backend  | Plain PHP 8.3 (no framework)|
| Frontend | Vue 3 + Vite                |
| Database | MySQL 8.0                   |
| Local dev| Docker + Nginx              |
| Email    | PHP `mail()` → Postmark/SendGrid (see todo.md) |
| SMS      | Stub → Twilio Phase 2       |
| Payments | Stub → Stripe (see todo.md) |

---

## Local development

### Prerequisites
- Docker + Docker Compose
- Node 20+ (for frontend builds)

### Setup

```bash
# 1. Clone and enter the project
git clone git@github.com:oepsteindev/quietNotify.git
cd quietNotify

# 2. Copy env
cp .env.example .env
# Edit .env as needed (defaults work with Docker as-is)

# 3. Start containers
docker compose up -d

# 4. Run database schema
docker exec -i quietnotify_db mysql -u quietnotify -psecret quietnotify < database/schema.sql

# 5. Install frontend deps and start Vite dev server
cd frontend
npm install
npm run dev
```

App is at **http://localhost:8080**  
Vite dev server is at **http://localhost:5173**

---

## Production build

```bash
cd frontend
npm run build
```

This outputs compiled assets to `public/assets/`. Deploy the whole repo to your shared host and point the web root at `public/`.

---

## Shared hosting deployment

1. Upload all files to your host.
2. Set the document root to the `public/` directory.
3. Copy `.env.example` to `.env` and fill in your database credentials.
4. Import the schema: `mysql -u user -p dbname < database/schema.sql`
5. Run all migrations in order: `database/migrations/001_*.sql` through `008_*.sql`
6. Run the frontend build locally first, then upload `public/assets/`.
7. Set up the cron job:

```
* * * * * php /full/path/to/quietNotify/cron/run.php >> /dev/null 2>&1
```

---

## Project structure

```
quietNotify/
├── cron/               # Cron runner (billing, reminders)
├── database/
│   └── schema.sql      # Full MySQL schema
├── docker/             # PHP-FPM and Nginx configs
├── frontend/           # Vue 3 + Vite source
│   └── src/
│       ├── views/      # Page components
│       ├── components/ # Shared components (nav)
│       ├── composables/# fetch wrapper
│       └── router/     # Vue Router
├── public/             # Web root
│   ├── index.php       # Entry point / router
│   ├── shell.php       # SPA HTML shell
│   ├── .htaccess       # Shared host URL rewriting
│   └── assets/         # Vite build output (git-ignored)
└── src/
    ├── Controllers/    # HTTP handlers
    ├── Core/           # Router, DB, Auth, Env, Vite
    ├── Models/         # Database queries
    └── Services/       # BillingEngine, ReminderDispatcher, Mailer, CsvImporter
```

---

## Key features (v1)

- **Account signup + login** with session auth and CSRF protection
- **Properties → Units → Tenants** hierarchy with full CRUD
- **Lease management** with active/ended status
- **Monthly rent charge generation** — idempotent, cron-driven
- **Reminder automation** — pre-due (day -3), due day, late +1, late +5
- **Late fee rules** — flat or percentage, with optional cap
- **Manual mark-as-paid** with automatic receipt email
- **Reminder pause/resume** per tenant
- **CSV import** for bulk unit and tenant setup
- **Dashboard** showing collected, outstanding, late count, upcoming
- **Editable reminder templates** with variable substitution

---

## Cron job

A dedicated `cron` Docker service runs `cron/run.php` every minute automatically — no extra setup needed locally or on Railway.

The cron runner handles everything:

1. Updates charge statuses (upcoming → due → late)
2. Generates this month's charges for active leases (idempotent)
3. Applies late fees to overdue charges
4. Schedules and sends rent reminder emails/SMS
5. Suppresses reminders for paid/waived charges
6. Schedules and sends appointment reminder emails/SMS

Logs are written to `/var/log/quietrent-cron.log` inside the `quietrent_cron` container. To check locally:

```bash
docker exec quietrent_cron tail -f /var/log/quietrent-cron.log
```

---

## TODO

See **[todo.md](todo.md)** for Stripe, email provider, Twilio, and production checklist items.

---

## Pricing (planned)

| Plan            | Price  | Units |
|-----------------|--------|-------|
| Starter         | $19/mo | 5     |
| Small Portfolio | $39/mo | 20    |
| Manager         | $79/mo | 75    |
