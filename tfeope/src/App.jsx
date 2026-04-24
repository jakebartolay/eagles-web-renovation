import { useEffect, useMemo, useState } from 'react';
import Footer from './components/Footer';
import { usePathRoute } from './components/HashRouter';
import Navigation from './components/Navigation';
import { getPendingApiRequestCount, subscribeToApiRequestActivity } from './config/api';
import {
  ClubsPage,
  EventsPage,
  HistoryPage,
  HomePage,
  MagnaCartaPage,
  MemberSearchPage,
  NewsPage,
  OfficersPage,
  VideosPage,
} from './pages';
import './styles.css';

const ROUTES = {
  '/': HomePage,
  '/history': HistoryPage,
  '/magna-carta': MagnaCartaPage,
  '/members/member_search': MemberSearchPage,
  '/news': NewsPage,
  '/events': EventsPage,
  '/officers': () => <OfficersPage groupKey="national" />,
  '/officers/national': () => <OfficersPage groupKey="national" />,
  '/officers/governors': () => <OfficersPage groupKey="governors" />,
  '/officers/appointed': () => <OfficersPage groupKey="appointed" />,
  '/officers/past-leaders': () => <OfficersPage groupKey="pastLeaders" />,
  '/clubs': ClubsPage,
  '/videos': VideosPage,
};

const PAGE_TITLES = {
  '/': 'The Fraternal Order of Eagles',
  '/history': 'History',
  '/magna-carta': 'Magna Carta',
  '/members/member_search': 'Member Search',
  '/news': 'News',
  '/events': 'Events',
  '/officers': 'National Officers',
  '/officers/national': 'National Officers',
  '/officers/governors': 'Governors',
  '/officers/appointed': 'Appointed Officers',
  '/officers/past-leaders': 'Past Leaders',
  '/clubs': 'Regional Clubs',
  '/videos': 'Videos',
};

export default function EaglesLanding() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [apiBusy, setApiBusy] = useState(() => getPendingApiRequestCount() > 0);
  const [showLoader, setShowLoader] = useState(true);
  const { currentPath, navigate } = usePathRoute();
  const isStandaloneMemberPage = currentPath === '/members/member_search';
  const routeClass = useMemo(() => {
    if (currentPath === '/') return 'home';
    const [topLevelRoute] = currentPath.split('/').filter(Boolean);
    return topLevelRoute || 'home';
  }, [currentPath]);

  const ActivePage = useMemo(() => ROUTES[currentPath] || HomePage, [currentPath]);

  useEffect(() => {
    const pageTitle = PAGE_TITLES[currentPath] || PAGE_TITLES['/'];
    document.title = `Ang Agila | ${pageTitle}`;
  }, [currentPath]);

  useEffect(() => {
    const unsubscribe = subscribeToApiRequestActivity((pendingCount) => {
      setApiBusy(pendingCount > 0);
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    if (currentPath !== '/') {
      setShowLoader(false);
      return undefined;
    }

    if (apiBusy) {
      setShowLoader(true);
      return undefined;
    }

    setShowLoader(true);
    const loaderTimer = window.setTimeout(() => {
      setShowLoader(false);
    }, 5000);

    return () => window.clearTimeout(loaderTimer);
  }, [apiBusy, currentPath]);

  useEffect(() => {
    if (!showLoader) {
      document.documentElement.classList.remove('app-loader-active');
      document.body.classList.remove('app-loader-active');
      return undefined;
    }

    document.documentElement.classList.add('app-loader-active');
    document.body.classList.add('app-loader-active');

    return () => {
      document.documentElement.classList.remove('app-loader-active');
      document.body.classList.remove('app-loader-active');
    };
  }, [showLoader]);

  return (
    <div className="app">
      {!isStandaloneMemberPage && (
        <Navigation currentPath={currentPath} onNavigate={navigate} menuOpen={menuOpen} setMenuOpen={setMenuOpen} />
      )}

      <main className={`main-content route-${routeClass}`}>
        <ActivePage />
      </main>

      {!isStandaloneMemberPage && <Footer />}

      {showLoader && (
        <div className="app-loader" role="status" aria-live="polite" aria-label="Loading page">
          <div className="app-loader-content">
            <div className="app-loader-logo-wrap">
              <img className="app-loader-logo" src="/logo.png" alt="The Fraternal Order of Eagles logo" />
            </div>
            <div className="app-loader-title">Fraternal Order of Eagles</div>
            <div className="app-loader-subtitle">Service Through Strong Brotherhood</div>
          </div>
        </div>
      )}
    </div>
  );
}
