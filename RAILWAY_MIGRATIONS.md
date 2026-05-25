# Running Database Migrations on Railway

After you've deployed to Railway and created the MySQL database, you need to apply all migrations in order. Migrations live in `database/migrations/` and are numbered — run them in sequence.

## Overview

Run these migrations in order on a fresh production database:

1. **001_add_product_type.sql** — Adds `product_type` column to accounts (multi-vertical support)
2. **002_update_plan_enum.sql** — Updates plan enum values
3. **003_drop_payments_table.sql** — Removes legacy payments table
4. **004_drop_sms_gateway.sql** — Removes legacy SMS gateway column
5. **005_hairdressers_vertical.sql** — Adds appointments and appointment_reminders tables
6. **006_account_settings_and_confirmation.sql** — Adds account settings and email confirmation
7. **007_appointment_payments.sql** — Adds appointment payments tracking
8. **008_user_accounts.sql** — Adds `user_accounts` junction table for multi-account support (one user → many businesses)

## How to Apply Migrations

### Option 1: Using Railway Dashboard (Easiest)

1. In Railway dashboard, go to your `db` (MySQL) service
2. Click on the service
3. Look for a **Data** or **Console** tab
4. Find a way to run SQL queries directly
   - Some hosting providers have a "Query" or "SQL" tab
   - If not available, use Option 2

### Option 2: Using MySQL CLI from Local Machine

If you have MySQL client installed locally:

```bash
# Get your Railway database connection details:
# From Railway dashboard → db service → Variables
# You'll see: DATABASE_URL or individual MYSQL_* variables

# Run all migrations in order:
for f in database/migrations/00*.sql; do
  echo "Running $f..."
  mysql -h <railway-host> -u <username> -p<password> <database> < "$f"
done
```

### Option 3: Manual SQL Execution

If you have a phpMyAdmin or similar database UI:

1. Copy the SQL from each migration file
2. Paste it into your database UI
3. Execute it

## The Migrations

| File | What it does |
|------|-------------|
| `001_add_product_type.sql` | Adds `product_type` to accounts (multi-vertical support) |
| `002_update_plan_enum.sql` | Updates plan enum values |
| `003_drop_payments_table.sql` | Removes legacy payments table |
| `004_drop_sms_gateway.sql` | Removes legacy SMS gateway column from tenants |
| `005_hairdressers_vertical.sql` | Creates appointments and appointment_reminders tables |
| `006_account_settings_and_confirmation.sql` | Adds account settings table and email confirmation flow |
| `007_appointment_payments.sql` | Adds appointment payments and late fees tracking |
| `008_user_accounts.sql` | Creates `user_accounts` junction table so one user can own multiple businesses (required for multi-vertical account isolation) |

### Key migration: 008_user_accounts.sql

This migration is critical. Without it, all accounts share the same data and users cannot switch between verticals (landlords / hairdressers / etc.) with isolated data.

```sql
CREATE TABLE IF NOT EXISTS user_accounts (
    user_id    INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    role       ENUM('owner','member') NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, account_id),
    CONSTRAINT fk_ua_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ua_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

INSERT IGNORE INTO user_accounts (user_id, account_id, role)
SELECT id, account_id, 'owner' FROM users;
```

## Verification

After running all migrations, verify the key tables exist:

```bash
mysql -h <host> -u <user> -p<pass> <database> -e "SHOW TABLES;"
```

You should see: `user_accounts`, `appointments`, `appointment_payments`, `account_settings`, and all core tables.

## Troubleshooting

**Error: "Column already exists"**
- The migration has already been applied. That's fine, skip it.

**Error: "Access denied"**
- Check your database credentials in Railway dashboard
- Make sure you're using the correct username and password

**Error: "Unknown database"**
- Make sure you're using the correct database name (check Railway dashboard)

## Next Steps

Once migrations are applied:

1. Test the app: create a test account and verify login works
2. Create a test property and tenant
3. Create a rent charge and test the reminder system
4. Send a test email to verify notifications work

---

**Don't forget:** These migrations must be run before the app will work correctly. If you skip them, you'll see database errors when creating accounts or sending SMS reminders.
