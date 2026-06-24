import { apiUrl as buildApiUrl } from '../lib/apiUrl';

export const API_ENDPOINTS = {
  auth: {
    session: '/v1/client/auth/session.php',
    login: '/v1/client/auth/login.php',
    signup: '/v1/client/auth/signup.php',
    logout: '/v1/client/auth/logout.php',
    linkEaglesId: '/v1/client/auth/link_eagles_id.php',
    updateAccount: '/v1/client/auth/update_account.php',
  },
  members: {
    single: '/v1/client/members/get_single.php',
  },
  forum: {
    categories: '/v1/client/forum/categories.php',
    thread: '/v1/client/forum/thread.php',
    threads: '/v1/client/forum/threads.php',
    posts: '/v1/client/forum/posts.php',
    views: '/v1/client/forum/views.php',
  },
};

function apiUrlCandidates(path = '') {
  return [buildApiUrl(path)];
}

export function apiUrl(path = '') {
  return apiUrlCandidates(path)[0];
}

function isLikelyNetworkError(error) {
  if (!(error instanceof Error)) {
    return false;
  }

  const message = String(error.message || '').toLowerCase();
  return error.name === 'TypeError' && (
    message.includes('failed to fetch') ||
    message.includes('networkerror') ||
    message.includes('load failed')
  );
}

function isOffline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false;
}

export async function fetchApiJson(endpoint, options = {}) {
  const candidates = apiUrlCandidates(endpoint);
  let lastUnexpectedResponse = null;
  let lastNetworkError = null;

  for (const [index, url] of candidates.entries()) {
    const hasFallback = index < candidates.length - 1;
    let response;

    try {
      response = await fetch(url, {
        credentials: 'include',
        cache: 'no-store',
        ...options,
        headers: {
          Accept: 'application/json',
          ...(options.headers || {}),
        },
      });
    } catch (error) {
      if (!isLikelyNetworkError(error)) {
        throw error;
      }

      if (isOffline()) {
        throw new Error('No internet connection. Please check your network and try again.');
      }

      lastNetworkError = error;
      if (hasFallback) {
        continue;
      }

      break;
    }

    const text = await response.text();
    const contentType = response.headers.get('content-type') || '';
    let payload = {};

    if (text) {
      try {
        payload = JSON.parse(text);
      } catch {
        const error = new Error(
          contentType.toLowerCase().includes('application/json')
            ? 'The server returned invalid JSON.'
            : 'The server returned an unexpected response.',
        );

        if (hasFallback && !contentType.toLowerCase().includes('application/json')) {
          lastUnexpectedResponse = error;
          continue;
        }

        throw error;
      }
    }

    if (!response.ok || payload.success === false || payload.ok === false) {
      const fallbackMessage =
        response.status === 401
          ? 'Your session has expired. Please sign in again.'
          : response.status === 403
            ? 'You do not have permission to perform this action.'
            : response.status === 404
              ? 'Service endpoint not found. Please contact support.'
              : response.status >= 500
                ? 'Server unavailable right now. Please try again later.'
                : 'Unable to complete the request right now.';

      throw new Error(payload.message || fallbackMessage);
    }

    return payload;
  }

  if (lastNetworkError) {
    throw new Error('Could not reach the API server. Please try again later.');
  }

  throw lastUnexpectedResponse || new Error('Unable to complete the request right now.');
}

export function postJson(endpoint, body, options = {}) {
  return fetchApiJson(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    body: JSON.stringify(body),
    ...options,
  });
}
