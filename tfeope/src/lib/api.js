function isLikelyNetworkError(error) {
  if (!(error instanceof Error)) {
    return false
  }

  const normalized = String(error.message || '').toLowerCase()
  return (
    error.name === 'TypeError'
    && (
      normalized.includes('failed to fetch')
      || normalized.includes('networkerror')
      || normalized.includes('load failed')
    )
  )
}

function isOffline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false
}

export async function fetchApiJson(url, options = {}) {
  let response
  try {
    response = await fetch(url, options)
  } catch (error) {
    if (isLikelyNetworkError(error)) {
      throw new Error(
        isOffline()
          ? 'No internet connection. Please check your network and try again.'
          : 'Could not reach the API server. Please try again later.',
      )
    }

    throw error
  }

  const text = await response.text()
  const contentType = response.headers.get('content-type') || ''

  let payload = {}

  if (text) {
    try {
      payload = JSON.parse(text)
    } catch {
      throw new Error(
        contentType.toLowerCase().includes('application/json')
          ? 'The server returned invalid JSON.'
          : 'The server returned an unexpected response.',
      )
    }
  }

  if (!response.ok || payload.success === false || payload.ok === false) {
    const fallbackMessage = response.status === 400
      ? 'Invalid request. Please review your input and try again.'
      : response.status === 401
        ? 'Your session has expired. Please sign in again.'
        : response.status === 403
          ? 'You do not have permission to perform this action.'
          : response.status === 404
            ? 'Service endpoint not found. Please contact support.'
            : response.status === 413
              ? 'Upload is too large. Please choose a smaller file.'
              : response.status >= 500
                ? 'Server unavailable right now. Please try again later.'
                : 'Unable to load data right now.'

    throw new Error(payload.message || fallbackMessage)
  }

  return payload
}

export function postJson(url, body, options = {}) {
  return fetchApiJson(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    body: JSON.stringify(body),
    ...options,
  })
}

export function formatLongDate(value) {
  if (!value) {
    return 'To be announced'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('en-PH', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(date)
}
