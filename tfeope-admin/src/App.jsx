import {
  startTransition,
  useEffect,
  useEffectEvent,
  useState,
} from 'react'
import Backdrop from '@mui/material/Backdrop'
import Box from '@mui/material/Box'
import CircularProgress from '@mui/material/CircularProgress'
import './App.css'
import './admin-app/admin.css'
import {
  ADMIN_APPOINTED_CREATE_ENDPOINT,
  ADMIN_APPOINTED_DELETE_ENDPOINT,
  ADMIN_APPOINTED_ENDPOINT,
  ADMIN_APPOINTED_UPDATE_ENDPOINT,
  ADMIN_BRANDING,
  ADMIN_DASHBOARD_ENDPOINT,
  ADMIN_EVENTS_ENDPOINT,
  ADMIN_EVENTS_CREATE_ENDPOINT,
  ADMIN_EVENTS_UPDATE_ENDPOINT,
  ADMIN_EVENTS_DELETE_ENDPOINT,
  ADMIN_FILE_MANAGER_ENDPOINT,
  ADMIN_CLUBS_CREATE_ENDPOINT,
  ADMIN_GOVERNORS_CREATE_ENDPOINT,
  ADMIN_GOVERNORS_DELETE_ENDPOINT,
  ADMIN_GOVERNORS_ENDPOINT,
  ADMIN_GOVERNORS_UPDATE_ENDPOINT,
  ADMIN_LOGIN_ENDPOINT,
  ADMIN_LOGOUT_ENDPOINT,
  ADMIN_MEMBERS_ENDPOINT,
  ADMIN_MEMBERS_CREATE_ENDPOINT,
  ADMIN_MEMBERS_DELETE_ENDPOINT,
  ADMIN_MEMBERS_IMPORT_ENDPOINT,
  ADMIN_MEMBERS_UPDATE_ENDPOINT,
  ADMIN_MEMORANDUM_ENDPOINT,
  ADMIN_MEMORANDUM_CREATE_ENDPOINT,
  ADMIN_MEMORANDUM_DELETE_ENDPOINT,
  ADMIN_MEMORANDUM_UPDATE_ENDPOINT,
  ADMIN_MAGNA_CARTA_ENDPOINT,
  ADMIN_MAGNA_CARTA_CREATE_ENDPOINT,
  ADMIN_MAGNA_CARTA_UPDATE_ENDPOINT,
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
  ADMIN_REGIONS_CREATE_ENDPOINT,
  ADMIN_REGIONS_UPDATE_ENDPOINT,
  ADMIN_PAST_LEADERS_ENDPOINT,
  ADMIN_PAST_LEADERS_CREATE_ENDPOINT,
  ADMIN_PAST_LEADERS_UPDATE_ENDPOINT,
  ADMIN_PAST_LEADERS_DELETE_ENDPOINT,
  ADMIN_VIDEOS_ENDPOINT,
  ADMIN_VIDEOS_CREATE_ENDPOINT,
  ADMIN_VIDEOS_UPDATE_ENDPOINT,
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
  PastLeadersPage,
} from './admin-app/pages/LeadershipPages'
import ActivityPage from './admin-app/pages/ActivityPage'
import FileManagerPage from './admin-app/pages/FileManagerPage'
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
  { key: 'appointed', label: 'Appointed Officers', endpoint: ADMIN_APPOINTED_ENDPOINT },
  { key: 'pastLeaders', label: 'Past Leaders', endpoint: ADMIN_PAST_LEADERS_ENDPOINT },
  { key: 'magnaCarta', label: 'Magna Carta', endpoint: ADMIN_MAGNA_CARTA_ENDPOINT },
]

const INACTIVITY_WARNING_MS = 20 * 60 * 1000
const INACTIVITY_EVENTS = ['pointerdown', 'keydown', 'mousemove', 'scroll', 'touchstart']

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
  pastLeaders: 'pastLeaders',
  magnaCarta: 'magnaCarta',
}

function sortLabels(items) {
  return [...items].sort((first, second) => first.localeCompare(second))
}

function normalizeLookupKey(value) {
  return String(value || '').trim().toUpperCase()
}

function decodeUriComponentSafe(value) {
  try {
    return decodeURIComponent(String(value || ''))
  } catch {
    return String(value || '')
  }
}

function parseGovernorSelection(value) {
  const selection = String(value || '').trim()
  if (selection === '' || selection === '__NEW__') {
    return {
      selection,
      governorId: 0,
      regionId: 0,
      regionName: '',
      governorName: '',
    }
  }

  const [governorIdPart, regionIdPart, encodedRegionName = '', encodedGovernorName = ''] = selection.split('::')

  return {
    selection,
    governorId: Number(governorIdPart || 0) || 0,
    regionId: Number(regionIdPart || 0) || 0,
    regionName: decodeUriComponentSafe(encodedRegionName).trim(),
    governorName: decodeUriComponentSafe(encodedGovernorName).trim(),
  }
}

function buildGovernorRegionCatalog(governors = []) {
  const byKey = {}

  governors.forEach((governor) => {
    const governorId = Number(governor?.id ?? governor?.governor_id ?? 0) || 0
    const governorName = String(governor?.name || governor?.governor_name || '').trim()
    const governorRegions = Array.isArray(governor?.regions) ? governor.regions : []

    governorRegions.forEach((regionItem) => {
      const regionName = String(regionItem?.name || regionItem?.region_name || '').trim()
      const regionKey = normalizeLookupKey(regionName)
      const regionId = Number(regionItem?.id ?? regionItem?.region_id ?? 0) || 0

      if (regionKey === '') {
        return
      }

      if (!byKey[regionKey]) {
        byKey[regionKey] = {
          id: regionId,
          name: regionName,
          governorId,
          governorName,
          clubsByKey: {},
        }
      } else {
        if (byKey[regionKey].id <= 0 && regionId > 0) {
          byKey[regionKey].id = regionId
        }

        if (byKey[regionKey].governorId <= 0 && governorId > 0) {
          byKey[regionKey].governorId = governorId
          byKey[regionKey].governorName = governorName
        }
      }

      const regionClubs = Array.isArray(regionItem?.clubs) ? regionItem.clubs : []
      regionClubs.forEach((clubItem) => {
        const clubName = String(clubItem?.name || clubItem?.club_name || '').trim()
        const clubKey = normalizeLookupKey(clubName)

        if (clubKey === '') {
          return
        }

        if (!byKey[regionKey].clubsByKey[clubKey]) {
          byKey[regionKey].clubsByKey[clubKey] = clubName
        }
      })
    })
  })

  const regionNames = sortLabels(
    Object.values(byKey)
      .map((regionEntry) => String(regionEntry?.name || '').trim())
      .filter(Boolean),
  )

  const regionClubMap = Object.fromEntries(
    regionNames.map((regionName) => {
      const regionEntry = byKey[normalizeLookupKey(regionName)]
      return [regionName, sortLabels(Object.values(regionEntry?.clubsByKey || {}))]
    }),
  )

  return {
    byKey,
    regionClubMap,
  }
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

function wait(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms)
  })
}

function loadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new Image()

    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve(image)
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Unable to read image.'))
    }

    image.src = url
  })
}

async function optimizeMemberImportPhoto(file) {
  if (!(file instanceof File) || !String(file.type || '').startsWith('image/')) {
    return file
  }

  if (String(file.type || '').toLowerCase().includes('gif')) {
    return file
  }

  try {
    const image = await loadImageFromFile(file)
    const maxSize = 900
    const scale = Math.min(1, maxSize / image.naturalWidth, maxSize / image.naturalHeight)
    const width = Math.max(1, Math.round(image.naturalWidth * scale))
    const height = Math.max(1, Math.round(image.naturalHeight * scale))
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height

    const context = canvas.getContext('2d')
    if (!context) {
      return file
    }

    context.drawImage(image, 0, 0, width, height)

    const blob = await new Promise((resolve) => {
      canvas.toBlob(resolve, 'image/webp', 0.78)
    })

    if (!blob || blob.size <= 0 || blob.size >= file.size) {
      return file
    }

    const baseName = file.name.replace(/\.[^.]+$/, '')
    const extension = String(blob.type || '').includes('webp') ? 'webp' : 'jpg'
    return new File([blob], `${baseName}.${extension}`, {
      type: blob.type || 'image/jpeg',
      lastModified: Date.now(),
    })
  } catch {
    return file
  }
}

async function optimizeMemberImportPhotos(files = []) {
  const optimized = []
  for (const file of files) {
    optimized.push(await optimizeMemberImportPhoto(file))
  }

  return optimized
}

function normalizeAppointedForAdmin(items = []) {
  if (!Array.isArray(items)) {
    return []
  }

  const hasNestedShape = items.some((item) => Array.isArray(item?.committees))
  if (!hasNestedShape) {
    return items.map((item, index) => ({
      id: String(item?.id || `appointed-${index + 1}`).trim(),
      name: String(item?.name || '').trim(),
      position: String(item?.position || '').trim(),
      committee: String(item?.committee || item?.club || '').trim(),
      region: String(item?.region || '').trim(),
      createdAt: String(item?.createdAt || item?.created_at || '').trim(),
      updatedAt: String(item?.updatedAt || item?.updated_at || '').trim(),
    }))
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

function isExternalVideoSource(value) {
  return /^https?:\/\//i.test(String(value || '').trim())
}

function youtubeVideoId(value) {
  try {
    const url = new URL(String(value || '').trim())
    if (url.hostname.includes('youtu.be')) {
      return url.pathname.replace('/', '').trim()
    }

    if (url.hostname.includes('youtube.com')) {
      const watchId = url.searchParams.get('v')
      if (watchId) return watchId
      const parts = url.pathname.split('/').filter(Boolean)
      const embedIndex = parts.indexOf('embed')
      if (embedIndex >= 0) return parts[embedIndex + 1] || ''
    }
  } catch {
    return ''
  }

  return ''
}

function youtubeThumbnailUrl(value) {
  const id = youtubeVideoId(value)
  return id ? `https://img.youtube.com/vi/${id}/hqdefault.jpg` : ''
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

function isAdminEntryPath(pathname) {
  const path = String(pathname || '/').replace(/\/+$/, '') || '/'
  return path === '/' || path.endsWith('/tfeope-admin') || path.endsWith('/index.php')
}

function resolveInitialPage() {
  if (typeof window === 'undefined') {
    return 'dashboard'
  }

  if (window.location.hash) {
    return normalizePage(window.location.hash, true)
  }

  return isAdminEntryPath(window.location.pathname) ? 'dashboard' : 'notFound'
}

function AdminNotFoundPage({
  onPrimary,
  onSecondary,
  primaryLabel = 'Dashboard',
  secondaryLabel = 'Logout',
  secondaryIcon = 'fa-right-from-bracket',
}) {
  return (
    <div className="admin-not-found" role="alert" aria-labelledby="admin-not-found-title">
      <div className="admin-not-found__panel">
        <p className="admin-not-found__eyebrow">404 Error</p>
        <h1 id="admin-not-found-title">Admin page not found</h1>
        <p>
          The admin page you opened is not available or the link is no longer valid.
        </p>
        <div className="admin-not-found__actions">
          <button type="button" className="admin-primary-button" onClick={onPrimary}>
            <i className="fas fa-house" aria-hidden="true"></i>
            {primaryLabel}
          </button>
          <button type="button" className="admin-secondary-button" onClick={onSecondary}>
            <i className={`fas ${secondaryIcon}`} aria-hidden="true"></i>
            {secondaryLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

function App() {
  const initialPage = resolveInitialPage()

  const [authChecking, setAuthChecking] = useState(true)
  const [user, setUser] = useState(null)
  const [dashboard, setDashboard] = useState(emptyDashboard)
  const [collections, setCollections] = useState(emptyCollections)
  const [moduleErrors, setModuleErrors] = useState({})
  const [form, setForm] = useState({ username: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [refreshing, setRefreshing] = useState(false)
  const [collectionsResolved, setCollectionsResolved] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [idlePromptOpen, setIdlePromptOpen] = useState(false)
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
  const [actionProgress, setActionProgress] = useState(null)
  const [memberIdCheckReady, setMemberIdCheckReady] = useState('')
  const [deleteConfirm, setDeleteConfirm] = useState({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Delete',
    onConfirm: null,
  })
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
    sourceType: 'upload',
    video: null,
    videoLink: '',
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
    full_position: '',
    image: null,
    imageUrl: '',
    imageFilename: '',
  })
  const [governorComposer, setGovernorComposer] = useState({
    id: '',
    name: '',
    image: null,
    imageUrl: '',
    imageFilename: '',
  })
  const [appointedComposer, setAppointedComposer] = useState({
    id: '',
    name: '',
    position: '',
    committee: '',
    region: '',
  })
  const [pastLeaderComposer, setPastLeaderComposer] = useState({
    id: '',
    name: '',
    position: '',
    term_start: '',
    term_end: '',
    achievements: '',
    order_priority: '0',
    is_active: '1',
    photo: null,
    photoUrl: '',
    photoFilename: '',
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
  const [regionClubComposer, setRegionClubComposer] = useState({
    setup_action: 'add_club',
    governor_selection: '',
    governor_id: '',
    governor_name: '',
    region_id: '',
    region_name: '',
    club_name: '',
  })
  const [memorandumComposer, setMemorandumComposer] = useState({
    id: '',
    title: '',
    description: '',
    status: 'Draft',
    pages: [],
    currentPages: [],
  })
  const [magnaCartaComposer, setMagnaCartaComposer] = useState({
    id: '',
    title: '',
    subtitle: '',
    description: '',
    status: 'Draft',
    image: null,
    imageUrl: '',
    imageFilename: '',
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
    photos: [],
    duplicates: [],
    photoReport: null,
    importStats: null,
    resultMessage: '',
  })
  const isSidebarVisible = isMobileView ? sidebarOpen : !sidebarCollapsed

  const isSuperAdmin = resolveAdminRoleId(user) === 1
  const governorRegionCatalog = buildGovernorRegionCatalog(collections.governors)
  const regionCatalogByKey = governorRegionCatalog.byKey
  const regionClubMap = governorRegionCatalog.regionClubMap
  const regions = Object.keys(regionClubMap)
  const memberIdCheck = (() => {
    const candidateId = String(memberComposer.id || '').trim().toUpperCase()
    if (actionModal === 'editMember') {
      return { status: 'current', message: 'Current member ID.' }
    }

    if (candidateId === '') {
      return { status: 'empty', message: 'Leave blank to auto-generate ID.' }
    }

    if (memberIdCheckReady !== candidateId) {
      return { status: 'checking', message: 'Checking Eagles ID...' }
    }

    const match = (collections.members || []).find((member) => (
      String(member?.id || member?.eagles_id || '').trim().toUpperCase() === candidateId
    ))

    if (!match) {
      return { status: 'available', message: 'This Eagles ID is available.' }
    }

    const name = String(match?.fullName || match?.name || `${match?.firstName || ''} ${match?.lastName || ''}`).trim()
    return {
      status: 'duplicate',
      message: `This Eagles ID already exists${name ? ` for ${name}` : ''}.`,
    }
  })()

  function resolveRegionEntry(value) {
    const key = normalizeLookupKey(value)
    if (key === '') {
      return null
    }

    return regionCatalogByKey[key] || null
  }

  function resolveRegionName(value) {
    const trimmedValue = String(value || '').trim()
    if (trimmedValue === '') {
      return trimmedValue
    }

    return String(resolveRegionEntry(trimmedValue)?.name || trimmedValue).trim()
  }

  function resolveClubName(regionValue, clubValue) {
    const trimmedClub = String(clubValue || '').trim()
    if (trimmedClub === '') {
      return trimmedClub
    }

    const regionEntry = resolveRegionEntry(regionValue)
    if (!regionEntry) {
      return trimmedClub
    }

    return String(regionEntry.clubsByKey[normalizeLookupKey(trimmedClub)] || trimmedClub).trim()
  }

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
      sourceType: 'upload',
      video: null,
      videoLink: '',
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
      full_position: '',
      image: null,
      imageUrl: '',
      imageFilename: '',
    })
  }

  function resetGovernorComposer() {
    setGovernorComposer({
      id: '',
      name: '',
      image: null,
      imageUrl: '',
      imageFilename: '',
    })
  }

  function resetAppointedComposer() {
    setAppointedComposer({
      id: '',
      name: '',
      position: '',
      committee: '',
      region: '',
    })
  }

  function resetPastLeaderComposer() {
    setPastLeaderComposer({
      id: '',
      name: '',
      position: '',
      term_start: '',
      term_end: '',
      achievements: '',
      order_priority: '0',
      is_active: '1',
      photo: null,
      photoUrl: '',
      photoFilename: '',
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

  function resetRegionClubComposer() {
    setRegionClubComposer({
      setup_action: 'add_club',
      governor_selection: '',
      governor_id: '',
      governor_name: '',
      region_id: '',
      region_name: '',
      club_name: '',
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

  function resetMagnaCartaComposer() {
    setMagnaCartaComposer({
      id: '',
      title: '',
      subtitle: '',
      description: '',
      status: 'Draft',
      image: null,
      imageUrl: '',
      imageFilename: '',
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
      photos: [],
      duplicates: [],
      photoReport: null,
      importStats: null,
      resultMessage: '',
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
      setCollectionsResolved(true)
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
      setCollectionsResolved(true)
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
          setCollectionsResolved(false)
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
      setIdlePromptOpen(false)
    }
  }, [user])

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined
    }

    if (!user || authChecking || idlePromptOpen) {
      return undefined
    }

    let timeoutId = window.setTimeout(() => {
      setIdlePromptOpen(true)
    }, INACTIVITY_WARNING_MS)

    const resetInactivityTimer = () => {
      window.clearTimeout(timeoutId)
      timeoutId = window.setTimeout(() => {
        setIdlePromptOpen(true)
      }, INACTIVITY_WARNING_MS)
    }

    INACTIVITY_EVENTS.forEach((eventName) => {
      window.addEventListener(eventName, resetInactivityTimer)
    })

    return () => {
      window.clearTimeout(timeoutId)
      INACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, resetInactivityTimer)
      })
    }
  }, [user, authChecking, idlePromptOpen])

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

  useEffect(() => {
    if (actionModal !== 'member') {
    setMemberIdCheckReady('')
      return undefined
    }

    const nextId = String(memberComposer.id || '').trim().toUpperCase()

    if (nextId === '') {
      setMemberIdCheckReady('')
      return undefined
    }

    setMemberIdCheckReady('')
    const timeoutId = window.setTimeout(() => {
      setMemberIdCheckReady(nextId)
    }, 650)

    return () => window.clearTimeout(timeoutId)
  }, [actionModal, memberComposer.id])

  useEffect(() => {
    if (!actionProgress?.active) {
      return undefined
    }

    const timer = window.setInterval(() => {
      setActionProgress((current) => {
        if (!current?.active) return current
        const nextValue = Math.min(88, current.value + (current.value < 50 ? 4 : 2))
        return { ...current, value: nextValue }
      })
    }, 520)

    return () => window.clearInterval(timer)
  }, [actionProgress?.active])

  function startActionProgress(label) {
    setActionProgress({
      active: true,
      value: 0,
      label,
    })

    return Date.now()
  }

  async function finishActionProgress(startedAt, label) {
    const elapsed = Date.now() - startedAt
    if (elapsed < 2400) {
      await wait(2400 - elapsed)
    }

    setActionProgress({
      active: false,
      value: 100,
      label,
    })

    window.setTimeout(() => {
      setActionProgress((current) => (current?.active ? current : null))
    }, 900)
  }

  function clearActionProgress() {
    setActionProgress(null)
  }

  async function handleLogin(event) {
    event.preventDefault()

    try {
      setBusy(true)
      setCollectionsResolved(false)
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

      setUser(payload.user || null)

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
      setCollectionsResolved(false)
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

  function handleContinueSession() {
    setIdlePromptOpen(false)
    setNotice('Session continued.')
  }

  async function handleIdleLogout() {
    setIdlePromptOpen(false)
    await handleLogout()
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
    if (
      ['member', 'editMember', 'memberImport', 'regionClub', 'appointed', 'editAppointed', 'pastLeader', 'editPastLeader'].includes(mode)
      && !isSuperAdmin
    ) {
      setError('Only super admins can manage this section.')
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

    if (mode === 'regionClub') {
      resetRegionClubComposer()
    }

    if (mode === 'editOfficer') {
      resetOfficerComposer()
    }

    if (mode === 'editGovernor') {
      resetGovernorComposer()
    }

    if (mode === 'appointed') {
      resetAppointedComposer()
    }

    if (mode === 'editAppointed') {
      resetAppointedComposer()
    }

    if (mode === 'pastLeader') {
      resetPastLeaderComposer()
    }

    if (mode === 'editPastLeader') {
      resetPastLeaderComposer()
    }

    if (mode === 'memorandum') {
      resetMemorandumComposer()
    }

    if (mode === 'magnaCarta') {
      resetMagnaCartaComposer()
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
    const storedVideo = String(item?.videoFilename || item?.videoUrl || '').trim()
    const externalVideo = isExternalVideoSource(storedVideo)
    setVideoComposer({
      id: String(item?.id || '').trim(),
      title: String(item?.title || '').trim(),
      description: String(item?.description || '').trim(),
      status: String(item?.status || 'Published').trim() || 'Published',
      sourceType: externalVideo ? 'link' : 'upload',
      video: null,
      videoLink: externalVideo ? storedVideo : '',
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

  function openMagnaCartaEditor(item) {
    setMagnaCartaComposer({
      id: String(item?.id || '').trim(),
      title: String(item?.title || '').trim(),
      subtitle: String(item?.subtitle || '').trim(),
      description: String(item?.description || item?.content || '').trim(),
      status: String(item?.status || (item?.isActive ? 'Published' : 'Draft')).trim() || 'Draft',
      image: null,
      imageUrl: String(item?.imageUrl || '').trim(),
      imageFilename: String(item?.imageFilename || '').trim(),
    })
    setActionModal('editMagnaCarta')
  }

  function openOfficerEditor(item) {
    const normalizedPosition = String(item?.position || item?.designation || '').trim()
    const normalizedFullPosition = String(item?.fullPosition || item?.full_position || normalizedPosition).trim()

    setOfficerComposer({
      id: String(item?.id || '').trim(),
      name: String(item?.name || item?.fullName || '').trim(),
      position: normalizedPosition,
      full_position: normalizedFullPosition,
      image: null,
      imageUrl: String(item?.imageUrl || item?.photoUrl || '').trim(),
      imageFilename: String(item?.imageFilename || '').trim(),
    })
    setActionModal('editOfficer')
  }

  function openGovernorEditor(item) {
    setGovernorComposer({
      id: String(item?.id || item?.governor_id || '').trim(),
      name: String(item?.name || item?.governor_name || '').trim(),
      image: null,
      imageUrl: String(item?.imageUrl || item?.photoUrl || '').trim(),
      imageFilename: String(item?.imageFilename || item?.governor_image || '').trim(),
    })
    setActionModal('editGovernor')
  }

  function openAppointedEditor(item) {
    setAppointedComposer({
      id: String(item?.id || '').trim(),
      name: String(item?.name || '').trim(),
      position: String(item?.position || '').trim(),
      committee: String(item?.committee || item?.club || '').trim(),
      region: String(item?.region || '').trim(),
    })
    setActionModal('editAppointed')
  }

  function openPastLeaderEditor(item) {
    setPastLeaderComposer({
      id: String(item?.id || '').trim(),
      name: String(item?.name || '').trim(),
      position: String(item?.position || '').trim(),
      term_start: String(item?.termStart || item?.term_start || '').trim(),
      term_end: String(item?.termEnd || item?.term_end || '').trim(),
      achievements: String(item?.achievements || '').trim(),
      order_priority: String(item?.orderPriority ?? item?.order_priority ?? 0),
      is_active: String(item?.is_active ?? (item?.isActive ? 1 : 0) ?? 1),
      photo: null,
      photoUrl: String(item?.photoUrl || item?.imageUrl || '').trim(),
      photoFilename: String(item?.photoFilename || item?.photo || '').trim(),
    })
    setActionModal('editPastLeader')
  }

  function openMemberEditor(item) {
    const incomingRegion = String(item?.region || item?.eagles_region || '').trim()
    const normalizedRegion = resolveRegionName(incomingRegion)
    const incomingClub = String(item?.club || item?.eagles_club || '').trim()
    const normalizedClub = resolveClubName(normalizedRegion, incomingClub)

    setMemberComposer({
      id: String(item?.id || item?.eagles_id || '').trim(),
      first_name: String(item?.firstName || item?.first_name || '').trim(),
      last_name: String(item?.lastName || item?.last_name || '').trim(),
      position: String(item?.position || item?.eagles_position || '').trim(),
      club: normalizedClub,
      region: normalizedRegion,
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

  function openDeleteConfirm({ title, message, confirmLabel = 'Delete', onConfirm }) {
    if (typeof onConfirm !== 'function') {
      return
    }

    setDeleteConfirm({
      open: true,
      title: String(title || 'Delete record?').trim() || 'Delete record?',
      message: String(message || 'This action cannot be undone.').trim() || 'This action cannot be undone.',
      confirmLabel: String(confirmLabel || 'Delete').trim() || 'Delete',
      onConfirm,
    })
  }

  function closeDeleteConfirm(force = false) {
    if (actionBusy && !force) {
      return
    }

    setDeleteConfirm({
      open: false,
      title: '',
      message: '',
      confirmLabel: 'Delete',
      onConfirm: null,
    })
  }

  async function confirmDeleteAction() {
    const handler = deleteConfirm?.onConfirm
    closeDeleteConfirm(true)

    if (typeof handler !== 'function') {
      return
    }

    try {
      await handler()
    } catch (confirmError) {
      setError(confirmError instanceof Error ? confirmError.message : 'Unable to complete delete action.')
    }
  }

  function updateNewsComposer(field, value) {
    setNewsComposer((current) => ({ ...current, [field]: value }))
  }

  function updateVideoComposer(field, value) {
    setVideoComposer((current) => {
      if (field === 'sourceType') {
        return {
          ...current,
          sourceType: value,
          video: value === 'link' ? null : current.video,
          videoLink: value === 'upload' ? '' : current.videoLink,
        }
      }

      if (field === 'videoLink') {
        const thumbnailUrl = youtubeThumbnailUrl(value)
        return {
          ...current,
          videoLink: value,
          thumbnailUrl: thumbnailUrl || current.thumbnailUrl,
          thumbnailFilename: thumbnailUrl || current.thumbnailFilename,
        }
      }

      return { ...current, [field]: value }
    })
  }

  function updateEventComposer(field, value) {
    setEventComposer((current) => ({ ...current, [field]: value }))
  }

  function updateOfficerComposer(field, value) {
    setOfficerComposer((current) => ({ ...current, [field]: value }))
  }

  function updateGovernorComposer(field, value) {
    setGovernorComposer((current) => ({ ...current, [field]: value }))
  }

  function updateAppointedComposer(field, value) {
    setAppointedComposer((current) => ({ ...current, [field]: value }))
  }

  function updatePastLeaderComposer(field, value) {
    setPastLeaderComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemberComposer(field, value) {
    setMemberComposer((current) => {
      if (field === 'status') {
        return { ...current, status: normalizeMemberStatus(value) }
      }

      if (field === 'region') {
        const nextRegion = resolveRegionName(String(value || '').trim())
        const availableClubs = regionClubMap[nextRegion] || []
        const currentClub = resolveClubName(nextRegion, String(current.club || '').trim())

        return {
          ...current,
          region: nextRegion,
          club: availableClubs.includes(currentClub) ? currentClub : '',
        }
      }

      if (field === 'club') {
        return { ...current, club: resolveClubName(current.region, String(value || '').trim()) }
      }

      return { ...current, [field]: value }
    })
  }

  function updateMemorandumComposer(field, value) {
    setMemorandumComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMagnaCartaComposer(field, value) {
    setMagnaCartaComposer((current) => ({ ...current, [field]: value }))
  }

  function updateUserComposer(field, value) {
    setUserComposer((current) => ({ ...current, [field]: value }))
  }

  function updateMemberImportForm(field, value) {
    setMemberImportForm((current) => ({
      ...current,
      [field]: value,
      duplicates: field === 'file' || field === 'photos' ? [] : current.duplicates,
      photoReport: field === 'file' || field === 'photos' ? null : current.photoReport,
      importStats: field === 'file' || field === 'photos' ? null : current.importStats,
      resultMessage: field === 'file' || field === 'photos' ? '' : current.resultMessage,
    }))
  }

  function updateRegionClubComposer(field, value) {
    setRegionClubComposer((current) => {
      if (field === 'governor_selection') {
        const nextValue = String(value || '').trim()

        if (nextValue === '__NEW__') {
          return {
            ...current,
            setup_action: 'add_club',
            governor_selection: '__NEW__',
            governor_id: '__NEW__',
            governor_name: '',
            region_id: '',
            region_name: '',
            club_name: '',
          }
        }

        if (nextValue === '') {
          return {
            ...current,
            setup_action: 'add_club',
            governor_selection: '',
            governor_id: '',
            governor_name: '',
            region_id: '',
            region_name: '',
            club_name: '',
          }
        }

        const parsedSelection = parseGovernorSelection(nextValue)
        const parsedGovernorId = parsedSelection.governorId
        const parsedRegionId = parsedSelection.regionId
        const parsedRegionName = parsedSelection.regionName
        const parsedGovernorName = parsedSelection.governorName

        return {
          ...current,
          setup_action: 'add_club',
          governor_selection: nextValue,
          governor_id: parsedGovernorId > 0 ? String(parsedGovernorId) : '',
          governor_name: parsedGovernorName,
          region_id: parsedRegionId > 0 ? String(parsedRegionId) : '',
          region_name: parsedRegionName,
          club_name: '',
        }
      }

      if (field === 'setup_action') {
        const nextAction = String(value || '').trim()
        if (nextAction === 'rename_region') {
          return { ...current, setup_action: 'rename_region', club_name: '' }
        }

        return { ...current, setup_action: 'add_club' }
      }

      return { ...current, [field]: value }
    })
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

    let progressStartedAt = null
    try {
      setActionBusy(true)
      progressStartedAt = startActionProgress(actionModal === 'editMember' ? 'Updating member...' : 'Creating member...')
      setError('')
      setNotice('')

      const effectiveRegion = resolveRegionName(String(memberComposer.region || '').trim())
      const effectiveClub = resolveClubName(effectiveRegion, String(memberComposer.club || '').trim())
      const hasStructuredRegions = Object.keys(regionCatalogByKey).length > 0

      if (effectiveRegion === '') {
        setError('Please choose a region first.')
        clearActionProgress()
        return
      }

      if (hasStructuredRegions && !resolveRegionEntry(effectiveRegion)) {
        setError('Selected region is not encoded yet. Please encode region and club first before adding a member.')
        clearActionProgress()
        return
      }

      if (effectiveClub === '') {
        setError('Please choose a club for the selected region.')
        clearActionProgress()
        return
      }

      const allowedClubs = regionClubMap[effectiveRegion] || []
      if (allowedClubs.length === 0) {
        setError('No clubs are encoded for this region yet. Please encode club first before adding a member.')
        clearActionProgress()
        return
      }

      if (!allowedClubs.includes(effectiveClub)) {
        setError('Please choose a club that belongs to the selected region.')
        clearActionProgress()
        return
      }

      if (actionModal !== 'editMember' && memberIdCheck.status === 'duplicate') {
        setError(memberIdCheck.message || 'Eagles ID already exists.')
        clearActionProgress()
        return
      }

      const formData = new FormData()
      if (String(memberComposer.id || '').trim() !== '') {
        formData.append('id', memberComposer.id)
      }
      formData.append('first_name', memberComposer.first_name)
      formData.append('last_name', memberComposer.last_name)
      formData.append('position', memberComposer.position)
      formData.append('club', effectiveClub)
      formData.append('region', effectiveRegion)
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
      await finishActionProgress(progressStartedAt, actionModal === 'editMember' ? 'Member updated.' : 'Member created.')

      await runAdminRefresh({ silent: true })
      setActivePage('members')
      setOpenGroups((current) => ({ ...current, members: true }))
      setNotice(actionModal === 'editMember' ? 'Member updated successfully.' : 'Member added successfully.')
      closeActionModal(true)
      resetMemberComposer()
    } catch (createError) {
      clearActionProgress()
      setError(createError.message || 'Unable to save the member.')
    } finally {
      setActionBusy(false)
      if (progressStartedAt !== null) {
        setActionProgress((current) => (current?.active ? null : current))
      }
    }
  }

  async function handleSaveRegionClub(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can manage members.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const selectedGovernorSelection = String(regionClubComposer.governor_selection || '').trim()
      const selectedGovernorPair = parseGovernorSelection(selectedGovernorSelection)
      const setupAction = String(regionClubComposer.setup_action || 'add_club').trim()
      const selectedGovernor = String(regionClubComposer.governor_id || '').trim()
      const newGovernorName = String(regionClubComposer.governor_name || '').trim()
      const selectedRegionId = Number(regionClubComposer.region_id || 0) || 0
      const regionName = String(regionClubComposer.region_name || '').trim()
      const clubName = String(regionClubComposer.club_name || '').trim()
      const renameExistingRegion = setupAction === 'rename_region' && selectedRegionId > 0
      let governorId = Number(selectedGovernor || 0) || 0
      let normalizedGovernorName = String(regionClubComposer.governor_name || '').trim()
      let createdGovernor = false
      let regionId = selectedRegionId
      let normalizedRegionName = regionName

      if (selectedGovernor === '__NEW__') {
        if (newGovernorName === '') {
          setError('Governor name is required when adding a new governor.')
          return
        }

        const governorPayload = await requestJson(ADMIN_GOVERNORS_CREATE_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            name: newGovernorName,
          }),
        })

        governorId = Number(governorPayload?.data?.id || governorPayload?.data?.governor_id || 0) || 0
        normalizedGovernorName = String(governorPayload?.data?.name || newGovernorName).trim() || newGovernorName
        createdGovernor = governorId > 0
      }

      if (governorId <= 0) {
        setError('Please choose a governor.')
        return
      }

      if (selectedGovernorSelection !== '__NEW__' && regionId > 0 && clubName === '' && !renameExistingRegion) {
        setError('Please enter a club name under the selected governor and region.')
        return
      }

      if (regionId <= 0 && regionName === '') {
        if (createdGovernor && clubName === '') {
          await runAdminRefresh({ silent: true })
          setActivePage('governors')
          setOpenGroups((current) => ({ ...current, leadership: true }))
          setNotice(`Saved governor ${normalizedGovernorName} successfully.`)
          closeActionModal(true)
          resetRegionClubComposer()
          return
        }

        setError('Region name is required to save region or club.')
        return
      }

      if (renameExistingRegion) {
        if (regionName === '') {
          setError('Updated region name is required.')
          return
        }

        const regionPayload = await requestJson(ADMIN_REGIONS_UPDATE_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            id: regionId,
            name: regionName,
            governor_id: governorId,
          }),
        })

        normalizedRegionName = String(regionPayload?.data?.name || regionName).trim() || regionName

        await runAdminRefresh({ silent: true })
        setActivePage('governors')
        setOpenGroups((current) => ({ ...current, leadership: true }))
        if (normalizedRegionName !== '' && selectedGovernorPair.regionName !== '') {
          setNotice(`Updated region ${selectedGovernorPair.regionName} to ${normalizedRegionName} successfully.`)
        } else {
          setNotice(`Updated region ${normalizedRegionName} successfully.`)
        }
        closeActionModal(true)
        resetRegionClubComposer()
        return
      }

      if (regionId <= 0) {
        const regionPayload = await requestJson(ADMIN_REGIONS_CREATE_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            name: regionName,
            governor_id: governorId,
          }),
        })

        regionId = Number(regionPayload?.data?.id || 0) || 0
        normalizedRegionName = String(regionPayload?.data?.name || regionName).trim() || regionName
      }

      let normalizedClubName = ''

      if (clubName !== '') {
        if (regionId <= 0) {
          setError('Region was not linked correctly. Please try again.')
          return
        }

        const clubPayload = await requestJson(ADMIN_CLUBS_CREATE_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            name: clubName,
            region_id: regionId,
            governor_id: governorId,
          }),
        })

        normalizedClubName = String(clubPayload?.data?.name || clubName).trim()
      }

      await runAdminRefresh({ silent: true })
      setActivePage('governors')
      setOpenGroups((current) => ({ ...current, leadership: true }))
      if (createdGovernor && normalizedClubName !== '') {
        setNotice(`Saved governor ${normalizedGovernorName}, region ${normalizedRegionName}, and club ${normalizedClubName}.`)
      } else if (createdGovernor) {
        setNotice(`Saved governor ${normalizedGovernorName} and region ${normalizedRegionName}.`)
      } else if (normalizedClubName !== '') {
        setNotice(`Saved ${normalizedGovernorName || 'Governor'} - ${normalizedRegionName} / ${normalizedClubName} successfully.`)
      } else {
        setNotice(`Saved region ${normalizedRegionName} successfully.`)
      }
      closeActionModal(true)
      resetRegionClubComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save region or club right now.')
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
      const videoSourceType = String(videoComposer.sourceType || 'upload')
      const videoLink = String(videoComposer.videoLink || '').trim()
      if (trimmedTitle === '') {
        setError('Video title is required.')
        return
      }

      if (videoSourceType === 'link' && videoLink === '') {
        setError('Please enter a YouTube or video link.')
        return
      }

      if (videoSourceType === 'upload' && actionModal !== 'editVideo' && !videoComposer.video) {
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
      if (videoSourceType === 'link') {
        formData.append('video_url', videoLink)
      }
      if (videoSourceType === 'upload' && videoComposer.video) {
        formData.append('video', videoComposer.video)
      }
      let thumbnailFile = videoComposer.thumbnail
      if (!thumbnailFile && videoSourceType === 'upload' && videoComposer.video) {
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

  function handleDeleteEvent(item) {
    const eventId = String(item?.id || item?.eventId || '').trim()
    if (eventId === '') {
      setError('A valid event ID is required.')
      return
    }

    const label = String(item?.title || item?.name || 'this event').trim() || 'this event'
    openDeleteConfirm({
      title: 'Delete Event?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Event',
      onConfirm: async () => {
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
      },
    })
  }

  function handleDeleteMember(item) {
    if (!isSuperAdmin) {
      setError('Only super admins can delete members.')
      return
    }

    const memberId = String(item?.id || item?.eagles_id || '').trim()
    if (memberId === '') {
      setError('A valid member ID is required.')
      return
    }

    const firstName = String(item?.firstName || item?.first_name || item?.eagles_firstName || '').trim()
    const lastName = String(item?.lastName || item?.last_name || item?.eagles_lastName || '').trim()
    const label = String(item?.fullName || item?.name || `${firstName} ${lastName}` || memberId).trim() || memberId

    openDeleteConfirm({
      title: 'Delete Member?',
      message: `Delete "${label}" (${memberId}) permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Member',
      onConfirm: async () => {
        try {
          setActionBusy(true)
          setError('')
          setNotice('')

          await requestJson(ADMIN_MEMBERS_DELETE_ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: memberId }),
          })

          await runAdminRefresh({ silent: true })
          setActivePage('members')
          setOpenGroups((current) => ({ ...current, members: true }))
          setNotice('Member deleted successfully.')
        } catch (deleteError) {
          setError(deleteError.message || 'Unable to delete the member.')
        } finally {
          setActionBusy(false)
        }
      },
    })
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
      const trimmedFullPosition = String(officerComposer.full_position || '').trim()

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
      formData.append('full_position', trimmedFullPosition || trimmedPosition)
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

  async function handleSaveGovernor(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can update governors.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const governorId = String(governorComposer.id || '').trim()
      const trimmedName = String(governorComposer.name || '').trim()

      if (governorId === '') {
        setError('Governor ID is required.')
        return
      }

      if (trimmedName === '') {
        setError('Governor name is required.')
        return
      }

      const formData = new FormData()
      formData.append('id', governorId)
      formData.append('name', trimmedName)
      if (governorComposer.image) {
        formData.append('image', governorComposer.image)
      }

      await requestJson(ADMIN_GOVERNORS_UPDATE_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('governors')
      setOpenGroups((current) => ({ ...current, leadership: true }))
      setNotice('Governor updated successfully.')
      closeActionModal(true)
      resetGovernorComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the governor.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSaveAppointed(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can manage appointed officers.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const isEditingAppointed = actionModal === 'editAppointed'
      const appointedId = String(appointedComposer.id || '').trim()
      const trimmedName = String(appointedComposer.name || '').trim()
      const trimmedPosition = String(appointedComposer.position || '').trim()
      const trimmedCommittee = String(appointedComposer.committee || '').trim()
      const trimmedRegion = String(appointedComposer.region || '').trim()

      if (isEditingAppointed && appointedId === '') {
        setError('A valid appointed officer ID is required.')
        return
      }

      if (trimmedName === '' || trimmedPosition === '' || trimmedCommittee === '' || trimmedRegion === '') {
        setError('Name, position, committee, and region are required.')
        return
      }

      const endpoint = isEditingAppointed
        ? ADMIN_APPOINTED_UPDATE_ENDPOINT
        : ADMIN_APPOINTED_CREATE_ENDPOINT

      const payload = {
        name: trimmedName,
        position: trimmedPosition,
        committee: trimmedCommittee,
        region: trimmedRegion,
      }

      if (isEditingAppointed) {
        payload.id = appointedId
      }

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      })

      await runAdminRefresh({ silent: true })
      setActivePage('appointed')
      setOpenGroups((current) => ({ ...current, leadership: true }))
      setNotice(isEditingAppointed ? 'Appointed officer updated successfully.' : 'Appointed officer added successfully.')
      closeActionModal(true)
      resetAppointedComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the appointed officer.')
    } finally {
      setActionBusy(false)
    }
  }

  async function handleSavePastLeader(event) {
    event.preventDefault()

    if (!isSuperAdmin) {
      setError('Only super admins can manage past leaders.')
      return
    }

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const isEditingPastLeader = actionModal === 'editPastLeader'
      const pastLeaderId = String(pastLeaderComposer.id || '').trim()
      const trimmedName = String(pastLeaderComposer.name || '').trim()
      const trimmedPosition = String(pastLeaderComposer.position || '').trim()
      const termStart = Number(pastLeaderComposer.term_start || 0) || 0
      const termEnd = Number(pastLeaderComposer.term_end || 0) || 0
      const achievements = String(pastLeaderComposer.achievements || '').trim()
      const orderPriority = Number(pastLeaderComposer.order_priority || 0) || 0
      const isActive = String(pastLeaderComposer.is_active ?? '1').trim() === '1' ? '1' : '0'

      if (isEditingPastLeader && pastLeaderId === '') {
        setError('A valid past leader ID is required.')
        return
      }

      if (trimmedName === '' || trimmedPosition === '') {
        setError('Name and position are required.')
        return
      }

      if (termStart < 1900 || termStart > 2100 || termEnd < 1900 || termEnd > 2100) {
        setError('Term start and term end must be valid years.')
        return
      }

      if (termEnd < termStart) {
        setError('Term end cannot be earlier than term start.')
        return
      }

      const formData = new FormData()
      if (isEditingPastLeader) {
        formData.append('id', pastLeaderId)
      }
      formData.append('name', trimmedName)
      formData.append('position', trimmedPosition)
      formData.append('term_start', String(termStart))
      formData.append('term_end', String(termEnd))
      formData.append('achievements', achievements)
      formData.append('order_priority', String(orderPriority))
      formData.append('is_active', isActive)
      if (pastLeaderComposer.photo) {
        formData.append('photo', pastLeaderComposer.photo)
      }

      const endpoint = isEditingPastLeader
        ? ADMIN_PAST_LEADERS_UPDATE_ENDPOINT
        : ADMIN_PAST_LEADERS_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('pastLeaders')
      setOpenGroups((current) => ({ ...current, leadership: true }))
      setNotice(isEditingPastLeader ? 'Past leader updated successfully.' : 'Past leader added successfully.')
      closeActionModal(true)
      resetPastLeaderComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save the past leader.')
    } finally {
      setActionBusy(false)
    }
  }

  function handleDeleteGovernor(item) {
    if (!isSuperAdmin) {
      setError('Only super admins can delete governors.')
      return
    }

    const governorId = String(item?.id || item?.governor_id || '').trim()
    if (governorId === '') {
      setError('A valid governor ID is required.')
      return
    }

    const label = String(item?.name || item?.governor_name || 'this governor').trim() || 'this governor'
    openDeleteConfirm({
      title: 'Delete Governor?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Governor',
      onConfirm: async () => {
        try {
          setActionBusy(true)
          setError('')
          setNotice('')

          await requestJson(ADMIN_GOVERNORS_DELETE_ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: governorId }),
          })

          await runAdminRefresh({ silent: true })
          setActivePage('governors')
          setOpenGroups((current) => ({ ...current, leadership: true }))
          setNotice('Governor deleted successfully.')
        } catch (deleteError) {
          setError(deleteError.message || 'Unable to delete the governor.')
        } finally {
          setActionBusy(false)
        }
      },
    })
  }

  function handleDeletePastLeader(item) {
    if (!isSuperAdmin) {
      setError('Only super admins can delete past leaders.')
      return
    }

    const pastLeaderId = String(item?.id || '').trim()
    if (pastLeaderId === '') {
      setError('A valid past leader ID is required.')
      return
    }

    const label = String(item?.name || 'this past leader').trim() || 'this past leader'
    openDeleteConfirm({
      title: 'Delete Past Leader?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Past Leader',
      onConfirm: async () => {
        try {
          setActionBusy(true)
          setError('')
          setNotice('')

          await requestJson(ADMIN_PAST_LEADERS_DELETE_ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: pastLeaderId }),
          })

          await runAdminRefresh({ silent: true })
          setActivePage('pastLeaders')
          setOpenGroups((current) => ({ ...current, leadership: true }))
          setNotice('Past leader deleted successfully.')
        } catch (deleteError) {
          setError(deleteError.message || 'Unable to delete the past leader.')
        } finally {
          setActionBusy(false)
        }
      },
    })
  }

  function handleDeleteAppointed(item) {
    if (!isSuperAdmin) {
      setError('Only super admins can delete appointed officers.')
      return
    }

    const appointedId = String(item?.id || '').trim()
    if (appointedId === '') {
      setError('A valid appointed officer ID is required.')
      return
    }

    const label = String(item?.name || 'this appointed officer').trim() || 'this appointed officer'
    openDeleteConfirm({
      title: 'Delete Appointed Officer?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Officer',
      onConfirm: async () => {
        try {
          setActionBusy(true)
          setError('')
          setNotice('')

          await requestJson(ADMIN_APPOINTED_DELETE_ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: appointedId }),
          })

          await runAdminRefresh({ silent: true })
          setActivePage('appointed')
          setOpenGroups((current) => ({ ...current, leadership: true }))
          setNotice('Appointed officer deleted successfully.')
        } catch (deleteError) {
          setError(deleteError.message || 'Unable to delete the appointed officer.')
        } finally {
          setActionBusy(false)
        }
      },
    })
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

    let progressStartedAt = null
    try {
      setActionBusy(true)
      progressStartedAt = startActionProgress('Uploading CSV and photos...')
      setError('')
      setNotice('')

      const photos = memberImportForm.photos || []
      setActionProgress((current) => (
        current ? { ...current, label: photos.length > 0 ? 'Compressing photos before upload...' : 'Preparing CSV upload...' } : current
      ))
      const optimizedPhotos = await optimizeMemberImportPhotos(photos)
      setActionProgress((current) => (
        current ? { ...current, label: 'Uploading CSV and photos...' } : current
      ))

      const formData = new FormData()
      formData.append('file', memberImportForm.file)
      optimizedPhotos.forEach((photo) => {
        formData.append('photos[]', photo)
      })

      const payload = await requestJson(ADMIN_MEMBERS_IMPORT_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })
      await finishActionProgress(progressStartedAt, 'CSV import processed.')
      const duplicates = Array.isArray(payload?.data?.duplicates) ? payload.data.duplicates : []
      const photoReport = {
        attached: Number(payload?.data?.photosAttached || 0) || 0,
        missing: Array.isArray(payload?.data?.missingPhotos) ? payload.data.missingPhotos : [],
        unmatched: Array.isArray(payload?.data?.unmatchedPhotos) ? payload.data.unmatchedPhotos : [],
        invalid: Array.isArray(payload?.data?.invalidPhotos) ? payload.data.invalidPhotos : [],
        errors: Array.isArray(payload?.data?.photoErrors) ? payload.data.photoErrors : [],
      }

      await runAdminRefresh({ silent: true })
      setOpenGroups((current) => ({ ...current, members: true }))
      setMemberImportForm((current) => ({
        ...current,
        duplicates,
        photoReport,
        importStats: {
          created: Number(payload?.data?.created || 0) || 0,
          updated: Number(payload?.data?.updated || 0) || 0,
          skipped: Number(payload?.data?.skipped || 0) || 0,
          duplicateCount: Number(payload?.data?.duplicateCount || duplicates.length) || 0,
          photosAttached: photoReport.attached,
        },
        resultMessage: payload?.message || '',
      }))

      if (
        duplicates.length > 0
        || photoReport.missing.length > 0
        || photoReport.unmatched.length > 0
        || photoReport.invalid.length > 0
        || photoReport.errors.length > 0
      ) {
        setActivePage('members')
        setNotice(payload?.message || 'Members imported with review items.')
        return
      }

      setActivePage('members')
      setNotice(payload?.message || 'Members imported successfully.')
      closeActionModal(true)
      resetMemberImportForm()
    } catch (importError) {
      clearActionProgress()
      setError(importError.message || 'Unable to import the CSV file.')
    } finally {
      setActionBusy(false)
      if (progressStartedAt !== null) {
        setActionProgress((current) => (current?.active ? null : current))
      }
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

  function handleDeleteUser(item) {
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
    openDeleteConfirm({
      title: 'Delete User?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete User',
      onConfirm: async () => {
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
      },
    })
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

  function handleDeleteMemorandum(item) {
    const memoId = String(item?.id || '').trim()
    if (memoId === '') {
      setError('A valid memorandum ID is required.')
      return
    }

    const label = String(item?.title || 'this memorandum').trim() || 'this memorandum'
    openDeleteConfirm({
      title: 'Delete Memorandum?',
      message: `Delete "${label}" permanently? This action cannot be undone.`,
      confirmLabel: 'Delete Memorandum',
      onConfirm: async () => {
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
      },
    })
  }

  async function handleSaveMagnaCarta(event) {
    event.preventDefault()

    try {
      setActionBusy(true)
      setError('')
      setNotice('')

      const formData = new FormData()
      formData.append('title', magnaCartaComposer.title)
      formData.append('subtitle', magnaCartaComposer.subtitle)
      formData.append('description', magnaCartaComposer.description)
      formData.append('status', magnaCartaComposer.status)

      if (magnaCartaComposer.id) {
        formData.append('id', magnaCartaComposer.id)
      }

      if (magnaCartaComposer.image) {
        formData.append('image', magnaCartaComposer.image)
      }

      const endpoint = magnaCartaComposer.id
        ? ADMIN_MAGNA_CARTA_UPDATE_ENDPOINT
        : ADMIN_MAGNA_CARTA_CREATE_ENDPOINT

      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })

      await runAdminRefresh({ silent: true })
      setActivePage('magnaCarta')
      setOpenGroups((current) => ({ ...current, content: true }))
      setNotice(magnaCartaComposer.id ? 'Magna Carta updated successfully.' : 'Magna Carta created successfully.')
      closeActionModal(true)
      resetMagnaCartaComposer()
    } catch (saveError) {
      setError(saveError.message || 'Unable to save Magna Carta item.')
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
  const pageLoading = !collectionsResolved || refreshing

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

  function renderProcessingBackdrop() {
    const progressValue = Math.round(actionProgress?.value || 0)
    const progressLabel = actionProgress?.label || 'Processing request...'

    return (
      <Backdrop
        open={actionBusy}
        sx={{
          color: '#f5f8ff',
          zIndex: 1800,
          backgroundColor: 'rgba(6, 15, 29, 0.52)',
          backdropFilter: 'blur(2px)',
        }}
      >
        <Box
          sx={{
            display: 'grid',
            justifyItems: 'center',
            gap: 1.25,
            px: 3,
            py: 2.5,
            borderRadius: 2,
            border: '1px solid rgba(255, 255, 255, 0.22)',
            background: 'linear-gradient(135deg, rgba(17, 40, 74, 0.95), rgba(10, 22, 43, 0.9))',
            boxShadow: '0 18px 44px rgba(0, 0, 0, 0.35)',
            width: 'min(460px, calc(100vw - 32px))',
          }}
        >
          <CircularProgress color="inherit" size={42} thickness={4.4} />
          <strong style={{ fontSize: '15px', letterSpacing: '0.01em' }}>{progressLabel}</strong>
          <span style={{ fontSize: '12px', opacity: 0.85 }}>Please wait</span>
          {actionProgress ? (
            <div className="admin-processing-progress" role="status" aria-live="polite">
              <div className="admin-processing-progress__header">
                <span>Progress</span>
                <strong>{progressValue}%</strong>
              </div>
              <div className="admin-processing-progress__track" aria-hidden="true">
                <span style={{ width: `${Math.max(0, Math.min(100, progressValue))}%` }}></span>
              </div>
              <div className="admin-processing-progress__steps" aria-hidden="true">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
              </div>
            </div>
          ) : null}
        </Box>
      </Backdrop>
    )
  }

  function renderInactivityPrompt() {
    return (
      <Backdrop
        open={idlePromptOpen}
        sx={{
          color: '#f5f8ff',
          zIndex: 1790,
          backgroundColor: 'rgba(6, 15, 29, 0.62)',
          backdropFilter: 'blur(2px)',
        }}
      >
        <Box
          sx={{
            width: 'min(520px, calc(100vw - 32px))',
            display: 'grid',
            gap: 1.4,
            px: { xs: 2.5, sm: 3.5 },
            py: { xs: 2.5, sm: 3.25 },
            borderRadius: 2.5,
            border: '1px solid rgba(255, 255, 255, 0.24)',
            background: 'linear-gradient(135deg, rgba(17, 40, 74, 0.96), rgba(10, 22, 43, 0.92))',
            boxShadow: '0 22px 48px rgba(0, 0, 0, 0.38)',
          }}
        >
          <p style={{ margin: 0, fontSize: 12, letterSpacing: '0.12em', textTransform: 'uppercase', opacity: 0.82 }}>
            Session Reminder
          </p>
          <h3 style={{ margin: 0, fontSize: 24, lineHeight: 1.15 }}>
            You have been inactive for 20 minutes.
          </h3>
          <p style={{ margin: 0, fontSize: 14, lineHeight: 1.6, opacity: 0.9 }}>
            Do you want to continue your admin session or logout now?
          </p>
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.25, flexWrap: 'wrap', mt: 0.8 }}>
            <button
              type="button"
              className="admin-secondary-button"
              onClick={handleContinueSession}
              disabled={busy || actionBusy}
            >
              <i className="fas fa-rotate-right" aria-hidden="true"></i>
              Continue
            </button>
            <button
              type="button"
              className="admin-danger-button"
              onClick={handleIdleLogout}
              disabled={busy || actionBusy}
            >
              <i className="fas fa-right-from-bracket" aria-hidden="true"></i>
              Logout
            </button>
          </Box>
        </Box>
      </Backdrop>
    )
  }

  function renderDeleteConfirmModal() {
    if (!deleteConfirm.open) {
      return null
    }

    return (
      <div className="admin-modal-backdrop admin-confirm-backdrop">
        <div className="admin-modal admin-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="admin-delete-confirm-title">
          <div className="admin-modal-header">
            <div>
              <p className="admin-modal-eyebrow">Delete Confirmation</p>
              <h2 id="admin-delete-confirm-title">{deleteConfirm.title}</h2>
              <p className="admin-modal-subtitle">{deleteConfirm.message}</p>
            </div>

            <button
              type="button"
              className="admin-icon-button"
              onClick={() => closeDeleteConfirm()}
              aria-label="Close confirmation"
              disabled={actionBusy}
            >
              <i className="fas fa-xmark" aria-hidden="true"></i>
            </button>
          </div>

          <div className="admin-confirm-modal__warning">
            <i className="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span>This delete action is permanent and cannot be undone.</span>
          </div>

          <div className="admin-modal-actions">
            <button
              type="button"
              className="admin-secondary-button"
              onClick={() => closeDeleteConfirm()}
              disabled={actionBusy}
            >
              Cancel
            </button>
            <button
              type="button"
              className="admin-danger-button"
              onClick={confirmDeleteAction}
              disabled={actionBusy}
            >
              <i className="fas fa-trash" aria-hidden="true"></i>
              {deleteConfirm.confirmLabel}
            </button>
          </div>
        </div>
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
            loading={pageLoading}
            isSuperAdmin={isSuperAdmin}
            onCreateMember={() => openActionModal('member')}
            onImportMembers={() => openActionModal('memberImport')}
            onEditMember={openMemberEditor}
            onDeleteMember={handleDeleteMember}
          />
        )
      case 'users':
        return (
          <UsersPage
            user={user}
            users={collections.users}
            query={query}
            loading={pageLoading}
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
            loading={pageLoading}
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
            loading={pageLoading}
            onCreateNews={() => openActionModal('news')}
            onEditNews={openNewsEditor}
          />
        )
      case 'videos':
        return (
          <VideosPage
            items={collections.videos}
            query={query}
            loading={pageLoading}
            onCreateVideo={() => openActionModal('video')}
            onEditVideo={openVideoEditor}
          />
        )
      case 'events':
        return (
          <EventsPage
            items={collections.events}
            query={query}
            loading={pageLoading}
            onCreateEvent={() => openActionModal('event')}
            onEditEvent={openEventEditor}
            onDeleteEvent={handleDeleteEvent}
          />
        )
      case 'magnaCarta':
        return (
          <MagnaCartaPage
            items={collections.magnaCarta}
            query={query}
            loading={pageLoading}
            onCreateMagnaCarta={() => openActionModal('magnaCarta')}
            onEditMagnaCarta={openMagnaCartaEditor}
          />
        )
      case 'officers':
        return (
          <OfficersPage
            items={collections.officers}
            query={query}
            loading={pageLoading}
            onEditOfficer={openOfficerEditor}
          />
        )
      case 'appointed':
        return (
          <AppointedPage
            items={collections.appointed}
            query={query}
            loading={pageLoading}
            isSuperAdmin={isSuperAdmin}
            onCreateAppointed={() => openActionModal('appointed')}
            onEditAppointed={openAppointedEditor}
            onDeleteAppointed={handleDeleteAppointed}
          />
        )
      case 'pastLeaders':
        return (
          <PastLeadersPage
            items={collections.pastLeaders}
            query={query}
            loading={pageLoading}
            isSuperAdmin={isSuperAdmin}
            onCreatePastLeader={() => openActionModal('pastLeader')}
            onEditPastLeader={openPastLeaderEditor}
            onDeletePastLeader={handleDeletePastLeader}
          />
        )
      case 'governors':
        return (
          <GovernorsPage
            items={collections.governors}
            query={query}
            loading={pageLoading}
            isSuperAdmin={isSuperAdmin}
            onCreateRegionClub={() => openActionModal('regionClub')}
            onEditGovernor={openGovernorEditor}
            onDeleteGovernor={handleDeleteGovernor}
          />
        )
      case 'activity':
        return <ActivityPage dashboard={dashboard} user={user} query={query} loading={pageLoading} />
      case 'fileManager':
        return (
          <FileManagerPage
            endpoint={ADMIN_FILE_MANAGER_ENDPOINT}
            loading={pageLoading}
            onError={setError}
            onNotice={setNotice}
          />
        )
      case 'dashboard':
      default:
        return (
          <DashboardPage
            dashboard={dashboard}
            collections={collections}
            query={query}
            user={user}
            loading={pageLoading}
            onNavigate={handlePageChange}
            onOpenQuickAction={openActionModal}
            isSuperAdmin={isSuperAdmin}
          />
        )
    }
  }

  if (authChecking && !user) {
    return (
      <div
        className="admin-shell login-mode"
        style={{ '--admin-login-bg': `url(${ADMIN_BRANDING.backgroundUrl})` }}
      >
        {renderFloatingBanner()}

        <div className="login-stage">
          <section className="login-card">
            <div className="login-card__mesh" aria-hidden="true"></div>

            <div className="login-loading">
              <i className="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
              <span>Checking existing admin session...</span>
            </div>
          </section>
        </div>
      </div>
    )
  }

  if (activePage === 'notFound') {
    return (
      <div
        className="admin-shell admin-error-mode"
        style={{ '--admin-login-bg': `url(${ADMIN_BRANDING.backgroundUrl})` }}
      >
        <AdminNotFoundPage
          onPrimary={() => handlePageChange('dashboard')}
          onSecondary={user ? handleLogout : () => handlePageChange('dashboard')}
          primaryLabel={user ? 'Dashboard' : 'Admin Login'}
          secondaryLabel={user ? 'Logout' : 'Reset Link'}
          secondaryIcon={user ? 'fa-right-from-bracket' : 'fa-rotate-left'}
        />
      </div>
    )
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

                {busy ? (
                  <div className="login-loading">
                    <i className="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                    <span>Signing in and preparing dashboard...</span>
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
      {renderProcessingBackdrop()}
      {renderInactivityPrompt()}
      {renderDeleteConfirmModal()}

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
        onGovernorSubmit={handleSaveGovernor}
        onAppointedSubmit={handleSaveAppointed}
        onPastLeaderSubmit={handleSavePastLeader}
        onMemberSubmit={handleSaveMember}
        onRegionClubSubmit={handleSaveRegionClub}
        onMemberImportSubmit={handleImportMembers}
        onUserSubmit={handleCreateUser}
        onMemorandumSubmit={handleSaveMemorandum}
        onMagnaCartaSubmit={handleSaveMagnaCarta}
        newsForm={newsComposer}
        videoForm={videoComposer}
        eventForm={eventComposer}
        officerForm={officerComposer}
        governorForm={governorComposer}
        appointedForm={appointedComposer}
        pastLeaderForm={pastLeaderComposer}
        memberForm={memberComposer}
        memberIdCheck={memberIdCheck}
        regionClubForm={regionClubComposer}
        memberImportForm={memberImportForm}
        userForm={userComposer}
        memorandumForm={memorandumComposer}
        magnaCartaForm={magnaCartaComposer}
        onNewsFieldChange={updateNewsComposer}
        onVideoFieldChange={updateVideoComposer}
        onEventFieldChange={updateEventComposer}
        onOfficerFieldChange={updateOfficerComposer}
        onGovernorFieldChange={updateGovernorComposer}
        onAppointedFieldChange={updateAppointedComposer}
        onPastLeaderFieldChange={updatePastLeaderComposer}
        onMemberFieldChange={updateMemberComposer}
        onRegionClubFieldChange={updateRegionClubComposer}
        onMemberImportFieldChange={updateMemberImportForm}
        onUserFieldChange={updateUserComposer}
        onMemorandumFieldChange={updateMemorandumComposer}
        onMagnaCartaFieldChange={updateMagnaCartaComposer}
        submitting={actionBusy}
        regions={regions}
        regionClubMap={regionClubMap}
        governors={collections.governors}
        isSuperAdmin={isSuperAdmin}
      />
    </div>
  )
}

export default App
