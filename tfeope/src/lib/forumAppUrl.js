const getDefaultForumAppUrl = () => {
  if (typeof window !== 'undefined' && window.location.port) {
    return `${window.location.protocol}//${window.location.hostname}/tfeope-forum`;
  }

  return '/tfeope-forum';
};

const normalizeBaseUrl = (value) => String(value || getDefaultForumAppUrl()).replace(/\/+$/, '');

export const FORUM_APP_BASE_URL = normalizeBaseUrl(import.meta.env.VITE_FORUM_APP_URL || getDefaultForumAppUrl());

export function forumAppUrl(path = '') {
  const cleanPath = String(path || '').replace(/^\/+/, '');
  return cleanPath ? `${FORUM_APP_BASE_URL}/${cleanPath}` : FORUM_APP_BASE_URL;
}

export function openForumApp(path = '', replace = false) {
  const nextUrl = forumAppUrl(path);

  if (replace) {
    window.location.replace(nextUrl);
    return;
  }

  window.location.href = nextUrl;
}
