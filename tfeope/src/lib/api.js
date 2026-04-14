export async function fetchApiJson(url, options = {}) {
  const response = await fetch(url, options)
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
    throw new Error(payload.message || 'Unable to load data right now.')
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
