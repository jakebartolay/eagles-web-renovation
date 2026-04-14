import {
  startTransition,
  useDeferredValue,
  useEffect,
  useEffectEvent,
  useState,
} from 'react'
import './App.css'
import {
  ADMIN_BRANDING,
  ADMIN_DASHBOARD_ENDPOINT,
  ADMIN_EVENTS_ENDPOINT,
  ADMIN_GOVERNORS_ENDPOINT,
  ADMIN_LOGIN_ENDPOINT,
  ADMIN_LOGOUT_ENDPOINT,
  ADMIN_MEMBERS_ENDPOINT,
  ADMIN_MEMBERS_CREATE_ENDPOINT,
  ADMIN_MEMORANDUM_ENDPOINT,
  ADMIN_MEMORANDUM_CREATE_ENDPOINT,
  ADMIN_MEMORANDUM_DELETE_ENDPOINT,
  ADMIN_MEMORANDUM_UPDATE_ENDPOINT,
  ADMIN_NEWS_ENDPOINT,
  ADMIN_NEWS_CREATE_ENDPOINT,
  ADMIN_NEWS_UPDATE_ENDPOINT,
  ADMIN_OFFICERS_ENDPOINT,
  ADMIN_SESSION_ENDPOINT,
  ADMIN_USERS_ENDPOINT,
  ADMIN_VIDEOS_ENDPOINT,
  APPOINTED_ENDPOINT,
  MAGNA_CARTA_ENDPOINT,
} from './config'
import {
  emptyCollections,
  emptyDashboard,
  initialSidebarGroups,
  navSections,
  normalizePage,
  pageHash,
  pageMeta,
} from './admin-app/constants'
import {
  normalizeCollection,
  normalizeDashboard,
  readJson,
} from './admin-app/utils'
import DashboardPage from './admin-app/pages/DashboardPage'
import { MembersPage, UsersPage } from './admin-app/pages/MembersPages'
import {
  EventsPage,
  MagnaCartaPage,
  MemorandumPage,
  NewsPage,
  VideosPage,
} from './admin-app/pages/ContentPages'
import {
  AppointedPage,
  GovernorsPage,
  OfficersPage,
} from './admin-app/pages/LeadershipPages'
import ActivityPage from './admin-app/pages/ActivityPage'
import ActionModal from './admin-app/components/ActionModal'

const collectionLoaders = [
  { key: 'members', label: 'Members', endpoint: ADMIN_MEMBERS_ENDPOINT },
  { key: 'users', label: 'Users', endpoint: ADMIN_USERS_ENDPOINT, superAdminOnly: true },
  { key: 'news', label: 'News', endpoint: ADMIN_NEWS_ENDPOINT },
  { key: 'videos', label: 'Videos', endpoint: ADMIN_VIDEOS_ENDPOINT },
  { key: 'events', label: 'Events', endpoint: ADMIN_EVENTS_ENDPOINT },
  { key: 'memorandums', label: 'Memorandum', endpoint: ADMIN_MEMORANDUM_ENDPOINT },
  { key: 'officers', label: 'Officers', endpoint: ADMIN_OFFICERS_ENDPOINT },
  { key: 'governors', label: 'Governors', endpoint: ADMIN_GOVERNORS_ENDPOINT },
  { key: 'appointed', label: 'Appointed Officers', endpoint: APPOINTED_ENDPOINT },
  { key: 'magnaCarta', label: 'Magna Carta', endpoint: MAGNA_CARTA_ENDPOINT },
]

const pageToCollectionKey = {
  members: 'members',
  users: 'users',
  news: 'news',
  videos: 'videos',
  events: 'events',
  memorandum: 'memorandums',
  officers: 'officers',
  governors: 'governors',
  appointed: 'appointed',
  magnaCarta: 'magnaCarta',
}

function resolveNewsImageAsset(item) {
  const mediaItems = Array.isArray(item?.media) ? item.media : []
  const fallbackImage = mediaItems.find((mediaItem) => String(mediaItem?.fileType || '').toLowerCase().includes('image'))

  return {
    imageUrl: String(item?.imageUrl || fallbackImage?.url || ''),
    imageFilename: String(item?.imageFilename || fallbackImage?.filename || ''),
  }
}

function App() {
  const initialPage = normalizePage(
    typeof window !== 'undefined' ? window.location.hash : 'dashboard',
    true,
  )

  const [authChecking, setAuthChecking] = useState(true)
  const [user, setUser] = useState(null)
  const [dashboard, setDashboard] = useState(emptyDashboard)
  const [collections, setCollections] = useState(emptyCollections)
  const [moduleErrors, setModuleErrors] = useState({})
  const [form, setForm] = useState({ username: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [filterText, setFilterText] = useState('')
  const [activePage, setActivePage] = useState(initialPage)
  const [openGroups, setOpenGroups] = useState(initialSidebarGroups(initialPage))
  const [actionModal, setActionModal] = useState(null)
  const [actionBusy, setActionBusy] = useState(false)
  const [newsComposer, setNewsComposer] = useState({
    id: '',
    title: '',
    content: '',
    status: 'Published',
    image: null,
    imageUrl: '',
    imageFilename: '',
  })
  const [memberComposer, setMemberComposer] = useState({
    id: '',
    first_name: '',
    last_name: '',
    position: '',
    club: '',
    club_new: '',
    region: '',
    region_new: '',
    photo: null,
  })
  const [memorandumComposer, setMemorandumComposer] = useState({
    id: '',
    title: '',
    description: '',
    status: 'Draft',
    pages: [],
    currentPages: [],
  })
  const deferredFilter = useDeferredValue(filterText)

  const isSuperAdmin = user?.roleId === 1
  const clubs = Array.from(new Set(
    collections.members
      .map((member) => String(member.club || '').trim())
      .filter(Boolean),
  )).sort((first, second) => first.localeCompare(second))
  const regions = Array.from(new Set(
    collections.members
      .map((member) => String(member.region || '').trim())
      .filter(Boolean),
  )).sort((first, second) => first.localeCompare(second))

  function resetNewsComposer() {
    setNewsComposer({
      id: '',
      title: '',
      content: '',
      status: 'Published',
      image: null,
      imageUrl: '',
      imageFilename: '',
    })
  }

  function resetMemberComposer() {
    setMemberComposer({
      id: '',
      first_name: '',
      last_name: '',
      position: '',
      club: '',
      club_new: '',
      region: '',
      region_new: '',
      photo: null,
    })
  }

  function resetMemorandumComposer() {
    setMemorandumComposer({
      id: '',
      title: '',
      description: '',
      status: 'Draft',
      pages: [],
      currentPages: [],
    })
  }

  async function loadCollections(currentUser = null) {
    const canAccessSuperAdminSections = Number(currentUser?.roleId || 0) === 1
    const activeLoaders = collectionLoaders.filter(
      (loader) => !loader.superAdminOnly || canAccessSuperAdminSections,
    )

    const results = await Promise.allSettled(activeLoaders.map(async (loader) => {
      const response = await fetch(loader.endpoint, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      const payload = await readJson(response)

      return {
        key: loader.key,
        label: loader.label,
        data: normalizeCollection(payload),
      }
    }))

    const nextCollections = {}
    const nextErrors = {}
    const failures = []
    let unauthorizedError = null

    collectionLoaders.forEach((loader) => {
      if (loader.superAdminOnly && !canAccessSuperAdminSections) {
        nextCollections[loader.key] = []
      }
    })

    results.forEach((result, index) => {
      const loader = activeLoaders[index]

      if (result.status === 'fulfilled') {
        nextCollections[result.value.key] = result.value.data
        return
      }

      if (result.reason?.status === 401) {
        unauthorizedError = result.reason
        return
      }

      nextErrors[loader.key] = result.reason?.message || `${loader.label} could not sync.`
      failures.push(loader.label)
    })

    if (unauthorizedError) {
      throw unauthorizedError
    }

    return { nextCollections, nextErrors, failures }
  }

  async function runAdminRefresh({ silent = false } = {}) {
    try {
      if (!silent) {
        setRefreshing(true)
        setError('')
      }

      const dashboardResponse = await fetch(ADMIN_DASHBOARD_ENDPOINT, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      const dashboardPayload = await readJson(dashboardResponse)
      const collectionPayload = await loadCollections(dashboardPayload.user || null)

      startTransition(() => {
        setUser(dashboardPayload.user || null)
        setDashboard(normalizeDashboard(dashboardPayload.data))
        setCollections((current) => ({
          ...current,
          ...collectionPayload.nextCollections,
        }))
        setModuleErrors(collectionPayload.nextErrors)
      })

      if (!silent) {
        if (collectionPayload.failures.length > 0) {
          setNotice(`Dashboard updated, but some sections could not refresh: ${collectionPayload.failures.join(', ')}.`)
        } else {
          setNotice('Dashboard updated successfully.')
        }
      }
    } catch (loadError) {
      if (loadError.status === 401) {
        startTransition(() => {
          setUser(null)
          setDashboard(emptyDashboard)
          setCollections(emptyCollections)
          setModuleErrors({})
        })
        setActionModal(null)
        setSidebarOpen(false)
        setFilterText('')
        setNotice('Your admin session ended. Please sign in again.')
        return
      }

      setError(loadError.message || 'Unable to load the dashboard right now.')
    } finally {
      if (!silent) {
        setRefreshing(false)
      }
    }
  }

  const refreshAdmin = useEffectEvent(async (options = {}) => {
    await runAdminRefresh(options)
  })

  useEffect(() => {
    let active = true

    async function hydrate() {
      try {
        setAuthChecking(true)
        setError('')

        const response = await fetch(ADMIN_SESSION_ENDPOINT, {
          credentials: 'include',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        const payload = await readJson(response)

        if (!active) {
          return
        }

        if (!payload.authenticated) {
          startTransition(() => {
            setUser(null)
            setDashboard(emptyDashboard)
            setCollections(emptyCollections)
            setModuleErrors({})
          })
          return
        }

        startTransition(() => {
          setUser(payload.user || null)
        })

        await runAdminRefresh({ silent: true })
      } catch (sessionError) {
        if (active) {
          setError(sessionError.message || 'Unable to restore the admin session.')
        }
      } finally {
        if (active) {
          setAuthChecking(false)
        }
      }
    }

    hydrate()

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    function handleResize() {
      if (window.innerWidth > 1040) {
        setSidebarOpen(false)
      }
    }

    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  useEffect(() => {
    if (!user) {
      return undefined
    }

    const intervalId = window.setInterval(() => {
      refreshAdmin({ silent: true })
    }, 60000)

    return () => window.clearInterval(intervalId)
  }, [user, refreshAdmin])

  useEffect(() => {
    if (!user) {
      return
    }

    const normalized = normalizePage(activePage, isSuperAdmin)
    if (normalized !== activePage) {
      setActivePage(normalized)
      setOpenGroups((current) => ({ ...current, ...initialSidebarGroups(normalized) }))
    }
  }, [activePage, isSuperAdmin, user])

  useEffect(() => {
    if (!user) {
      return undefined
    }

    function syncFromHash() {
      const nextPage = normalizePage(window.location.hash, isSuperAdmin)
      setActivePage(nextPage)
      setOpenGroups((current) => ({ ...current, ...initialSidebarGroups(nextPage) }))
    }

    window.addEventListener('hashchange', syncFromHash)
    return () => window.removeEventListener('hashchange', syncFromHash)
  }, [isSuperAdmin, user])

  useEffect(() => {
    if (!user || typeof window === 'undefined') {
      return
    }

    if (window.location.pathname.includes('/tfeope-api/')) {
      return
    }

    window.history.replaceState(null, '', pageHash(activePage))
  }, [activePage, user])

  useEffect(() => {
    if (!error && !notice) {
      return undefined
    }

    const timeoutId = window.setTimeout(() => {
      setError('')
      setNotice('')
    }, 3000)

    return () => window.clearTimeout(timeoutId)
  }, [error, notice])

  async function handleLogin(event) {
    event.preventDefault()

    try {
      setBusy(true)
      setError('')
      setNotice('')

      const response = await fetch(ADMIN_LOGIN_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(form),
      })
      const payload = await readJson(response)

      startTransition(() => {
        setUser(payload.user || null)
      })

      await runAdminRefresh({ silent: true })
      setForm({ username: '', password: '' })
      setNotice('Signed in successfully.')
    } catch (loginError) {
      setError(loginError.message || 'Unable to sign in.')
    } finally {
      setBusy(false)
    }
  }

  async function handleLogout() {
    try {
      setBusy(true)
      setError('')
      setNotice('')

      const response = await fetch(ADMIN_LOGOUT_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      await readJson(response)

      startTransition(() => {
        setUser(null)
        setDashboard(emptyDashboard)
        setCollections(emptyCollections)
        setModuleErrors({})
      })
      setSidebarOpen(false)
      setFilterText('')
      setActionModal(null)
      setActivePage('dashboard')
      setOpenGroups(initialSidebarGroups('dashboard'))
      setNotice('Admin session closed.')
    } catch (logoutError) {
      setError(logoutError.message || 'Unable to sign out.')
    } finally {
      setBusy(false)
    }
  }

  function handlePageChange(page) {
    const normalized = normalizePage(page, isSuperAdmin)
    setActivePage(normalized)
    setOpenGroups((current) => ({ ...current, ...initialSidebarGroups(normalized) }))
    setSidebarOpen(false)
  }

  function toggleGroup(groupId) {
    setOpenGroups((current) => ({ ...current, [groupId]: !current[groupId] }))
  }

  function openActionModal(mode) {
    if (mode === 'member' && !isSuperAdmin) {
      setError('Only super admins can add members.')
      return
    }

    if (mode === 'news') {
      resetNewsComposer()
    }

    if (mode === 'member') {
      resetMemberComposer()
    }

    if (mode === 'memorandum') {
      resetMemorandumComposer()
    }

    setActionModal(mode)
  }

  function openNewsEditor(item) {
    const imageAsset = resolveNewsImageAsset(item)

    setNewsComposer({
      id: String(item?.id || ''),
      title: String(item?.title || ''),
      content: String(item?.content || ''),
      status: String(item?.status || 'Draft'),
      image: null,
      imageUrl: imageAsset.imageUrl,
      imageFilename: imageAsset.imageFilename,
    })
    setActionModal('editNews')
  }

  function openMemorandumEditor(item) {
    setMemorandumComposer({
      id: String(item?.id || ''),
      title: String(item?.title || ''),
      description: String(item?.description || ''),
      status: String(item?.status || 'Draft'),
      pages: [],
      currentPages: Array.isArray(item?.pages) ? item.pages : [],
    })
    setActionModal('editMemorandum')
  }

  function closeActionModal(force = false) {
    if (actionBusy && !force) {
      return
    }

    setActionModal(null)
  }

  function updateNewsComposer(field, value) {
    setNewsComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemberComposer(field, value) {
    setMemberComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemorandumComposer(field, value) {
    setMemorandumComposer((current) => ({ ...current, [field]: value }))
  }

  async function handleSaveNews(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const formData = new FormData()
      formData.append('title', newsComposer.title)
      formData.append('content', newsComposer.content)
      formData.append('status', newsComposer.status)
      if (newsComposer.image) {
        formData.append('image', newsComposer.image)
      }

      if (newsComposer.id) {
        formData.append('id', newsComposer.id)
      }

      const endpoint = newsComposer.id
        ? ADMIN_NEWS_UPDATE_ENDPOINT
        : ADMIN_NEWS_CREATE_ENDPOINT

      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })
      await readJson(response)

      await runAdminRefresh({ silent: true })
      setActivePage('news')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice(newsComposer.id ? 'News updated successfully.' : 'Post created successfully.')
      closeActionModal(true)
      resetNewsComposer()
    } catch (createError) {
      setError(createError.message || 'Unable to save the news.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleCreateMember(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const formData = new FormData()
      formData.append('id', memberComposer.id)
      formData.append('first_name', memberComposer.first_name)
      formData.append('last_name', memberComposer.last_name)
      formData.append('position', memberComposer.position)
      formData.append('club', memberComposer.club)
      formData.append('club_new', memberComposer.club_new)
      formData.append('region', memberComposer.region)
      formData.append('region_new', memberComposer.region_new)
      if (memberComposer.photo) {
        formData.append('photo', memberComposer.photo)
      }

      const response = await fetch(ADMIN_MEMBERS_CREATE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })
      await readJson(response)

      await runAdminRefresh({ silent: true })
      setActivePage('members')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice('Member added successfully.')
      closeActionModal(true)
      resetMemberComposer()
    } catch (createError) {
      setError(createError.message || 'Unable to add the member.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSaveMemorandum(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const formData = new FormData()
      formData.append('title', memorandumComposer.title)
      formData.append('description', memorandumComposer.description)
      formData.append('status', memorandumComposer.status)

      if (memorandumComposer.id) {
        formData.append('id', memorandumComposer.id)
      }

      memorandumComposer.pages.forEach((file) => {
        formData.append('pages[]', file)
      })

      const endpoint = memorandumComposer.id
        ? ADMIN_MEMORANDUM_UPDATE_ENDPOINT
        : ADMIN_MEMORANDUM_CREATE_ENDPOINT

      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })
      await readJson(response)

      await runAdminRefresh({ silent: true })
      setActivePage('memorandum')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice(memorandumComposer.id ? 'Memorandum updated successfully.' : 'Memorandum created successfully.')
      closeActionModal(true)
      resetMemorandumComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the memorandum.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleDeleteMemorandum(item) {
    const memoId = String(item?.id || '').trim()
    if (memoId === '') {
      setError('A valid memorandum ID is required.')
      return
    }

    const label = String(item?.title || 'this memorandum').trim() || 'this memorandum'
    if (typeof window !== 'undefined' && !window.confirm(`Delete "${label}"?`)) {
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const response = await fetch(ADMIN_MEMORANDUM_DELETE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: memoId }),
      })
      await readJson(response)

      await runAdminRefresh({ silent: true })
      setNotice('Memorandum deleted successfully.')
    } catch (deleteError) {
      setError(deleteError.message || 'Unable to delete the memorandum.')
    } finally {
      setActionBusy(false)
    }
  }

  const query = deferredFilter.trim().toLowerCase()
  const activeCollectionError = moduleErrors[pageToCollectionKey[activePage]]

  function renderActivePage() {
    switch (activePage) {
      case 'members':
        return <MembersPage dashboard={dashboard} members={collections.members} query={query} />
      case 'users':
        return <UsersPage dashboard={dashboard} user={user} users={collections.users} query={query} />
      case 'memorandum':
        return (
          <MemorandumPage
            dashboard={dashboard}
            items={collections.memorandums}
            query={query}
            onCreateMemorandum={() => openActionModal('memorandum')}
            onEditMemorandum={openMemorandumEditor}
            onDeleteMemorandum={handleDeleteMemorandum}
          />
        )
      case 'news':
        return (
          <NewsPage
            dashboard={dashboard}
            items={collections.news}
            query={query}
            onCreateNews={() => openActionModal('news')}
            onEditNews={openNewsEditor}
          />
        )
      case 'videos':
        return <VideosPage dashboard={dashboard} items={collections.videos} query={query} />
      case 'events':
        return <EventsPage dashboard={dashboard} items={collections.events} query={query} />
      case 'magnaCarta':
        return <MagnaCartaPage items={collections.magnaCarta} query={query} />
      case 'officers':
        return <OfficersPage items={collections.officers} query={query} />
      case 'appointed':
        return <AppointedPage items={collections.appointed} query={query} />
      case 'governors':
        return <GovernorsPage items={collections.governors} query={query} />
      case 'activity':
        return <ActivityPage dashboard={dashboard} user={user} query={query} />
      case 'dashboard':
      default:
        return (
          <DashboardPage
            dashboard={dashboard}
            collections={collections}
            query={query}
            onNavigate={handlePageChange}
            onOpenQuickAction={openActionModal}
            isSuperAdmin={isSuperAdmin}
          />
        )
    }
  }

  if (!user) {
    return (
      <div
        className="admin-shell login-mode"
        style={{ '--admin-login-bg': `url(${ADMIN_BRANDING.backgroundUrl})` }}
      >
        {error || notice ? (
          <div className={`floating-banner ${error ? 'error' : 'success'}`}>
            {error || notice}
          </div>
        ) : null}

        <div className="login-stage">
          <section className="login-card">
            <div className="login-brand">
              <img src={ADMIN_BRANDING.logoUrl} alt="TFEOPE Eagles Logo" />
              <div>
                <h1>{ADMIN_BRANDING.title}</h1>
              </div>
            </div>

            {authChecking ? (
              <div className="login-loading">
                <i className="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                <span>Checking existing admin session...</span>
              </div>
            ) : (
              <form className="login-form" onSubmit={handleLogin}>
                <label>
                  <span>Username</span>
                  <input
                    type="text"
                    name="username"
                    autoComplete="username"
                    placeholder="Enter admin username"
                    value={form.username}
                    onChange={(event) =>
                      setForm((current) => ({ ...current, username: event.target.value }))
                    }
                    required
                  />
                </label>

                <label>
                  <span>Password</span>
                  <input
                    type="password"
                    name="password"
                    autoComplete="current-password"
                    placeholder="Enter password"
                    value={form.password}
                    onChange={(event) =>
                      setForm((current) => ({ ...current, password: event.target.value }))
                    }
                    required
                  />
                </label>

                <button type="submit" disabled={busy}>
                  <i className={`fas ${busy ? 'fa-circle-notch fa-spin' : 'fa-right-to-bracket'}`} aria-hidden="true"></i>
                  {busy ? 'Signing in...' : 'Sign In to Admin'}
                </button>
              </form>
            )}
          </section>
        </div>
      </div>
    )
  }

  return (
    <div className="admin-shell dashboard-mode">
      {error || notice ? (
        <div className={`floating-banner ${error ? 'error' : 'success'}`}>
          {error || notice}
        </div>
      ) : null}

      <button
        className="sidebar-toggle"
        type="button"
        aria-label="Open sidebar"
        onClick={() => setSidebarOpen(true)}
      >
        <i className="fas fa-bars" aria-hidden="true"></i>
      </button>

      <div
        className={`sidebar-backdrop ${sidebarOpen ? 'show' : ''}`}
        aria-hidden={sidebarOpen ? 'false' : 'true'}
        onClick={() => setSidebarOpen(false)}
      ></div>

      <aside className={`admin-sidebar ${sidebarOpen ? 'open' : ''}`}>
        <div className="sidebar-brand">
          <img src={ADMIN_BRANDING.logoUrl} alt="TFEOPE Eagles Logo" />
          <div>
            <h2>{ADMIN_BRANDING.title}</h2>
            <p>{user.name || user.username}</p>
          </div>
        </div>

        <div className="sidebar-user">
          <span className={`status-badge ${isSuperAdmin ? 'positive' : 'warning'}`}>{user.roleLabel}</span>
          <span>{isSuperAdmin ? 'Super admin access' : 'Admin access'}</span>
        </div>

        <nav className="sidebar-nav">
          {navSections.map((section) => {
            if (section.kind === 'page') {
              const meta = pageMeta[section.page]
              if (meta?.superAdminOnly && !isSuperAdmin) {
                return null
              }

              return (
                <button
                  key={section.page}
                  className={`nav-link ${activePage === section.page ? 'active' : ''}`}
                  type="button"
                  onClick={() => handlePageChange(section.page)}
                >
                  <i className={`fas ${section.icon}`}></i>
                  <span>{section.label}</span>
                </button>
              )
            }

            const visiblePages = section.pages.filter((page) => !page.superAdminOnly || isSuperAdmin)
            if (visiblePages.length === 0) {
              return null
            }

            const groupActive = visiblePages.some((page) => page.page === activePage)
            const groupOpen = openGroups[section.id] || groupActive

            return (
              <div className={`sidebar-group ${groupOpen ? 'open' : ''}`} key={section.id}>
                <button
                  className={`nav-link group-toggle ${groupActive ? 'active' : ''}`}
                  type="button"
                  onClick={() => toggleGroup(section.id)}
                  aria-expanded={groupOpen}
                >
                  <span className="nav-link-main">
                    <i className={`fas ${section.icon}`}></i>
                    <span>{section.label}</span>
                  </span>
                  <i className="fas fa-chevron-down nav-arrow"></i>
                </button>
                <div className="sidebar-subnav">
                  {visiblePages.map((page) => (
                    <button
                      key={page.page}
                      className={`nav-link sub-link ${activePage === page.page ? 'active' : ''}`}
                      type="button"
                      onClick={() => handlePageChange(page.page)}
                    >
                      <i className={`fas ${page.icon}`}></i>
                      <span>{page.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            )
          })}
        </nav>

        <button className="logout-button" type="button" onClick={handleLogout} disabled={busy}>
          <i className={`fas ${busy ? 'fa-circle-notch fa-spin' : 'fa-right-from-bracket'}`} aria-hidden="true"></i>
          {busy ? 'Processing...' : 'Logout'}
        </button>
        <p className="sidebar-version">TFEOPE Admin</p>
      </aside>

      <main className="admin-main">
        {activeCollectionError ? <div className="inline-banner error">{activeCollectionError}</div> : null}

        {renderActivePage()}
      </main>

      <ActionModal
        mode={actionModal}
        open={Boolean(actionModal)}
        onClose={() => closeActionModal()}
        onNewsSubmit={handleSaveNews}
        onMemberSubmit={handleCreateMember}
        onMemorandumSubmit={handleSaveMemorandum}
        newsForm={newsComposer}
        memberForm={memberComposer}
        memorandumForm={memorandumComposer}
        onNewsFieldChange={updateNewsComposer}
        onMemberFieldChange={updateMemberComposer}
        onMemorandumFieldChange={updateMemorandumComposer}
        submitting={actionBusy}
        clubs={clubs}
        regions={regions}
        isSuperAdmin={isSuperAdmin}
      />
    </div>
  )
}

export default App
