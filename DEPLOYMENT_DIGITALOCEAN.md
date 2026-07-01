# Deploying QuietNotify to Digital Ocean

## Stack
- **PHP 8.3-FPM** (app container)
- **nginx** (web container — serves built Vue assets + proxies PHP)
- **MySQL 8.0** (db container)
- **Alpine cron** (cron container — runs every minute)
- **Docker Compose** orchestrates everything

The Vue frontend is pre-built — no Node container needed in production.

---

## 1. Create the Droplet

Recommended specs to start:
- **Image:** Ubuntu 24.04 LTS
- **Size:** 2 GB RAM / 1 vCPU ($18/month) — 1 GB is too tight for MySQL + FPM + nginx
- **Region:** Closest to your customers
- **SSH keys:** Add your key during creation

---

## 2. Initial Server Setup

```bash
ssh root@YOUR_DROPLET_IP

# Update packages
apt update && apt upgrade -y

# Basic firewall
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw enable

# Install Docker
curl -fsSL https://get.docker.com | sh

# Install Docker Compose plugin
apt install docker-compose-plugin -y

# Verify
docker compose version
```

---

## 3. Get the App on the Server

If the repo is on GitHub (recommended):
```bash
cd /var/www
git clone https://github.com/YOUR_USERNAME/quietNotify.git app
cd app
```

Or copy files from your local machine:
```bash
# Run this locally, not on the server
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'node_modules/' \
  /Users/orenepsteinredux/Sites/quietNotify/ root@YOUR_DROPLET_IP:/var/www/app/
```

---

## 4. Configure the Environment

```bash
cd /var/www/app

# Copy the example and fill in real values
cp .env.example .env
nano .env
```

Values to fill in (everything else is already set):
```
APP_URL=https://yourdomain.com
DB_PASS=<generate a strong password>
DB_ROOT_PASS=<generate a different strong password>
MAIL_PASSWORD=kJPk8th*]}$N
TEXTBELT_KEY=<your key>
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=<from Stripe dashboard — see Step 7>
```

Generate strong passwords:
```bash
openssl rand -base64 32
```

---

## 5. Run Database Migrations

Start only the DB container first, then run migrations:

```bash
docker compose -f docker-compose.prod.yml up -d db

# Wait for MySQL to be healthy (~15 seconds)
docker compose -f docker-compose.prod.yml ps

# Load schema
docker exec -i quietrent_db mysql -u quietrent -p"$(grep DB_PASS .env | cut -d= -f2)" quietrent < database/schema.sql

# Run migrations in order
for f in database/migrations/*.sql; do
  echo "Running $f..."
  docker exec -i quietrent_db mysql -u quietrent -p"$(grep DB_PASS .env | cut -d= -f2)" quietrent < "$f"
done
```

---

## 6. SSL Certificate (Let's Encrypt)

Point your domain's DNS A record to the droplet IP first and wait for propagation, then:

```bash
# Install Certbot on the host
apt install certbot -y

# Get the cert (standalone — nothing on port 80 yet)
certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# Certs land at /etc/letsencrypt/live/yourdomain.com/
# The prod nginx container mounts /etc/letsencrypt read-only
```

Update `docker/nginx/prod.conf` — replace `DOMAIN` with your actual domain:
```bash
sed -i 's/DOMAIN/yourdomain.com/g' docker/nginx/prod.conf
```

Set up auto-renewal (certs expire every 90 days):
```bash
crontab -e
# Add:
0 3 * * * certbot renew --quiet --pre-hook "docker stop quietrent_web" --post-hook "docker start quietrent_web"
```

---

## 7. Register the Stripe Webhook

1. Go to [Stripe Dashboard → Developers → Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. URL: `https://yourdomain.com/api/webhooks/stripe`
4. Events to listen for:
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
5. Copy the **Signing secret** (`whsec_...`) into `.env` as `STRIPE_WEBHOOK_SECRET`

---

## 8. Start All Containers

```bash
cd /var/www/app
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```

All four containers should show `Up`:
- `quietrent_app`
- `quietrent_web`
- `quietrent_db`
- `quietrent_cron`

---

## 9. Verify Everything Works

```bash
# Check cron is firing
docker logs quietrent_cron --tail 20

# Check nginx / app
curl -I https://yourdomain.com

# Tail cron log
docker exec quietrent_cron tail -f /var/log/quietrent-cron.log
```

---

## 10. Static Pages

The following static HTML files are served directly by nginx from the repo root and `html/` directory — no build step is needed. They are updated by committing changes locally and running `git pull` on the server.

### Marketing homepage
| URL | File |
|-----|------|
| `https://yourdomain.com/` | `index.html` |

### Documentation
| URL | File |
|-----|------|
| `https://yourdomain.com/html/docs-landlords.html` | `html/docs-landlords.html` |
| `https://yourdomain.com/html/docs-hairdressers.html` | `html/docs-hairdressers.html` |
| `https://yourdomain.com/html/docs-tradesmen.html` | `html/docs-tradesmen.html` |

### Legal
| URL | File |
|-----|------|
| `https://yourdomain.com/html/privacy-landlords.html` | `html/privacy-landlords.html` |
| `https://yourdomain.com/html/privacy-hairdressers.html` | `html/privacy-hairdressers.html` |
| `https://yourdomain.com/html/terms-landlords.html` | `html/terms-landlords.html` |
| `https://yourdomain.com/html/terms-hairdressers.html` | `html/terms-hairdressers.html` |

To verify all static pages are reachable after deploy:
```bash
for path in "/" "/html/docs-landlords.html" "/html/docs-hairdressers.html" "/html/docs-tradesmen.html" \
            "/html/privacy-landlords.html" "/html/terms-landlords.html"; do
  echo -n "$path → "
  curl -o /dev/null -s -w "%{http_code}\n" "https://yourdomain.com$path"
done
```
Every line should return `200`.

---

## Go-Live Checklist

Before flipping DNS / announcing:

- [ ] DNS A record pointed to droplet IP
- [ ] SSL cert issued and nginx serving HTTPS
- [ ] Stripe LIVE keys in `.env` (not test keys)
- [ ] Stripe webhook registered for `https://yourdomain.com/api/webhooks/stripe`
- [ ] `STRIPE_WEBHOOK_SECRET` updated to the live webhook signing secret
- [ ] All four Docker containers running
- [ ] Test a registration + subscription flow end-to-end
- [ ] Send a manual test email + SMS from the server
- [ ] Marketing homepage loads (`/`) and all three product cards are visible
- [ ] Docs pages load: `/html/docs-landlords.html`, `/html/docs-hairdressers.html`, `/html/docs-tradesmen.html`
- [ ] Legal pages load: privacy and terms for landlords and hairdressers
- [ ] All "Start free trial" buttons on homepage link to `/register`
- [ ] All "Read docs →" links on homepage resolve correctly

---

## Useful Commands

```bash
# Restart all containers
docker compose -f docker-compose.prod.yml restart

# Restart one container
docker compose -f docker-compose.prod.yml restart app

# View logs
docker compose -f docker-compose.prod.yml logs -f web
docker compose -f docker-compose.prod.yml logs -f app

# Pull latest code and redeploy
git pull
docker compose -f docker-compose.prod.yml up -d --build

# MySQL shell
docker exec -it quietrent_db mysql -u quietrent -p quietrent

# Rebuild frontend (do this locally, commit, then git pull on server)
cd frontend && npm run build
git add ../public/assets && git commit -m "rebuild frontend" && git push
```

---

## Backups

Automate a nightly DB dump:
```bash
crontab -e
# Add:
0 2 * * * docker exec quietrent_db mysqldump -u quietrent -p"YOUR_DB_PASS" quietrent | gzip > /var/backups/quietrent-$(date +\%F).sql.gz && find /var/backups -name "quietrent-*.sql.gz" -mtime +7 -delete
```
