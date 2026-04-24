import Backdrop from '@mui/material/Backdrop';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
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

export default function EaglesLanding() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [apiBusy, setApiBusy] = useState(() => getPendingApiRequestCount() > 0);
  const { currentPath, navigate } = usePathRoute();
  const isStandaloneMemberPage = currentPath === '/members/member_search';
  const routeClass = useMemo(() => {
    if (currentPath === '/') return 'home';
    const [topLevelRoute] = currentPath.split('/').filter(Boolean);
    return topLevelRoute || 'home';
  }, [currentPath]);

  const ActivePage = useMemo(() => ROUTES[currentPath] || HomePage, [currentPath]);

  useEffect(() => {
    const unsubscribe = subscribeToApiRequestActivity((pendingCount) => {
      setApiBusy(pendingCount > 0);
    });

    return unsubscribe;
  }, []);

  return (
    <div className="app">
      {!isStandaloneMemberPage && (
        <Navigation currentPath={currentPath} onNavigate={navigate} menuOpen={menuOpen} setMenuOpen={setMenuOpen} />
      )}

      <main className={`main-content route-${routeClass}`}>
        <ActivePage />
      </main>

      {!isStandaloneMemberPage && <Footer />}

      <Backdrop
        open={apiBusy}
        sx={{
          color: '#fff',
          zIndex: (theme) => theme.zIndex.drawer + 2000,
          backgroundColor: 'rgba(4, 12, 30, 0.45)',
          backdropFilter: 'blur(2px)',
        }}
      >
        <Box
          sx={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: 1.2,
            px: 3,
            py: 2.5,
            borderRadius: 2,
            border: '1px solid rgba(255, 255, 255, 0.25)',
            backgroundColor: 'rgba(0, 0, 0, 0.38)',
          }}
        >
          <CircularProgress color="inherit" size={34} thickness={4.2} />
          <strong style={{ fontSize: '0.95rem', letterSpacing: '0.02em' }}>Loading data...</strong>
        </Box>
      </Backdrop>
    </div>
  );
}
