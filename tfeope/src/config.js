const API_ORIGIN = (import.meta.env.VITE_API_ORIGIN || (import.meta.env.DEV ? 'http://localhost' : '')).replace(/\/$/, '')

function apiUrl(path) {
  return `${API_ORIGIN}${path}`
}

export const PUBLIC_HOME_ENDPOINT = apiUrl('/tfeope-api/api/public/home.php')
export const PUBLIC_NEWS_ENDPOINT = apiUrl('/tfeope-api/v1/client/news/get_all.php')
export const PUBLIC_VIDEOS_ENDPOINT = apiUrl('/tfeope-api/v1/client/videos/get_all.php')
export const PUBLIC_UPCOMING_EVENTS_ENDPOINT = apiUrl('/tfeope-api/v1/client/events/get_upcoming.php')
export const PUBLIC_PAST_EVENTS_ENDPOINT = apiUrl('/tfeope-api/v1/client/events/get_past.php')
export const PUBLIC_GOVERNORS_ENDPOINT = apiUrl('/tfeope-api/v1/client/governors/get_all.php')
export const PUBLIC_OFFICERS_ENDPOINT = apiUrl('/tfeope-api/v1/client/officers/get_all.php')
export const PUBLIC_MAGNA_CARTA_ENDPOINT = apiUrl('/tfeope-api/v1/client/magna_carta/get_all.php')
export const PUBLIC_APPOINTED_ENDPOINT = apiUrl('/tfeope-api/v1/client/appointed/get_all.php')
export const PUBLIC_AUTH_SESSION_ENDPOINT = apiUrl('/tfeope-api/v1/client/auth/session.php')
export const PUBLIC_AUTH_LOGIN_ENDPOINT = apiUrl('/tfeope-api/v1/client/auth/login.php')
export const PUBLIC_AUTH_SIGNUP_ENDPOINT = apiUrl('/tfeope-api/v1/client/auth/signup.php')
export const PUBLIC_MEMBER_VERIFY_ENDPOINT = apiUrl('/tfeope-api/v1/client/members/verify.php')

export const PUBLIC_BRANDING = {
  logoUrl: new URL('./static/logo.png', import.meta.url).href,
  alphaLogoUrl: new URL('./static/eagles alpha systems.png', import.meta.url).href,
  heroUrl: new URL('./static/homebg.jpg', import.meta.url).href,
  prayerVideoUrl: publicMediaUrl('videos', 'eagles prayer.mp4'),
  anthemVideoUrl: publicMediaUrl('videos', 'national_anthem.mp4'),
  hymnVideoUrl: publicMediaUrl('videos', 'eagles hymn 2025.mp4'),
}

export function publicMediaUrl(group, filename) {
  if (!group || !filename) {
    return null
  }

  const query = new URLSearchParams({
    group,
    file: filename,
  })

  return apiUrl(`/tfeope-api/media.php?${query.toString()}`)
}
