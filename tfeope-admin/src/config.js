import adminBackgroundUrl from './assets/admin-bg.png'

const PROD_API_ORIGIN = 'https://api.tfoepe-inc.com.ph'

function normalizeOrigin(origin) {
  const trimmed = String(origin || '').trim().replace(/\/$/, '')
  if (!trimmed) {
    return trimmed
  }

  // Force HTTPS for non-local environments.
  if (!import.meta.env.DEV && /^http:\/\//i.test(trimmed)) {
    const isLocalhost = /^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i.test(trimmed)
    if (!isLocalhost) {
      return trimmed.replace(/^http:\/\//i, 'https://')
    }
  }

  return trimmed
}

const defaultOrigin = import.meta.env.VITE_API_ORIGIN
  || (import.meta.env.DEV
    ? 'http://localhost'
    : PROD_API_ORIGIN)

const API_ORIGIN = normalizeOrigin(defaultOrigin)
const defaultBasePath = import.meta.env.DEV ? '/tfeope-api' : ''
const API_BASE_PATH = (import.meta.env.VITE_API_BASE_PATH ?? defaultBasePath).replace(/\/$/, '')

function apiUrl(pathname) {
  const normalizedPath = pathname.startsWith('/') ? pathname : `/${pathname}`
  return `${API_ORIGIN}${API_BASE_PATH}${normalizedPath}`
}

export const ADMIN_BRANDING = {
  logoUrl: '/logo.png',
  backgroundUrl: adminBackgroundUrl,
  title: 'TFEOPE Admin',
}

export const ADMIN_API_BASE_URL = `${API_ORIGIN}${API_BASE_PATH}`

export const ADMIN_SESSION_ENDPOINT = apiUrl('/api/admin/session.php')
export const ADMIN_LOGIN_ENDPOINT = apiUrl('/api/admin/login.php')
export const ADMIN_LOGOUT_ENDPOINT = apiUrl('/api/admin/logout.php')
export const ADMIN_DASHBOARD_ENDPOINT = apiUrl('/api/admin/dashboard.php')
export const ADMIN_MEMBERS_ENDPOINT = apiUrl('/v1/admin/members/get_all.php')
export const ADMIN_MEMBERS_CREATE_ENDPOINT = apiUrl('/v1/admin/members/create.php')
export const ADMIN_MEMBERS_UPDATE_ENDPOINT = apiUrl('/v1/admin/members/update.php')
export const ADMIN_MEMBERS_DELETE_ENDPOINT = apiUrl('/v1/admin/members/delete.php')
export const ADMIN_MEMBERS_IMPORT_ENDPOINT = apiUrl('/v1/admin/members/import_csv.php')
export const ADMIN_USERS_ENDPOINT = apiUrl('/v1/admin/users/get_all.php')
export const ADMIN_USERS_CREATE_ENDPOINT = apiUrl('/v1/admin/users/create.php')
export const ADMIN_USERS_UPDATE_ENDPOINT = apiUrl('/v1/admin/users/update.php')
export const ADMIN_USERS_DELETE_ENDPOINT = apiUrl('/v1/admin/users/delete.php')
export const ADMIN_NEWS_ENDPOINT = apiUrl('/v1/admin/news/get_all.php')
export const ADMIN_NEWS_CREATE_ENDPOINT = apiUrl('/v1/admin/news/create.php')
export const ADMIN_NEWS_UPDATE_ENDPOINT = apiUrl('/v1/admin/news/update.php')
export const ADMIN_NEWS_DELETE_ENDPOINT = apiUrl('/v1/admin/news/delete.php')
export const ADMIN_VIDEOS_ENDPOINT = apiUrl('/v1/admin/videos/get_all.php')
export const ADMIN_VIDEOS_CREATE_ENDPOINT = apiUrl('/v1/admin/videos/create.php')
export const ADMIN_VIDEOS_UPDATE_ENDPOINT = apiUrl('/v1/admin/videos/update.php')
export const ADMIN_VIDEOS_DELETE_ENDPOINT = apiUrl('/v1/admin/videos/delete.php')
export const ADMIN_EVENTS_ENDPOINT = apiUrl('/v1/admin/events/get_all.php')
export const ADMIN_EVENTS_CREATE_ENDPOINT = apiUrl('/v1/admin/events/create.php')
export const ADMIN_EVENTS_UPDATE_ENDPOINT = apiUrl('/v1/admin/events/update.php')
export const ADMIN_EVENTS_DELETE_ENDPOINT = apiUrl('/v1/admin/events/delete.php')
export const ADMIN_MEMORANDUM_ENDPOINT = apiUrl('/v1/admin/memorandum/get_all.php')
export const ADMIN_MEMORANDUM_CREATE_ENDPOINT = apiUrl('/v1/admin/memorandum/create.php')
export const ADMIN_MEMORANDUM_UPDATE_ENDPOINT = apiUrl('/v1/admin/memorandum/update.php')
export const ADMIN_MEMORANDUM_DELETE_ENDPOINT = apiUrl('/v1/admin/memorandum/delete.php')
export const ADMIN_MAGNA_CARTA_ENDPOINT = apiUrl('/v1/admin/magna_carta/get_all.php')
export const ADMIN_MAGNA_CARTA_CREATE_ENDPOINT = apiUrl('/v1/admin/magna_carta/create.php')
export const ADMIN_MAGNA_CARTA_UPDATE_ENDPOINT = apiUrl('/v1/admin/magna_carta/update.php')
export const ADMIN_OFFICERS_ENDPOINT = apiUrl('/v1/admin/officers/get_all.php')
export const ADMIN_OFFICERS_UPDATE_ENDPOINT = apiUrl('/v1/admin/officers/update.php')
export const ADMIN_GOVERNORS_ENDPOINT = apiUrl('/v1/admin/governors/get_all.php')
export const ADMIN_GOVERNORS_CREATE_ENDPOINT = apiUrl('/v1/admin/governors/create.php')
export const ADMIN_GOVERNORS_UPDATE_ENDPOINT = apiUrl('/v1/admin/governors/update.php')
export const ADMIN_GOVERNORS_DELETE_ENDPOINT = apiUrl('/v1/admin/governors/delete.php')
export const ADMIN_PAST_LEADERS_ENDPOINT = apiUrl('/v1/admin/past_leaders/get_all.php')
export const ADMIN_PAST_LEADERS_CREATE_ENDPOINT = apiUrl('/v1/admin/past_leaders/create.php')
export const ADMIN_PAST_LEADERS_UPDATE_ENDPOINT = apiUrl('/v1/admin/past_leaders/update.php')
export const ADMIN_PAST_LEADERS_DELETE_ENDPOINT = apiUrl('/v1/admin/past_leaders/delete.php')
export const ADMIN_APPOINTED_ENDPOINT = apiUrl('/v1/admin/appointed/get_all.php')
export const ADMIN_APPOINTED_CREATE_ENDPOINT = apiUrl('/v1/admin/appointed/create.php')
export const ADMIN_APPOINTED_UPDATE_ENDPOINT = apiUrl('/v1/admin/appointed/update.php')
export const ADMIN_APPOINTED_DELETE_ENDPOINT = apiUrl('/v1/admin/appointed/delete.php')
export const ADMIN_REGIONS_CREATE_ENDPOINT = apiUrl('/v1/admin/regions/create.php')
export const ADMIN_REGIONS_UPDATE_ENDPOINT = apiUrl('/v1/admin/regions/update.php')
export const ADMIN_CLUBS_CREATE_ENDPOINT = apiUrl('/v1/admin/clubs/create.php')
export const APPOINTED_ENDPOINT = apiUrl('/v1/client/appointed/get_all.php')
export const MAGNA_CARTA_ENDPOINT = apiUrl('/v1/client/magna_carta/get_all.php')
