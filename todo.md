# Quiet Rent - TODO

## Done
- [x] Stripe subscription billing (checkout, webhooks, cancel, change plan)
- [x] Email reminders via PHPMailer/SMTP
- [x] SMS service via Twilio SDK (coded, needs live credentials)
- [x] Reminder dispatch respects subscription status (won't send for canceled/expired accounts)
- [x] sms_gateway saved on tenant create/update
- [x] Cron endpoint (`public/cron.php`) calls correct dispatcher sequence
- [x] Payment provider tracking removed (app is notification-only, not rent collection)

## Remaining

### SMS (ready to activate)
- [ ] Add real Twilio credentials to `.env` (`TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`)
- [ ] Test with magic number `+15005550006` before going live
- [ ] Once Twilio confirmed working: remove `SMS::sendViaGateway()` and carrier dropdown in UI

### Cleanup
- [ ] Delete `test_checkout.php` and `test_stripe_checkout.php` from repo root

## Phase 2+ (after validation)
- [ ] Weekly digest email for landlords
- [ ] Partial payment support
- [ ] Owner/manager permission layers
- [ ] Branded tenant portal
- [ ] CSV export
