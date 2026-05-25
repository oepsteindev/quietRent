# Hairdresser Vertical — Full Booking Platform Buildout Plan

## Goal

Transform the existing hairdresser appointment reminder stub into a full solo-stylist booking platform that competes with Booksy at half the price ($19/mo vs $29.99/mo).

**Target customer:** Solo hairdressers and small salons (1-3 stylists) who want online booking without paying Booksy prices or dealing with marketplace lock-in.

---

## What We Already Have

- Appointment creation (admin-side)
- Appointment reminders (email + SMS)
- Basic payment tracking (`appointment_payments` table)
- Multi-account isolation (`user_accounts` table)
- Account settings system

---

## What We Need to Build

### Phase 1 — Core Booking (4–6 weeks) ✅ Launch-ready after this

| Feature | Effort | Notes |
|---------|--------|-------|
| Service menu (service name, duration, price) | 2–3 days | New `services` table; CRUD in dashboard |
| Weekly schedule / availability settings | 3–4 days | Days open, hours open per day |
| Time slot engine | 3–4 days | Generate available slots based on schedule + existing bookings |
| Public booking page | 1–2 weeks | Client-facing page at `/book/{username}` — no login required |
| Booking confirmation flow | 2–3 days | Client picks service → time → enters name/phone/email → confirms |
| Two-way SMS confirmation | 2–3 days | Twilio already wired; send confirmation + reminder to client |
| Client management | 2–3 days | List of past/upcoming clients per stylist |
| Calendar view (stylist dashboard) | 1 week | Week/day view of appointments |

**Phase 1 total: ~4–6 weeks**

---

### Phase 2 — Policies + Payments (2–3 weeks)

| Feature | Effort | Notes |
|---------|--------|-------|
| Deposit collection at booking | 1 week | Stripe already wired; require % upfront |
| Cancellation policy enforcement | 3–4 days | Define window (e.g. 24hr), charge deposit if violated |
| No-show tracking | 2 days | Mark no-show, flag client |
| Online payment at time of service | 3–4 days | Send payment link after appointment |

**Phase 2 total: ~2–3 weeks**

---

### Phase 3 — Multi-Stylist / Small Salon (2–3 weeks)

| Feature | Effort | Notes |
|---------|--------|-------|
| Staff management | 1 week | Add stylists to account, each with own schedule |
| Per-stylist booking pages | 3–4 days | `/book/{salon}/{stylist}` or salon-level page with stylist picker |
| Commission tracking | 3–4 days | Track revenue per stylist |

**Phase 3 total: ~2–3 weeks**

---

### Phase 4 — Marketplace (months, optional)

| Feature | Effort | Notes |
|---------|--------|-------|
| Discovery / search | Large | "Find a stylist near me" |
| Reviews + ratings | Large | Client-facing reviews |
| SEO-optimized profiles | Large | Rank on Google for local searches |

**Recommendation:** Skip Phase 4 unless growth demands it. The marketplace is what makes Booksy hard to leave — but it's also what makes Booksy expensive to build. Win on price and simplicity first.

---

## Database Changes Needed (Phase 1)

```sql
-- Services a stylist offers
CREATE TABLE services (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    duration   SMALLINT UNSIGNED NOT NULL,  -- minutes
    price      DECIMAL(8,2) NOT NULL,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Stylist weekly availability
CREATE TABLE availability (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,  -- 0=Sun, 6=Sat
    open_time  TIME NOT NULL,
    close_time TIME NOT NULL
);

-- Public booking slug
ALTER TABLE accounts ADD COLUMN booking_slug VARCHAR(60) UNIQUE AFTER product_type;
```

The existing `appointments` and `appointment_reminders` tables stay as-is.

---

## Pricing vs Booksy

| Plan | Our Price | Booksy Equivalent | Savings |
|------|-----------|-------------------|---------|
| Solo stylist | $19/mo | $29.99/mo | **37% cheaper** |
| Small salon (3 stylists) | $39/mo | $69.99/mo ($29.99 + 2×$20) | **44% cheaper** |
| Payment processing | 2.9% + $0.30 (Stripe) | 2.49–3.5% | Similar |

---

## Launch Strategy for Hairdressers

1. Scrape hairdresser leads from Google Places (script already exists, just change search query)
2. Build Phase 1 (4–6 weeks)
3. Offer **free 3-month trial** to first 20 hairdressers — get feedback, fix gaps
4. Launch paid tier once booking flow is proven
5. Use hairdresser testimonials to sell more hairdressers

---

## Priority Order

1. **Ship landlord version first** — it's done, just needs deployment
2. **Start hairdresser Phase 1** in parallel or immediately after launch
3. **Phase 2 + 3** based on what early hairdresser customers ask for

---

## Open Questions

- Do we want one booking URL per stylist or per salon?
- Deposit: fixed amount or % of service price?
- Do we want a mobile app eventually, or just a great mobile-optimized web experience?
