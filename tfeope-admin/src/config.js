import adminBackgroundUrl from './assets/admin-bg.png'
import adminLogoUrl from './assets/eagles.png'

const defaultOrigin = import.meta.env.VITE_API_ORIGIN
  || (import.meta.env.DEV
    ? 'http://localhost'
    : typeof window !== 'undefined'
      ? window.location.origin
      : '')

const API_ORIGIN = defaultOrigin.replace(/\/$/, '')
const API_BASE_PATH = (import.meta.env.VITE_API_BASE_PATH || '/tfeope-api').replace(/\/$/, '')

function apiUrl(pathname) {
  const normalizedPath = pathname.startsWith('/') ? pathname : `/${pathname}`
  return `${API_ORIGIN}${API_BASE_PATH}${normalizedPath}`
}

export const ADMIN_BRANDING = {
  logoUrl: adminLogoUrl,
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
export const ADMIN_OFFICERS_ENDPOINT = apiUrl('/v1/admin/officers/get_all.php')
export const ADMIN_GOVERNORS_ENDPOINT = apiUrl('/v1/admin/governors/get_all.php')
export const APPOINTED_ENDPOINT = apiUrl('/v1/client/appointed/get_all.php')
export const MAGNA_CARTA_ENDPOINT = apiUrl('/v1/client/magna_carta/get_all.php')
