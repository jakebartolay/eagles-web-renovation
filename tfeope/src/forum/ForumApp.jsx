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
  Plus,
  Search,
  Send,
  Settings,
  Tag,
  UserPlus,
  UserRound,
  Users,
  X,
} from 'lucide-react';
import { createElement, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import MemberVirtualIdCard from '../components/MemberVirtualIdCard';
import SocialIcon from '../components/SocialIcon';
import { SOCIAL_LINKS } from '../components/socialLinks';
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

const REPLY_FORM = {
  body: '',
};

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

function normalizeThreadReply(post) {
  const author = post?.author || {};

  return {
    id: Number(post?.id) || 0,
    authorId: Number(author.id) || 0,
    author: author.name || author.username || 'Member',
    authorUsername: author.username || '',
    authorRoleId: Number(author.roleId) || 0,
    authorIsMember: Boolean(author.isMember),
    authorClub: author.club || null,
    authorAvatarSeed: author.avatarSeed || '',
    authorAvatarStyle: author.avatarStyle || '',
    body: post?.body || '',
    parentPostId: post?.parentPostId || null,
    createdAt: post?.createdAt || '',
    likes: Number(post?.likeCount) || 0,
  };
}

function normalizeThreadDetail(payload) {
  const thread = payload?.thread || {};
  const author = thread.author || {};
  const replies = Array.isArray(payload?.posts) ? payload.posts.map(normalizeThreadReply) : [];

  return {
    id: Number(thread.id) || 0,
    categoryId: Number(thread.categoryId) || 0,
    category: thread.categorySlug || '',
    categoryName: thread.categoryName || '',
    title: thread.title || '',
    slug: thread.slug || '',
    authorId: Number(author.id) || 0,
    author: author.name || author.username || 'Member',
    authorUsername: author.username || '',
    authorRoleId: Number(author.roleId) || 0,
    authorIsMember: Boolean(author.isMember),
    authorClub: author.club || null,
    authorAvatarSeed: author.avatarSeed || '',
    authorAvatarStyle: author.avatarStyle || '',
    body: thread.body || '',
    image: thread.image || null,
    pinned: Boolean(thread.isPinned),
    locked: Boolean(thread.isLocked),
    views: Number(thread.views) || 0,
    replyCount: Number(thread.replyCount) || replies.length,
    approveCount: Number(thread.approveCount) || 0,
    disapproveCount: Number(thread.disapproveCount) || 0,
    myReaction: thread.myReaction || null,
    createdAt: thread.createdAt || '',
    replies,
    detailsLoaded: true,
  };
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
    <section className="auth-screen">
      <div className="auth-card auth-card-small">
        <div className="brand-lockup">
          <img src="/logo.png" alt="TFOE-PE logo" />
          <div>
            <strong>Member Forum</strong>
            <span>Checking session...</span>
          </div>
        </div>
      </div>
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
  const isForumView = !['/profile', '/settings'].includes(activeRoute);

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

    if (!isForumView) {
      onNavigate('/');
    }
  };

  return (
    <div className="forum-app">
      <header className="topbar">
        <div className="topbar-brand">
          <img src="/logo.png" alt="TFOE-PE logo" />
          <span>Ang Agila Forum</span>
        </div>

        {isAuthenticated || isForumView ? (
          <nav className="topbar-nav" aria-label="Forum navigation">
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

            {isAuthenticated ? (
              <button
                className={isForumView ? 'active' : ''}
                type="button"
                onClick={() => onNavigate('/')}
              >
                <MessageSquare size={17} />
                <span>Forum</span>
              </button>
            ) : null}
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
          <div className="topbar-auth">
            <button type="button" onClick={() => onNavigate('/login')}>
              <LogIn size={16} />
              <span>Sign in</span>
            </button>
            <button className="primary" type="button" onClick={() => onNavigate('/signup')}>
              <UserPlus size={16} />
              <span>Sign up</span>
            </button>
          </div>
        )}
      </header>

      <main>{children}</main>

      <ForumFooter isAuthenticated={isAuthenticated} onNavigate={onNavigate} />
    </div>
  );
}

function ForumFooter({ isAuthenticated, onNavigate }) {
  return (
    <footer className="forum-footer">
      <div className="forum-footer-main">
        <section className="forum-footer-intro">
          <div className="forum-footer-brand">
            <img src="/logo.png" alt="TFOE-PE logo" />
            <div>
              <strong>Ang Agila Forum</strong>
              <span>TFOE-PE Member Community</span>
            </div>
          </div>
          <p>
            A shared space for Philippine Eagles members to connect, exchange updates, and strengthen
            brotherhood through service.
          </p>
          <div className="forum-footer-socials" aria-label="Ang Agila social media">
            {SOCIAL_LINKS.map(({ href, label, network }) => (
              <a key={network} href={href} target="_blank" rel="noreferrer" aria-label={label} title={label}>
                <SocialIcon network={network} size={18} />
              </a>
            ))}
          </div>
        </section>

        <nav className="forum-footer-links" aria-label="Forum links">
          <strong>Forum</strong>
          <button type="button" onClick={() => onNavigate('/')}>Discussions</button>
          <button type="button" onClick={() => onNavigate(isAuthenticated ? '/profile' : '/login')}>
            {isAuthenticated ? 'My Profile' : 'Sign In'}
          </button>
          <button type="button" onClick={() => onNavigate(isAuthenticated ? '/settings' : '/signup')}>
            {isAuthenticated ? 'Settings' : 'Create Account'}
          </button>
        </nav>

        <nav className="forum-footer-links" aria-label="Member resources">
          <strong>Members</strong>
          <a href="/members/member_search">Virtual Member ID</a>
          <a href="/membership/application">ID Application</a>
          <a href="/clubs">Regional Clubs</a>
        </nav>

        <nav className="forum-footer-links" aria-label="Ang Agila website links">
          <strong>Ang Agila</strong>
          <a href="/">Main Website</a>
          <a href="/news">News &amp; Videos</a>
          <a href="/events">Events</a>
        </nav>
      </div>

      <div className="forum-footer-bottom">
        <p>© {new Date().getFullYear()} Ang Agila · The Fraternal Order of Eagles. All rights reserved.</p>
        <span>Service Through Strong Brotherhood</span>
      </div>
    </footer>
  );
}

function ForumPage({ isAuthenticated, member, onRequireAuth, searchQuery }) {
  const [categories, setCategories] = useState([]);
  const [threads, setThreads] = useState([]);
  const [activeCategory, setActiveCategory] = useState('all');
  const [selectedThreadId, setSelectedThreadId] = useState('');
  const [threadForm, setThreadForm] = useState(THREAD_FORM);
  const [replyForm, setReplyForm] = useState(REPLY_FORM);
  const [feedback, setFeedback] = useState('');
  const [loadingForum, setLoadingForum] = useState(true);
  const [loadingThreadId, setLoadingThreadId] = useState('');
  const [submittingThread, setSubmittingThread] = useState(false);
  const [submittingReply, setSubmittingReply] = useState(false);

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
            detailsLoaded: Array.isArray(thread.replies) && thread.replies.length > 0,
          })),
        );

        setCategories(nextCategories);
        setThreads(nextThreads);
        setSelectedThreadId((current) => current || nextThreads[0]?.id || '');
        setFeedback('');
      })
      .catch((error) => {
        if (cancelled) return;
        setCategories([]);
        setThreads([]);
        setFeedback(error.message || 'Unable to load forum discussions right now.');
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingForum(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

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

  const selectedThread = useMemo(() => {
    const visibleMatch = filteredThreads.find((thread) => thread.id === selectedThreadId);
    if (visibleMatch) {
      return visibleMatch;
    }

    return filteredThreads[0] || sortedThreads[0] || null;
  }, [filteredThreads, selectedThreadId, sortedThreads]);

  const totalReplies = useMemo(
    () =>
      threads.reduce(
        (sum, thread) => sum + (Number(thread.replyCount) || (Array.isArray(thread.replies) ? thread.replies.length : 0)),
        0,
      ),
    [threads],
  );

  const selectedThreadDetailId = selectedThread?.id || '';
  const selectedThreadDetailsLoaded = Boolean(selectedThread?.detailsLoaded);

  useEffect(() => {
    if (!selectedThreadDetailId || selectedThreadDetailsLoaded) {
      return undefined;
    }

    let cancelled = false;
    setLoadingThreadId(String(selectedThreadDetailId));

    fetchApiJson(`${API_ENDPOINTS.forum.thread}?id=${encodeURIComponent(selectedThreadDetailId)}&trackView=0`)
      .then((payload) => {
        if (cancelled) return;

        const threadDetail = normalizeThreadDetail(payload);
        if (!threadDetail.id) return;

        setThreads((current) =>
          sortThreads(
            current.map((thread) =>
              thread.id === threadDetail.id
                ? {
                    ...thread,
                    ...threadDetail,
                    views: Math.max(Number(thread.views) || 0, Number(threadDetail.views) || 0),
                  }
                : thread,
            ),
          ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setThreads((current) =>
            current.map((thread) =>
              thread.id === selectedThreadDetailId
                ? {
                    ...thread,
                    detailsLoaded: true,
                  }
                : thread,
            ),
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingThreadId('');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [selectedThreadDetailId, selectedThreadDetailsLoaded]);

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
        setThreads((current) => sortThreads([{ ...nextThread, detailsLoaded: true }, ...current]));
        setSelectedThreadId(nextThread.id);
      }

      setThreadForm({ ...THREAD_FORM, category });
      setActiveCategory('all');
      setFeedback(payload.message || 'Discussion posted.');
    } catch (error) {
      setFeedback(error.message || 'Unable to post discussion right now.');
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

  const handleReply = async (event) => {
    event.preventDefault();

    if (!isAuthenticated) {
      onRequireAuth();
      return;
    }

    const body = replyForm.body.trim();

    if (!body || !selectedThread) {
      setFeedback('Please enter a reply first.');
      return;
    }

    setSubmittingReply(true);
    setFeedback('');

    try {
      const payload = await postJson(API_ENDPOINTS.forum.posts, {
        threadId: selectedThread.id,
        body,
      });
      const nextReply = payload?.data;

      if (nextReply) {
        setThreads((current) =>
          sortThreads(
            current.map((thread) =>
              thread.id === selectedThread.id
                ? {
                    ...thread,
                    replies: [...(thread.replies || []), nextReply],
                    detailsLoaded: true,
                    replyCount: (Number(thread.replyCount) || (thread.replies || []).length) + 1,
                    lastReplyAt: nextReply.createdAt,
                  }
                : thread,
            ),
          ),
        );
      }

      setReplyForm(REPLY_FORM);
      setFeedback(payload.message || 'Reply added.');
    } catch (error) {
      setFeedback(error.message || 'Unable to add reply right now.');
    } finally {
      setSubmittingReply(false);
    }
  };

  const topicLabel = (thread) => thread?.categoryName || categoryLabelByKey.get(thread?.category) || thread?.category || 'Topic';

  return (
    <section className="forum-page" aria-labelledby="forum-title">
      <header className="page-header">
        <div>
          <span className="page-kicker">Public Community</span>
          <h1 id="forum-title">Forum</h1>
        </div>
        <div className="page-stats" aria-label="Forum summary">
          <span>
            <MessageSquare size={17} />
            {threads.length} discussions
          </span>
          <span>
            <Users size={17} />
            {totalReplies} replies
          </span>
        </div>
      </header>

      <div className="forum-workspace">
        <aside className="forum-sidebar" aria-label="Forum tools">
          {isAuthenticated ? (
            <form className="tool-panel forum-composer" onSubmit={handleCreateThread}>
              <div className="panel-title">
                <Plus size={18} />
                <span>New Discussion</span>
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
                <Send size={17} />
                <span>{submittingThread ? 'Posting...' : 'Post Discussion'}</span>
              </button>

              {feedback ? <div className="forum-feedback">{feedback}</div> : null}
            </form>
          ) : (
            <div className="tool-panel guest-panel">
              <div className="panel-title">
                <Plus size={18} />
                <span>Join the discussion</span>
              </div>
              <p>Visitors can read all topics. Sign in or create an account to post and reply.</p>
              <button type="button" onClick={onRequireAuth}>
                <LogIn size={17} />
                <span>Sign in to post</span>
              </button>
            </div>
          )}

          <div className="tool-panel">
            <div className="panel-title">
              <Tag size={18} />
              <span>Topics</span>
            </div>
            <div className="category-list">
              {categoryOptions.map((category) => (
                <button
                  className={activeCategory === category.key ? 'active' : ''}
                  key={category.key}
                  type="button"
                  onClick={() => setActiveCategory(category.key)}
                >
                  {category.label}
                </button>
              ))}
            </div>
          </div>
        </aside>

        <section className="forum-main" aria-label="Forum discussions">
          <div className="forum-layout">
            <div className="thread-list" aria-label="Discussion list">
              {filteredThreads.length > 0 ? (
                filteredThreads.map((thread) => (
                  <ThreadListItem
                    isSelected={selectedThread?.id === thread.id}
                    key={thread.id}
                    onSelect={() => handleSelectThread(thread.id)}
                    thread={thread}
                  />
                ))
              ) : (
                <EmptyState
                  icon={MessageSquare}
                  title={loadingForum ? 'Loading discussions...' : 'No discussions found.'}
                  text={loadingForum ? 'Please wait while the forum loads.' : 'Try another topic or search term.'}
                />
              )}
            </div>

            <article className="thread-detail" aria-label="Selected discussion">
              {selectedThread ? (
                <>
                  <div className="thread-detail-head">
                    <span className="topic-pill">{topicLabel(selectedThread)}</span>
                    <h2>{selectedThread.title}</h2>
                    <div className="thread-detail-meta">
                      <AuthorLabel name={selectedThread.author} username={selectedThread.authorUsername} />
                      <span>{formatForumDate(selectedThread.createdAt)}</span>
                    </div>
                  </div>

                  <p className="thread-detail-body">{selectedThread.body}</p>

                  <div className="action-row">
                    <span>
                      <Eye size={17} />
                      {selectedThread.views} views
                    </span>
                    <span>
                      <MessageSquare size={17} />
                      {Number(selectedThread.replyCount) || selectedThread.replies.length} replies
                    </span>
                  </div>

                  <div className="reply-list">
                    <h3>Replies</h3>
                    {loadingThreadId === String(selectedThread.id) ? (
                      <p className="no-replies">Loading replies...</p>
                    ) : selectedThread.replies.length > 0 ? (
                      selectedThread.replies.map((reply) => (
                        <div className="reply-item" key={reply.id}>
                          <div className="reply-avatar">{getMemberInitials(reply.author)}</div>
                          <div>
                            <div className="reply-meta">
                              <AuthorLabel name={reply.author} username={reply.authorUsername} />
                              <span>{formatForumDate(reply.createdAt)}</span>
                            </div>
                            <p>{reply.body}</p>
                          </div>
                        </div>
                      ))
                    ) : (
                      <p className="no-replies">No replies yet.</p>
                    )}
                  </div>

                  <form className="reply-form" onSubmit={handleReply}>
                    {isAuthenticated ? (
                      <>
                        <label htmlFor="reply-body">
                          <span>Reply as {member.name}</span>
                          <textarea
                            id="reply-body"
                            rows={4}
                            value={replyForm.body}
                            onChange={(event) => setReplyForm({ body: event.target.value })}
                            placeholder="Write a reply"
                          />
                        </label>
                        <button type="submit" disabled={submittingReply}>
                          <Send size={17} />
                          <span>{submittingReply ? 'Sending...' : 'Send Reply'}</span>
                        </button>
                      </>
                    ) : (
                      <div className="reply-signin">
                        <strong>Want to reply?</strong>
                        <button type="button" onClick={onRequireAuth}>
                          Sign in
                        </button>
                      </div>
                    )}
                  </form>
                </>
              ) : (
                <EmptyState
                  icon={MessageSquare}
                  title={loadingForum ? 'Loading forum.' : 'Select a discussion.'}
                  text={loadingForum ? 'The database-backed forum is loading.' : 'Choose a topic from the list.'}
                />
              )}
            </article>
          </div>
        </section>
      </div>
    </section>
  );
}

function ThreadListItem({ isSelected, onSelect, thread }) {
  return (
    <button className={`thread-item ${isSelected ? 'active' : ''}`} type="button" onClick={onSelect}>
      <div className="thread-topline">
        <span className="topic-pill">{thread.categoryName || thread.category}</span>
        {thread.pinned ? (
          <span className="pinned-label">
            <Pin size={14} />
            Pinned
          </span>
        ) : null}
      </div>
      <strong>{thread.title}</strong>
      <p>{thread.body}</p>
      <div className="thread-meta">
        <AuthorLabel name={thread.author} username={thread.authorUsername} />
        <span>
          <Clock size={14} />
          {formatForumDate(getLatestActivityTime(thread))}
        </span>
        <span>
          <MessageSquare size={14} />
          {Number(thread.replyCount) || thread.replies.length}
        </span>
      </div>
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
