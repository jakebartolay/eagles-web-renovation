import Skeleton from '@mui/material/Skeleton'

function formatNumber(value) {
  const number = Number(value || 0)
  return Number.isFinite(number) ? number.toLocaleString() : '0'
}

function formatMetricValue(value) {
  if (typeof value === 'string') {
    return value
  }

  return formatNumber(value)
}

function resolveUserDisplayName(user) {
  return String(user?.name || user?.username || 'Admin').trim() || 'Admin'
}

function resolveUserInitials(user) {
  const displayName = resolveUserDisplayName(user)
  const parts = displayName.split(/\s+/).filter(Boolean)
  return parts
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
}

function actionTone(actionType) {
  const normalized = String(actionType || '').toLowerCase()

  if (normalized.includes('delete')) return 'danger'
  if (normalized.includes('create')) return 'positive'
  if (normalized.includes('login') || normalized.includes('logout')) return 'warning'
  return 'info'
}

function actionIcon(actionType) {
  const normalized = String(actionType || '').toLowerCase()

  if (normalized.includes('delete')) return 'fa-trash-can'
  if (normalized.includes('create')) return 'fa-plus'
  if (normalized.includes('login')) return 'fa-right-to-bracket'
  if (normalized.includes('logout')) return 'fa-right-from-bracket'
  return 'fa-pen-to-square'
}

function QuickStatCard({ label, value, subtext, icon, tone = 'info', onClick }) {
  return (
    <button
      type="button"
      className={`dashboard-stat-card ${tone}`}
      onClick={onClick}
      style={{ textAlign: 'left', cursor: onClick ? 'pointer' : 'default' }}
    >
      <span className="dashboard-stat-card__icon">
        <i className={`fas ${icon}`} aria-hidden="true"></i>
      </span>
      <span className="dashboard-stat-card__label">{label}</span>
      <strong className="dashboard-stat-card__value">{formatMetricValue(value)}</strong>
      <small className="dashboard-stat-card__subtext">{subtext}</small>
      <span className="dashboard-stat-card__hint">
        {onClick ? 'Open section' : 'Overview only'}
      </span>
    </button>
  )
}

function QuickActionButton({ label, icon, onClick, variant = 'secondary' }) {
  return (
    <button
      type="button"
      className={variant === 'primary' ? 'admin-primary-button' : 'admin-secondary-button'}
      onClick={onClick}
    >
      <i className={`fas ${icon}`} aria-hidden="true"></i>
      {label}
    </button>
  )
}

function ActivityItem({ item }) {
  const tone = actionTone(item?.actionType)

  return (
    <article className={`dashboard-activity-item ${tone}`}>
      <span className="dashboard-activity-item__icon">
        <i className={`fas ${actionIcon(item?.actionType)}`} aria-hidden="true"></i>
      </span>

      <div className="dashboard-activity-item__main">
        <div className="dashboard-activity-item__top">
          <strong>{item?.actionType || 'Action'}</strong>
          <span>{item?.createdAt || 'No timestamp'}</span>
        </div>

        <p className="dashboard-activity-item__description">
          {(item?.adminUsername || 'Unknown admin')}
          {' - '}
          {item?.description || 'No description available.'}
        </p>
      </div>
    </article>
  )
}

function ErrorLogsPanel({ logs }) {
  const lines = Array.isArray(logs?.lines) ? logs.lines : []
  const available = Boolean(logs?.available) && lines.length > 0

  return (
    <section className="dashboard-panel dashboard-error-logs">
      <div className="dashboard-panel__header">
        <div>
          <p className="page-kicker">Super Admin Only</p>
          <h3>Server Error Logs</h3>
        </div>
        <span className={`dashboard-error-logs__status ${available ? 'active' : 'empty'}`}>
          <i className={`fas ${available ? 'fa-circle-exclamation' : 'fa-circle-check'}`} aria-hidden="true"></i>
          {available ? `${lines.length} recent line(s)` : 'No readable logs'}
        </span>
      </div>

      {available ? (
        <>
          <div className="dashboard-error-logs__meta">
            <span>Source: {logs?.source || 'error_log'}</span>
            <span>Checked: {logs?.checkedAt || 'Just now'}</span>
          </div>
          <pre className="dashboard-error-logs__list">
            {lines.join('\n')}
          </pre>
        </>
      ) : (
        <div className="dashboard-empty-state">
          <i className="fas fa-file-circle-check" aria-hidden="true"></i>
          <p>No readable PHP error log was found from the API folder. If deployment fails, check cPanel Error Logs.</p>
        </div>
      )}
    </section>
  )
}

function SnapshotRow({ label, icon, value }) {
  return (
    <div className="dashboard-snapshot-row">
      <span className="dashboard-snapshot-row__label">
        <i className={`fas ${icon}`} aria-hidden="true"></i>
        {label}
      </span>
      <strong>{formatNumber(value)}</strong>
    </div>
  )
}

function DashboardLoadingState({ isSuperAdmin }) {
  return (
    <section className="dashboard-page">
      <section className="dashboard-hero">
        <div className="dashboard-hero__topline">
          <span className="dashboard-hero__badge">
            <Skeleton variant="text" width={130} height={18} />
          </span>

          <div className="dashboard-hero__profile">
            <span className="dashboard-hero__avatar">
              <Skeleton variant="circular" width={28} height={28} />
            </span>
            <div>
              <Skeleton variant="text" width={120} height={20} />
              <Skeleton variant="text" width={170} height={16} />
            </div>
          </div>
        </div>

        <div className="dashboard-hero__copy">
          <p className="page-kicker"><Skeleton variant="text" width={92} height={16} /></p>
          <h2><Skeleton variant="text" width="72%" height={36} /></h2>
          <p>
            <Skeleton variant="text" width="96%" height={18} />
            <Skeleton variant="text" width="90%" height={18} />
          </p>
        </div>

        <div className="dashboard-hero__actions">
          <Skeleton variant="rounded" width={132} height={42} />
          <Skeleton variant="rounded" width={170} height={42} />
          {isSuperAdmin ? <Skeleton variant="rounded" width={132} height={42} /> : null}
        </div>

        <div className="dashboard-hero__metrics">
          {Array.from({ length: 3 }).map((_, index) => (
            <article className="dashboard-hero-stat" key={`hero-loading-${index}`}>
              <Skeleton variant="text" width={92} height={16} />
              <Skeleton variant="text" width={82} height={32} />
              <Skeleton variant="text" width={132} height={16} />
            </article>
          ))}
        </div>
      </section>

      <div className="dashboard-stats-grid">
        {Array.from({ length: 8 }).map((_, index) => (
          <article className="dashboard-stat-card info" key={`stat-loading-${index}`} style={{ pointerEvents: 'none' }}>
            <span className="dashboard-stat-card__icon">
              <Skeleton variant="circular" width={36} height={36} />
            </span>
            <span className="dashboard-stat-card__label"><Skeleton variant="text" width={88} height={16} /></span>
            <strong className="dashboard-stat-card__value"><Skeleton variant="text" width={72} height={32} /></strong>
            <small className="dashboard-stat-card__subtext"><Skeleton variant="text" width={128} height={16} /></small>
            <span className="dashboard-stat-card__hint"><Skeleton variant="text" width={96} height={14} /></span>
          </article>
        ))}
      </div>

      <div className="dashboard-panels">
        <section className="dashboard-panel">
          <div className="dashboard-panel__header">
            <div>
              <p className="page-kicker"><Skeleton variant="text" width={88} height={16} /></p>
              <h3><Skeleton variant="text" width={170} height={28} /></h3>
            </div>
            <Skeleton variant="rounded" width={124} height={36} />
          </div>

          <div className="dashboard-activity-list">
            {Array.from({ length: 6 }).map((_, index) => (
              <article className="dashboard-activity-item info" key={`activity-loading-${index}`}>
                <span className="dashboard-activity-item__icon">
                  <Skeleton variant="circular" width={30} height={30} />
                </span>
                <div className="dashboard-activity-item__main">
                  <div className="dashboard-activity-item__top">
                    <strong><Skeleton variant="text" width={110} height={20} /></strong>
                    <span><Skeleton variant="text" width={120} height={16} /></span>
                  </div>
                  <p className="dashboard-activity-item__description">
                    <Skeleton variant="text" width="92%" height={16} />
                  </p>
                </div>
              </article>
            ))}
          </div>
        </section>

        <div className="dashboard-panel-stack">
          <section className="dashboard-panel">
            <div className="dashboard-panel__header compact">
              <div>
                <p className="page-kicker"><Skeleton variant="text" width={80} height={16} /></p>
                <h3><Skeleton variant="text" width={90} height={26} /></h3>
              </div>
            </div>

            <div className="dashboard-snapshot-list">
              {Array.from({ length: 6 }).map((_, index) => (
                <div className="dashboard-snapshot-row" key={`snapshot-loading-${index}`}>
                  <span className="dashboard-snapshot-row__label">
                    <Skeleton variant="text" width={124} height={16} />
                  </span>
                  <strong><Skeleton variant="text" width={56} height={22} /></strong>
                </div>
              ))}
            </div>
          </section>

          <section className="dashboard-panel">
            <div className="dashboard-panel__header compact">
              <div>
                <p className="page-kicker"><Skeleton variant="text" width={82} height={16} /></p>
                <h3><Skeleton variant="text" width={136} height={26} /></h3>
              </div>
            </div>

            <div className="dashboard-shortcut-list">
              {Array.from({ length: 3 }).map((_, index) => (
                <div className="dashboard-shortcut-card" key={`shortcut-loading-${index}`} style={{ pointerEvents: 'none' }}>
                  <strong><Skeleton variant="text" width={170} height={20} /></strong>
                  <span><Skeleton variant="text" width="96%" height={16} /></span>
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>
    </section>
  )
}

export default function DashboardPage({
  dashboard = {},
  collections = {},
  query = '',
  user,
  loading = false,
  onNavigate,
  onOpenQuickAction,
  isSuperAdmin,
}) {
  const stats = dashboard?.stats || {}
  const activity = Array.isArray(dashboard?.activity) ? dashboard.activity : []
  const errorLogs = dashboard?.errorLogs || null
  const displayName = resolveUserDisplayName(user)
  const firstName = displayName.split(/\s+/)[0] || 'Admin'
  const accessHelper = isSuperAdmin ? 'Super admin controls enabled' : 'Admin workspace ready'

  const filteredActivity = activity.filter((item) => {
    if (!query) return true
    return JSON.stringify(item).toLowerCase().includes(query.toLowerCase())
  })

  const totalCollections = Object.values(collections).reduce((total, items) => (
    total + (Array.isArray(items) ? items.length : 0)
  ), 0)

  const heroMetrics = [
    {
      label: 'Visible records',
      value: totalCollections,
      helper: 'Across all synced admin collections',
    },
    {
      label: 'Recent actions',
      value: filteredActivity.length,
      helper: query ? 'Matching your current search' : 'Latest audit entries',
    },
    {
      label: 'Access level',
      value: isSuperAdmin ? 'Super Admin' : 'Admin',
      helper: isSuperAdmin ? 'Full content and member controls' : 'Operational access enabled',
    },
  ]

  if (loading) {
    return <DashboardLoadingState isSuperAdmin={isSuperAdmin} />
  }

  return (
    <section className="dashboard-page">
      <section className="dashboard-hero">
        <div className="dashboard-hero__topline">
          <span className="dashboard-hero__badge">
            <i className="fas fa-shield-halved" aria-hidden="true"></i>
            Admin control center
          </span>

          <div className="dashboard-hero__profile">
            <span className="dashboard-hero__avatar">{resolveUserInitials(user)}</span>
            <div>
              <strong>{displayName}</strong>
              <small>{accessHelper}</small>
            </div>
          </div>
        </div>

        <div className="dashboard-hero__copy">
          <p className="page-kicker">Daily Overview</p>
          <h2>{`Welcome back, ${firstName}. Keep the workspace sharp and organized.`}</h2>
          <p>
            Review publishing activity, member records, and leadership data from one
            polished control center built for faster daily admin work.
          </p>
        </div>

        <div className="dashboard-hero__actions">
          <QuickActionButton
            label="Open News"
            icon="fa-newspaper"
            variant="primary"
            onClick={() => onNavigate?.('news')}
          />
          <QuickActionButton
            label="Open Memorandum"
            icon="fa-file-lines"
            onClick={() => onNavigate?.('memorandum')}
          />
          {isSuperAdmin ? (
            <QuickActionButton
              label="Add Member"
              icon="fa-user-plus"
              onClick={() => onOpenQuickAction?.('member')}
            />
          ) : null}
        </div>

        <div className="dashboard-hero__metrics">
          {heroMetrics.map((item) => (
            <article className="dashboard-hero-stat" key={item.label}>
              <span>{item.label}</span>
              <strong>{formatMetricValue(item.value)}</strong>
              <small>{item.helper}</small>
            </article>
          ))}
        </div>
      </section>

      <div className="dashboard-stats-grid">
        <QuickStatCard
          label="Members"
          value={stats.members}
          subtext={`${formatNumber(stats.activeMembers)} active members`}
          icon="fa-users"
          tone="positive"
          onClick={() => onNavigate?.('members')}
        />
        <QuickStatCard
          label="News"
          value={stats.news}
          subtext={`${formatNumber(stats.publishedNews)} published | ${formatNumber(stats.draftNews)} draft`}
          icon="fa-newspaper"
          tone="warm"
          onClick={() => onNavigate?.('news')}
        />
        <QuickStatCard
          label="Videos"
          value={stats.videos}
          subtext="Media content library"
          icon="fa-video"
          tone="info"
          onClick={() => onNavigate?.('videos')}
        />
        <QuickStatCard
          label="Events"
          value={stats.events}
          subtext={`${formatNumber(stats.upcomingEvents)} upcoming schedule(s)`}
          icon="fa-calendar-days"
          tone="info"
          onClick={() => onNavigate?.('events')}
        />
        <QuickStatCard
          label="Memorandums"
          value={stats.memorandums}
          subtext="Circulars and uploaded pages"
          icon="fa-file-lines"
          tone="warm"
          onClick={() => onNavigate?.('memorandum')}
        />
        <QuickStatCard
          label="Officers"
          value={stats.officers}
          subtext="Leadership roster"
          icon="fa-user-tie"
          tone="positive"
          onClick={() => onNavigate?.('officers')}
        />
        <QuickStatCard
          label="Governors"
          value={stats.governors}
          subtext="Regional assignments"
          icon="fa-scale-balanced"
          tone="info"
          onClick={() => onNavigate?.('governors')}
        />
        <QuickStatCard
          label="Past Leaders"
          value={stats.pastLeaders}
          subtext="Leadership history records"
          icon="fa-landmark"
          tone="warm"
          onClick={() => onNavigate?.('pastLeaders')}
        />
        <QuickStatCard
          label="Admins"
          value={stats.admins}
          subtext={isSuperAdmin ? 'Super admin access enabled' : 'Operational admin access'}
          icon="fa-user-shield"
          tone="danger"
          onClick={() => onNavigate?.('activity')}
        />
      </div>

      <div className="dashboard-panels">
        <section className="dashboard-panel">
          <div className="dashboard-panel__header">
            <div>
              <p className="page-kicker">Audit Snapshot</p>
              <h3>Recent Admin Actions</h3>
            </div>
            <button
              type="button"
              className="admin-secondary-button"
              onClick={() => onNavigate?.('activity')}
            >
              <i className="fas fa-clock-rotate-left" aria-hidden="true"></i>
              View full log
            </button>
          </div>

          {filteredActivity.length === 0 ? (
            <div className="dashboard-empty-state">
              <i className="fas fa-inbox" aria-hidden="true"></i>
              <p>No recent activity matched your current search.</p>
            </div>
          ) : (
            <div className="dashboard-activity-list">
              {filteredActivity.slice(0, 8).map((item, index) => (
                <ActivityItem key={`${item?.createdAt || 'activity'}-${index}`} item={item} />
              ))}
            </div>
          )}
        </section>

        <div className="dashboard-panel-stack">
          <section className="dashboard-panel">
            <div className="dashboard-panel__header compact">
              <div>
                <p className="page-kicker">Collections</p>
                <h3>Snapshot</h3>
              </div>
            </div>

            <div className="dashboard-snapshot-list">
              <SnapshotRow label="Members" icon="fa-users" value={collections?.members?.length} />
              <SnapshotRow label="Users" icon="fa-user-shield" value={collections?.users?.length} />
              <SnapshotRow label="News" icon="fa-newspaper" value={collections?.news?.length} />
              <SnapshotRow label="Videos" icon="fa-video" value={collections?.videos?.length} />
              <SnapshotRow label="Events" icon="fa-calendar-days" value={collections?.events?.length} />
              <SnapshotRow label="Memorandums" icon="fa-file-lines" value={collections?.memorandums?.length} />
              <SnapshotRow label="Officers" icon="fa-user-tie" value={collections?.officers?.length} />
              <SnapshotRow label="Governors" icon="fa-scale-balanced" value={collections?.governors?.length} />
              <SnapshotRow label="Appointed" icon="fa-user-check" value={collections?.appointed?.length} />
              <SnapshotRow label="Past Leaders" icon="fa-landmark" value={collections?.pastLeaders?.length} />
              <SnapshotRow label="Magna Carta" icon="fa-book" value={collections?.magnaCarta?.length} />
            </div>
          </section>

          <section className="dashboard-panel">
            <div className="dashboard-panel__header compact">
              <div>
                <p className="page-kicker">Navigation</p>
                <h3>Shortcut Actions</h3>
              </div>
            </div>

            <div className="dashboard-shortcut-list">
              <button type="button" className="dashboard-shortcut-card" onClick={() => onNavigate?.('members')}>
                <strong>Members Directory</strong>
                <span>Review member profiles, clubs, and status data.</span>
              </button>

              <button type="button" className="dashboard-shortcut-card" onClick={() => onNavigate?.('news')}>
                <strong>Content Publishing</strong>
                <span>Jump into news, media, and event records.</span>
              </button>

              <button type="button" className="dashboard-shortcut-card" onClick={() => onNavigate?.('officers')}>
                <strong>Leadership View</strong>
                <span>Open officer and governor assignments quickly.</span>
              </button>
            </div>
          </section>
        </div>
      </div>

      {isSuperAdmin ? <ErrorLogsPanel logs={errorLogs} /> : null}
    </section>
  )
}
