# Multi-Product Architecture

quietNotify is designed to support multiple product variants from a single codebase. Each variant shares the same core functionality (notification, payment, workflow automation) but has different branding, terminology, and menu configurations tailored to specific industries.

## Current Products

1. **quietNotify for Landlords** - Rent reminders and collection
2. **quietNotify for Dentists** - Appointment reminders and payments (template)
3. **quietNotify for Real Estate Agents** - Showing reminders and commission tracking (template)

## How It Works

### 1. Product Configuration (`frontend/src/config/products.js`)

Each product variant is defined in the `PRODUCTS` object with:

- **id**: Unique identifier (e.g., `landlords`, `dentists`, `agents`)
- **name**: Full product name (e.g., "quietNotify for Landlords")
- **shortName**: Brand name (e.g., "quietNotify")
- **tagline**: Industry identifier (e.g., "for Landlords")
- **branding**: Colors, icons
- **terminology**: Industry-specific terms mapping
- **navigation**: Menu items with labels

### 2. Database Product Type

The `accounts` table includes a `product_type` field:

```sql
ALTER TABLE accounts
ADD COLUMN product_type ENUM('landlords', 'dentists', 'agents')
NOT NULL DEFAULT 'landlords';
```

This allows:
- Different signups for different products
- Multi-product accounts (future)
- Product-specific features and limitations

### 3. Dynamic Navigation

The `AppNav.vue` component uses the product configuration to display:
- Product-specific branding
- Industry-specific menu labels
- Same routes, different terminology

### 4. Product Detection

The `getCurrentProduct()` function determines which product variant to use:

```javascript
// Current: environment variable
const productId = import.meta.env.VITE_PRODUCT_ID || 'landlords'

// Future options:
// - Subdomain: landlords.quietnotify.com
// - URL path: /landlords, /dentists
// - User account settings
```

## Adding a New Product Variant

### Step 1: Define Product Configuration

Add your product to `frontend/src/config/products.js`:

```javascript
export const PRODUCTS = {
  // ... existing products ...

  plumbers: {
    id: 'plumbers',
    name: 'quietNotify for Plumbers',
    shortName: 'quietNotify',
    tagline: 'for Plumbers',

    branding: {
      primaryColor: '#3b82f6',
      icon: 'wrench',
    },

    terminology: {
      properties: 'Service Areas',
      property: 'Service Area',
      tenants: 'Customers',
      tenant: 'Customer',
      units: 'Jobs',
      unit: 'Job',
      rent: 'Invoices',
      payment: 'Payment',
      payments: 'Payments',
    },

    navigation: [
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
      { to: '/properties', label: 'Service Areas', icon: 'map' },
      { to: '/tenants', label: 'Customers', icon: 'users' },
      { to: '/rent', label: 'Invoices', icon: 'dollar' },
      { to: '/import', label: 'Import', icon: 'upload' },
      { to: '/settings', label: 'Settings', icon: 'gear' },
      { to: '/billing', label: 'Billing', icon: 'creditcard' },
    ],
  },
}
```

### Step 2: Update Database Schema

Add the new product type to the ENUM:

```sql
ALTER TABLE accounts
MODIFY COLUMN product_type ENUM('landlords', 'dentists', 'agents', 'plumbers')
NOT NULL DEFAULT 'landlords';
```

### Step 3: Create Marketing Page

Update `index.html` or create a dedicated landing page for the new product variant.

### Step 4: Set Product Detection

Choose how users access this product:

**Option A: Environment Variable**
```bash
VITE_PRODUCT_ID=plumbers npm run dev
```

**Option B: Subdomain** (future)
```javascript
// In getCurrentProduct()
const subdomain = window.location.hostname.split('.')[0]
if (subdomain === 'plumbers') return PRODUCTS.plumbers
```

**Option C: URL Path** (future)
```javascript
// In getCurrentProduct()
const path = window.location.pathname.split('/')[1]
if (path === 'plumbers') return PRODUCTS.plumbers
```

### Step 5: Customize Views (Optional)

For product-specific features, use the product configuration:

```vue
<script setup>
import { getCurrentProduct } from '@/config/products'

const product = getCurrentProduct()
</script>

<template>
  <h1>{{ product.terminology.tenants }} Dashboard</h1>
  <!-- For dentists: "Patients Dashboard" -->
  <!-- For landlords: "Tenants Dashboard" -->
</template>
```

## Benefits of This Architecture

1. **Single Codebase**: Maintain one app, deploy multiple products
2. **Rapid Launches**: New verticals in hours, not weeks
3. **Shared Improvements**: Bug fixes and features benefit all products
4. **Industry Positioning**: Tailored messaging for each market
5. **Easy Scaling**: Add products without architectural changes

## Core Functionality (Shared Across All Products)

- Authentication and account management
- Entity management (properties/practices/listings)
- Contact management (tenants/patients/clients)
- Payment tracking
- Automated reminders
- CSV import
- Dashboard analytics
- Subscription billing

## What Changes Per Product

- Branding colors and logos
- Navigation menu labels
- Form field labels
- Email template terminology
- Marketing copy
- Help documentation

## Future Enhancements

1. **Multi-Product Accounts**: Allow one account to manage multiple product types
2. **Product-Specific Features**: Toggle features based on product type
3. **Subdomain Routing**: Automatic product detection from URL
4. **Product Marketplace**: Let users discover other quietNotify products
5. **Custom Domains**: White-label options for enterprise customers

## Best Practices

1. Keep core functionality product-agnostic
2. Use terminology mapping instead of hardcoded labels
3. Test changes across all product variants
4. Document product-specific customizations
5. Default to the most common use case (landlords)

## Example: Building "quietNotify for Dentists"

**What stays the same:**
- All backend logic
- Database schema
- API endpoints
- Core business rules
- Authentication flow

**What changes:**
- "Properties" → "Practices"
- "Tenants" → "Patients"
- "Units" → "Chairs"
- "Rent" → "Appointments"
- Marketing messaging
- Color scheme (green instead of blue)

**Implementation time:** ~2-4 hours for configuration + marketing page

## Testing Different Products Locally

```bash
# Test landlords product
VITE_PRODUCT_ID=landlords npm run dev

# Test dentists product
VITE_PRODUCT_ID=dentists npm run dev

# Test agents product
VITE_PRODUCT_ID=agents npm run dev
```

## Deployment Strategy

**Option 1: Single Deployment with Subdomain Detection**
- Deploy once
- Use subdomain to determine product
- landlords.quietnotify.com, dentists.quietnotify.com

**Option 2: Separate Deployments**
- Deploy same codebase multiple times
- Set VITE_PRODUCT_ID per deployment
- Different domains per product

**Option 3: Path-Based**
- quietnotify.com/landlords
- quietnotify.com/dentists
- Single deployment, path detection

## Questions?

See `frontend/src/config/products.js` for the full product configuration reference.
