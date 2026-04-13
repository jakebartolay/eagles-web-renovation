import adminBackgroundUrl from './assets/admin-bg.png'
import adminLogoUrl from './assets/eagles.png'

const API_ORIGIN = (import.meta.env.VITE_API_ORIGIN || (import.meta.env.DEV ? 'http://localhost' : '')).replace(/\/$/, '')

function apiUrl(path) {
  return `${API_ORIGIN}${path}`
}

export const ADMIN_BRANDING = {
  logoUrl: adminLogoUrl,
  backgroundUrl: adminBackgroundUrl,
}

export const ADMIN_SESSION_ENDPOINT = apiUrl('/tfeope-api/api/admin/session.php')
export const ADMIN_LOGIN_ENDPOINT = apiUrl('/tfeope-api/api/admin/login.php')
export const ADMIN_LOGOUT_ENDPOINT = apiUrl('/tfeope-api/api/admin/logout.php')
export const ADMIN_DASHBOARD_ENDPOINT = apiUrl('/tfeope-api/api/admin/dashboard.php')
