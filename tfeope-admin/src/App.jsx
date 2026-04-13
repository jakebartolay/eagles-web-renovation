import { useEffect, useState } from 'react'
import './App.css'
import {
  ADMIN_BRANDING,
  ADMIN_DASHBOARD_ENDPOINT,
  ADMIN_LOGIN_ENDPOINT,
  ADMIN_LOGOUT_ENDPOINT,
  ADMIN_SESSION_ENDPOINT,
} from './config'

const emptyDashboard = {
  stats: {
    members: 0,
    regions: 0,
    clubs: 0,
  },
  recentMembers: [],
  latestNews: null,
  latestVideo: null,
  activity: [],
}

function formatDateTime(value) {
  if (!value) {
    return 'Just now'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-PH').format(Number(value) || 0)
}

function reportTypeClass(actionType) {
  const normalized = String(actionType || '').toLowerCase()

  if (normalized.includes('create')) {
    return 't-create'
  }

  if (normalized.includes('update') || normalized.includes('edit')) {
    return 't-update'
  }

  if (normalized.includes('delete') || normalized.includes('remove')) {
    return 't-delete'
  }

  if (normalized.includes('login') || normalized.includes('signin')) {
    return 't-login'
  }

  return ''
}

async function readJson(response) {
  const raw = await response.text()
  const contentType = response.headers.get('content-type') || ''
  const responsePath = (() => {
    try {
      return new URL(response.url).pathname
    } catch {
      return response.url || 'unknown endpoint'
    }
  })()

  if (!contentType.toLowerCase().includes('application/json')) {
    throw new Error(
      `Expected JSON from ${responsePath}, but got HTML/text instead. Check the API route.`,
    )
  }

  let payload

  try {
    payload = JSON.parse(raw)
  } catch {
    throw new Error(`Invalid JSON response from ${responsePath}.`)
  }

  if (!response.ok || !payload.ok) {
    throw new Error(payload.message || 'Request failed.')
  }

  return payload
}

function App() {
  const [authChecking, setAuthChecking] = useState(true)
  const [user, setUser] = useState(null)
  const [dashboard, setDashboard] = useState(emptyDashboard)
  const [form, setForm] = useState({ username: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [info, setInfo] = useState('')
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [membersOpen, setMembersOpen] = useState(false)
  const [contentOpen, setContentOpen] = useState(false)
  const [officersOpen, setOfficersOpen] = useState(false)

  useEffect(() => {
    async function hydrate() {
      try {
        setAuthChecking(true)

        const sessionResponse = await fetch(ADMIN_SESSION_ENDPOINT, {
          credentials: 'include',
        })
        const sessionPayload = await readJson(sessionResponse)

        if (!sessionPayload.authenticated) {
          setUser(null)
          setDashboard(emptyDashboard)
          return
        }

        const dashboardResponse = await fetch(ADMIN_DASHBOARD_ENDPOINT, {
          credentials: 'include',
        })
        const dashboardPayload = await readJson(dashboardResponse)

        setUser(dashboardPayload.user)
        setDashboard({
          ...emptyDashboard,
          ...(dashboardPayload.data || {}),
          stats: {
            ...emptyDashboard.stats,
            ...(dashboardPayload.data?.stats || {}),
          },
        })
      } catch (loadError) {
        setError(loadError.message || 'Unable to load admin session.')
      } finally {
        setAuthChecking(false)
      }
    }

    hydrate()
  }, [])

  useEffect(() => {
    function handleResize() {
      if (window.innerWidth > 768) {
        setSidebarOpen(false)
      }
    }

    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  async function handleLogin(event) {
    event.preventDefault()

    try {
      setBusy(true)
      setError('')
      setInfo('')

      const loginResponse = await fetch(ADMIN_LOGIN_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(form),
      })
      const loginPayload = await readJson(loginResponse)

      const dashboardResponse = await fetch(ADMIN_DASHBOARD_ENDPOINT, {
        credentials: 'include',
      })
      const dashboardPayload = await readJson(dashboardResponse)

      setUser(loginPayload.user)
      setDashboard({
        ...emptyDashboard,
        ...(dashboardPayload.data || {}),
        stats: {
          ...emptyDashboard.stats,
          ...(dashboardPayload.data?.stats || {}),
        },
      })
      setInfo('Admin session is now active.')
      setForm({ username: '', password: '' })
    } catch (loginError) {
      setError(loginError.message || 'Unable to login.')
    } finally {
      setBusy(false)
    }
  }

  async function handleLogout() {
    try {
      setBusy(true)
      setError('')
      setInfo('')

      const response = await fetch(ADMIN_LOGOUT_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
      })
      await readJson(response)

      setUser(null)
      setDashboard(emptyDashboard)
      setSidebarOpen(false)
      setMembersOpen(false)
      setContentOpen(false)
      setOfficersOpen(false)
      setInfo('Admin session closed.')
    } catch (logoutError) {
      setError(logoutError.message || 'Unable to logout.')
    } finally {
      setBusy(false)
    }
  }

  function closeSidebar() {
    setSidebarOpen(false)
  }

  const isSuperAdmin = user?.roleId === 1
  const greeting = isSuperAdmin ? 'Hi Super Admin' : 'Hi Admin'

  if (!user) {
    return (
      <div
        className="admin-shell login-mode"
        style={{ '--admin-login-bg': `url(${ADMIN_BRANDING.backgroundUrl})` }}
      >
        {error || info ? (
          <div className={`admin-flash ${error ? 'error' : 'success'}`}>
            {error || info}
          </div>
        ) : null}

        <div className="login-stage">
          <div className="login-container">
            <img src={ADMIN_BRANDING.logoUrl} alt="Eagles Logo" />
            <h1>Admin Login</h1>

            <form onSubmit={handleLogin}>
              <input
                type="text"
                name="username"
                placeholder="Username"
                autoComplete="username"
                value={form.username}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    username: event.target.value,
                  }))
                }
                required
              />
              <input
                type="password"
                name="password"
                placeholder="Password"
                autoComplete="current-password"
                value={form.password}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    password: event.target.value,
                  }))
                }
                required
              />
              <button type="submit" disabled={busy || authChecking}>
                {busy ? 'Signing in...' : 'Login'}
              </button>
            </form>

            <p className="login-helper">
              {authChecking
                ? 'Checking existing admin session...'
                : 'Use an account with role_id 1 or 2 from the users table.'}
            </p>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="admin-shell dashboard-mode">
      <button
        className="sidebar-toggle"
        id="sidebarToggle"
        type="button"
        aria-label="Open sidebar"
        onClick={() => setSidebarOpen(true)}
      >
        <i className="fas fa-bars"></i>
      </button>

      <div
        className={`sidebar-overlay ${sidebarOpen ? 'show' : ''}`}
        id="sidebarOverlay"
        aria-hidden={sidebarOpen ? 'false' : 'true'}
        onClick={closeSidebar}
      ></div>

      <aside className={`sidebar ${sidebarOpen ? 'show' : ''}`} id="adminSidebar" aria-label="Admin sidebar">
        <div className="logo">
          <img src={ADMIN_BRANDING.logoUrl} alt="Eagles Logo" />
          <h2>Admin</h2>
          <p className="admin-greeting">
            {greeting}
            {user.name || user.username ? `, ${user.name || user.username}` : ''}
          </p>
        </div>

        <div className="sidebar-menu" id="sidebarMenu">
          <a href="#dashboard" className="active" onClick={closeSidebar}>
            <i className="fas fa-home"></i>
            <span>Dashboard</span>
          </a>

          {isSuperAdmin ? (
            <a href="#reports-section" onClick={closeSidebar}>
              <i className="fas fa-file-alt"></i>
              <span>Logs &amp; Reports</span>
            </a>
          ) : null}

          <div className={`sidebar-dropdown ${membersOpen ? 'open' : ''}`} id="membersDropdown">
            <button
              className={`dropdown-toggle ${membersOpen ? 'active' : ''}`}
              id="membersToggle"
              type="button"
              aria-expanded={membersOpen ? 'true' : 'false'}
              onClick={() => setMembersOpen((current) => !current)}
            >
              <i className="fas fa-users"></i>
              <span>Members Management</span>
              <i className="fas fa-chevron-down arrow" aria-hidden="true"></i>
            </button>

            <div className="dropdown-menu">
              <a href="#members-section" onClick={closeSidebar}>
                <i className="fas fa-id-card"></i>
                <span>Official Members</span>
              </a>
              {isSuperAdmin ? (
                <a href="#members-section" onClick={closeSidebar}>
                  <i className="fas fa-user-cog"></i>
                  <span>Users</span>
                </a>
              ) : null}
            </div>
          </div>

          <div className={`sidebar-dropdown ${contentOpen ? 'open' : ''}`} id="contentDropdown">
            <button
              className={`dropdown-toggle ${contentOpen ? 'active' : ''}`}
              id="contentToggle"
              type="button"
              aria-expanded={contentOpen ? 'true' : 'false'}
              onClick={() => setContentOpen((current) => !current)}
            >
              <i className="fas fa-folder"></i>
              <span>Content Management</span>
              <i className="fas fa-chevron-down arrow" aria-hidden="true"></i>
            </button>

            <div className="dropdown-menu">
              <a href="#content-section" onClick={closeSidebar}>
                <i className="fas fa-file-alt"></i>
                <span>Memorandum</span>
              </a>
              <a href="#content-section" onClick={closeSidebar}>
                <i className="fas fa-newspaper"></i>
                <span>News</span>
              </a>
              <a href="#content-section" onClick={closeSidebar}>
                <i className="fas fa-video"></i>
                <span>Videos</span>
              </a>
              <a href="#content-section" onClick={closeSidebar}>
                <i className="fas fa-calendar"></i>
                <span>Events</span>
              </a>
              <a href="#content-section" onClick={closeSidebar}>
                <i className="fas fa-book"></i>
                <span>Magna Carta</span>
              </a>
            </div>
          </div>

          <div className={`sidebar-dropdown ${officersOpen ? 'open' : ''}`} id="officersDropdown">
            <button
              className={`dropdown-toggle ${officersOpen ? 'active' : ''}`}
              id="officersToggle"
              type="button"
              aria-expanded={officersOpen ? 'true' : 'false'}
              onClick={() => setOfficersOpen((current) => !current)}
            >
              <i className="fas fa-cogs"></i>
              <span>Officers Management</span>
              <i className="fas fa-chevron-down arrow" aria-hidden="true"></i>
            </button>

            <div className="dropdown-menu">
              <a href="#dashboard" onClick={closeSidebar}>
                <i className="fas fa-user-tie"></i>
                <span>Officers</span>
              </a>
              <a href="#dashboard" onClick={closeSidebar}>
                <i className="fas fa-user-check"></i>
                <span>Appointed</span>
              </a>
              <a href="#dashboard" onClick={closeSidebar}>
                <i className="fas fa-user-shield"></i>
                <span>Governors</span>
              </a>
            </div>
          </div>

          {isSuperAdmin ? (
            <button
              type="button"
              className="sidebar-danger-btn"
              onClick={() => setInfo('Reset database workflow is not migrated yet in the React admin.')}
            >
              <i className="fas fa-triangle-exclamation"></i>
              <span>Reset Database</span>
            </button>
          ) : null}

          <div className="sidebar-logout">
            <button type="button" onClick={handleLogout}>
              <i className="fas fa-sign-out-alt"></i>
              <span>{busy ? 'Processing...' : 'Logout'}</span>
            </button>
          </div>
        </div>
      </aside>

      <main className="main-content" id="dashboard">
        <div className="header">
          <h1>Dashboard</h1>
          <a className="top-pill" href="#reports-section">
            <i className="fas fa-file-alt"></i> View Logs
          </a>
        </div>

        {error || info ? (
          <div className={`admin-banner ${error ? 'error' : 'info'}`}>
            {error || info}
          </div>
        ) : null}

        <div className="cards">
          <div className="card">
            <i className="fas fa-users"></i>
            <h3>Total Members</h3>
            <p>{formatNumber(dashboard.stats.members)}</p>
          </div>
          <div className="card">
            <i className="fas fa-map"></i>
            <h3>Total Regions</h3>
            <p>{formatNumber(dashboard.stats.regions)}</p>
          </div>
          <div className="card">
            <i className="fas fa-flag"></i>
            <h3>Total Clubs</h3>
            <p>{formatNumber(dashboard.stats.clubs)}</p>
          </div>
        </div>

        <div className="section" id="members-section">
          <h2>Recently Added Members</h2>
          <table className="recent-members">
            <thead>
              <tr>
                <th>Name</th>
                <th>Region</th>
                <th>Club</th>
                <th>Position</th>
              </tr>
            </thead>
            <tbody>
              {dashboard.recentMembers.length > 0 ? (
                dashboard.recentMembers.map((member, index) => (
                  <tr key={`${member.name || 'member'}-${index}`}>
                    <td>{member.name || 'Unnamed member'}</td>
                    <td>{member.region || 'No region'}</td>
                    <td>{member.club || 'No club'}</td>
                    <td>{member.position || 'No position'}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="4">No members found</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="section news-videos" id="content-section">
          <div className="news-card">
            <h3>Latest News</h3>
            {dashboard.latestNews ? (
              <>
                <h4>{dashboard.latestNews.title}</h4>
                <p>{dashboard.latestNews.excerpt || 'No news excerpt available.'}</p>
                <p className="panel-timestamp">{formatDateTime(dashboard.latestNews.createdAt)}</p>
                <a className="edit-btn" href="#content-section">
                  <i className="fas fa-broadcast-tower"></i> Live Data
                </a>
              </>
            ) : (
              <p>No published news found.</p>
            )}
          </div>

          <div className="video-card">
            <h3>Latest Video</h3>
            {dashboard.latestVideo ? (
              <>
                <h4>{dashboard.latestVideo.title}</h4>
                <p>{dashboard.latestVideo.excerpt || 'No video description available.'}</p>
                <p className="panel-timestamp">{formatDateTime(dashboard.latestVideo.createdAt)}</p>
                <a className="edit-btn" href="#content-section">
                  <i className="fas fa-play"></i> Live Data
                </a>
              </>
            ) : (
              <p>No published video found.</p>
            )}
          </div>
        </div>

        <div className="section" id="reports-section">
          <div className="reports-card">
            <h3>Latest Reports</h3>
            <ul className="reports-list">
              {dashboard.activity.length > 0 ? (
                dashboard.activity.map((item, index) => (
                  <li className="reports-item" key={`${item.actionType || 'report'}-${index}`}>
                    <div className="reports-top">
                      <span className="reports-id">Record #{index + 1}</span>
                      <span className={`reports-type ${reportTypeClass(item.actionType)}`}>
                        {item.actionType || 'Action'}
                      </span>
                    </div>
                    <div className="reports-msg">
                      {item.description || 'No description provided.'}
                    </div>
                    <div className="reports-meta">
                      <span>{item.adminUsername || 'Unknown admin'}</span>
                      <span>{formatDateTime(item.createdAt)}</span>
                    </div>
                  </li>
                ))
              ) : (
                <li className="reports-empty">No activity logs available.</li>
              )}
            </ul>
          </div>
        </div>
      </main>
    </div>
  )
}

export default App
