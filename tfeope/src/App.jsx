import { createElement, useEffect, useMemo, useState } from 'react';
import Footer from './components/Footer';
import { usePathRoute } from './components/HashRouter';
import Navigation from './components/Navigation';
import { getPendingApiRequestCount, subscribeToApiRequestActivity } from './config/api';
import ForumApp from './forum/ForumApp';
import { openForumApp } from './lib/forumAppUrl';
import {
  EventsPage,
  HistoryPage,
  HomePage,
  MagnaCartaPage,
  MemberApplicationPage,
  MemberSearchPage,
  NewsPage,
  OfficersPage,
  UserClubPage,
  UserGovernorProfilePage,
  UserGovernorsPage,
  VideosPage,
} from './pages';
import './styles.css';

function ForumRedirectPage({ to = '/forum' }) {
  useEffect(() => {
    openForumApp(to, true);
  }, [to]);

  return (
    <section className="not-found-page" aria-labelledby="forum-redirect-title">
      <div className="not-found-page__inner">
        <img className="not-found-page__logo" src="/logo.png" alt="The Fraternal Order of Eagles logo" />
        <p className="not-found-page__eyebrow">Member Forum</p>
        <h1 id="forum-redirect-title">Redirecting...</h1>
        <p>Member login, signup, profile, and forum access are now in the standalone forum app.</p>
        <div className="not-found-page__actions">
          <button type="button" onClick={() => openForumApp(to)}>
            Open Member Forum
          </button>
        </div>
      </div>
    </section>
  );
}

const ROUTES = {
  '/': HomePage,
  '/history': HistoryPage,
  '/magna-carta': MagnaCartaPage,
  '/membership/application': MemberApplicationPage,
  '/members/member_search': MemberSearchPage,
  '/users': () => <ForumRedirectPage to="/login" />,
  '/users/governors': UserGovernorsPage,
  '/users/governors.html': UserGovernorsPage,
  '/governors.html': UserGovernorsPage,
  '/users/clubs': UserClubPage,
  '/users/club_page.html': UserClubPage,
  '/users/cluib_page.html': UserClubPage,
  '/club_page.html': UserClubPage,
  '/cluib_page.html': UserClubPage,
  '/users/governor_profile.html': UserGovernorProfilePage,
  '/governor_profile.html': UserGovernorProfilePage,
  '/users/profile': () => <ForumRedirectPage to="/profile" />,
  '/users/portal': () => <ForumRedirectPage to="/profile" />,
  '/users/login': () => <ForumRedirectPage to="/login" />,
  '/users/signup': () => <ForumRedirectPage to="/signup" />,
  '/member/login': () => <ForumRedirectPage to="/login" />,
  '/news': NewsPage,
  '/events': EventsPage,
  '/forum': ForumApp,
  '/officers': () => <OfficersPage groupKey="national" />,
  '/officers/national': () => <OfficersPage groupKey="national" />,
  '/officers/governors': UserGovernorsPage,
  '/officers/appointed': () => <OfficersPage groupKey="appointed" />,
  '/officers/past-leaders': () => <OfficersPage groupKey="pastLeaders" />,
  '/clubs': UserClubPage,
  '/regional-clubs': UserClubPage,
  '/videos': VideosPage,
};

const PAGE_TITLES = {
  '/': 'The Fraternal Order of Eagles',
  '/history': 'History',
  '/magna-carta': 'Magna Carta',
  '/membership/application': 'Membership ID Application',
  '/members/member_search': 'Member Search',
  '/users': 'User Login',
  '/users/governors': 'User Governors',
  '/users/governors.html': 'User Governors',
  '/governors.html': 'User Governors',
  '/users/clubs': 'User Club',
  '/users/club_page.html': 'User Club',
  '/users/cluib_page.html': 'User Club',
  '/club_page.html': 'User Club',
  '/cluib_page.html': 'User Club',
  '/users/governor_profile.html': 'Governor Profile',
  '/governor_profile.html': 'Governor Profile',
  '/users/profile': 'My Profile',
  '/users/portal': 'Member Portal',
  '/users/login': 'User Login',
  '/users/signup': 'User Signup',
  '/member/login': 'Member Portal',
  '/news': 'News',
  '/events': 'Events',
  '/forum': 'Forum',
  '/officers': 'National Officers',
  '/officers/national': 'National Officers',
  '/officers/governors': 'Governors',
  '/officers/appointed': 'Appointed Officers',
  '/officers/past-leaders': 'Past Leaders',
  '/clubs': 'Regional Clubs',
  '/regional-clubs': 'Regional Clubs',
  '/videos': 'Videos',
};

const resolveRouteComponent = (path) => {
  if (ROUTES[path]) return ROUTES[path];
  if (path.startsWith('/forum/')) return ForumApp;
  if (path === '/tfeope-forum' || path.startsWith('/tfeope-forum/')) {
    return ForumApp;
  }
  if (path.startsWith('/users/governors/')) return UserGovernorProfilePage;
  if (path.startsWith('/users/clubs/')) return UserClubPage;
  if (path.startsWith('/officers/governors/')) return UserClubPage;
  if (path.startsWith('/clubs/regions/')) return UserClubPage;
  if (path.startsWith('/clubs/')) return UserClubPage;
  if (path.startsWith('/regional-clubs/regions/')) return UserClubPage;
  if (path.startsWith('/regional-clubs/')) return UserClubPage;
  return null;
};

const getPageTitle = (path) => {
  if (PAGE_TITLES[path]) return PAGE_TITLES[path];
  if (path.startsWith('/forum/')) return 'Forum';
  if (path === '/tfeope-forum' || path.startsWith('/tfeope-forum/')) return 'Forum';
  if (path.startsWith('/users/governors/')) return 'Governor Profile';
  if (path.startsWith('/users/clubs/')) return 'Club Members';
  if (path.startsWith('/officers/governors/')) return 'Regional Clubs';
  if (path.startsWith('/clubs/regions/')) return 'Regional Clubs';
  if (path.startsWith('/clubs/')) return 'Club Members';
  if (path.startsWith('/regional-clubs/regions/')) return 'Regional Clubs';
  if (path.startsWith('/regional-clubs/')) return 'Club Members';
  return 'Page Not Found';
};

const LEGACY_USER_PATHS = new Set([
  '/governors.html',
  '/club_page.html',
  '/cluib_page.html',
  '/governor_profile.html',
]);

function NotFoundPage({ onNavigate }) {
  return (
    <section className="not-found-page" aria-labelledby="not-found-title">
      <div className="not-found-page__inner">
        <img className="not-found-page__logo" src="/logo.png" alt="The Fraternal Order of Eagles logo" />
        <p className="not-found-page__eyebrow">404 Error</p>
        <h1 id="not-found-title">Page not found</h1>
        <p>
          The page you opened may have moved, expired, or does not exist in the TFEOPE website.
        </p>
        <div className="not-found-page__actions">
          <button type="button" onClick={() => onNavigate('/')}>
            Go Home
          </button>
          <button type="button" className="not-found-page__secondary" onClick={() => onNavigate('/news')}>
            View News
          </button>
        </div>
      </div>
    </section>
  );
}

function HomeLoader({ apiBusy }) {
  const [timerVisible, setTimerVisible] = useState(true);
  const visible = apiBusy || timerVisible;

  useEffect(() => {
    if (apiBusy) {
      return undefined;
    }

    const loaderTimer = window.setTimeout(() => {
      setTimerVisible(false);
    }, 5000);

    return () => window.clearTimeout(loaderTimer);
  }, [apiBusy]);

  useEffect(() => {
    if (!visible) {
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
  }, [visible]);

  if (!visible) {
    return null;
  }

  return (
    <div className="app-loader" role="status" aria-live="polite" aria-label="Loading page">
      <div className="app-loader-content">
        <div className="app-loader-logo-wrap">
          <img className="app-loader-logo" src="/logo.png" alt="The Fraternal Order of Eagles logo" />
        </div>
        <div className="app-loader-title">Fraternal Order of Eagles</div>
        <div className="app-loader-subtitle">Service Through Strong Brotherhood</div>
      </div>
    </div>
  );
}

export default function EaglesLanding() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [apiBusy, setApiBusy] = useState(() => getPendingApiRequestCount() > 0);
  const { currentPath, navigate } = usePathRoute();
  const isStandalonePage =
    currentPath === '/forum' ||
    currentPath.startsWith('/forum/') ||
    currentPath === '/tfeope-forum' ||
    currentPath.startsWith('/tfeope-forum/') ||
    currentPath === '/members/member_search' ||
    currentPath.startsWith('/users') ||
    currentPath === '/member/login' ||
    LEGACY_USER_PATHS.has(currentPath);
  const routeClass = useMemo(() => {
    if (currentPath === '/') return 'home';
    const [topLevelRoute] = currentPath.split('/').filter(Boolean);
    return topLevelRoute || 'home';
  }, [currentPath]);

  const ActivePage = useMemo(() => resolveRouteComponent(currentPath), [currentPath]);
  const isNotFound = ActivePage === null;

  useEffect(() => {
    const pageTitle = getPageTitle(currentPath);
    document.title = `Ang Agila | ${pageTitle}`;
  }, [currentPath]);

  useEffect(() => {
    const unsubscribe = subscribeToApiRequestActivity((pendingCount) => {
      setApiBusy(pendingCount > 0);
    });

    return unsubscribe;
  }, []);

  return (
    <div className={`app ${isNotFound ? 'app--not-found' : ''}`}>
      {!isStandalonePage && !isNotFound && (
        <Navigation currentPath={currentPath} onNavigate={navigate} menuOpen={menuOpen} setMenuOpen={setMenuOpen} />
      )}

      <main className={`main-content route-${routeClass}`}>
        {isNotFound ? <NotFoundPage onNavigate={navigate} /> : createElement(ActivePage, { currentPath })}
      </main>

      {!isStandalonePage && !isNotFound && <Footer />}

      {currentPath === '/' ? <HomeLoader apiBusy={apiBusy} /> : null}
    </div>
  );
}
