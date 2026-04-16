function createRequestError(message, details = {}) {
  const error = new Error(message)
  Object.assign(error, details)
  return error
}

function isLikelyNetworkError(error) {
  if (!(error instanceof Error)) {
    return false
  }

  const normalizedMessage = String(error.message || '').toLowerCase()
  return (
    error.name === 'TypeError'
    && (
      normalizedMessage.includes('failed to fetch')
      || normalizedMessage.includes('networkerror')
      || normalizedMessage.includes('load failed')
    )
  )
}

function isOffline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false
}

export async function readJson(res) {
  const raw = await res.text()
  let data = {}

  try {
    data = raw ? JSON.parse(raw) : {}
  } catch {
    data = {}
  }

  if (!res.ok) {
    const fallbackMessage = res.status === 413
      ? 'Upload is too large. Please choose a smaller file.'
      : res.status >= 500
        ? 'Server unavailable right now. Please try again later.'
        : raw.includes('POST Content-Length')
          ? 'Upload is too large. Please choose a smaller file.'
          : 'Request failed.'

    throw createRequestError(data?.message || fallbackMessage, {
      status: res.status,
      code: data?.code || '',
      data,
    })
  }

  return data
}

export function normalizeRequestError(error, fallbackMessage = 'Something went wrong.') {
  if (error instanceof Error && typeof error.status === 'number') {
    return error
  }

  if (isLikelyNetworkError(error)) {
    const offline = isOffline()
    return createRequestError(
      offline
        ? 'No internet connection. Please check your network and try again.'
        : 'Server unavailable right now. Please try again later.',
      {
        code: offline ? 'OFFLINE' : 'SERVER_UNAVAILABLE',
        cause: error,
      },
    )
  }

  if (error instanceof Error) {
    return error
  }

  return createRequestError(fallbackMessage)
}

export async function requestJson(input, init) {
  try {
    const response = await fetch(input, init)
    return await readJson(response)
  } catch (error) {
    throw normalizeRequestError(error)
  }
}

export function normalizeDashboard(data) {
  return data || {}
}

export function normalizeCollection(data) {
  return data?.data || data || []
}
