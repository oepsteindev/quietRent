/**
 * Thin fetch wrapper that always sends credentials and CSRF token.
 * Token is seeded from the <meta name="csrf-token"> tag embedded in the HTML shell.
 */

let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? null

export function setCsrf(token) {
  csrfToken = token
}

export async function api(path, options = {}) {
  const token = csrfToken
  const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { 'X-CSRF-Token': token } : {}),
    ...options.headers,
  }

  const res = await fetch(path, {
    credentials: 'include',
    redirect: 'manual',
    ...options,
    headers,
  })

  if (res.type === 'opaqueredirect' || res.status === 0) {
    window.location.href = '/login'
    return
  }

  if (!res.ok) {
    const body = await res.text().then(t => {
      try { return JSON.parse(t) }
      catch { return { error: res.statusText } }
    })
    throw new Error(body?.error ?? `HTTP ${res.status}`)
  }

  return res.json()
}

export const get  = (path) => api(path)
export const post = (path, data) => api(path, {
  method: 'POST',
  body: JSON.stringify({ ...data, _csrf: csrfToken ?? '' }),
})
export const del  = (path) => api(`${path}/delete`, { method: 'POST', body: JSON.stringify({ _csrf: csrfToken ?? '' }) })
