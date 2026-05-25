# Rent Payment Plan

## Goal
Allow tenants to pay rent directly through quietNotify — eliminating the "pay immediately: {payment_link}" workaround and giving landlords a full payment dashboard.

---

## Recommended Approach: Stripe Payment Links

Since quietNotify already uses Stripe for subscription billing, the simplest path is **Stripe Payment Links** + webhooks.

### How it works
1. Landlord connects their Stripe account via Stripe Connect (OAuth flow)
2. When a rent charge is generated, a Stripe Payment Link is created for that exact amount
3. The link is substituted into `{payment_link}` in reminder emails
4. When tenant pays, Stripe fires a `checkout.session.completed` webhook
5. Backend marks the rent charge as `paid` automatically

### Pros
- Already have Stripe set up — no new vendor
- Stripe handles PCI compliance
- Credit card + ACH debit both supported
- Automatic receipt emails to tenants
- Payouts go directly to landlord's Stripe account (no funds touching our platform)

### Cons
- Stripe fees: ~2.9% + $0.30 per card, 0.8% for ACH (capped at $5)
- Landlord must create/connect a Stripe account

---

## Alternative: ACH via Plaid

For landlords who want to avoid card fees (common for large rent amounts).

- Plaid Link collects tenant bank account + routing number
- Plaid Transfer initiates ACH pull
- ~$0.30 flat per transfer vs. $24+ on a $800 rent via card
- More setup complexity: Plaid account, identity verification, micro-deposit confirmation

**Verdict**: Offer Plaid as an upgrade option, Stripe as the default.

---

## Alternative: External Link (Current)

Already implemented — landlord pastes their Venmo/Zelle/PayPal/Stripe URL into Settings → Payment link. It appears in emails. No automation; landlord marks paid manually.

This remains the free-tier option.

---

## Database Changes Required

```sql
-- Track Stripe Connect for landlords who want integrated payments
ALTER TABLE accounts
  ADD COLUMN stripe_connect_account_id VARCHAR(255) NULL,
  ADD COLUMN stripe_connect_status ENUM('pending','active','disabled') NULL;

-- Per-charge payment tracking
ALTER TABLE rent_charges
  ADD COLUMN stripe_payment_link_id  VARCHAR(255) NULL,
  ADD COLUMN stripe_payment_link_url VARCHAR(500) NULL,
  ADD COLUMN stripe_session_id       VARCHAR(255) NULL,
  ADD COLUMN paid_at                 DATETIME     NULL;
```

---

## Backend Changes Required

### New: `src/Controllers/PaymentController.php`
- `GET /api/payments/connect` — redirect landlord to Stripe Connect OAuth
- `GET /api/payments/connect/callback` — handle OAuth return, save `stripe_connect_account_id`
- `POST /api/payments/create-link/{chargeId}` — create Stripe Payment Link for a charge
- `POST /api/webhooks/stripe-connect` — handle `checkout.session.completed` to auto-mark paid

### Modified: `src/Services/ReminderDispatcher.php`
- In `scheduleAll()`, if account has Stripe Connect active and charge has no payment link yet, call `PaymentController::createLink()` inline and store the URL
- `{payment_link}` becomes the Stripe-generated URL instead of the manual one

### Modified: `src/Models/RentCharge.php`
- Add `updatePaymentLink(int $id, string $linkId, string $url): void`
- Add `markPaidByStripe(string $sessionId): void`

---

## Frontend Changes Required

### Settings.vue (landlords)
- Add "Connect Stripe" button → opens Stripe Connect OAuth
- Show Connect status badge (connected / not connected)
- When connected, per-charge payment links are auto-generated

### Rent.vue
- Show payment link icon/button next to each unpaid charge
- When Stripe Connect is active, "Copy payment link" button per row
- When not connected, shows the manual URL from account settings

### New: Billing section for Connect (optional)
- Payout history pulled from Stripe Connect API

---

## Implementation Order

1. Stripe Connect OAuth flow (accounts table + connect endpoint)
2. Payment Link creation on charge generation
3. Webhook handler to auto-mark paid
4. Frontend: Connect button in Settings, link buttons in Rent table
5. Plaid ACH (phase 2, optional)

---

## Pricing Suggestion

| Tier | Payment Feature |
|------|----------------|
| Free / Trial | Manual payment link (Venmo/Zelle URL) |
| Starter | Stripe-generated per-charge links, auto-mark paid via webhook |
| Pro | ACH via Plaid (lower fees for high-volume landlords) |

---

## Notes
- Never hold tenant funds — use Stripe Connect "direct charges" so money flows Tenant → Landlord directly
- Add a `quietNotify` application fee (e.g. 0.5%) on successful payments as a revenue model, if desired
- NACHA rules for ACH require identity verification — Plaid handles this but adds onboarding friction
