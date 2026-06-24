import { apiUrl } from './lib/apiUrl'

export const PUBLIC_API_BASE_URL = '/client-api'

export const PUBLIC_HOME_ENDPOINT = apiUrl('/api/public/home.php')
export const PUBLIC_NEWS_ENDPOINT = apiUrl('/v1/client/news/get_all.php')
export const PUBLIC_VIDEOS_ENDPOINT = apiUrl('/v1/client/videos/get_all.php')
export const PUBLIC_UPCOMING_EVENTS_ENDPOINT = apiUrl('/v1/client/events/get_upcoming.php')
export const PUBLIC_PAST_EVENTS_ENDPOINT = apiUrl('/v1/client/events/get_past.php')
export const PUBLIC_GOVERNORS_ENDPOINT = apiUrl('/v1/client/governors/get_all.php')
export const PUBLIC_OFFICERS_ENDPOINT = apiUrl('/v1/client/officers/get_all.php')
export const PUBLIC_MAGNA_CARTA_ENDPOINT = apiUrl('/v1/client/magna_carta/get_all.php')
export const PUBLIC_APPOINTED_ENDPOINT = apiUrl('/v1/client/appointed/get_all.php')

export const PUBLIC_BRANDING = {
  logoUrl: new URL('./static/logo.png', import.meta.url).href,
  alphaLogoUrl: new URL('./static/eagles alpha systems.png', import.meta.url).href,
  heroUrl: new URL('./static/homebg.jpg', import.meta.url).href,
  prayerVideoUrl: 'https://www.youtube.com/watch?v=e0kMQ-cJIEo',
  anthemVideoUrl: 'https://www.youtube.com/watch?v=l6gYeAE0l_Y',
  hymnVideoUrl: 'https://www.youtube.com/watch?v=DuZRGwRA0mc',
}

export function publicMediaUrl(group, filename) {
  if (!group || !filename) {
    return null
  }

  const query = new URLSearchParams({
    group,
    file: filename,
  })

  return apiUrl(`/media.php?${query.toString()}`)
}

export function publicOfficersByCategoryUrl(category) {
  const query = new URLSearchParams()
  if (category) {
    query.set('category', category)
  }

  const suffix = query.toString()
  return apiUrl(`/v1/client/officers/get_all.php${suffix ? `?${suffix}` : ''}`)
}
