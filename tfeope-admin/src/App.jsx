import {
  startTransition,
  useEffect,
  useEffectEvent,
  useState,
} from 'react'
import './App.css'
import './admin-app/admin.css'
import {
  ADMIN_BRANDING,
  ADMIN_DASHBOARD_ENDPOINT,
  ADMIN_EVENTS_ENDPOINT,
  ADMIN_EVENTS_CREATE_ENDPOINT,
  ADMIN_EVENTS_UPDATE_ENDPOINT,
  ADMIN_EVENTS_DELETE_ENDPOINT,
  ADMIN_GOVERNORS_ENDPOINT,
  ADMIN_LOGIN_ENDPOINT,
  ADMIN_LOGOUT_ENDPOINT,
  ADMIN_MEMBERS_ENDPOINT,
  ADMIN_MEMBERS_CREATE_ENDPOINT,
  ADMIN_MEMBERS_IMPORT_ENDPOINT,
  ADMIN_MEMBERS_UPDATE_ENDPOINT,
  ADMIN_MEMORANDUM_ENDPOINT,
  ADMIN_MEMORANDUM_CREATE_ENDPOINT,
  ADMIN_MEMORANDUM_DELETE_ENDPOINT,
  ADMIN_MEMORANDUM_UPDATE_ENDPOINT,
  ADMIN_NEWS_ENDPOINT,
  ADMIN_NEWS_CREATE_ENDPOINT,
  ADMIN_NEWS_UPDATE_ENDPOINT,
  ADMIN_OFFICERS_ENDPOINT,
  ADMIN_OFFICERS_UPDATE_ENDPOINT,
  ADMIN_SESSION_ENDPOINT,
  ADMIN_USERS_ENDPOINT,
  ADMIN_USERS_CREATE_ENDPOINT,
  ADMIN_USERS_UPDATE_ENDPOINT,
  ADMIN_USERS_DELETE_ENDPOINT,
  ADMIN_VIDEOS_ENDPOINT,
  ADMIN_VIDEOS_CREATE_ENDPOINT,
  ADMIN_VIDEOS_UPDATE_ENDPOINT,
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
  requestJson,
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

function sortLabels(items) {
  return [...items].sort((first, second) => first.localeCompare(second))
}

function buildRegionClubMap(members = [], governors = []) {
  const nextMap = {}

  function ensureRegion(value) {
    const region = String(value || '').trim()
    if (region === '') {
      return ''
    }

    if (!nextMap[region]) {
      nextMap[region] = new Set()
    }

    return region
  }

  function registerClub(regionValue, clubValue) {
    const region = ensureRegion(regionValue)
    const club = String(clubValue || '').trim()

    if (region === '' || club === '') {
      return
    }

    nextMap[region].add(club)
  }

  governors.forEach((governor) => {
    const governorRegions = Array.isArray(governor?.regions) ? governor.regions : []

    governorRegions.forEach((regionItem) => {
      const regionName = ensureRegion(regionItem?.name)
      const regionClubs = Array.isArray(regionItem?.clubs) ? regionItem.clubs : []

      regionClubs.forEach((clubItem) => {
        registerClub(regionName, clubItem?.name)
      })
    })
  })

  members.forEach((member) => {
    const region = String(member?.region || member?.eagles_region || '').trim()
    const club = String(member?.club || member?.eagles_club || '').trim()

    ensureRegion(region)
    registerClub(region, club)
  })

  return Object.fromEntries(
    sortLabels(Object.keys(nextMap)).map((region) => [
      region,
      sortLabels(Array.from(nextMap[region] || [])),
    ]),
  )
}

function resolveNewsImageAsset(item) {
  const mediaItems = Array.isArray(item?.media) ? item.media : []
  const fallbackImage = mediaItems.find((mediaItem) => String(mediaItem?.fileType || '').toLowerCase().includes('image'))

  return {
    imageUrl: String(item?.imageUrl || fallbackImage?.url || ''),
    imageFilename: String(item?.imageFilename || fallbackImage?.filename || ''),
  }
}

function resolveAdminRoleId(admin) {
  return Number(admin?.roleId ?? admin?.role_id ?? 0) || 0
}

function normalizeMemberStatus(value) {
  return String(value || '').trim().toUpperCase() === 'RENEWAL' ? 'RENEWAL' : 'ACTIVE'
}

function normalizeAppointedForAdmin(items = []) {
  if (!Array.isArray(items)) {
    return []
  }

  const rows = []

  items.forEach((regionItem) => {
    const regionName = String(regionItem?.name || regionItem?.region || '').trim()
    const committees = Array.isArray(regionItem?.committees) ? regionItem.committees : []

    committees.forEach((committeeItem) => {
      const committeeName = String(committeeItem?.name || committeeItem?.committee || '').trim()
      const officers = Array.isArray(committeeItem?.officers) ? committeeItem.officers : []

      officers.forEach((officerItem, officerIndex) => {
        rows.push({
          id: String(officerItem?.id || `${regionName}-${committeeName}-${officerIndex + 1}`).trim(),
          name: String(officerItem?.name || '').trim(),
          position: String(officerItem?.position || '').trim(),
          club: String(officerItem?.club || committeeName || '').trim(),
          committee: String(officerItem?.committee || committeeName || '').trim(),
          region: String(officerItem?.region || regionName || '').trim(),
          createdAt: String(officerItem?.createdAt || officerItem?.created_at || '').trim(),
          updatedAt: String(officerItem?.updatedAt || officerItem?.updated_at || '').trim(),
        })
      })
    })
  })

  return rows
}

function toLocalIsoDate(value) {
  const date = value instanceof Date ? value : new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function toThumbnailFilename(videoName) {
  const base = String(videoName || 'video')
    .replace(/\.[^/.]+$/, '')
    .trim()
    .replace(/[^a-zA-Z0-9_-]+/g, '_')
  return `${base || 'video'}_thumb.jpg`
}

function generateVideoThumbnail(videoFile) {
  return new Promise((resolve, reject) => {
    if (!(videoFile instanceof File)) {
      reject(new Error('Invalid video file.'))
      return
    }

    const objectUrl = URL.createObjectURL(videoFile)
    const video = document.createElement('video')
    video.preload = 'metadata'
    video.muted = true
    video.playsInline = true
    video.src = objectUrl

    let cleaned = false
    const cleanup = () => {
      if (cleaned) return
      cleaned = true
      URL.revokeObjectURL(objectUrl)
      video.removeAttribute('src')
      video.load()
    }

    video.onerror = () => {
      cleanup()
      reject(new Error('Unable to read video file.'))
    }

    video.onloadedmetadata = () => {
      const duration = Number(video.duration || 0)
      const targetSecond = duration > 0
        ? Math.min(Math.max(duration * 0.2, 0.1), Math.max(duration - 0.1, 0.1))
        : 0.1

      try {
        video.currentTime = targetSecond
      } catch (error) {
        cleanup()
        reject(error)
      }
    }

    video.onseeked = () => {
      try {
        const sourceWidth = video.videoWidth || 1280
        const sourceHeight = video.videoHeight || 720
        const maxWidth = 1280
        const scale = sourceWidth > maxWidth ? maxWidth / sourceWidth : 1
        const width = Math.max(1, Math.round(sourceWidth * scale))
        const height = Math.max(1, Math.round(sourceHeight * scale))

        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const context = canvas.getContext('2d')

        if (!context) {
          throw new Error('Unable to create thumbnail context.')
        }

        context.drawImage(video, 0, 0, width, height)
        canvas.toBlob(
          (blob) => {
            cleanup()

            if (!blob) {
              reject(new Error('Unable to generate thumbnail image.'))
              return
            }

            resolve(new File([blob], toThumbnailFilename(videoFile.name), { type: 'image/jpeg' }))
          },
          'image/jpeg',
          0.88,
        )
      } catch (error) {
        cleanup()
        reject(error)
      }
    }
  })
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
  const [isMobileView, setIsMobileView] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }

    return window.innerWidth <= 1040
  })
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }

    return window.localStorage.getItem('admin-sidebar-collapsed') === 'true'
  })
  const [activePage, setActivePage] = useState(initialPage)
  const [openGroups, setOpenGroups] = useState(initialSidebarGroups(initialPage))
  const [actionModal, setActionModal] = useState(null)
  const [actionBusy, setActionBusy] = useState(false)
  const [newsComposer, setNewsComposer] = useState({
    id: '',
    title: '',
    content: '',
    status: 'Published',
    publishedDate: '',
    image: null,
    imageUrl: '',
    imageFilename: '',
  })
  const [videoComposer, setVideoComposer] = useState({
    id: '',
    title: '',
    description: '',
    status: 'Published',
    video: null,
    videoUrl: '',
    videoFilename: '',
    thumbnail: null,
    thumbnailUrl: '',
    thumbnailFilename: '',
    createdAt: '',
  })
  const [eventComposer, setEventComposer] = useState({
    id: '',
    title: '',
    description: '',
    date: '',
    type: 'upcoming',
    media: null,
    mediaUrl: '',
    mediaFilename: '',
    createdAt: '',
  })
  const [officerComposer, setOfficerComposer] = useState({
    id: '',
    name: '',
    position: '',
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
    region: '',
    status: normalizeMemberStatus('ACTIVE'),
    photo: null,
    photoUrl: '',
    dateAdded: '',
  })
  const [memorandumComposer, setMemorandumComposer] = useState({
    id: '',
    title: '',
    description: '',
    status: 'Draft',
    pages: [],
    currentPages: [],
  })
  const [userComposer, setUserComposer] = useState({
    name: '',
    username: '',
    password: '',
    confirmPassword: '',
    roleId: '2',
    eaglesId: '',
  })
  const [memberImportForm, setMemberImportForm] = useState({
    file: null,
  })
  const isSidebarVisible = isMobileView ? sidebarOpen : !sidebarCollapsed

  const isSuperAdmin = resolveAdminRoleId(user) === 1
  const regionClubMap = buildRegionClubMap(collections.members, collections.governors)
  const regions = Object.keys(regionClubMap)

  function resetNewsComposer() {
    setNewsComposer({
      id: '',
      title: '',
      content: '',
      status: 'Published',
      publishedDate: '',
      image: null,
      imageUrl: '',
      imageFilename: '',
    })
  }

  function resetVideoComposer() {
    setVideoComposer({
      id: '',
      title: '',
      description: '',
      status: 'Published',
      video: null,
      videoUrl: '',
      videoFilename: '',
      thumbnail: null,
      thumbnailUrl: '',
      thumbnailFilename: '',
      createdAt: '',
    })
  }

  function resetEventComposer() {
    setEventComposer({
      id: '',
      title: '',
      description: '',
      date: '',
      type: 'upcoming',
      media: null,
      mediaUrl: '',
      mediaFilename: '',
      createdAt: '',
    })
  }

  function resetOfficerComposer() {
    setOfficerComposer({
      id: '',
      name: '',
      position: '',
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
      region: '',
      status: normalizeMemberStatus('ACTIVE'),
      photo: null,
      photoUrl: '',
      dateAdded: '',
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

  function resetUserComposer() {
    setUserComposer({
      name: '',
      username: '',
      password: '',
      confirmPassword: '',
      roleId: '2',
      eaglesId: '',
    })
  }

  function resetMemberImportForm() {
    setMemberImportForm({
      file: null,
    })
  }

  function formatWelcomeNotice(nextUser) {
    const roleLabel = String(nextUser?.roleLabel || '').trim() || 'Admin'
    const displayName = String(nextUser?.name || nextUser?.username || 'Admin').trim() || 'Admin'
    return `Welcome, ${roleLabel} ${displayName}.`
  }

  async function loadCollections(currentUser = null) {
    const canAccessSuperAdminSections = Number(currentUser?.roleId || 0) === 1
    const activeLoaders = collectionLoaders.filter(
      (loader) => !loader.superAdminOnly || canAccessSuperAdminSections,
    )

    const results = await Promise.allSettled(activeLoaders.map(async (loader) => {
      const payload = await requestJson(loader.endpoint, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })

      let data = normalizeCollection(payload)
      if (loader.key === 'appointed') {
        data = normalizeAppointedForAdmin(data)
      }

      return {
        key: loader.key,
        label: loader.label,
        data,
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

      const dashboardPayload = await requestJson(ADMIN_DASHBOARD_ENDPOINT, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
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

  const refreshAdminEvent = useEffectEvent(async (options = {}) => {
    await runAdminRefresh(options)
  })

  useEffect(() => {
    let active = true

    async function hydrate() {
      try {
        setAuthChecking(true)
        setError('')

        const payload = await requestJson(ADMIN_SESSION_ENDPOINT, {
          credentials: 'include',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })

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

        await refreshAdminEvent({ silent: true })
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
    if (typeof window === 'undefined') {
      return
    }

    window.localStorage.setItem('admin-sidebar-collapsed', String(sidebarCollapsed))
  }, [sidebarCollapsed])

  useEffect(() => {
    function handleResize() {
      const mobileViewport = window.innerWidth <= 1040
      setIsMobileView(mobileViewport)

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
      refreshAdminEvent({ silent: true })
    }, 60000)

    return () => window.clearInterval(intervalId)
  }, [user])

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

      const payload = await requestJson(ADMIN_LOGIN_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(form),
      })

      startTransition(() => {
        setUser(payload.user || null)
      })

      await runAdminRefresh({ silent: true })
      setForm({ username: '', password: '' })
      setNotice(formatWelcomeNotice(payload.user || null))
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

      await requestJson(ADMIN_LOGOUT_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })

      startTransition(() => {
        setUser(null)
        setDashboard(emptyDashboard)
        setCollections(emptyCollections)
        setModuleErrors({})
      })
      setSidebarOpen(false)
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

  function dismissBanner() {
    setError('')
    setNotice('')
  }

  function handlePageChange(page) {
    const normalized = normalizePage(page, isSuperAdmin)
    setActivePage(normalized)
    setOpenGroups((current) => ({ ...current, ...initialSidebarGroups(normalized) }))
    setSidebarOpen(false)
  }

  function toggleGroup(groupId) {
    if (sidebarCollapsed && typeof window !== 'undefined' && window.innerWidth > 1040) {
      setSidebarCollapsed(false)
      setOpenGroups((current) => ({ ...current, [groupId]: true }))
      return
    }

    setOpenGroups((current) => ({ ...current, [groupId]: !current[groupId] }))
  }

  function toggleSidebarCollapsed() {
    if (typeof window !== 'undefined' && window.innerWidth <= 1040) {
      setSidebarOpen((current) => !current)
      return
    }

    setSidebarCollapsed((current) => !current)
  }

  function openActionModal(mode) {
    if (['member', 'editMember', 'memberImport'].includes(mode) && !isSuperAdmin) {
      setError('Only super admins can manage members.')
      return
    }

    if (['user', 'editUser'].includes(mode) && !isSuperAdmin) {
      setError('Only super admins can manage admin accounts.')
      return
    }

    if (mode === 'news') {
      resetNewsComposer()
    }

    if (mode === 'member') {
      resetMemberComposer()
    }

    if (mode === 'video') {
      resetVideoComposer()
    }

    if (mode === 'event') {
      resetEventComposer()
    }

    if (mode === 'memberImport') {
      resetMemberImportForm()
    }

    if (mode === 'editOfficer') {
      resetOfficerComposer()
    }

    if (mode === 'memorandum') {
      resetMemorandumComposer()
    }

    if (mode === 'user') {
      resetUserComposer()
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
      publishedDate: String(item?.createdAt || item?.created_at || '').trim().slice(0, 10),
      image: null,
      imageUrl: imageAsset.imageUrl,
      imageFilename: imageAsset.imageFilename,
    })
    setActionModal('editNews')
  }

  function openVideoEditor(item) {
    setVideoComposer({
      id: String(item?.id || '').trim(),
      title: String(item?.title || '').trim(),
      description: String(item?.description || '').trim(),
      status: String(item?.status || 'Published').trim() || 'Published',
      video: null,
      videoUrl: String(item?.videoUrl || '').trim(),
      videoFilename: String(item?.videoFilename || '').trim(),
      thumbnail: null,
      thumbnailUrl: String(item?.thumbnailUrl || '').trim(),
      thumbnailFilename: String(item?.thumbnailFilename || '').trim(),
      createdAt: String(item?.createdAt || '').trim(),
    })
    setActionModal('editVideo')
  }

  function openEventEditor(item) {
    setEventComposer({
      id: String(item?.id || item?.eventId || '').trim(),
      title: String(item?.title || item?.name || '').trim(),
      description: String(item?.description || item?.content || '').trim(),
      date: String(item?.date || item?.event_date || '').trim().slice(0, 10),
      type: String(item?.type || item?.event_type || 'upcoming').trim().toLowerCase() === 'past'
        ? 'past'
        : 'upcoming',
      media: null,
      mediaUrl: String(item?.mediaUrl || '').trim(),
      mediaFilename: String(item?.mediaFilename || '').trim(),
      createdAt: String(item?.createdAt || item?.created_at || '').trim(),
    })
    setActionModal('editEvent')
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

  function openOfficerEditor(item) {
    setOfficerComposer({
      id: String(item?.id || '').trim(),
      name: String(item?.name || item?.fullName || '').trim(),
      position: String(item?.position || item?.designation || '').trim(),
      image: null,
      imageUrl: String(item?.imageUrl || item?.photoUrl || '').trim(),
      imageFilename: String(item?.imageFilename || '').trim(),
    })
    setActionModal('editOfficer')
  }

  function openMemberEditor(item) {
    setMemberComposer({
      id: String(item?.id || item?.eagles_id || '').trim(),
      first_name: String(item?.firstName || item?.first_name || '').trim(),
      last_name: String(item?.lastName || item?.last_name || '').trim(),
      position: String(item?.position || item?.eagles_position || '').trim(),
      club: String(item?.club || item?.eagles_club || '').trim(),
      region: String(item?.region || item?.eagles_region || '').trim(),
      status: normalizeMemberStatus(item?.status || item?.eagles_status || 'ACTIVE'),
      photo: null,
      photoUrl: String(item?.picUrl || item?.photoUrl || '').trim(),
      dateAdded: String(item?.dateAdded || item?.eagles_dateAdded || '').trim(),
    })
    setActionModal('editMember')
  }

  function openUserEditor(item) {
    setUserComposer({
      id: String(item?.id || item?.user_id || '').trim(),
      name: String(item?.name || '').trim(),
      username: String(item?.username || '').trim(),
      password: '',
      confirmPassword: '',
      roleId: String(item?.roleId ?? item?.role_id ?? 2),
      eaglesId: String(item?.eaglesId || item?.eagles_id || '').trim(),
    })
    setActionModal('editUser')
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

  function updateVideoComposer(field, value) {
    setVideoComposer((current) => ({ ...current, [field]: value }))
  }

  function updateEventComposer(field, value) {
    setEventComposer((current) => ({ ...current, [field]: value }))
  }

  function updateOfficerComposer(field, value) {
    setOfficerComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemberComposer(field, value) {
    setMemberComposer((current) => {
      if (field === 'status') {
        return { ...current, status: normalizeMemberStatus(value) }
      }

      if (field === 'region') {
        const nextRegion = String(value || '').trim()
        const availableClubs = regionClubMap[nextRegion] || []
        const currentClub = String(current.club || '').trim()

        return {
          ...current,
          region: nextRegion,
          club: availableClubs.includes(currentClub) ? currentClub : '',
        }
      }

      return { ...current, [field]: value }
    })
  }

  function updateMemorandumComposer(field, value) {
    setMemorandumComposer((current) => ({ ...current, [field]: value }))
  }

  function updateUserComposer(field, value) {
    setUserComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemberImportForm(field, value) {
    setMemberImportForm((current) => ({ ...current, [field]: value }))
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
      if (String(newsComposer.publishedDate || '').trim() !== '') {
        formData.append('published_date', String(newsComposer.publishedDate).trim())
      }
      if (newsComposer.image) {
        formData.append('image', newsComposer.image)
      }

      if (newsComposer.id) {
        formData.append('id', newsComposer.id)
      }

      const endpoint = newsComposer.id
        ? ADMIN_NEWS_UPDATE_ENDPOINT
        : ADMIN_NEWS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

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

  async function handleSaveMember(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can manage members.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const trimmedRegion = String(memberComposer.region || '').trim()
      const trimmedClub = String(memberComposer.club || '').trim()
      const allowedClubs = regionClubMap[trimmedRegion] || []

      if (trimmedRegion === '') {
        setError('Please choose a region first.')
        return
      }

      if (trimmedClub === '') {
        setError('Please choose a club for the selected region.')
        return
      }

      if (allowedClubs.length > 0 && !allowedClubs.includes(trimmedClub)) {
        setError('Please choose a club that belongs to the selected region.')
        return
      }

      const formData = new FormData()
      if (String(memberComposer.id || '').trim() !== '') {
        formData.append('id', memberComposer.id)
      }
      formData.append('first_name', memberComposer.first_name)
      formData.append('last_name', memberComposer.last_name)
      formData.append('position', memberComposer.position)
      formData.append('club', trimmedClub)
      formData.append('region', trimmedRegion)
      formData.append('status', normalizeMemberStatus(memberComposer.status))
      if (memberComposer.photo) {
        formData.append('photo', memberComposer.photo)
      }

      const endpoint = actionModal === 'editMember'
        ? ADMIN_MEMBERS_UPDATE_ENDPOINT
        : ADMIN_MEMBERS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('members')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice(actionModal === 'editMember' ? 'Member updated successfully.' : 'Member added successfully.')
      closeActionModal(true)
      resetMemberComposer()
    } catch (createError) {
      setError(createError.message || 'Unable to save the member.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSaveVideo(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const trimmedTitle = String(videoComposer.title || '').trim()
      if (trimmedTitle === '') {
        setError('Video title is required.')
        return
      }

      if (actionModal !== 'editVideo' && !videoComposer.video) {
        setError('Please choose a video file first.')
        return
      }

      const formData = new FormData()
      if (String(videoComposer.id || '').trim() !== '') {
        formData.append('id', videoComposer.id)
      }
      formData.append('title', trimmedTitle)
      formData.append('description', String(videoComposer.description || '').trim())
      formData.append('status', String(videoComposer.status || 'Published').trim() || 'Published')
      if (videoComposer.video) {
        formData.append('video', videoComposer.video)
      }
      let thumbnailFile = videoComposer.thumbnail
      if (!thumbnailFile && videoComposer.video) {
        try {
          thumbnailFile = await generateVideoThumbnail(videoComposer.video)
        } catch (thumbnailError) {
          thumbnailFile = null
        }
      }
      if (thumbnailFile) {
        formData.append('thumbnail', thumbnailFile)
      }

      const endpoint = actionModal === 'editVideo'
        ? ADMIN_VIDEOS_UPDATE_ENDPOINT
        : ADMIN_VIDEOS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('videos')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice(actionModal === 'editVideo' ? 'Video updated successfully.' : 'Video uploaded successfully.')
      closeActionModal(true)
      resetVideoComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the video.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSaveEvent(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const isEditingEvent = actionModal === 'editEvent'
      const trimmedTitle = String(eventComposer.title || '').trim()
      const trimmedDate = String(eventComposer.date || '').trim()
      const trimmedType = String(eventComposer.type || 'upcoming').trim().toLowerCase() === 'past'
        ? 'past'
        : 'upcoming'
      const today = new Date()
      const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate())
      const todayIso = toLocalIsoDate(todayDate)
      const yesterdayDate = new Date(todayDate)
      yesterdayDate.setDate(yesterdayDate.getDate() - 1)
      const yesterdayIso = toLocalIsoDate(yesterdayDate)

      if (trimmedTitle === '' || trimmedDate === '') {
        setError('Event title and date are required.')
        return
      }

      if (trimmedType === 'upcoming') {
        if (trimmedDate < todayIso || trimmedDate > '2027-12-31') {
          setError('Upcoming events must be between today and December 31, 2027.')
          return
        }
      } else if (trimmedDate < '2000-01-01' || trimmedDate > yesterdayIso) {
        setError('Past events must be from year 2000 up to yesterday.')
        return
      }

      const formData = new FormData()
      if (isEditingEvent) {
        formData.append('id', String(eventComposer.id || '').trim())
      }
      formData.append('title', trimmedTitle)
      formData.append('description', String(eventComposer.description || '').trim())
      formData.append('date', trimmedDate)
      formData.append('type', trimmedType)
      if (eventComposer.media) {
        formData.append('media', eventComposer.media)
      }

      const endpoint = isEditingEvent
        ? ADMIN_EVENTS_UPDATE_ENDPOINT
        : ADMIN_EVENTS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('events')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice(isEditingEvent ? 'Event updated successfully.' : 'Event created successfully.')
      closeActionModal(true)
      resetEventComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the event.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleDeleteEvent(item) {
    const eventId = String(item?.id || item?.eventId || '').trim()
    if (eventId === '') {
      setError('A valid event ID is required.')
      return
    }

    const label = String(item?.title || item?.name || 'this event').trim() || 'this event'
    if (typeof window !== 'undefined' && !window.confirm(`Delete "${label}"?`)) {
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      await requestJson(ADMIN_EVENTS_DELETE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: eventId }),
      })

      await runAdminRefresh({ silent: true })
      setActivePage('events')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice('Event deleted successfully.')
    } catch (deleteError) {
      setError(deleteError.message || 'Unable to delete the event.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSaveOfficer(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const officerId = String(officerComposer.id || '').trim()
      const trimmedName = String(officerComposer.name || '').trim()
      const trimmedPosition = String(officerComposer.position || '').trim()

      if (officerId === '') {
        setError('Officer ID is required.')
        return
      }

      if (trimmedName === '' || trimmedPosition === '') {
        setError('Officer name and position are required.')
        return
      }

      const formData = new FormData()
      formData.append('id', officerId)
      formData.append('name', trimmedName)
      formData.append('position', trimmedPosition)
      formData.append('full_position', trimmedPosition)
      if (officerComposer.image) {
        formData.append('image', officerComposer.image)
      }

      await requestJson(ADMIN_OFFICERS_UPDATE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('officers')
      setOpenGroups((current) => ({ ...current, leadership: true }))
      setNotice('Officer updated successfully.')
      closeActionModal(true)
      resetOfficerComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the officer.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleImportMembers(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can import members.')
      return
    }

    if (!memberImportForm.file) {
      setError('Please choose a CSV file first.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const formData = new FormData()
      formData.append('file', memberImportForm.file)

      const payload = await requestJson(ADMIN_MEMBERS_IMPORT_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('members')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice(payload?.message || 'Members imported successfully.')
      closeActionModal(true)
      resetMemberImportForm()
    } catch (importError) {
      setError(importError.message || 'Unable to import the CSV file.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleCreateUser(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can manage admin accounts.')
      return
    }

    const isEditingUser = actionModal === 'editUser'
    const userId = Number(userComposer.id || 0) || 0
    const trimmedName = String(userComposer.name || '').trim()
    const trimmedUsername = String(userComposer.username || '').trim()
    const password = String(userComposer.password || '')
    const confirmPassword = String(userComposer.confirmPassword || '')
    const roleId = Number(userComposer.roleId || 2) || 2
    const eaglesId = String(userComposer.eaglesId || '').trim()

    if (trimmedName === '' || trimmedUsername === '' || (!isEditingUser && password === '')) {
      setError('Name, username, and password are required.')
      return
    }

    if (isEditingUser && userId <= 0) {
      setError('A valid user ID is required.')
      return
    }

    const changingPassword = password !== '' || confirmPassword !== ''
    if ((!isEditingUser || changingPassword) && password !== confirmPassword) {
      setError('Password confirmation does not match.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const endpoint = isEditingUser
        ? ADMIN_USERS_UPDATE_ENDPOINT
        : ADMIN_USERS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          ...(isEditingUser ? { id: userId } : {}),
          name: trimmedName,
          username: trimmedUsername,
          password,
          role_id: roleId,
          eagles_id: eaglesId,
        }),
      })

      await runAdminRefresh({ silent: true })
      setActivePage('users')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice(isEditingUser ? 'User updated successfully.' : 'User added successfully.')
      closeActionModal(true)
      resetUserComposer()
    } catch (createError) {
      setError(createError.message || (isEditingUser ? 'Unable to update the user.' : 'Unable to add the user.'))
    } finally {
      setActionBusy(false)
    }
  }

  async function handleDeleteUser(item) {
    if (!isSuperAdmin) {
      setError('Only super admins can manage admin accounts.')
      return
    }

    const userId = Number(item?.id || item?.user_id || 0) || 0
    if (userId <= 0) {
      setError('A valid user ID is required.')
      return
    }

    if (userId === Number(user?.id || 0)) {
      setError('You cannot delete your current signed-in account.')
      return
    }

    const label = String(item?.name || item?.username || `User ${userId}`).trim()
    if (typeof window !== 'undefined' && !window.confirm(`Delete "${label}"?`)) {
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      await requestJson(ADMIN_USERS_DELETE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: userId }),
      })

      await runAdminRefresh({ silent: true })
      setActivePage('users')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice('User deleted successfully.')
    } catch (deleteError) {
      setError(deleteError.message || 'Unable to delete the user.')
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

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

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

      await requestJson(ADMIN_MEMORANDUM_DELETE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: memoId }),
      })

      await runAdminRefresh({ silent: true })
      setNotice('Memorandum deleted successfully.')
    } catch (deleteError) {
      setError(deleteError.message || 'Unable to delete the memorandum.')
    } finally {
      setActionBusy(false)
    }
  }

  const query = ''
  const activeCollectionError = moduleErrors[pageToCollectionKey[activePage]]
  const bannerMessage = error || notice
  const normalizedNotice = notice.trim().toLowerCase()
  const bannerVariant = error ? 'error' : 'success'
  const bannerTitle = error
    ? 'Action needed'
    : normalizedNotice.startsWith('welcome')
      ? 'Welcome back'
      : 'Admin update'
  const bannerIcon = error ? 'fa-circle-exclamation' : 'fa-circle-check'

  function renderFloatingBanner() {
    if (!bannerMessage) {
      return null
    }

    return (
      <div
        className={`floating-banner ${bannerVariant}`}
        role={error ? 'alert' : 'status'}
        aria-live="polite"
      >
        <span className="floating-banner__icon" aria-hidden="true">
          <i className={`fas ${bannerIcon}`}></i>
        </span>

        <div className="floating-banner__body">
          <p className="floating-banner__eyebrow">{bannerTitle}</p>
          <strong>{bannerMessage}</strong>
        </div>

        <button
          type="button"
          className="floating-banner__close"
          onClick={dismissBanner}
          aria-label="Dismiss notification"
        >
          <i className="fas fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    )
  }

  function renderActivePage() {
    switch (activePage) {
      case 'members':
        return (
          <MembersPage
            members={collections.members}
            query={query}
            isSuperAdmin={isSuperAdmin}
            onCreateMember={() => openActionModal('member')}
            onImportMembers={() => openActionModal('memberImport')}
            onEditMember={openMemberEditor}
          />
        )
      case 'users':
        return (
          <UsersPage
            user={user}
            users={collections.users}
            query={query}
            isSuperAdmin={isSuperAdmin}
            onCreateUser={() => openActionModal('user')}
            onEditUser={openUserEditor}
            onDeleteUser={handleDeleteUser}
          />
        )
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
        return (
          <VideosPage
            items={collections.videos}
            query={query}
            onCreateVideo={() => openActionModal('video')}
            onEditVideo={openVideoEditor}
          />
        )
      case 'events':
        return (
          <EventsPage
            items={collections.events}
            query={query}
            onCreateEvent={() => openActionModal('event')}
            onEditEvent={openEventEditor}
            onDeleteEvent={handleDeleteEvent}
          />
        )
      case 'magnaCarta':
        return <MagnaCartaPage items={collections.magnaCarta} query={query} />
      case 'officers':
        return (
          <OfficersPage
            items={collections.officers}
            query={query}
            onEditOfficer={openOfficerEditor}
          />
        )
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
            user={user}
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
        {renderFloatingBanner()}

        <div className="login-stage">
          <section className="login-card">
            <div className="login-card__mesh" aria-hidden="true"></div>

            <div className="login-card__grid">
              <div className="login-card__content">
                <p className="login-kicker">Secure Admin Console</p>

                <div className="login-brand">
                  <img src={ADMIN_BRANDING.logoUrl} alt="TFEOPE Eagles Logo" />
                  <div>
                    <h1>{ADMIN_BRANDING.title}</h1>
                    <p>Manage members, content, and leadership updates in one protected workspace.</p>
                  </div>
                </div>

                <p className="login-lead">
                  Step into a cleaner control center for publishing, membership updates,
                  and daily TFEOPE admin operations.
                </p>

                <div className="login-feature-strip">
                  <span>
                    <i className="fas fa-shield-halved" aria-hidden="true"></i>
                    Protected access
                  </span>
                  <span>
                    <i className="fas fa-chart-line" aria-hidden="true"></i>
                    Live dashboard
                  </span>
                  <span>
                    <i className="fas fa-layer-group" aria-hidden="true"></i>
                    Organized controls
                  </span>
                </div>
              </div>

              <div className="login-form-shell">
                <div className="login-form-header">
                  <p className="login-form-kicker">Admin Sign In</p>
                  <h2>Welcome back</h2>
                  <p>Use your assigned admin credentials to open the dashboard.</p>
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

                <p className="login-form-note">
                  Authorized TFEOPE admins only. Your dashboard, member tools, and content controls
                  will load right after sign in.
                </p>
              </div>
            </div>
          </section>
        </div>
      </div>
    )
  }

  return (
    <div className={`admin-shell dashboard-mode ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
      {renderFloatingBanner()}

      <button
        className={`sidebar-toggle ${isSidebarVisible ? 'is-open' : ''}`}
        type="button"
        aria-label={isSidebarVisible ? 'Close menu' : 'Open menu'}
        title={isSidebarVisible ? 'Close menu' : 'Open menu'}
        onClick={toggleSidebarCollapsed}
      >
        <span className="sidebar-toggle__icon" aria-hidden="true">
          <i className={`fas ${isSidebarVisible ? 'fa-xmark' : 'fa-bars'}`}></i>
        </span>
        {isMobileView ? (
          <span className="sidebar-toggle__label">{isSidebarVisible ? 'Close Menu' : 'Menu'}</span>
        ) : null}
      </button>

      <div
        className={`sidebar-backdrop ${sidebarOpen ? 'show' : ''}`}
        aria-hidden={sidebarOpen ? 'false' : 'true'}
        onClick={() => setSidebarOpen(false)}
      ></div>

      <aside className={`admin-sidebar ${sidebarOpen ? 'open' : ''}`} aria-label="Admin sidebar">
        <div className="sidebar-brand">
          <img src={ADMIN_BRANDING.logoUrl} alt="TFEOPE Eagles Logo" />
          <div className="sidebar-brand__copy">
            <h2>{ADMIN_BRANDING.title}</h2>
            <p>{user.name || user.username}</p>
          </div>
        </div>

        <div className="sidebar-user">
          <span className={`status-badge ${isSuperAdmin ? 'positive' : 'warning'}`}>{user.roleLabel}</span>
          <span className="sidebar-user__meta">{isSuperAdmin ? 'Super admin access' : 'Admin access'}</span>
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
                  aria-label={section.label}
                  title={sidebarCollapsed ? section.label : undefined}
                >
                  <i className={`fas ${section.icon}`}></i>
                  <span className="nav-link-label">{section.label}</span>
                </button>
              )
            }

            const visiblePages = section.pages.filter((page) => !page.superAdminOnly || isSuperAdmin)
            if (visiblePages.length === 0) {
              return null
            }

            const groupActive = visiblePages.some((page) => page.page === activePage)
            const groupOpen = Boolean(openGroups[section.id])

            return (
              <div className={`sidebar-group ${groupOpen ? 'open' : ''}`} key={section.id}>
                <button
                  className={`nav-link group-toggle ${groupActive ? 'active' : ''}`}
                  type="button"
                  onClick={() => toggleGroup(section.id)}
                  aria-expanded={groupOpen}
                  aria-label={section.label}
                  title={sidebarCollapsed ? section.label : undefined}
                >
                  <span className="nav-link-main">
                    <i className={`fas ${section.icon}`}></i>
                    <span className="nav-link-label">{section.label}</span>
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
                      aria-label={page.label}
                    >
                      <i className={`fas ${page.icon}`}></i>
                      <span className="nav-link-label">{page.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            )
          })}
        </nav>

        <div className="sidebar-footer">
          <button
            className="logout-button"
            type="button"
            onClick={handleLogout}
            disabled={busy}
            aria-label={busy ? 'Processing logout' : 'Logout'}
            title={sidebarCollapsed ? 'Logout' : undefined}
          >
            <i className={`fas ${busy ? 'fa-circle-notch fa-spin' : 'fa-right-from-bracket'}`} aria-hidden="true"></i>
            <span className="logout-button__label">{busy ? 'Processing...' : 'Logout'}</span>
          </button>
          <p className="sidebar-version">TFEOPE Admin</p>
        </div>
      </aside>

      <main className="admin-main">
        {activeCollectionError ? <div className="inline-banner error">{activeCollectionError}</div> : null}

        <section className="admin-page-stage">
          {renderActivePage()}
        </section>
      </main>

      <ActionModal
        mode={actionModal}
        open={Boolean(actionModal)}
        onClose={() => closeActionModal()}
        onNewsSubmit={handleSaveNews}
        onVideoSubmit={handleSaveVideo}
        onEventSubmit={handleSaveEvent}
        onOfficerSubmit={handleSaveOfficer}
        onMemberSubmit={handleSaveMember}
        onMemberImportSubmit={handleImportMembers}
        onUserSubmit={handleCreateUser}
        onMemorandumSubmit={handleSaveMemorandum}
        newsForm={newsComposer}
        videoForm={videoComposer}
        eventForm={eventComposer}
        officerForm={officerComposer}
        memberForm={memberComposer}
        memberImportForm={memberImportForm}
        userForm={userComposer}
        memorandumForm={memorandumComposer}
        onNewsFieldChange={updateNewsComposer}
        onVideoFieldChange={updateVideoComposer}
        onEventFieldChange={updateEventComposer}
        onOfficerFieldChange={updateOfficerComposer}
        onMemberFieldChange={updateMemberComposer}
        onMemberImportFieldChange={updateMemberImportForm}
        onUserFieldChange={updateUserComposer}
        onMemorandumFieldChange={updateMemorandumComposer}
        submitting={actionBusy}
        regions={regions}
        regionClubMap={regionClubMap}
        isSuperAdmin={isSuperAdmin}
      />
    </div>
  )
}

export default App
