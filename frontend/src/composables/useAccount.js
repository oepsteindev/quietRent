import { ref, computed } from 'vue'
import { getProduct, PRODUCTS } from '../config/products.js'

// True when product was pinned by subdomain or ?vertical= param.
// In that case the DB value must not override it — one account can use any vertical.
let _pinned = false

function detectProduct() {
  // 1. Subdomain: hairdressers.quietnotify.com or hairdressers.localhost → 'hairdressers'
  const parts = window.location.hostname.split('.')
  if (parts.length >= 2 && PRODUCTS[parts[0]]) {
    _pinned = true
    return parts[0]
  }

  // 2. ?vertical= URL param (localhost dev) — persist in session so nav survives navigation
  const param = new URLSearchParams(window.location.search).get('vertical')
  if (param && PRODUCTS[param]) {
    _pinned = true
    sessionStorage.setItem('qn_vertical', param)
    return param
  }

  // 3. Session storage (carries the pinned choice across in-app page navigations)
  const stored = sessionStorage.getItem('qn_vertical')
  if (stored && PRODUCTS[stored]) {
    _pinned = true
    return stored
  }

  // 4. Build-time env / default (DB value may override this)
  return import.meta.env.VITE_PRODUCT_ID || 'landlords'
}

const _productType = ref(detectProduct())

export const product = computed(() => getProduct(_productType.value) || PRODUCTS.landlords)

export function setProductType(type) {
  // Subdomain / ?vertical= context wins — don't let the DB value override it.
  // This lets one admin account log in to any vertical.
  if (_pinned) return
  if (type && PRODUCTS[type] && type !== _productType.value) {
    _productType.value = type
  }
}

export function isPinned()      { return _pinned }
export function pinnedVertical() { return _pinned ? _productType.value : null }
