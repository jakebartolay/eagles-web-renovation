const PROD_API_BASE = 'https://api.tfoepe-inc.com.ph/api';
const LOCAL_API_BASE = 'http://127.0.0.1/tfeope-api';

export const API_BASE = (
  import.meta.env.VITE_API_BASE ||
  (import.meta.env.DEV ? LOCAL_API_BASE : PROD_API_BASE)
).replace(/\/$/, '');

// Runtime API base — may be swapped at runtime if the deployed server exposes a
// different mount point (e.g. `/api` vs `/tfeope-api`). Exported for testing.
export let RUNTIME_API_BASE = API_BASE;
export const setRuntimeApiBase = (base) => {
  RUNTIME_API_BASE = (base || '').toString().replace(/\/$/, '');
};

export const API_ENDPOINTS = {
  home: '/api/public/home.php',
  auth: {
    session: '/v1/client/auth/session.php',
    login: '/v1/client/auth/login.php',
    signup: '/v1/client/auth/signup.php',
  },
  news: '/v1/client/news/get_all.php',
  videos: '/v1/client/videos/get_all.php',
  events: {
    all: '/v1/client/events/get_all.php',
    upcoming: '/v1/client/events/get_upcoming.php',
    past: '/v1/client/events/get_past.php',
  },
  memorandum: '/v1/client/memorandum/get_all.php',
  officers: '/v1/client/officers/get_all.php',
  governors: '/v1/client/governors/get_all.php',
  clubs: '/v1/client/clubs/get_all.php',
  appointed: '/v1/client/appointed/get_all.php',
  pastLeaders: '/v1/client/past_leaders/get_all.php',
  magnaCarta: '/v1/client/magna_carta/get_all.php',
  members: {
    all: '/v1/client/members/get_all.php',
    single: '/v1/client/members/get_single.php',
    verify: '/v1/client/members/verify.php',
  },
};

export const buildApiUrl = (path) => `${RUNTIME_API_BASE}${path}`;
const ABSOLUTE_URL_PATTERN = /^(https?:)?\/\//i;
const API_DISABLED_FROM_ENV = import.meta.env.VITE_DISABLE_API === 'true';
const API_DISABLE_QUERY_KEY = 'noapi';
const RESPONSE_CACHE = new Map();
const REQUEST_ACTIVITY_LISTENERS = new Set();
let inFlightRequestCount = 0;

const COMMON_LIST_KEYS = ['data', 'result', 'results', 'items', 'rows', 'records', 'list'];

const getRuntimeApiPreference = () => {
  if (typeof window === 'undefined') return false;

  const params = new URLSearchParams(window.location.search);
  const queryValue = params.get(API_DISABLE_QUERY_KEY);
  if (queryValue === '1') return true;
  if (queryValue === '0') return false;

  try {
    const stored = window.localStorage.getItem('disable_api_requests');
    if (stored === '1') return true;
    if (stored === '0') return false;
    return null;
  } catch {
    return null;
  }
};

export const shouldSkipApiRequests = () => {
  const runtimePreference = getRuntimeApiPreference();
  if (runtimePreference !== null) return runtimePreference;

  return API_DISABLED_FROM_ENV;
};

const getNestedList = (payload, keys) => {
  for (const key of keys) {
    const value = payload?.[key];
    if (Array.isArray(value)) return value;
  }

  for (const key of keys) {
    const value = payload?.[key];
    if (value && typeof value === 'object') {
      for (const nestedKey of keys) {
        if (Array.isArray(value[nestedKey])) return value[nestedKey];
      }
    }
  }

  return [];
};

const notifyRequestActivity = () => {
  REQUEST_ACTIVITY_LISTENERS.forEach((listener) => {
    try {
      listener(inFlightRequestCount);
    } catch {
      // Ignore listener failures to keep request flow stable.
    }
  });
};

const incrementInFlightRequestCount = () => {
  inFlightRequestCount += 1;
  notifyRequestActivity();
};

const decrementInFlightRequestCount = () => {
  inFlightRequestCount = Math.max(0, inFlightRequestCount - 1);
  notifyRequestActivity();
};

export const getPendingApiRequestCount = () => inFlightRequestCount;

export const subscribeToApiRequestActivity = (listener) => {
  if (typeof listener !== 'function') {
    return () => {};
  }

  REQUEST_ACTIVITY_LISTENERS.add(listener);
  listener(inFlightRequestCount);

  return () => {
    REQUEST_ACTIVITY_LISTENERS.delete(listener);
  };
};

export const extractList = (payload, preferredKeys = []) => {
  if (Array.isArray(payload)) return payload;
  if (!payload || typeof payload !== 'object') return [];
  return getNestedList(payload, [...preferredKeys, ...COMMON_LIST_KEYS]);
};

export const fetchJson = async (endpoint) => {
  // Useful in frontend-only development when local API is intentionally offline.
  if (shouldSkipApiRequests()) {
    return {};
  }

  const cached = RESPONSE_CACHE.get(endpoint);
  if (cached) return cached;

  incrementInFlightRequestCount();

  const fetchWithBase = async (base) => {
    const url = `${base}${endpoint}`;
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      throw new Error(`Request failed: ${response.status}`);
    }
    return response.json();
  };

  const attemptPromise = (async () => {
    try {
      return await fetchWithBase(RUNTIME_API_BASE);
    } catch (primaryError) {
      // Try sensible fallback bases (swap between /api and /tfeope-api).
      const candidates = [];
      if (RUNTIME_API_BASE.endsWith('/api')) {
        candidates.push(RUNTIME_API_BASE.replace(/\/api$/, '/tfeope-api'));
      } else if (RUNTIME_API_BASE.endsWith('/tfeope-api')) {
        candidates.push(RUNTIME_API_BASE.replace(/\/tfeope-api$/, '/api'));
      } else {
        // Generic fallbacks if the base is unexpected
        candidates.push((RUNTIME_API_BASE + '/tfeope-api').replace(/\/\/+/, '/'));
        candidates.push((RUNTIME_API_BASE + '/api').replace(/\/\/+/, '/'));
      }

      for (const candidate of candidates) {
        if (!candidate || candidate === RUNTIME_API_BASE) continue;
        try {
          const data = await fetchWithBase(candidate);
          // Persist the working base for subsequent requests.
          RUNTIME_API_BASE = candidate.replace(/\/$/, '');
          return data;
        } catch (e) {
          // try next candidate
        }
      }

      // Nothing worked — rethrow original error for callers to handle.
      throw primaryError;
    } finally {
      decrementInFlightRequestCount();
    }
  })();

  // Cache the promise so parallel callers share the same request.
  RESPONSE_CACHE.set(endpoint, attemptPromise);
  // Ensure failures remove the cache so subsequent calls can retry.
  attemptPromise.catch(() => RESPONSE_CACHE.delete(endpoint));

  return attemptPromise;
};

export const resolveMediaUrl = (rawPath) => {
  if (!rawPath || typeof rawPath !== 'string') return '';

  const path = rawPath.trim();
  if (!path) return '';
  if (path.startsWith('data:') || path.startsWith('blob:')) return path;
  if (ABSOLUTE_URL_PATTERN.test(path)) return path;

  const normalized = path.replace(/^\.?\//, '');
  return `${RUNTIME_API_BASE}/${normalized}`;
};

export const resolveImageFromItem = (item, keys = []) => {
  if (!item || typeof item !== 'object') return '';

  const readByPath = (source, path) => {
    if (!path.includes('.')) return source[path];
    return path.split('.').reduce((acc, part) => {
      if (acc === null || acc === undefined) return undefined;
      if (/^\d+$/.test(part)) return acc[Number(part)];
      return acc[part];
    }, source);
  };

  for (const key of keys) {
    const raw = readByPath(item, key);
    if (typeof raw === 'string' && raw.trim()) {
      return resolveMediaUrl(raw);
    }
  }

  return '';
};
