# Quiet Rent PRD

## Product summary
Quiet Rent is a narrow micro-SaaS for small landlords and micro property managers who want rent collection to feel less personal, less awkward, and less manual. The product automates rent reminders, late notices, receipts, and simple payment collection while giving the owner a clean dashboard that shows who paid, who is late, and what action is needed next.

The product exists because rent collection is one of the biggest recurring headaches for small landlords, and the market already values features such as online payments, autopay, automated reminders, late fees, receipts, and payment history.

## Product thesis
The best opportunity is not to build another full property management suite. The opportunity is to build a lightweight, easier, cheaper tool for owners with small portfolios who mainly want three things: collect rent online, remind tenants automatically, and reduce uncomfortable one-off communication.

This narrow positioning matters because most micro-SaaS products fail to reach strong recurring revenue when they try to serve too many use cases, pile on features, and never establish clear distribution or retention.

## Why most micro-SaaS products fail to reach $10K MRR
Most micro-SaaS products stall because they have one or more of these problems:

- Weak or fuzzy customer pain, so the product is "nice to have" rather than urgent.
- Broad positioning, so the messaging never feels tailored to one buyer.
- Too many features too early, which delays launch and muddies value.
- Poor distribution, often relying on SEO or vague social posting instead of direct outreach.
- Underpricing, which creates weak unit economics and attracts low-intent customers.
- Churn from low activation, bad onboarding, failed payments, or weak habit formation.

Quiet Rent should be designed explicitly against those failure modes: narrow niche, obvious ROI, fast setup, clear pricing, and distribution that does not depend on luck.

## Ideal customer profile
Primary users:

- Landlords with 1 to 30 units
- Micro property managers with 20 to 150 units
- Users who still rely on checks, Zelle, Venmo, spreadsheets, or manual follow-up
- Users who want less tenant interaction, not more
- Users who do not want a complex enterprise platform

The small-landlord market already adopts software with features like online rent collection, recurring payments, reminders, late fees, and receipts, but pricing and complexity vary widely across tools.

## Jobs to be done

- "When rent is due, remind tenants automatically so I don't have to chase them."
- "When someone is late, apply the next step consistently without me texting them manually."
- "Let tenants pay online so I don't have to coordinate checks, cash, or door visits."
- "Show me exactly who paid, who is late, and what is outstanding."
- "Send receipts automatically so I don't have to answer basic payment questions."

## Positioning
### One-line positioning
Automated rent reminders and simple online collection for small landlords who want fewer awkward tenant conversations.

### What Quiet Rent is
- A rent collection and reminder layer
- A lightweight landlord operations tool
- A self-serve app with simple setup

### What Quiet Rent is not
- A full accounting suite
- A maintenance platform
- A leasing marketplace
- A tenant screening product
- An enterprise PMS competitor

## Core promise
Quiet Rent should save time, reduce late payments, and make rent collection less emotional by turning recurring follow-up into an automatic system.

## Scope
### In scope for v1
- Account signup and login
- Property and unit setup
- Tenant records
- Lease/rent schedule setup
- CSV import for units and tenants
- Monthly rent due automation
- Pre-due reminders
- Due-date reminders
- Late notices
- Late fee rules
- Online payment links
- Payment receipts
- Dashboard with status by unit and tenant
- Manual mark-as-paid option
- Pause reminders per tenant
- Subscription billing for Quiet Rent
- Failed-payment dunning for Quiet Rent subscription

### Out of scope for v1
- Maintenance requests
- Tenant screening
- Lease e-signing
- Bank reconciliation
- Owner accounting
- Trust accounting
- Vendor portals
- Mobile apps
- Eviction workflow management
- Legal document generation
- Bookkeeping exports beyond simple CSV

## Key product principles
- Setup in under 15 minutes for a small portfolio
- Default workflows that require almost no configuration
- Plain language and boring UI
- Email and SMS automation first, advanced features later
- Show cash and status clearly, not vanity analytics
- Self-serve trial and onboarding, no sales call required

## User roles
- Landlord: full access to properties, tenants, reminders, payments, and billing
- Property manager: operational access but optional billing restrictions

## Main workflow
1. User signs up.
2. User creates property and units or uploads CSV.
3. User adds tenants and monthly rent amounts.
4. User sets rent due date and simple late-fee rules.
5. User connects Stripe or another supported payment processor for payment links.
6. System automatically schedules reminders around each due date.
7. Tenants receive reminders and payment links by email, SMS, or both.
8. Dashboard updates when rent is paid, marked paid manually, or remains overdue.
9. System sends receipts after successful payment.

## Functional requirements
### Authentication and account management
- Email/password auth
- Password reset
- Trial state
- Subscription plan state
- Basic tenant isolation by account_id

### Properties and units
- Create property
- Add units under each property
- Store address, unit identifier, monthly rent, due date, grace period, late-fee settings
- Active/inactive status

### Tenant management
- Add one or more tenants per unit
- Contact fields: name, email, phone
- Preferred delivery method: email, SMS, both
- Lease start and lease end
- Move-out flag
- Reminder pause flag

### Import and onboarding
- CSV import for properties, units, and tenants
- Field mapping preview
- Validation for duplicate units and invalid rent values
- Import log and undo for recent imports

### Billing schedule engine
- Monthly rent schedule per occupied unit
- Rules based on due date and grace period
- Reminder cadence defaults:
  - Day -3: friendly reminder
  - Day 0 morning: rent due reminder
  - Day +1: late notice
  - Day +5: stronger late notice with late fee information
- Automatic suppression once rent is marked paid or payment succeeds
- Per-tenant pause/resume

### Payments
- Payment link per tenant or unit
- Stripe-hosted payment links or equivalent provider integration
- Manual payment recording for check/cash/Zelle users
- Payment status tracking
- Automatic receipt email after successful payment
- Payment history by tenant and unit

### Late fees
- Flat late fee
- Percentage late fee
- Grace period support
- Optional max late fee cap
- Apply once per billing cycle in v1

### Messaging
- Editable templates for:
  - Upcoming rent reminder
  - Rent due today
  - Late notice
  - Receipt confirmation
- Variables:
  - tenant_name
  - property_name
  - unit_label
  - rent_amount
  - due_date
  - late_fee_amount
  - payment_link
  - landlord_name
- Delivery status tracking
- Quiet hours for SMS

### Dashboard
Must show on first screen:

- Collected this month
- Outstanding this month
- Late tenants count
- Upcoming due in next 7 days
- Recent payments
- Tenants requiring manual follow-up

### Reporting
- Monthly collection rate
- Late payment rate
- Total collected online vs manual
- Aging buckets for overdue rent
- Receipts sent
- Reminder delivery success/failure

## Non-functional requirements
- Mobile-friendly dashboard
- Fast load on ordinary hosting
- Cron-safe recurring jobs
- Idempotent billing schedule creation
- Retry handling for transient provider failures
- Clear audit log for reminders and payment events
- Basic privacy policy and terms pages
- Deliverability guidance for email sender setup

## UX guidance for Claude
- Keep the interface extremely plain
- Optimize for landlords who are not technical
- Make the dashboard feel calm and operational, not analytical
- Use labels like Paid, Due, Late, Paused, Upcoming
- Use a simple setup checklist on first login
- Avoid charts unless they make a decision clearer
- Do not add AI features in v1

## Suggested pricing
Pricing must sit below heavier property management suites but above "toy app" territory so the product can sustain support and acquisition.

Proposed plans:

- Starter: $19/month, up to 5 units, email reminders, receipts, payment tracking
- Small Portfolio: $39/month, up to 20 units, email + SMS, late fees, CSV import
- Manager: $79/month, up to 75 units, multi-property support, team access, digest emails
- Extra units: usage-based overage or forced upgrade

Optional fees:
- SMS pass-through or bundled credits
- Payment processing fees remain with Stripe/provider

## Technical recommendations
Suggested stack for speed:

- Backend: Laravel preferred
- DB: MySQL or Postgres
- Queue/jobs: Redis or DB-backed queues
- Scheduler: cron
- Payments: Stripe
- Email: Postmark or SendGrid
- SMS: Twilio
- Hosting: VPS or managed Laravel hosting

Architecture principles:

- Monolith first
- Strong audit trail for reminders and payment events
- Idempotent recurring rent generation
- Clean separation between billing cycles, reminders, and payment records
- Minimal third-party dependencies beyond comms and payments

## Database outline
Core tables:

- accounts
- users
- subscriptions
- properties
- units
- tenants
- leases
- rent_charges
- payments
- payment_receipts
- reminder_rules
- reminders
- message_deliveries
- imports
- import_rows
- audit_logs

Important fields:

- units: property_id, unit_label, monthly_rent_cents, due_day, grace_days, late_fee_type, late_fee_value, is_active
- tenants: unit_id, full_name, email, phone, preferred_channel, is_active
- leases: tenant_id, unit_id, start_date, end_date, status
- rent_charges: unit_id, tenant_id, period_month, amount_cents, due_date, late_fee_cents, status, paid_at
- reminders: rent_charge_id, stage, channel, scheduled_at, sent_at, status
- payments: rent_charge_id, provider, provider_payment_id, amount_cents, paid_at, source_type

## V1 acceptance criteria
- New user can add units and tenants or import CSV in under 15 minutes
- System generates monthly rent charges correctly for active leases
- System sends the default reminder cadence automatically
- System stops reminders after payment is recorded
- Late fees are applied according to the configured simple rule
- Dashboard correctly shows paid, due, and late statuses
- Trial user can subscribe without contacting support
- Failed Quiet Rent subscription payments trigger dunning messages

## Recommended MVP build phases
### Phase 1: MVP in 3 to 5 weeks
- Auth
- Account setup
- Property/unit/tenant CRUD
- CSV import
- Monthly rent charge generation
- Email reminders
- Payment links
- Manual mark as paid
- Basic dashboard
- Stripe subscription billing

### Phase 2: 2 to 3 weeks
- SMS reminders
- Late fee rules
- Receipts
- Better filtering and reporting
- Weekly owner digest

### Phase 3: only after validation
- Partial payment rules
- Blocking partial payments
- Basic owner/manager permission layers
- Branded tenant portal
- Limited accounting exports

## Go-to-market plan
### The easiest first marketing step
Target one highly specific niche of landlords or managers directly rather than trying to rank on SEO or compete head-on with general property software brands.

Recommended first segment:

- Small landlords with 3 to 20 units
- Self-managing duplexes, fourplexes, and small multifamily buildings
- Micro property managers handling scattered-site residential rentals

### First campaign
1. Build a one-page landing page with one promise: "Automated rent reminders and payment tracking for small landlords."
2. Create a 60-second demo showing setup, reminder flow, and dashboard.
3. Build a list of 100 local landlords, PM firms, or investor groups from Google Maps, BiggerPockets, local REIA sites, and Facebook groups.
4. Send a short cold email or LinkedIn message offering a free 14-day trial.
5. Offer "we will import your first tenants and units for free" to reduce setup friction.

### Sample cold email
Subject: Rent reminders for small landlords

Hi {{FirstName}},

Built a lightweight tool for small landlords that automates rent reminders, late notices, and payment receipts so you spend less time chasing tenants manually.

It is not a full property management platform. It just handles the rent-collection side cleanly.

If useful, there is a free 14-day trial, and the first import of units and tenants is free.

- {{YourName}}

## How Quiet Rent avoids the $10K MRR trap
- It solves a recurring monthly pain instead of an occasional admin task.
- It targets a user with clear money flow and budget, not hobby users.
- It keeps scope narrow, which improves launch speed and messaging.
- It has direct ROI: fewer late payments, less manual chasing, clearer status.
- It can be sold without calls through a simple self-serve funnel plus light onboarding help.
- It still needs retention discipline, especially onboarding, payment recovery, and subscription dunning.

## Claude implementation brief
Claude should implement this as a narrow, production-minded SaaS, not a general property management product. Claude should optimize for speed to MVP, simple UX, stable recurring billing-cycle generation, clean reminder jobs, and auditable payment/reminder history. Claude should avoid gold-plating, multi-product abstractions, speculative AI features, and any feature outside the explicit MVP scope.
