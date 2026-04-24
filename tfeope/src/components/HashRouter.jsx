import { useEffect, useState } from 'react';

const NAVIGATE_EVENT = 'app:navigate';

const normalizePath = (path) => {
  if (!path) return '/';
  return path.startsWith('/') ? path : `/${path}`;
};

const scrollPageToTop = () => {
  window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
};

export const navigateTo = (path) => {
  const nextPath = normalizePath(path);
  if (window.location.pathname === nextPath) {
    scrollPageToTop();
    return;
  }
  window.history.pushState({}, '', nextPath);
  window.dispatchEvent(new Event(NAVIGATE_EVENT));
  scrollPageToTop();
};

export const usePathRoute = () => {
  const [currentPath, setCurrentPath] = useState(normalizePath(window.location.pathname));

  useEffect(() => {
    const handleRouteChange = () => {
      setCurrentPath(normalizePath(window.location.pathname));
      scrollPageToTop();
    };

    window.addEventListener('popstate', handleRouteChange);
    window.addEventListener(NAVIGATE_EVENT, handleRouteChange);
    return () => {
      window.removeEventListener('popstate', handleRouteChange);
      window.removeEventListener(NAVIGATE_EVENT, handleRouteChange);
    };
  }, []);

  const navigate = (path) => {
    navigateTo(path);
    setCurrentPath(normalizePath(path));
  };

  return { currentPath, navigate };
};
