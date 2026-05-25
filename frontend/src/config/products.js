/**
 * Product Configuration System
 *
 * This file defines the multi-product architecture for quietNotify.
 * Each product variant shares the same core functionality but has
 * different branding, terminology, and menu configurations.
 */

export const PRODUCTS = {
  landlords: {
    id: 'landlords',
    name: 'quietNotify for Landlords',
    shortName: 'quietNotify',
    tagline: 'for Landlords',

    // Branding
    branding: {
      primaryColor: '#5571f3',
      icon: 'house',
    },

    // Terminology mapping
    terminology: {
      properties: 'Properties',
      property: 'Property',
      tenants: 'Tenants',
      tenant: 'Tenant',
      units: 'Units',
      unit: 'Unit',
      rent: 'Rent',
      payment: 'Payment',
      payments: 'Payments',
    },

    // Navigation menu items
    navigation: [
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
      { to: '/properties', label: 'Properties', icon: 'building' },
      { to: '/tenants', label: 'Tenants', icon: 'users' },
      { to: '/rent', label: 'Rent', icon: 'dollar' },
      { to: '/import', label: 'Import', icon: 'upload' },
      { to: '/settings', label: 'Settings', icon: 'gear' },
      { to: '/billing', label: 'Billing', icon: 'creditcard' },
    ],
  },

  dentists: {
    id: 'dentists',
    name: 'quietNotify for Dentists',
    shortName: 'quietNotify',
    tagline: 'for Dentists',

    branding: {
      primaryColor: '#10b981',
      icon: 'tooth',
    },

    terminology: {
      properties: 'Practices',
      property: 'Practice',
      tenants: 'Patients',
      tenant: 'Patient',
      units: 'Chairs',
      unit: 'Chair',
      rent: 'Appointments',
      payment: 'Payment',
      payments: 'Payments',
    },

    navigation: [
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
      { to: '/properties', label: 'Practices', icon: 'building' },
      { to: '/tenants', label: 'Patients', icon: 'users' },
      { to: '/rent', label: 'Appointments', icon: 'calendar' },
      { to: '/import', label: 'Import', icon: 'upload' },
      { to: '/settings', label: 'Settings', icon: 'gear' },
      { to: '/billing', label: 'Billing', icon: 'creditcard' },
    ],
  },

  hairdressers: {
    id: 'hairdressers',
    name: 'quietNotify for Hairdressers',
    shortName: 'quietNotify',
    tagline: 'for Hairdressers',

    branding: {
      primaryColor: '#ec4899',
      icon: 'scissors',
    },

    terminology: {
      properties: 'Salons',
      property: 'Salon',
      tenants: 'Clients',
      tenant: 'Client',
      units: 'Stylists',
      unit: 'Stylist',
      rent: 'Appointments',
      payment: 'Payment',
      payments: 'Payments',
    },

    navigation: [
      { to: '/dashboard',           label: 'Dashboard',    icon: 'dashboard' },
      { to: '/properties',          label: 'Salons',        icon: 'building' },
      { to: '/tenants',             label: 'Clients',       icon: 'users' },
      { to: '/appointments',        label: 'Appointments',  icon: 'calendar' },
      { to: '/appointment-payments',label: 'Payments',      icon: 'dollar' },
      { to: '/settings',            label: 'Settings',      icon: 'gear' },
      { to: '/billing',             label: 'Billing',       icon: 'creditcard' },
    ],
  },

  tradesmen: {
    id: 'tradesmen',
    name: 'quietNotify for Tradesmen',
    shortName: 'quietNotify',
    tagline: 'for Tradesmen',

    branding: {
      primaryColor: '#f97316',
      icon: 'wrench',
    },

    terminology: {
      properties: 'Companies',
      property: 'Company',
      tenants: 'Clients',
      tenant: 'Client',
      units: 'Tradesmen',
      unit: 'Tradesman',
      rent: 'Jobs',
      payment: 'Payment',
      payments: 'Payments',
    },

    navigation: [
      { to: '/dashboard', label: 'Dashboard',  icon: 'dashboard' },
      { to: '/properties', label: 'Companies', icon: 'building' },
      { to: '/tenants',    label: 'Clients',   icon: 'users' },
      { to: '/jobs',       label: 'Jobs',      icon: 'wrench' },
      { to: '/invoices',   label: 'Invoices',  icon: 'dollar' },
      { to: '/settings',   label: 'Settings',  icon: 'gear' },
      { to: '/billing',    label: 'Billing',   icon: 'creditcard' },
    ],
  },

  agents: {
    id: 'agents',
    name: 'quietNotify for Real Estate Agents',
    shortName: 'quietNotify',
    tagline: 'for Real Estate Agents',

    branding: {
      primaryColor: '#f59e0b',
      icon: 'home',
    },

    terminology: {
      properties: 'Listings',
      property: 'Listing',
      tenants: 'Clients',
      tenant: 'Client',
      units: 'Properties',
      unit: 'Property',
      rent: 'Showings',
      payment: 'Commission',
      payments: 'Commissions',
    },

    navigation: [
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
      { to: '/properties', label: 'Listings', icon: 'building' },
      { to: '/tenants', label: 'Clients', icon: 'users' },
      { to: '/rent', label: 'Showings', icon: 'calendar' },
      { to: '/import', label: 'Import', icon: 'upload' },
      { to: '/settings', label: 'Settings', icon: 'gear' },
      { to: '/billing', label: 'Billing', icon: 'creditcard' },
    ],
  },
}

/**
 * Get the current product configuration
 * In the future, this could be determined by:
 * - Subdomain (landlords.quietnotify.com, dentists.quietnotify.com)
 * - URL path (/landlords, /dentists)
 * - User account settings
 * - Environment variable
 */
export function getCurrentProduct(productId = null) {
  const id = productId || import.meta.env.VITE_PRODUCT_ID || 'landlords'
  return PRODUCTS[id] || PRODUCTS.landlords
}

/**
 * Get product by ID
 */
export function getProduct(productId) {
  return PRODUCTS[productId]
}

/**
 * Get all available products
 */
export function getAllProducts() {
  return Object.values(PRODUCTS)
}
