const getDefaultForumAppUrl = () => {
  if (typeof window !== 'undefined' && window.location.port) {
    return `${window.location.origin}/forum`;
  }

  return '/forum';
};

const normalizeBaseUrl = (value) => String(value || getDefaultForumAppUrl()).replace(/\/+$/, '');

export const FORUM_APP_BASE_URL = normalizeBaseUrl(import.meta.env.VITE_FORUM_APP_URL || getDefaultForumAppUrl());

export function forumAppUrl(path = '') {
  const cleanPath = String(path || '').replace(/^\/+/, '');
  const normalizedPath = /(^|\/)forum$/i.test(FORUM_APP_BASE_URL)
    ? cleanPath.replace(/^forum(?:\/|$)/i, '')
    : cleanPath;

  return normalizedPath ? `${FORUM_APP_BASE_URL}/${normalizedPath}` : FORUM_APP_BASE_URL;
}

export function openForumApp(path = '', replace = false) {
  const nextUrl = forumAppUrl(path);

  if (replace) {
    window.location.replace(nextUrl);
    return;
  }

  window.location.href = nextUrl;
}
