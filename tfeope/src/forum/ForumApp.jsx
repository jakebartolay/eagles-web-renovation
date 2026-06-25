import {
  CheckCircle2,
  ChevronDown,
  CircleAlert,
  Clock,
  Eye,
  EyeOff,
  IdCard,
  Lock,
  LogIn,
  LogOut,
  MessageSquare,
  Pin,
  RefreshCw,
  Search,
  Send,
  Settings,
  Tag,
  ThumbsDown,
  ThumbsUp,
  UserPlus,
  UserRound,
  X,
} from 'lucide-react';
import Skeleton from '@mui/material/Skeleton';
import { createElement, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import MemberVirtualIdCard from '../components/MemberVirtualIdCard';
import { API_ENDPOINTS, fetchApiJson, postJson } from './api';
import forumStylesUrl from './styles.css?url';

const ROUTE_EVENT = 'app:navigate';
const LOCAL_BASE_PATHS = ['/tfeope-forum', '/forum'];
const ADMIN_ROLE_IDS = new Set([1, 2]);
const FORUM_STYLESHEET_ID = 'tfeope-forum-styles';

const LOGIN_FORM = {
  username: '',
  password: '',
};

const SIGNUP_FORM = {
  name: '',
  username: '',
  password: '',
  passwordConfirm: '',
};

const THREAD_FORM = {
  title: '',
  category: '',
  body: '',
};

const CHANGELOG_ENTRIES = [
  {
    badge: 'Latest',
    date: 'June 25, 2026',
    title: 'Community feed redesign',
    summary: 'The forum interface was refreshed into a cleaner social feed inspired by Facebook and Reddit.',
    changes: [
      'Added card-style discussion feed with vote score, views, replies, and pinned labels.',
      'Updated the composer, topic sidebar, and thread detail panel for easier scanning.',
      'Kept the existing forum API flow intact so posts, replies, and categories still use the same backend.',
    ],
  },
  {
    badge: 'Stability',
    date: 'June 25, 2026',
    title: 'Forum API downtime notice',
    summary: 'Members now see a clear warning when the API or server is not responding.',
    changes: [
      'Added a persistent service notice with a Retry action.',
      'Improved empty states when live forum data cannot be loaded.',
      'Covered load, thread detail, post, and reply failures.',
    ],
  },
  {
    badge: 'Account',
    date: 'June 2026',
    title: 'Member account tools',
    summary: 'Profile, account settings, and member ID access were grouped into the forum shell.',
    changes: [
      'Added account menu for Profile, Settings, and Logout.',
      'Added Eagles ID linking and member record display.',
      'Added access to the digital member ID card when verified data is available.',
    ],
  },
];

function getLatestActivityTime(thread) {
  return thread.lastReplyAt || thread.replies?.[thread.replies.length - 1]?.createdAt || thread.createdAt;
}

function sortThreads(threads) {
  return [...threads].sort((first, second) => {
    if (first.pinned !== second.pinned) {
      return first.pinned ? -1 : 1;
    }

    return new Date(getLatestActivityTime(second)).getTime() - new Date(getLatestActivityTime(first)).getTime();
  });
}

function getBasePath() {
  if (typeof window === 'undefined') {
    return '';
  }

  const currentPath = window.location.pathname.toLowerCase();
  return LOCAL_BASE_PATHS.find((basePath) => currentPath === basePath || currentPath.startsWith(`${basePath}/`)) || '';
}

function normalizePath(path) {
  if (!path) {
    return '/';
  }

  const nextPath = path.startsWith('/') ? path : `/${path}`;
  return nextPath.replace(/\/+$/, '') || '/';
}

function getRoutePath() {
  if (typeof window === 'undefined') {
    return '/';
  }

  const basePath = getBasePath();
  const path = basePath ? window.location.pathname.slice(basePath.length) || '/' : window.location.pathname;
  return normalizePath(path);
}

function toBrowserPath(path) {
  const basePath = getBasePath();
  return `${basePath}${normalizePath(path)}` || '/';
}

function useForumRoute() {
  const [route, setRoute] = useState(getRoutePath);

  useEffect(() => {
    const handleRouteChange = () => {
      setRoute(getRoutePath());
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    };

    window.addEventListener('popstate', handleRouteChange);
    window.addEventListener(ROUTE_EVENT, handleRouteChange);

    return () => {
      window.removeEventListener('popstate', handleRouteChange);
      window.removeEventListener(ROUTE_EVENT, handleRouteChange);
    };
  }, []);

  const navigate = useCallback((path) => {
    const nextPath = toBrowserPath(path);

    if (window.location.pathname !== nextPath) {
      window.history.pushState({}, '', nextPath);
    }

    window.dispatchEvent(new Event(ROUTE_EVENT));
  }, []);

  return { route, navigate };
}

function formatForumDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return 'Recent';
  }

  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

function formatCompactNumber(value) {
  return new Intl.NumberFormat('en-PH', {
    maximumFractionDigits: 1,
    notation: 'compact',
  }).format(Number(value) || 0);
}

function getThreadTagClass(thread) {
  const topic = `${thread?.category || ''} ${thread?.categoryName || ''}`.toLowerCase();

  if (topic.includes('help') || topic.includes('support') || topic.includes('question')) {
    return 'tag-help';
  }

  if (topic.includes('news') || topic.includes('announce') || topic.includes('update')) {
    return 'tag-news';
  }

  if (topic.includes('meta') || topic.includes('system') || topic.includes('developer')) {
    return 'tag-meta';
  }

  if (topic.includes('off')) {
    return 'tag-off';
  }

  return 'tag-gen';
}

function getMemberInitials(name) {
  return String(name || 'Member')
    .split(/[\s,]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'M';
}

function buildMember(session, details, loadingDetails) {
  const firstName = details?.firstName || '';
  const lastName = details?.lastName || '';
  const name =
    details?.fullName ||
    [firstName, lastName].filter(Boolean).join(' ') ||
    session?.name ||
    session?.username ||
    'Member';
  const eaglesId = details?.id || session?.eaglesId || '';

  return {
    name,
    username: session?.username || '',
    initials: getMemberInitials(name),
    eaglesId,
    club: details?.club || '',
    clubPosition: details?.position || '',
    region: details?.region || '',
    governor: details?.governor || '',
    regionalPosition: details?.regionalPosition || details?.regional_position || '',
    status: loadingDetails
      ? 'Loading member status...'
      : details?.statusLabel || details?.status || (session?.eaglesId ? 'Member record not found' : 'Eagles ID not linked'),
    statusNote: details?.status
      ? 'Latest status from your member record.'
      : session?.eaglesId
        ? 'No member status was found for this Eagles ID.'
        : 'Add your Eagles ID when ready.',
    picUrl: details?.picUrl || '',
  };
}

function isForumServiceIssue(error) {
  if (!(error instanceof Error)) {
    return false;
  }

  const message = String(error.message || '').toLowerCase();

  return (
    message.includes('api server') ||
    message.includes('server unavailable') ||
    message.includes('unexpected response') ||
    message.includes('invalid json') ||
    message.includes('network') ||
    message.includes('internet connection') ||
    message.includes('service endpoint') ||
    message.includes('unable to complete the request right now')
  );
}

function createForumServiceNotice(error) {
  const message = error instanceof Error ? String(error.message || '').toLowerCase() : '';
  const offline =
    message.includes('internet connection') ||
    (typeof navigator !== 'undefined' && navigator.onLine === false);

  return {
    title: offline ? 'Connection offline' : 'Forum service issue',
    message: offline
      ? 'Your device appears to be offline. Reconnect to the internet, then retry loading the forum.'
      : 'The forum API/server is not responding right now. Members can keep reading loaded content, but new posts and updates may be unavailable.',
  };
}

function ForumApplication() {
  const { route, navigate } = useForumRoute();
  const [authState, setAuthState] = useState({ status: 'loading', session: null });
  const [memberDetails, setMemberDetails] = useState(null);
  const [memberLoading, setMemberLoading] = useState(false);
  const [memberReloadKey, setMemberReloadKey] = useState(0);
  const [toast, setToast] = useState(null);
  const [forumSearchQuery, setForumSearchQuery] = useState('');

  const session = authState.session;
  const isAuthenticated = authState.status === 'authenticated' && Boolean(session);
  const member = useMemo(() => buildMember(session, memberDetails, memberLoading), [memberDetails, memberLoading, session]);

  const showToast = useCallback((notification) => {
    setToast({
      id: `${Date.now()}-${Math.random()}`,
      ...notification,
    });
  }, []);

  const dismissToast = useCallback(() => {
    setToast(null);
  }, []);

  useEffect(() => {
    let cancelled = false;

    fetchApiJson(API_ENDPOINTS.auth.session)
      .then((payload) => {
        if (cancelled) return;
        const nextSession = payload.authenticated ? payload.data : null;
        setMemberLoading(Boolean(nextSession?.eaglesId));
        setAuthState({
          status: nextSession ? 'authenticated' : 'guest',
          session: nextSession,
        });
      })
      .catch(() => {
        if (!cancelled) {
          setAuthState({ status: 'guest', session: null });
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const eaglesId = session?.eaglesId || '';

    if (!eaglesId) {
      return undefined;
    }

    let cancelled = false;

    fetchApiJson(`${API_ENDPOINTS.members.single}?id=${encodeURIComponent(eaglesId)}`)
      .then((payload) => {
        if (!cancelled) {
          setMemberDetails(payload?.data || null);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setMemberDetails(null);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setMemberLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [memberReloadKey, session?.eaglesId]);

  const handleLogin = async (credentials) => {
    const payload = await postJson(API_ENDPOINTS.auth.login, credentials);
    const nextSession = payload.data || null;
    setMemberLoading(Boolean(nextSession?.eaglesId));
    setAuthState({
      status: nextSession ? 'authenticated' : 'guest',
      session: nextSession,
    });
    setMemberDetails(null);
    showToast({
      type: 'success',
      title: 'Welcome to the forum',
      message: payload.message || `Signed in as ${nextSession?.name || nextSession?.username || credentials.username}.`,
    });
    navigate('/');
  };

  const handleSignup = async (details) => {
    return postJson(API_ENDPOINTS.auth.signup, details);
  };

  const handleLogout = async () => {
    try {
      await postJson(API_ENDPOINTS.auth.logout, {});
    } catch {
      // Keep logout responsive even if the network request is interrupted.
    } finally {
      setAuthState({ status: 'guest', session: null });
      setMemberDetails(null);
      setMemberLoading(false);
      navigate('/');
    }
  };

  const handleLinkEaglesId = async (eaglesId) => {
    const payload = await postJson(API_ENDPOINTS.auth.linkEaglesId, { eaglesId });
    const responseData = payload.data || {};
    const nextSession = {
      ...(session || {}),
      ...responseData,
      eaglesId: responseData.eaglesId || responseData.eagles_id || eaglesId,
      name: responseData.name || session?.name || '',
    };

    setAuthState({ status: 'authenticated', session: nextSession });
    setMemberDetails(null);
    setMemberLoading(true);
    setMemberReloadKey((current) => current + 1);
    return payload;
  };

  const handleUpdateAccount = async (changes) => {
    const payload = await postJson(API_ENDPOINTS.auth.updateAccount, changes);
    const responseData = payload.data || {};

    setAuthState((current) => ({
      status: 'authenticated',
      session: {
        ...(current.session || {}),
        ...responseData,
      },
    }));

    return payload;
  };

  useEffect(() => {
    const titleByRoute = {
      '/': 'Forum',
      '/changelogs': 'Changelogs',
      '/profile': 'Profile',
      '/settings': 'Settings',
      '/login': 'Sign In',
      '/signup': 'Create Account',
    };

    document.title = `TFOE-PE Member Forum | ${titleByRoute[route] || 'Forum'}`;
  }, [route]);

  let page;

  if (authState.status === 'loading') {
    page = <LoadingScreen />;
  } else if (isAuthenticated && ADMIN_ROLE_IDS.has(Number(session?.roleId || session?.role_id || 0))) {
    page = <AccessNotice onLogout={handleLogout} />;
  } else if (route === '/login' || (['/profile', '/settings'].includes(route) && !isAuthenticated)) {
    page = <LoginPage onLogin={handleLogin} onNavigate={navigate} onNotify={showToast} />;
  } else if (route === '/signup') {
    page = <SignupPage onNavigate={navigate} onNotify={showToast} onSignup={handleSignup} />;
  } else {
    page = (
      <MemberShell
        activeRoute={route}
        isAuthenticated={isAuthenticated}
        member={member}
        onLogout={handleLogout}
        onNavigate={navigate}
        onSearchChange={setForumSearchQuery}
        searchQuery={forumSearchQuery}
      >
        {route === '/profile' ? (
          <ProfilePage
            member={member}
            memberDetails={memberDetails}
            onLinkEaglesId={handleLinkEaglesId}
            session={session}
          />
        ) : route === '/settings' ? (
          <SettingsPage member={member} onUpdateAccount={handleUpdateAccount} />
        ) : route === '/changelogs' ? (
          <ChangelogsPage onNavigate={navigate} />
        ) : (
          <ForumPage
            isAuthenticated={isAuthenticated}
            member={member}
            onRequireAuth={() => navigate('/login')}
            searchQuery={forumSearchQuery}
          />
        )}
      </MemberShell>
    );
  }

  return (
    <>
      {page}
      <ToastNotification toast={toast} onDismiss={dismissToast} />
    </>
  );
}

export default function ForumApp() {
  useEffect(() => {
    const existingStylesheet = document.getElementById(FORUM_STYLESHEET_ID);
    if (existingStylesheet) {
      return undefined;
    }

    const stylesheet = document.createElement('link');
    stylesheet.id = FORUM_STYLESHEET_ID;
    stylesheet.rel = 'stylesheet';
    stylesheet.href = forumStylesUrl;
    document.head.appendChild(stylesheet);

    return () => stylesheet.remove();
  }, []);

  return <ForumApplication />;
}

function ToastNotification({ toast, onDismiss }) {
  useEffect(() => {
    if (!toast) {
      return undefined;
    }

    const timeoutId = window.setTimeout(onDismiss, 5000);
    return () => window.clearTimeout(timeoutId);
  }, [onDismiss, toast]);

  if (!toast) {
    return null;
  }

  const Icon = toast.type === 'error' ? CircleAlert : CheckCircle2;

  return (
    <div
      key={toast.id}
      className={`forum-toast ${toast.type}`}
      role={toast.type === 'error' ? 'alert' : 'status'}
      aria-live={toast.type === 'error' ? 'assertive' : 'polite'}
    >
      <span className="forum-toast-icon" aria-hidden="true">
        <Icon size={22} strokeWidth={2.4} />
      </span>
      <div className="forum-toast-copy">
        <strong>{toast.title}</strong>
        <p>{toast.message}</p>
      </div>
      <button type="button" onClick={onDismiss} aria-label="Dismiss notification">
        <X size={18} />
      </button>
      <span className="forum-toast-timer" aria-hidden="true" />
    </div>
  );
}

function LoadingScreen() {
  return (
    <section
      className="forum-loading-screen"
      aria-label="Forum loading"
      style={{ minHeight: '100vh', background: '#f5f6fa' }}
    >
      <header
        className="forum-loading-topbar"
        style={{
          alignItems: 'center',
          background: 'rgba(255, 255, 255, 0.96)',
          borderBottom: '1px solid #dde2ec',
          color: '#111827',
          display: 'flex',
          fontSize: '0.94rem',
          fontWeight: 850,
          justifyContent: 'center',
          minHeight: 52,
          position: 'sticky',
          top: 0,
          zIndex: 10,
        }}
      >
        <span>Forum</span>
      </header>
      <main
        className="forum-page forum-loading-page"
        style={{
          boxSizing: 'border-box',
          margin: '0 auto',
          padding: 14,
          width: 'min(1160px, 100%)',
        }}
      >
        <div
          className="forum-workspace"
          style={{
            alignItems: 'start',
            display: 'grid',
            gap: 14,
            gridTemplateColumns: 'minmax(0, 1fr) 248px',
          }}
        >
          <section
            className="feed"
            aria-label="Loading forum feed"
            style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 0 }}
          >
            <div className="pinned-strip forum-skeleton-pinned" aria-hidden="true">
              <Skeleton animation="wave" variant="rounded" width={18} height={18} />
              <span className="pinned-text">
                <Skeleton animation="wave" variant="text" width="44%" sx={{ fontSize: '0.82rem' }} />
                <Skeleton animation="wave" variant="text" width="82%" sx={{ fontSize: '0.76rem' }} />
              </span>
            </div>
            <div className="thread-list feed-list">
              {Array.from({ length: 4 }, (_, index) => (
                <ForumPostSkeleton key={`initial-forum-post-${index}`} />
              ))}
            </div>
          </section>

          <aside
            className="forum-sidebar sidebar"
            aria-label="Loading forum sidebar"
            style={{ display: 'flex', flexDirection: 'column', gap: 10, minWidth: 0 }}
          >
            <ForumComposerSkeleton />
            <ForumSidebarSkeleton titleWidth="42%" rows={5} />
            <ForumSidebarSkeleton titleWidth="52%" rows={4} />
          </aside>
        </div>
      </main>
    </section>
  );
}

function LoginPage({ onLogin, onNavigate, onNotify }) {
  const [form, setForm] = useState(LOGIN_FORM);
  const [submitting, setSubmitting] = useState(false);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);

    try {
      await onLogin({
        username: form.username.trim(),
        password: form.password,
      });
    } catch (error) {
      onNotify({
        type: 'error',
        title: 'Sign in failed',
        message: error.message || 'Unable to sign in right now.',
      });
      setForm((current) => ({ ...current, password: '' }));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthScreen
      eyebrow="Member Access"
      title="Sign in to Forum"
      subtitle="Use your member portal username and password."
      onNavigate={onNavigate}
    >
      <form className="auth-form" onSubmit={handleSubmit}>
        <label htmlFor="login-username">
          <span>Username</span>
          <input
            id="login-username"
            type="text"
            autoComplete="username"
            placeholder="Enter your username"
            value={form.username}
            onChange={(event) => updateField('username', event.target.value)}
            required
          />
        </label>

        <label htmlFor="login-password">
          <span>Password</span>
          <PasswordInput
            id="login-password"
            autoComplete="current-password"
            placeholder="Enter your password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            required
          />
        </label>

        <button className="auth-submit" type="submit" disabled={submitting}>
          <LogIn size={18} />
          <span>{submitting ? 'Logging in...' : 'Login to Forum'}</span>
        </button>

        <p className="auth-switch-text">
          Need an account?{' '}
          <button type="button" onClick={() => onNavigate('/signup')}>
            Create one
          </button>
        </p>

        <button className="auth-secondary-action" type="button" onClick={() => onNavigate('/')}>
          Continue as visitor
        </button>
      </form>
    </AuthScreen>
  );
}

function SignupPage({ onNavigate, onNotify, onSignup }) {
  const [form, setForm] = useState(SIGNUP_FORM);
  const [submitting, setSubmitting] = useState(false);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);

    try {
      const payload = await onSignup({
        name: form.name.trim(),
        username: form.username.trim(),
        password: form.password,
        passwordConfirm: form.passwordConfirm,
      });

      setForm(SIGNUP_FORM);
      onNotify({
        type: 'success',
        title: 'Account created',
        message: payload.message || 'Account created. You can sign in now.',
      });
    } catch (error) {
      onNotify({
        type: 'error',
        title: 'Registration failed',
        message: error.message || 'Unable to create your account right now.',
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthScreen
      eyebrow="Member Registration"
      title="Create Account"
      subtitle="Register with a username first. Add your Eagles ID later in Profile."
      onNavigate={onNavigate}
    >
      <form className="auth-form" onSubmit={handleSubmit}>
        <label htmlFor="signup-name">
          <span>Full Name</span>
          <input
            id="signup-name"
            type="text"
            autoComplete="name"
            placeholder="Enter your full name"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            required
          />
        </label>

        <label htmlFor="signup-username">
          <span>Username</span>
          <input
            id="signup-username"
            type="text"
            autoComplete="username"
            placeholder="Choose a username"
            value={form.username}
            onChange={(event) => updateField('username', event.target.value)}
            required
          />
        </label>

        <label htmlFor="signup-password">
          <span>Password</span>
          <PasswordInput
            id="signup-password"
            autoComplete="new-password"
            placeholder="Create a password"
            minLength={8}
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            required
          />
        </label>

        <label htmlFor="signup-password-confirm">
          <span>Confirm Password</span>
          <PasswordInput
            id="signup-password-confirm"
            autoComplete="new-password"
            placeholder="Confirm your password"
            minLength={8}
            value={form.passwordConfirm}
            onChange={(event) => updateField('passwordConfirm', event.target.value)}
            required
          />
        </label>

        <button className="auth-submit" type="submit" disabled={submitting}>
          <UserPlus size={18} />
          <span>{submitting ? 'Creating account...' : 'Create Account'}</span>
        </button>

        <p className="auth-switch-text">
          Already have an account?{' '}
          <button type="button" onClick={() => onNavigate('/login')}>
            Sign in instead
          </button>
        </p>

        <button className="auth-secondary-action" type="button" onClick={() => onNavigate('/')}>
          Browse forum first
        </button>
      </form>
    </AuthScreen>
  );
}

function PasswordInput({ autoComplete, id, minLength, onChange, placeholder, value }) {
  const [passwordVisible, setPasswordVisible] = useState(false);

  return (
    <div className="password-input-wrap">
      <input
        id={id}
        type={passwordVisible ? 'text' : 'password'}
        autoComplete={autoComplete}
        placeholder={placeholder}
        minLength={minLength}
        value={value}
        onChange={onChange}
        required
      />
      <button
        type="button"
        onClick={() => setPasswordVisible((current) => !current)}
        aria-controls={id}
        aria-label={passwordVisible ? 'Hide password' : 'Show password'}
        aria-pressed={passwordVisible}
        title={passwordVisible ? 'Hide password' : 'Show password'}
      >
        {passwordVisible ? <EyeOff size={19} /> : <Eye size={19} />}
      </button>
    </div>
  );
}

function AuthScreen({ eyebrow, title, subtitle, children, onNavigate }) {
  return (
    <section className="auth-screen" aria-labelledby="auth-title">
      <div className="auth-card auth-card-single">
        <header className="auth-single-header">
          <button className="auth-single-logo" type="button" onClick={() => onNavigate('/')}>
            <img src="/logo.png" alt="TFOE-PE logo" />
          </button>
          <span>{eyebrow}</span>
          <h1 id="auth-title">{title}</h1>
          <p>{subtitle}</p>
        </header>
        <div className="auth-form-panel">{children}</div>
      </div>
    </section>
  );
}

function AccessNotice({ onLogout }) {
  return (
    <section className="auth-screen">
      <div className="auth-card auth-card-small">
        <div className="access-notice">
          <Lock size={32} />
          <strong>Admin account detected</strong>
          <p>This member forum is for member accounts.</p>
          <button type="button" onClick={onLogout}>
            Logout
          </button>
        </div>
      </div>
    </section>
  );
}

function MemberShell({
  activeRoute,
  children,
  isAuthenticated,
  member,
  onLogout,
  onNavigate,
  onSearchChange,
  searchQuery,
}) {
  const [accountMenuOpen, setAccountMenuOpen] = useState(false);
  const accountMenuRef = useRef(null);
  const isForumRoute = activeRoute === '/';
  const isChangelogsRoute = activeRoute === '/changelogs';
  const showCommunityNav = isForumRoute || isChangelogsRoute || isAuthenticated;

  useEffect(() => {
    if (!accountMenuOpen) {
      return undefined;
    }

    const handlePointerDown = (event) => {
      if (!accountMenuRef.current?.contains(event.target)) {
        setAccountMenuOpen(false);
      }
    };
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        setAccountMenuOpen(false);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [accountMenuOpen]);

  const navigateFromAccountMenu = (path) => {
    setAccountMenuOpen(false);
    onNavigate(path);
  };

  const logoutFromAccountMenu = () => {
    setAccountMenuOpen(false);
    onLogout();
  };

  const handleForumSearchChange = (event) => {
    onSearchChange(event.target.value);

    if (!isForumRoute) {
      onNavigate('/');
    }
  };

  return (
    <div className="forum-app">
      <header className="topbar">
        <div className="topbar-side" aria-hidden="true" />

        {showCommunityNav ? (
          <nav className="topbar-nav" aria-label="Forum navigation">
            <button className="topbar-brand topbar-brand-center" type="button" onClick={() => onNavigate('/')}>
              <img src="/logo.png" alt="TFOE-PE logo" />
              <span>Ang Agila Forum</span>
            </button>

            {isForumRoute ? (
              <label className="topbar-search" htmlFor="topbar-forum-search">
                <Search size={17} aria-hidden="true" />
                <input
                  id="topbar-forum-search"
                  type="search"
                  value={searchQuery}
                  onChange={handleForumSearchChange}
                  placeholder="Search forum"
                />
              </label>
            ) : null}

            <button
              className={isForumRoute ? 'active' : ''}
              type="button"
              onClick={() => onNavigate('/')}
            >
              <MessageSquare size={17} />
              <span>Feed</span>
            </button>

            <button
              className={isChangelogsRoute ? 'active' : ''}
              type="button"
              onClick={() => onNavigate('/changelogs')}
            >
              <Settings size={17} />
              <span>Updates</span>
            </button>
          </nav>
        ) : null}

        {isAuthenticated ? (
          <div className="topbar-account-dropdown" ref={accountMenuRef}>
            <button
              className={`topbar-account-trigger ${accountMenuOpen ? 'open' : ''}`}
              type="button"
              onClick={() => setAccountMenuOpen((current) => !current)}
              aria-expanded={accountMenuOpen}
              aria-haspopup="menu"
            >
              <span className="account-trigger-avatar" aria-hidden="true">
                {member.picUrl ? <img src={member.picUrl} alt="" /> : <UserRound size={19} strokeWidth={2.4} />}
              </span>
              <span>Accounts</span>
              <ChevronDown className="account-chevron" size={17} aria-hidden="true" />
            </button>

            {accountMenuOpen ? (
              <div className="account-dropdown-menu" role="menu">
                <div className="account-dropdown-summary">
                  <span className="account-summary-avatar" aria-hidden="true">
                    {member.picUrl ? <img src={member.picUrl} alt="" /> : <UserRound size={22} strokeWidth={2.3} />}
                  </span>
                  <div>
                    <small>Signed in account</small>
                    <strong>{member.name}</strong>
                    <span>@{member.username || 'member'}</span>
                  </div>
                </div>

                <div className="account-dropdown-actions">
                  <button
                    className={activeRoute === '/profile' ? 'active' : ''}
                    type="button"
                    role="menuitem"
                    onClick={() => navigateFromAccountMenu('/profile')}
                  >
                    <UserRound size={18} />
                    <span>Profile</span>
                  </button>
                  <button
                    className={activeRoute === '/settings' ? 'active' : ''}
                    type="button"
                    role="menuitem"
                    onClick={() => navigateFromAccountMenu('/settings')}
                  >
                    <Settings size={18} />
                    <span>Settings</span>
                  </button>
                  <button className="danger" type="button" role="menuitem" onClick={logoutFromAccountMenu}>
                    <LogOut size={18} />
                    <span>Logout</span>
                  </button>
                </div>
              </div>
            ) : null}
          </div>
        ) : (
          <div className="topbar-side" aria-hidden="true" />
        )}
      </header>

      <main>{children}</main>
    </div>
  );
}

function ChangelogsPage({ onNavigate }) {
  return (
    <section className="changelog-page" aria-labelledby="changelog-title">
      <header className="page-header changelog-header">
        <div>
          <span className="page-kicker">Developer Notes</span>
          <h1 id="changelog-title">System Updates</h1>
          <p>Latest improvements, fixes, and release notes for the Ang Agila member forum.</p>
        </div>
        <button className="changelog-feed-link" type="button" onClick={() => onNavigate('/')}>
          <MessageSquare size={17} />
          <span>Back to Feed</span>
        </button>
      </header>

      <div className="changelog-hero-card">
        <div>
          <span>Release Transparency</span>
          <strong>What changed in the system?</strong>
          <p>
            This page keeps members aware of visible updates, technical fixes, and developer-side changes that affect the
            forum experience.
          </p>
        </div>
        <div className="changelog-hero-meter">
          <CheckCircle2 size={28} />
          <strong>{CHANGELOG_ENTRIES.length}</strong>
          <span>published notes</span>
        </div>
      </div>

      <div className="changelog-timeline">
        {CHANGELOG_ENTRIES.map((entry) => (
          <article className="changelog-card" key={`${entry.date}-${entry.title}`}>
            <div className="changelog-card-marker" aria-hidden="true">
              <Clock size={18} />
            </div>
            <div className="changelog-card-body">
              <div className="changelog-card-topline">
                <span>{entry.badge}</span>
                <time>{entry.date}</time>
              </div>
              <h2>{entry.title}</h2>
              <p>{entry.summary}</p>
              <ul>
                {entry.changes.map((change) => (
                  <li key={change}>{change}</li>
                ))}
              </ul>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function ForumPage({ isAuthenticated, member, onRequireAuth, searchQuery }) {
  const [categories, setCategories] = useState([]);
  const [threads, setThreads] = useState([]);
  const [activeCategory, setActiveCategory] = useState('all');
  const [selectedThreadId, setSelectedThreadId] = useState('');
  const [threadForm, setThreadForm] = useState(THREAD_FORM);
  const [feedback, setFeedback] = useState('');
  const [serviceNotice, setServiceNotice] = useState(null);
  const [loadingForum, setLoadingForum] = useState(true);
  const [forumReloadKey, setForumReloadKey] = useState(0);
  const [submittingThread, setSubmittingThread] = useState(false);

  const categoryOptions = useMemo(
    () => [
      { key: 'all', label: 'All Topics' },
      ...categories.map((category) => ({
        id: category.id,
        key: category.slug,
        label: category.name,
      })),
    ],
    [categories],
  );

  const categoryLabelByKey = useMemo(
    () => new Map(categoryOptions.map((category) => [category.key, category.label])),
    [categoryOptions],
  );

  const defaultCategory = categories[0]?.slug || '';
  const selectedCategory = categories.some((category) => category.slug === threadForm.category)
    ? threadForm.category
    : defaultCategory;

  const retryForumLoad = useCallback(() => {
    setFeedback('');
    setServiceNotice(null);
    setLoadingForum(true);
    setForumReloadKey((current) => current + 1);
  }, []);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      fetchApiJson(API_ENDPOINTS.forum.categories),
      fetchApiJson(API_ENDPOINTS.forum.threads),
    ])
      .then(([categoriesPayload, threadsPayload]) => {
        if (cancelled) return;
        const nextCategories = Array.isArray(categoriesPayload?.data) ? categoriesPayload.data : [];
        const nextThreads = sortThreads(
          (Array.isArray(threadsPayload?.data) ? threadsPayload.data : []).map((thread) => ({
            ...thread,
            replies: Array.isArray(thread.replies) ? thread.replies : [],
            detailsLoaded: Array.isArray(thread.replies) && thread.replies.length > 0,
          })),
        );

        setCategories(nextCategories);
        setThreads(nextThreads);
        setSelectedThreadId((current) => current || nextThreads[0]?.id || '');
        setFeedback('');
        setServiceNotice(null);
      })
      .catch((error) => {
        if (cancelled) return;
        setCategories([]);
        setThreads([]);
        setFeedback('');
        setServiceNotice(createForumServiceNotice(error));
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingForum(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [forumReloadKey]);

  const sortedThreads = useMemo(() => sortThreads(threads), [threads]);
  const filteredThreads = useMemo(() => {
    const normalizedSearch = searchQuery.trim().toLowerCase();

    return sortedThreads.filter((thread) => {
      const matchesCategory = activeCategory === 'all' || thread.category === activeCategory;
      if (!matchesCategory) {
        return false;
      }

      if (!normalizedSearch) {
        return true;
      }

      return [
        thread.title,
        thread.body,
        thread.author,
        thread.authorUsername,
        thread.categoryName,
        categoryLabelByKey.get(thread.category),
      ]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch));
    });
  }, [activeCategory, categoryLabelByKey, searchQuery, sortedThreads]);

  const totalReplies = useMemo(
    () =>
      threads.reduce(
        (total, thread) =>
          total + (Number(thread.replyCount) || (Array.isArray(thread.replies) ? thread.replies.length : 0)),
        0,
      ),
    [threads],
  );

  const pinnedThread = useMemo(
    () => sortedThreads.find((thread) => thread.pinned) || sortedThreads[0] || null,
    [sortedThreads],
  );

  const forumStats = useMemo(
    () => [
      { label: 'Topics', value: categories.length },
      { label: 'Threads', value: threads.length },
      { label: 'Replies', value: totalReplies },
      { label: 'Online now', value: loadingForum ? 'Checking' : 'Live', online: true },
    ],
    [categories.length, loadingForum, threads.length, totalReplies],
  );

  const updateThreadForm = (field, value) => {
    setThreadForm((current) => ({ ...current, [field]: value }));
  };

  const handleCreateThread = async (event) => {
    event.preventDefault();

    if (!isAuthenticated) {
      onRequireAuth();
      return;
    }

    const title = threadForm.title.trim();
    const body = threadForm.body.trim();
    const category = selectedCategory;

    if (!title || !body || !category) {
      setFeedback('Please complete the title, topic, and message.');
      return;
    }

    setSubmittingThread(true);
    setFeedback('');

    try {
      const payload = await postJson(API_ENDPOINTS.forum.threads, {
        title,
        body,
        category,
      });
      const nextThread = payload?.data;

      if (nextThread) {
        setThreads((current) =>
          sortThreads([
            {
              ...nextThread,
              replies: Array.isArray(nextThread.replies) ? nextThread.replies : [],
              detailsLoaded: true,
            },
            ...current,
          ]),
        );
        setSelectedThreadId(nextThread.id);
      }

      setThreadForm({ ...THREAD_FORM, category });
      setActiveCategory('all');
      setFeedback(payload.message || 'Discussion posted.');
      setServiceNotice(null);
    } catch (error) {
      setFeedback(error.message || 'Unable to post discussion right now.');
      if (isForumServiceIssue(error)) {
        setServiceNotice(createForumServiceNotice(error));
      }
    } finally {
      setSubmittingThread(false);
    }
  };

  const handleSelectThread = (threadId) => {
    setSelectedThreadId(threadId);

    setThreads((current) =>
      current.map((thread) =>
        thread.id === threadId
          ? {
              ...thread,
              views: (Number(thread.views) || 0) + 1,
            }
          : thread,
      ),
    );

    postJson(API_ENDPOINTS.forum.views, { threadId }).catch(() => {
      // Viewing should never interrupt reading the forum.
    });
  };

  return (
    <section className="forum-page" aria-labelledby="forum-title">
      <h1 id="forum-title" className="sr-only">Forum</h1>

      {serviceNotice ? (
        <ForumServiceNotice isRetrying={loadingForum} notice={serviceNotice} onRetry={retryForumLoad} />
      ) : null}

      <div className="forum-workspace">
        <section className="feed" aria-label="Forum discussions">
          <button
            className="pinned-strip"
            type="button"
            onClick={() => {
              if (pinnedThread) {
                handleSelectThread(pinnedThread.id);
              }
            }}
          >
            <Pin size={16} aria-hidden="true" />
            <span className="pinned-text">
              <strong>{pinnedThread?.title || 'Welcome to the member forum'}</strong>
              <span>
                {pinnedThread?.body ||
                  'Read the community rules, search before posting, and keep every chapter conversation respectful.'}
              </span>
            </span>
          </button>

          <div className="thread-list feed-list" aria-label="Discussion list">
            {loadingForum && filteredThreads.length === 0 ? (
              Array.from({ length: 4 }, (_, index) => <ForumPostSkeleton key={`forum-post-skeleton-${index}`} />)
            ) : filteredThreads.length > 0 ? (
              filteredThreads.map((thread) => (
                <ThreadListItem
                  isSelected={String(selectedThreadId) === String(thread.id)}
                  key={thread.id}
                  onSelect={() => handleSelectThread(thread.id)}
                  thread={thread}
                />
              ))
            ) : (
              <EmptyState
                icon={MessageSquare}
                title={
                  loadingForum
                    ? 'Loading discussions...'
                    : serviceNotice
                      ? 'Forum temporarily unavailable.'
                      : 'No discussions found.'
                }
                text={
                  loadingForum
                    ? 'Please wait while the forum loads.'
                    : serviceNotice
                      ? 'Use Retry above to check if the API/server is back online.'
                      : 'Try another topic or search term.'
                }
              />
            )}
          </div>

          <div className="load-more" role={loadingForum ? 'status' : undefined}>
            {loadingForum ? (
              <>
                <span className="load-spinner" aria-hidden="true" />
                <span>Loading more posts...</span>
              </>
            ) : (
              <span>{filteredThreads.length > 0 ? "You've reached the end." : 'Waiting for discussions.'}</span>
            )}
          </div>
        </section>

        <aside className="forum-sidebar sidebar" aria-label="Forum tools">
          {loadingForum ? (
            <ForumComposerSkeleton />
          ) : isAuthenticated ? (
            <form className="s-card forum-composer" onSubmit={handleCreateThread}>
              <div className="s-header">
                <UserRound size={15} aria-hidden="true" />
                <span className="s-title">Create post</span>
              </div>

              <div className="composer-body">
                <div className="composer-identity">
                  <div className="composer-avatar">{member.initials}</div>
                  <div>
                    <strong>{member.name}</strong>
                    <span>Start a discussion</span>
                  </div>
                </div>

                <label htmlFor="thread-title">
                  <span>Title</span>
                  <input
                    id="thread-title"
                    type="text"
                    value={threadForm.title}
                    onChange={(event) => updateThreadForm('title', event.target.value)}
                    placeholder="Post title"
                  />
                </label>

                <label htmlFor="thread-category">
                  <span>Topic</span>
                  <select
                    id="thread-category"
                    value={selectedCategory}
                    onChange={(event) => updateThreadForm('category', event.target.value)}
                    disabled={categories.length === 0}
                  >
                    {categories.map((category) => (
                      <option key={category.slug} value={category.slug}>
                        {category.name}
                      </option>
                    ))}
                  </select>
                </label>

                <label htmlFor="thread-body">
                  <span>Message</span>
                  <textarea
                    id="thread-body"
                    value={threadForm.body}
                    onChange={(event) => updateThreadForm('body', event.target.value)}
                    placeholder="Start the conversation"
                    rows={5}
                  />
                </label>

                <button type="submit" disabled={submittingThread || categories.length === 0}>
                  <Send size={16} />
                  <span>{submittingThread ? 'Posting...' : 'Post Discussion'}</span>
                </button>

                {feedback ? <div className="forum-feedback">{feedback}</div> : null}
              </div>
            </form>
          ) : null}

          <div className="s-card topic-panel">
            <div className="s-header">
              <Tag size={15} aria-hidden="true" />
              <span className="s-title">Topics</span>
            </div>
            <div className="category-list">
              {categoryOptions.map((category) => (
                <button
                  className={activeCategory === category.key ? 'active' : ''}
                  key={category.key}
                  type="button"
                  onClick={() => setActiveCategory(category.key)}
                >
                  <span className="category-mark">{category.key === 'all' ? '#' : category.label[0]}</span>
                  <span>{category.label}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="s-card rules-card">
            <div className="s-header">
              <CheckCircle2 size={15} aria-hidden="true" />
              <span className="s-title">Community rules</span>
            </div>
            <div className="rules-list">
              {[
                'Be respectful to all members.',
                'No spam or self-promotion.',
                'Search before posting.',
                'Use the correct category.',
                "Report issues; don't escalate them.",
              ].map((rule, index) => (
                <div className="rule-row" key={rule}>
                  <span className="rule-num">{index + 1}</span>
                  <span className="rule-text">{rule}</span>
                </div>
              ))}
            </div>
            {!isAuthenticated ? (
              <div className="join-banner">
                <p>Join the member discussion and create your first post.</p>
                <button className="join-now-btn" type="button" onClick={onRequireAuth}>
                  <UserPlus size={15} />
                  <span>Join us now</span>
                </button>
              </div>
            ) : null}
          </div>

          <div className="s-card">
            <div className="s-header">
              <MessageSquare size={15} aria-hidden="true" />
              <span className="s-title">Forum stats</span>
            </div>
            <div className="stats-list">
              {forumStats.map((stat) => (
                <div className="stat-row" key={stat.label}>
                  <span className="stat-label">
                    {stat.online ? <span className="online-dot" aria-hidden="true" /> : null}
                    {stat.label}
                  </span>
                  <span className={`stat-val ${stat.online ? 'online' : ''}`}>
                    {typeof stat.value === 'number' ? formatCompactNumber(stat.value) : stat.value}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </section>
  );
}

function ForumServiceNotice({ isRetrying, notice, onRetry }) {
  return (
    <div className="forum-service-notice" role="alert" aria-live="assertive">
      <span className="forum-service-notice-icon" aria-hidden="true">
        <CircleAlert size={22} strokeWidth={2.4} />
      </span>
      <div className="forum-service-notice-copy">
        <strong>{notice.title}</strong>
        <p>{notice.message}</p>
      </div>
      <button type="button" onClick={onRetry} disabled={isRetrying}>
        <RefreshCw size={16} />
        <span>{isRetrying ? 'Checking...' : 'Retry'}</span>
      </button>
    </div>
  );
}

function ForumPostSkeleton() {
  return (
    <div className="post-card thread-item forum-post-skeleton" aria-hidden="true">
      <span className="post-body">
        <span className="post-top">
          <Skeleton animation="wave" variant="rounded" width={68} height={18} />
          <Skeleton animation="wave" variant="text" width={116} sx={{ fontSize: '0.72rem' }} />
          <Skeleton animation="wave" variant="text" width={58} sx={{ fontSize: '0.72rem' }} />
        </span>
        <span className="post-content-line">
          <span className="post-copy">
            <Skeleton animation="wave" variant="text" width="74%" sx={{ fontSize: '1rem' }} />
            <Skeleton animation="wave" variant="text" width="94%" sx={{ fontSize: '0.82rem' }} />
            <Skeleton animation="wave" variant="text" width="58%" sx={{ fontSize: '0.82rem' }} />
          </span>
          <Skeleton animation="wave" className="post-thumb-skeleton" variant="rounded" width={80} height={56} />
        </span>
        <span className="post-actions">
          <Skeleton animation="wave" variant="rounded" width={92} height={24} />
          <Skeleton animation="wave" variant="rounded" width={72} height={24} />
          <Skeleton animation="wave" variant="rounded" width={82} height={24} />
          <Skeleton animation="wave" variant="rounded" width={62} height={24} />
          <Skeleton animation="wave" variant="rounded" width={68} height={24} />
        </span>
      </span>
    </div>
  );
}

function ForumComposerSkeleton() {
  return (
    <div className="s-card forum-composer forum-composer-skeleton" aria-hidden="true">
      <div className="s-header">
        <Skeleton animation="wave" variant="circular" width={15} height={15} />
        <Skeleton animation="wave" variant="text" width="44%" sx={{ fontSize: '0.76rem' }} />
      </div>
      <div className="composer-body">
        <div className="composer-identity">
          <Skeleton animation="wave" variant="circular" width={34} height={34} />
          <div className="composer-skeleton-copy">
            <Skeleton animation="wave" variant="text" width={112} sx={{ fontSize: '0.82rem' }} />
            <Skeleton animation="wave" variant="text" width={84} sx={{ fontSize: '0.69rem' }} />
          </div>
        </div>
        <Skeleton animation="wave" variant="rounded" width="100%" height={34} />
        <Skeleton animation="wave" variant="rounded" width="100%" height={34} />
        <Skeleton animation="wave" variant="rounded" width="100%" height={92} />
        <Skeleton animation="wave" variant="rounded" width="100%" height={32} />
      </div>
    </div>
  );
}

function ForumSidebarSkeleton({ rows, titleWidth }) {
  return (
    <div className="s-card forum-sidebar-skeleton" aria-hidden="true">
      <div className="s-header">
        <Skeleton animation="wave" variant="circular" width={15} height={15} />
        <Skeleton animation="wave" variant="text" width={titleWidth} sx={{ fontSize: '0.76rem' }} />
      </div>
      <div className="rules-list">
        {Array.from({ length: rows }, (_, index) => (
          <div className="rule-row" key={`sidebar-skeleton-${index}`}>
            <Skeleton animation="wave" variant="circular" width={18} height={18} />
            <Skeleton animation="wave" variant="text" width={`${86 - index * 8}%`} sx={{ fontSize: '0.72rem' }} />
          </div>
        ))}
      </div>
    </div>
  );
}

function ThreadListItem({ isSelected, onSelect, thread }) {
  const replyCount = Number(thread.replyCount) || (Array.isArray(thread.replies) ? thread.replies.length : 0);
  const likeCount = Number(thread.approveCount) || 0;
  const dislikeCount = Number(thread.disapproveCount) || 0;
  const topicLabel = thread.categoryName || thread.category || 'General';

  return (
    <button className={`post-card thread-item ${isSelected ? 'active' : ''}`} type="button" onClick={onSelect}>
      <span className="post-body">
        <span className="post-top">
          <span className={`post-tag ${getThreadTagClass(thread)}`}>{topicLabel}</span>
          <span className="post-author">
            by <AuthorLabel name={thread.author} username={thread.authorUsername} />
          </span>
          <span className="post-dot" aria-hidden="true">
            -
          </span>
          <span className="post-time">{formatForumDate(getLatestActivityTime(thread))}</span>
          {thread.pinned ? (
            <span className="pinned-label">
              <Pin size={13} />
              Pinned
            </span>
          ) : null}
        </span>
        <span className="post-content-line">
          <span className="post-copy">
            <strong className="post-title">{thread.title}</strong>
            <span className="post-preview">{thread.body}</span>
          </span>
          <span className="post-thumb" aria-hidden="true">
            {thread.pinned ? <Pin size={21} /> : <MessageSquare size={21} />}
          </span>
        </span>
        <span className="post-actions">
          <span className="action-btn like-action">
            <ThumbsUp size={13} />
            Like {formatCompactNumber(likeCount)}
          </span>
          <span className="action-btn dislike-action">
            <ThumbsDown size={13} />
            Dislike {formatCompactNumber(dislikeCount)}
          </span>
          <span className="action-btn">
            <MessageSquare size={13} />
            {formatCompactNumber(replyCount)} comments
          </span>
          <span className="action-btn">
            <Eye size={13} />
            {formatCompactNumber(Number(thread.views) || 0)}
          </span>
          <span className="action-btn">
            <Clock size={13} />
            Latest
          </span>
        </span>
      </span>
    </button>
  );
}

function AuthorLabel({ name, username }) {
  const showUsername = Boolean(username && username !== name);

  return (
    <span className="forum-author">
      <strong>{name || username || 'Member'}</strong>
      {showUsername ? <small>@{username}</small> : null}
    </span>
  );
}

function ProfilePage({ member, memberDetails, onLinkEaglesId, session }) {
  const [eaglesId, setEaglesId] = useState(session?.eaglesId || '');
  const [feedback, setFeedback] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [memberIdOpen, setMemberIdOpen] = useState(false);
  const closeMemberId = useCallback(() => setMemberIdOpen(false), []);

  const handleSubmit = async (event) => {
    event.preventDefault();

    const nextEaglesId = eaglesId.trim().toUpperCase();
    if (!/^TFOEPE[0-9]{8}$/.test(nextEaglesId)) {
      setFeedback({
        type: 'error',
        message: 'Eagles ID must use the format TFOEPE followed by 8 numbers.',
      });
      return;
    }

    setSubmitting(true);
    setFeedback(null);

    try {
      const payload = await onLinkEaglesId(nextEaglesId);
      setFeedback({
        type: 'success',
        message: payload.message || 'Eagles ID linked successfully.',
      });
    } catch (error) {
      setFeedback({
        type: 'error',
        message: error.message || 'Unable to link Eagles ID right now.',
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="profile-page" aria-labelledby="profile-title">
      <header className="page-header">
        <div>
          <span className="page-kicker">Member Access</span>
          <h1 id="profile-title">Profile</h1>
        </div>
        <div className="profile-id-badge">
          <IdCard size={18} />
          <span>{member.eaglesId || 'No Eagles ID linked'}</span>
        </div>
      </header>

      <div className="profile-grid">
        <section className="profile-card profile-summary">
          <div className="profile-hero">
            <div className="profile-photo">
              <img src={member.picUrl || '/logo.png'} alt="" />
            </div>
            <div>
              <h2>{member.name}</h2>
              <p>{member.username || 'Member account'}</p>
              <span>{member.status}</span>
            </div>
          </div>

          <div className="status-panel">
            <strong>ID Status</strong>
            <p>{member.statusNote}</p>
          </div>
        </section>

        <section className="profile-card">
          <div className="profile-card-head">Eagles ID</div>
          <form className="eagles-id-form" onSubmit={handleSubmit}>
            <label htmlFor="profile-eagles-id">
              <span>Eagles ID</span>
              <input
                id="profile-eagles-id"
                type="text"
                autoComplete="off"
                placeholder="TFOEPE20260000"
                value={eaglesId}
                onChange={(event) => setEaglesId(event.target.value.toUpperCase())}
                required
              />
            </label>

            <button type="submit" disabled={submitting}>
              {submitting ? 'Saving...' : session?.eaglesId ? 'Update Eagles ID' : 'Link Eagles ID'}
            </button>

            {feedback ? <div className={`auth-alert ${feedback.type}`}>{feedback.message}</div> : null}
          </form>
        </section>

        {member.eaglesId && memberDetails ? (
          <section className="profile-card profile-card-wide member-id-access-card">
            <div className="member-id-access-copy">
              <span className="member-id-access-icon" aria-hidden="true">
                <IdCard size={30} />
              </span>
              <div>
                <span>Digital Member ID</span>
                <strong>{member.eaglesId}</strong>
                <p>Open your verified member profile and virtual ID card.</p>
              </div>
            </div>
            <button
              className="member-id-access-action"
              type="button"
              onClick={() => setMemberIdOpen(true)}
            >
              <Eye size={18} />
              <span>View My Member ID</span>
            </button>
          </section>
        ) : null}

        <section className="profile-card profile-card-wide">
          <div className="profile-card-head">Member Information</div>
          <div className="profile-info-grid">
            <InfoCell label="Name" value={member.name} />
            <InfoCell label="Username" value={member.username} />
            <InfoCell label="Club" value={member.club} />
            <InfoCell label="Club Position" value={member.clubPosition} />
            <InfoCell label="Region" value={member.region} />
            <InfoCell label="Governor" value={member.governor} />
            <InfoCell label="Regional Position" value={member.regionalPosition} />
            <InfoCell label="ID Number" value={member.eaglesId} />
          </div>
        </section>

        {!memberDetails && session?.eaglesId ? (
          <section className="profile-card profile-card-wide">
            <EmptyState
              icon={IdCard}
              title="Member record not found."
              text="The linked Eagles ID has no matching member profile data yet."
            />
          </section>
        ) : null}
      </div>

      {memberIdOpen ? <MemberIdModal member={member} onClose={closeMemberId} /> : null}
    </section>
  );
}

function MemberIdModal({ member, onClose }) {
  const closeButtonRef = useRef(null);

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    closeButtonRef.current?.focus();

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [onClose]);

  return (
    <div
      className="member-id-modal-backdrop"
      onPointerDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        className="member-id-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-label="Digital member ID card"
      >
        <button
          className="member-id-modal-close"
          type="button"
          ref={closeButtonRef}
          onClick={onClose}
          aria-label="Close member ID"
        >
          <X size={21} />
        </button>

        <div className="member-id-modal-card">
          <MemberVirtualIdCard member={member} memberId={member.eaglesId} />
        </div>

        <p className="member-id-modal-hint">Click outside the card or press Esc to close.</p>
      </div>
    </div>
  );
}

function SettingsPage({ member, onUpdateAccount }) {
  const [displayNameForm, setDisplayNameForm] = useState({
    name: member.name || '',
    currentPassword: '',
  });
  const [usernameForm, setUsernameForm] = useState({
    username: member.username || '',
    currentPassword: '',
  });
  const [passwordForm, setPasswordForm] = useState({
    currentPassword: '',
    password: '',
    passwordConfirm: '',
  });
  const [displayNameFeedback, setDisplayNameFeedback] = useState(null);
  const [usernameFeedback, setUsernameFeedback] = useState(null);
  const [passwordFeedback, setPasswordFeedback] = useState(null);
  const [savingDisplayName, setSavingDisplayName] = useState(false);
  const [savingUsername, setSavingUsername] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);

  const updateDisplayNameField = (field, value) => {
    setDisplayNameForm((current) => ({ ...current, [field]: value }));
  };

  const updateUsernameField = (field, value) => {
    setUsernameForm((current) => ({ ...current, [field]: value }));
  };

  const updatePasswordField = (field, value) => {
    setPasswordForm((current) => ({ ...current, [field]: value }));
  };

  const handleDisplayNameSubmit = async (event) => {
    event.preventDefault();
    const name = displayNameForm.name.trim();

    if (name === member.name) {
      setDisplayNameFeedback({ type: 'error', message: 'Enter a different display name.' });
      return;
    }

    setSavingDisplayName(true);
    setDisplayNameFeedback(null);

    try {
      const payload = await onUpdateAccount({
        name,
        currentPassword: displayNameForm.currentPassword,
      });
      setDisplayNameForm({ name, currentPassword: '' });
      setDisplayNameFeedback({
        type: 'success',
        message: payload.message || 'Display name updated successfully.',
      });
    } catch (error) {
      setDisplayNameForm((current) => ({ ...current, currentPassword: '' }));
      setDisplayNameFeedback({
        type: 'error',
        message: error.message || 'Unable to update your display name.',
      });
    } finally {
      setSavingDisplayName(false);
    }
  };

  const handleUsernameSubmit = async (event) => {
    event.preventDefault();
    const username = usernameForm.username.trim();

    if (username === member.username) {
      setUsernameFeedback({ type: 'error', message: 'Enter a different username.' });
      return;
    }

    setSavingUsername(true);
    setUsernameFeedback(null);

    try {
      const payload = await onUpdateAccount({
        username,
        currentPassword: usernameForm.currentPassword,
      });
      setUsernameForm({ username, currentPassword: '' });
      setUsernameFeedback({
        type: 'success',
        message: payload.message || 'Username updated successfully.',
      });
    } catch (error) {
      setUsernameForm((current) => ({ ...current, currentPassword: '' }));
      setUsernameFeedback({
        type: 'error',
        message: error.message || 'Unable to update your username.',
      });
    } finally {
      setSavingUsername(false);
    }
  };

  const handlePasswordSubmit = async (event) => {
    event.preventDefault();

    if (passwordForm.password !== passwordForm.passwordConfirm) {
      setPasswordFeedback({ type: 'error', message: 'New passwords do not match.' });
      return;
    }

    setSavingPassword(true);
    setPasswordFeedback(null);

    try {
      const payload = await onUpdateAccount(passwordForm);
      setPasswordForm({ currentPassword: '', password: '', passwordConfirm: '' });
      setPasswordFeedback({
        type: 'success',
        message: payload.message || 'Password updated successfully.',
      });
    } catch (error) {
      setPasswordForm((current) => ({ ...current, currentPassword: '' }));
      setPasswordFeedback({
        type: 'error',
        message: error.message || 'Unable to update your password.',
      });
    } finally {
      setSavingPassword(false);
    }
  };

  return (
    <section className="profile-page" aria-labelledby="settings-title">
      <header className="page-header">
        <div>
          <span className="page-kicker">Account</span>
          <h1 id="settings-title">Settings</h1>
        </div>
      </header>

      <div className="settings-grid">
        <section className="profile-card">
          <div className="profile-card-head">Forum Account</div>
          <div className="profile-info-grid settings-info-grid">
            <InfoCell label="Display Name" value={member.name} />
            <InfoCell label="Username" value={member.username} />
            <InfoCell label="Eagles ID" value={member.eaglesId} />
            <InfoCell label="Member Status" value={member.status} />
          </div>
        </section>

        <div className="settings-security-grid">
          <section className="profile-card">
            <div className="profile-card-head">Change Display Name</div>
            <form className="auth-form settings-form" onSubmit={handleDisplayNameSubmit}>
              <label htmlFor="settings-display-name">
                <span>New Display Name</span>
                <input
                  id="settings-display-name"
                  type="text"
                  autoComplete="name"
                  minLength={2}
                  maxLength={150}
                  value={displayNameForm.name}
                  onChange={(event) => updateDisplayNameField('name', event.target.value)}
                  required
                />
              </label>

              <label htmlFor="settings-name-current-password">
                <span>Current Password</span>
                <PasswordInput
                  id="settings-name-current-password"
                  autoComplete="current-password"
                  placeholder="Confirm your current password"
                  value={displayNameForm.currentPassword}
                  onChange={(event) => updateDisplayNameField('currentPassword', event.target.value)}
                />
              </label>

              <button className="auth-submit" type="submit" disabled={savingDisplayName}>
                {savingDisplayName ? 'Saving display name...' : 'Save Display Name'}
              </button>

              {displayNameFeedback ? (
                <div className={`auth-alert ${displayNameFeedback.type}`}>{displayNameFeedback.message}</div>
              ) : null}
            </form>
          </section>

          <section className="profile-card">
            <div className="profile-card-head">Change Username</div>
            <form className="auth-form settings-form" onSubmit={handleUsernameSubmit}>
              <label htmlFor="settings-username">
                <span>New Username</span>
                <input
                  id="settings-username"
                  type="text"
                  autoComplete="username"
                  minLength={4}
                  maxLength={20}
                  pattern="[a-zA-Z0-9_]+"
                  value={usernameForm.username}
                  onChange={(event) => updateUsernameField('username', event.target.value)}
                  required
                />
              </label>

              <label htmlFor="settings-username-current-password">
                <span>Current Password</span>
                <PasswordInput
                  id="settings-username-current-password"
                  autoComplete="current-password"
                  placeholder="Confirm your current password"
                  value={usernameForm.currentPassword}
                  onChange={(event) => updateUsernameField('currentPassword', event.target.value)}
                />
              </label>

              <button className="auth-submit" type="submit" disabled={savingUsername}>
                {savingUsername ? 'Saving username...' : 'Save Username'}
              </button>

              {usernameFeedback ? (
                <div className={`auth-alert ${usernameFeedback.type}`}>{usernameFeedback.message}</div>
              ) : null}
            </form>
          </section>

          <section className="profile-card">
            <div className="profile-card-head">Change Password</div>
            <form className="auth-form settings-form" onSubmit={handlePasswordSubmit}>
              <label htmlFor="settings-current-password">
                <span>Current Password</span>
                <PasswordInput
                  id="settings-current-password"
                  autoComplete="current-password"
                  placeholder="Enter your current password"
                  value={passwordForm.currentPassword}
                  onChange={(event) => updatePasswordField('currentPassword', event.target.value)}
                />
              </label>

              <label htmlFor="settings-new-password">
                <span>New Password</span>
                <PasswordInput
                  id="settings-new-password"
                  autoComplete="new-password"
                  minLength={8}
                  placeholder="At least 8 characters"
                  value={passwordForm.password}
                  onChange={(event) => updatePasswordField('password', event.target.value)}
                />
              </label>

              <label htmlFor="settings-confirm-password">
                <span>Confirm New Password</span>
                <PasswordInput
                  id="settings-confirm-password"
                  autoComplete="new-password"
                  minLength={8}
                  placeholder="Repeat your new password"
                  value={passwordForm.passwordConfirm}
                  onChange={(event) => updatePasswordField('passwordConfirm', event.target.value)}
                />
              </label>

              <button className="auth-submit" type="submit" disabled={savingPassword}>
                {savingPassword ? 'Saving password...' : 'Save Password'}
              </button>

              {passwordFeedback ? (
                <div className={`auth-alert ${passwordFeedback.type}`}>{passwordFeedback.message}</div>
              ) : null}
            </form>
          </section>
        </div>
      </div>
    </section>
  );
}

function InfoCell({ label, value }) {
  return (
    <div className="info-cell">
      <span>{label}</span>
      <strong>{value || 'Not available'}</strong>
    </div>
  );
}

function EmptyState({ icon, text, title }) {
  return (
    <div className="empty-state">
      {createElement(icon, { size: 30 })}
      <strong>{title}</strong>
      <p>{text}</p>
    </div>
  );
}
