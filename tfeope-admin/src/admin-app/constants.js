export const emptyDashboard = {
  stats: {},
  recentActivity: [],
  activity: [],
  user: null,
}

export const emptyCollections = {
  members: [],
  users: [],
  news: [],
  videos: [],
  events: [],
  memorandums: [],
  officers: [],
  governors: [],
  appointed: [],
  magnaCarta: [],
}

export const pageMeta = {
  dashboard: {
    title: 'Dashboard',
    eyebrow: 'Control Center',
    description: 'Monitor collections, quick actions, and the latest admin activity in one place.',
    searchPlaceholder: 'Search recent actions and dashboard data',
  },
  members: {
    title: 'Members',
    group: 'members',
    eyebrow: 'Member Directory',
    description: 'Review member records, status, clubs, and regional coverage.',
    searchPlaceholder: 'Search members by name, club, region, or status',
    superAdminOnly: false,
  },
  users: {
    title: 'Users',
    group: 'members',
    eyebrow: 'Access Control',
    description: 'Manage admin accounts, usernames, and permission levels.',
    searchPlaceholder: 'Search users by name, username, role, or ID',
    superAdminOnly: true,
  },
  news: {
    title: 'News',
    group: 'content',
    eyebrow: 'Content Studio',
    description: 'Publish editorial updates with cover images, statuses, and edit actions.',
    searchPlaceholder: 'Search news by title, status, or content',
  },
  videos: {
    title: 'Videos',
    group: 'content',
    eyebrow: 'Content Studio',
    description: 'Track video entries, thumbnails, and publishing details.',
    searchPlaceholder: 'Search videos by title, description, or status',
  },
  events: {
    title: 'Events',
    group: 'content',
    eyebrow: 'Content Studio',
    description: 'Monitor event schedules, locations, and upcoming activity.',
    searchPlaceholder: 'Search events by title, location, or details',
  },
  memorandum: {
    title: 'Memorandum',
    group: 'content',
    eyebrow: 'Document Center',
    description: 'Manage memorandum records, attached pages, and publishing state.',
    searchPlaceholder: 'Search memorandums by title, status, or description',
  },
  officers: {
    title: 'Officers',
    group: 'leadership',
    eyebrow: 'Leadership Directory',
    description: 'Browse officer assignments, clubs, and regional placement.',
    searchPlaceholder: 'Search officers by name, position, club, or region',
  },
  governors: {
    title: 'Governors',
    group: 'leadership',
    eyebrow: 'Leadership Directory',
    description: 'Keep governor roles, districts, and updates organized.',
    searchPlaceholder: 'Search governors by name, district, or designation',
  },
  appointed: {
    title: 'Appointed Officers',
    group: 'leadership',
    eyebrow: 'Leadership Directory',
    description: 'Review appointed leadership records across clubs and regions.',
    searchPlaceholder: 'Search appointed officers by name, position, club, or region',
  },
  magnaCarta: {
    title: 'Magna Carta',
    group: 'content',
    eyebrow: 'Policy Reference',
    description: 'Maintain policy references and supporting static content.',
    searchPlaceholder: 'Search Magna Carta entries by title or description',
  },
  activity: {
    title: 'Activity',
    eyebrow: 'Audit Trail',
    description: 'Inspect recent admin actions, timelines, and operational changes.',
    searchPlaceholder: 'Search activity by admin, action type, or description',
  },
}

export const navSections = [
  {
    kind: 'page',
    page: 'dashboard',
    label: 'Dashboard',
    icon: 'fa-house',
  },
  {
    kind: 'group',
    id: 'members',
    label: 'Members',
    icon: 'fa-users',
    pages: [
      {
        page: 'members',
        label: 'Members',
        icon: 'fa-user-group',
      },
      {
        page: 'users',
        label: 'Users',
        icon: 'fa-user-shield',
        superAdminOnly: true,
      },
    ],
  },
  {
    kind: 'group',
    id: 'content',
    label: 'Content',
    icon: 'fa-folder-open',
    pages: [
      {
        page: 'news',
        label: 'News',
        icon: 'fa-newspaper',
      },
      {
        page: 'videos',
        label: 'Videos',
        icon: 'fa-video',
      },
      {
        page: 'events',
        label: 'Events',
        icon: 'fa-calendar-days',
      },
      {
        page: 'memorandum',
        label: 'Memorandum',
        icon: 'fa-file-lines',
      },
      {
        page: 'magnaCarta',
        label: 'Magna Carta',
        icon: 'fa-book',
      },
    ],
  },
  {
    kind: 'group',
    id: 'leadership',
    label: 'Leadership',
    icon: 'fa-sitemap',
    pages: [
      {
        page: 'officers',
        label: 'Officers',
        icon: 'fa-user-tie',
      },
      {
        page: 'governors',
        label: 'Governors',
        icon: 'fa-scale-balanced',
      },
      {
        page: 'appointed',
        label: 'Appointed',
        icon: 'fa-user-check',
      },
    ],
  },
  {
    kind: 'page',
    page: 'activity',
    label: 'Activity',
    icon: 'fa-clock-rotate-left',
  },
]

export function normalizePage(page, isSuperAdmin = false) {
  const normalized = String(page || '')
    .replace(/^#\/?/, '')
    .trim()

  const fallback = 'dashboard'
  if (!normalized) return fallback
  if (!pageMeta[normalized]) return fallback
  if (pageMeta[normalized]?.superAdminOnly && !isSuperAdmin) return fallback

  return normalized
}

export function pageHash(page) {
  return `#${page}`
}

export function initialSidebarGroups(activePage = 'dashboard') {
  return {
    members: ['members', 'users'].includes(activePage),
    content: ['news', 'videos', 'events', 'memorandum', 'magnaCarta'].includes(activePage),
    leadership: ['officers', 'governors', 'appointed'].includes(activePage),
  }
}
