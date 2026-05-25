# Ready for Deployment Checklist

## ✅ Completed (No Action Needed)

- [x] Vue frontend built for production (`frontend/` → `public/assets/`)
- [x] Public cron endpoint created (`public/cron.php`)
- [x] Environment template created (`.env.example`)
- [x] Vite integration handles both dev & production modes
- [x] All code fixed (type hints, SMS credentials, email config)
- [x] Database schema migrations documented

## 📋 Manual Setup Required (You'll Do These)

### Before Deploying to Railway:

1. **Get Twilio credentials** ($20/month)
   - Create account at https://www.twilio.com
   - Grab: Account SID, Auth Token, Phone Number
   - Add to `.env` before deploying

2. **Optional: Get Stripe credentials** (only if you want payment collection)
   - Create account at https://stripe.com
   - Grab: Publishable Key, Secret Key
   - Add to `.env` before deploying

### Deploy to Railway (Follow DEPLOYMENT_RAILWAY.md):

1. Create Railway account
2. Connect GitHub repo
3. Set environment variables in Railway dashboard
4. Point domain to Railway
5. Test the deployment

### After Deploying to Railway:

1. **Apply database migrations** (Follow RAILWAY_MIGRATIONS.md)
   - Run all 8 migrations in `database/migrations/` in order (001 through 008)
   - Migration 008 (`user_accounts`) is required for multi-vertical data isolation

2. **Verify the cron service is running**
   - The `cron` Docker service runs `cron/run.php` every minute automatically
   - In Railway, check the `cron` service logs for `Cron run complete` entries
   - No external cron service needed — it's built into the deployment

3. **Test end-to-end**
   - Create a test account
   - Create a property, unit, tenant
   - Create a rent charge
   - Verify email/SMS sends

4. **Blast your mailing list**
   - Point them to your live domain
   - Ask for feedback

## 📁 Files Created for Deployment

| File | Purpose |
|------|---------|
| `DEPLOYMENT_RAILWAY.md` | Step-by-step Railway deployment guide |
| `RAILWAY_MIGRATIONS.md` | Database migration instructions (all 8 migrations) |
| `docker-compose.yml` | Includes `cron` service — runs every minute automatically |
| `cron/run.php` | CLI cron script (billing, rent reminders, appointment reminders) |
| `public/cron.php` | Web endpoint fallback for manual cron triggers |
| `.env.example` | Template of all required environment variables |
| `.railwayignore` | Tells Railway which files to exclude from build |
| `public/assets/` | Built Vue frontend (production-ready) |

## 📊 Cost Summary

| Item | Cost | Notes |
|------|------|-------|
| Domain | ~$1/mo | Already purchased for 1 year |
| Railway hosting | $0/mo free tier → $5/mo Hobby | Free tier has $5 credits/mo; upgrade when exceeded |
| Twilio SMS | ~$1.50/mo at low volume | $1/mo number + ~$0.008/SMS; scales with tenant count |
| Stripe | 2.9% + $0.30 | Only charged on customer payments |
| Email | $0 | Using existing SMTP credentials |
| **Total** | **~$1.50–6.50/mo** | Scales with usage; very cheap to start |

## 🚀 Quick Start Path

1. Get Twilio credentials
2. Read `DEPLOYMENT_RAILWAY.md` and deploy to Railway
3. Read `RAILWAY_MIGRATIONS.md` and run database migrations
4. Set up cron job at cron-job.org to call your `/cron.php` hourly
5. Test: create a property, unit, tenant, and rent charge
6. Send mailing list blast

Estimated time: 1-2 hours total

## ❓ Questions?

- **Deployment issues?** → Check `DEPLOYMENT_RAILWAY.md` troubleshooting section
- **Database issues?** → Check `RAILWAY_MIGRATIONS.md`
- **Cron not working?** → Check Railway logs for `/cron.php` errors
- **Email not sending?** → Verify credentials in `.env` match what you set in Railway dashboard

---

Everything else is ready. The app is production-ready to deploy. You just need your credentials and to follow the guides.
