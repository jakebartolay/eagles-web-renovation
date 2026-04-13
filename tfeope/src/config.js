const API_ORIGIN = (import.meta.env.VITE_API_ORIGIN || (import.meta.env.DEV ? 'http://localhost' : '')).replace(/\/$/, '')

function apiUrl(path) {
  return `${API_ORIGIN}${path}`
}

export const PUBLIC_HOME_ENDPOINT = apiUrl('/tfeope-api/api/public/home.php')
export const PUBLIC_NEWS_ENDPOINT = apiUrl('/tfeope-api/v1/client/news/get_all.php')

export const PUBLIC_BRANDING = {
  logoUrl: new URL('./static/logo.png', import.meta.url).href,
  alphaLogoUrl: new URL('./static/eagles alpha systems.png', import.meta.url).href,
  heroUrl: new URL('./static/homebg.jpg', import.meta.url).href,
  prayerVideoUrl: new URL('./static/eagles prayer.mp4', import.meta.url).href,
  anthemVideoUrl: new URL('./static/national_anthem.mp4', import.meta.url).href,
  hymnVideoUrl: new URL('./static/eagles hymn 2025.mp4', import.meta.url).href,
}
