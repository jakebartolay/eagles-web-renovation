import { useDeferredValue, useEffect, useState } from 'react'

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100]

function matchesQuery(item, query) {
  if (!query) return true
  return JSON.stringify(item).toLowerCase().includes(query.toLowerCase())
}

function formatDate(value) {
  if (!value) return 'Not available'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return String(value)
  }

  return parsed.toLocaleString()
}

function countUnique(items, fields) {
  const values = new Set()

  items.forEach((item) => {
    fields.forEach((field) => {
      const value = String(item?.[field] || '').trim()
      if (value) {
        values.add(value)
      }
    })
  })

  return values.size
}

function displayName(item) {
  const fallbackName = `${item?.firstName || item?.first_name || ''} ${item?.lastName || item?.last_name || ''}`
    .trim()

  return item?.fullName || item?.name || fallbackName || 'Unnamed record'
}

function initialsFromName(name) {
  return String(name || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('') || 'NA'
}

function resolveUserRoleId(item) {
  return Number(item?.roleId ?? item?.role_id ?? 0) || 0
}

function resolveUserRoleLabel(item) {
  const roleLabel = String(item?.roleLabel || item?.role_name || item?.role || '').trim()
  if (roleLabel) {
    return roleLabel
  }

  return resolveUserRoleId(item) === 1 ? 'Super Admin' : 'Admin'
}

function userRoleTone(item) {
  return resolveUserRoleId(item) === 1 ? 'danger' : 'info'
}

function userAccessSummary(item, currentUserId) {
  const roleId = resolveUserRoleId(item)
  if (Number(item?.id || item?.user_id || 0) === Number(currentUserId || 0)) {
    return 'Current session'
  }

  return roleId === 1 ? 'Full access' : 'Managed access'
}

function resolveMemberId(item) {
  return String(item?.id || item?.eagles_id || '').trim()
}

function resolveMemberStatus(item) {
  return String(item?.status || item?.eagles_status || 'ACTIVE').trim() || 'ACTIVE'
}

function resolveMemberPhoto(item) {
  return String(item?.picUrl || item?.photoUrl || item?.photo || item?.eagles_pic || '').trim()
}

function resolveMemberField(item, field) {
  return String(item?.[field] || item?.[`eagles_${field}`] || '').trim()
}

function memberStatusTone(status) {
  const normalized = String(status || '').toLowerCase()
  if (normalized.includes('active')) return 'positive'
  if (normalized.includes('inactive')) return 'danger'
  if (normalized.includes('pending')) return 'warm'
  return 'info'
}

function SectionCard({ eyebrow, title, subtitle, actions, metrics = [], children }) {
  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          {eyebrow ? <p className="page-kicker">{eyebrow}</p> : null}
          <h2>{title}</h2>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        {actions ? <div className="content-section-card__actions">{actions}</div> : null}
      </div>

      {metrics.length > 0 ? (
        <div className="content-summary-strip">
          {metrics.map((metric) => (
            <article className={`content-summary-chip ${metric.tone || 'default'}`} key={metric.label}>
              <span>{metric.label}</span>
              <strong>{metric.value}</strong>
              <small>{metric.helper}</small>
            </article>
          ))}
        </div>
      ) : null}

      <div className="content-section-card__body">{children}</div>
    </section>
  )
}

function EmptyState({ label, icon = 'fa-users' }) {
  return (
    <div className="content-empty-state">
      <i className={`fas ${icon}`} aria-hidden="true"></i>
      <p>No {label} found.</p>
    </div>
  )
}

function EntityAvatar({ src, label }) {
  if (src) {
    return <img src={src} alt={label} className="entity-avatar entity-avatar--photo" />
  }

  return <span className="entity-avatar">{initialsFromName(label)}</span>
}

function TableSearch({ value, onChange, placeholder }) {
  return (
    <label className="table-search">
      <i className="fas fa-magnifying-glass" aria-hidden="true"></i>
      <input
        type="search"
        value={value}
        onChange={onChange}
        placeholder={placeholder}
      />
    </label>
  )
}

export function MembersPage({
  members = [],
  query = '',
  isSuperAdmin = false,
  onCreateMember,
  onImportMembers,
  onEditMember,
}) {
  const [tableSearch, setTableSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [clubFilter, setClubFilter] = useState('all')
  const [regionFilter, setRegionFilter] = useState('all')
  const [rowsPerPage, setRowsPerPage] = useState(PAGE_SIZE_OPTIONS[0])
  const [currentPage, setCurrentPage] = useState(1)
  const deferredTableSearch = useDeferredValue(tableSearch)

  const statusOptions = Array.from(new Set(
    members
      .map((item) => resolveMemberStatus(item))
      .filter(Boolean),
  )).sort((first, second) => first.localeCompare(second))

  const clubOptions = Array.from(new Set(
    members
      .map((item) => resolveMemberField(item, 'club'))
      .filter(Boolean),
  )).sort((first, second) => first.localeCompare(second))

  const regionOptions = Array.from(new Set(
    members
      .map((item) => resolveMemberField(item, 'region'))
      .filter(Boolean),
  )).sort((first, second) => first.localeCompare(second))

  const hasTableFilters = (
    tableSearch.trim().length > 0
    || statusFilter !== 'all'
    || clubFilter !== 'all'
    || regionFilter !== 'all'
  )

  const filteredItems = members.filter((item) => {
    if (!matchesQuery(item, query)) return false
    if (!matchesQuery(item, deferredTableSearch)) return false

    const status = resolveMemberStatus(item)
    const club = resolveMemberField(item, 'club')
    const region = resolveMemberField(item, 'region')

    if (statusFilter !== 'all' && status.toLowerCase() !== statusFilter) return false
    if (clubFilter !== 'all' && club.toLowerCase() !== clubFilter) return false
    if (regionFilter !== 'all' && region.toLowerCase() !== regionFilter) return false

    return true
  })

  const activeMembers = filteredItems.filter((item) => (
    resolveMemberStatus(item).toLowerCase().includes('active')
  )).length
  const totalItems = filteredItems.length
  const totalPages = Math.max(1, Math.ceil(totalItems / rowsPerPage))
  const startIndex = (currentPage - 1) * rowsPerPage
  const paginatedItems = filteredItems.slice(startIndex, startIndex + rowsPerPage)
  const rangeStart = totalItems === 0 ? 0 : startIndex + 1
  const rangeEnd = totalItems === 0 ? 0 : startIndex + paginatedItems.length

  useEffect(() => {
    setCurrentPage(1)
  }, [deferredTableSearch, statusFilter, clubFilter, regionFilter, query, rowsPerPage])

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages)
    }
  }, [currentPage, totalPages])

  function clearTableFilters() {
    setTableSearch('')
    setStatusFilter('all')
    setClubFilter('all')
    setRegionFilter('all')
    setCurrentPage(1)
  }

  return (
    <SectionCard
      eyebrow="Member Directory"
      title="Members"
      subtitle={`${filteredItems.length} member record(s) currently visible.`}
      metrics={[
        {
          label: 'Active',
          value: activeMembers,
          helper: 'Members marked active',
          tone: 'positive',
        },
        {
          label: 'Clubs',
          value: countUnique(filteredItems, ['club', 'eagles_club']),
          helper: 'Unique club groups',
          tone: 'info',
        },
        {
          label: 'Regions',
          value: countUnique(filteredItems, ['region', 'eagles_region']),
          helper: 'Geographic coverage',
          tone: 'warm',
        },
      ]}
      actions={isSuperAdmin ? (
        <>
          <button type="button" className="admin-secondary-button" onClick={onImportMembers}>
            <i className="fas fa-file-arrow-up" aria-hidden="true"></i>
            Upload CSV
          </button>
          <button type="button" className="admin-primary-button" onClick={onCreateMember}>
            <i className="fas fa-user-plus" aria-hidden="true"></i>
            Create Member
          </button>
        </>
      ) : null}
    >
      <div className="members-toolbar">
        <TableSearch
          value={tableSearch}
          onChange={(event) => setTableSearch(event.target.value)}
          placeholder="Search inside members table"
        />

        <div className="members-toolbar__line">
          <div className="members-toolbar__filters">
            <label className="table-select">
              <span>Status</span>
              <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                <option value="all">All statuses</option>
                {statusOptions.map((status) => (
                  <option key={status} value={status.toLowerCase()}>
                    {status}
                  </option>
                ))}
              </select>
            </label>

            <label className="table-select">
              <span>Club</span>
              <select value={clubFilter} onChange={(event) => setClubFilter(event.target.value)}>
                <option value="all">All clubs</option>
                {clubOptions.map((club) => (
                  <option key={club} value={club.toLowerCase()}>
                    {club}
                  </option>
                ))}
              </select>
            </label>

            <label className="table-select">
              <span>Region</span>
              <select value={regionFilter} onChange={(event) => setRegionFilter(event.target.value)}>
                <option value="all">All regions</option>
                {regionOptions.map((region) => (
                  <option key={region} value={region.toLowerCase()}>
                    {region}
                  </option>
                ))}
              </select>
            </label>

            {hasTableFilters ? (
              <button type="button" className="admin-secondary-button members-toolbar__clear" onClick={clearTableFilters}>
                <i className="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                Clear
              </button>
            ) : null}
          </div>

          <p className="table-pagination__info members-toolbar__info">
            Showing {rangeStart}-{rangeEnd} of {totalItems}
          </p>
        </div>
      </div>

      {!filteredItems.length ? (
        <EmptyState label="members" icon="fa-user-group" />
      ) : (
        <div className="members-table-wrap">
          <table className="members-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Member</th>
                <th>Position</th>
                <th>Club</th>
                <th>Region</th>
                <th>Status</th>
                <th>Added</th>
                {isSuperAdmin ? <th>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {paginatedItems.map((item, index) => {
                const name = displayName(item)
                const memberId = resolveMemberId(item)
                const position = resolveMemberField(item, 'position') || 'No position'
                const club = resolveMemberField(item, 'club') || 'Club not set'
                const region = resolveMemberField(item, 'region') || 'Region not set'
                const status = resolveMemberStatus(item)
                const dateAdded = formatDate(item?.dateAdded || item?.eagles_dateAdded)

                return (
                  <tr key={memberId || `${name}-${index}`}>
                    <td data-label="ID">{memberId || 'N/A'}</td>
                    <td data-label="Member">
                      <div className="members-table__identity">
                        <EntityAvatar src={resolveMemberPhoto(item)} label={name} />
                        <div className="members-table__identity-copy">
                          <strong>{name}</strong>
                        </div>
                      </div>
                    </td>
                    <td data-label="Position">{position}</td>
                    <td data-label="Club">{club}</td>
                    <td data-label="Region">{region}</td>
                    <td data-label="Status">
                      <span className={`member-status-badge ${memberStatusTone(status)}`}>{status}</span>
                    </td>
                    <td data-label="Added">{dateAdded}</td>
                    {isSuperAdmin ? (
                      <td data-label="Actions">
                        <button
                          type="button"
                          className="admin-secondary-button members-table__edit"
                          onClick={() => onEditMember?.(item)}
                        >
                          <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                          Edit
                        </button>
                      </td>
                    ) : null}
                  </tr>
                )
              })}
            </tbody>
          </table>

          <div className="table-pagination">
            <div className="table-pagination__slot table-pagination__slot--left">
              <p className="table-pagination__info">
                Page {currentPage} of {totalPages}
              </p>
            </div>

            <div className="table-pagination__slot table-pagination__slot--center">
              <label className="table-select table-select--compact table-select--footer">
                <span>Rows per page</span>
                <select value={rowsPerPage} onChange={(event) => setRowsPerPage(Number(event.target.value) || PAGE_SIZE_OPTIONS[0])}>
                  {PAGE_SIZE_OPTIONS.map((size) => (
                    <option key={size} value={size}>
                      {size}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="table-pagination__slot table-pagination__slot--right">
              <div className="table-pagination__actions">
                <button
                  type="button"
                  className="admin-secondary-button table-pagination__button"
                  onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                  disabled={currentPage === 1}
                >
                  Previous
                </button>
                <button
                  type="button"
                  className="admin-secondary-button table-pagination__button"
                  onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                  disabled={currentPage === totalPages}
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </SectionCard>
  )
}

export function UsersPage({
  users = [],
  query = '',
  user,
  isSuperAdmin = false,
  onCreateUser,
  onEditUser,
  onDeleteUser,
}) {
  const filteredItems = users.filter((item) => matchesQuery(item, query))
  const superAdmins = filteredItems.filter((item) => (
    resolveUserRoleId(item) === 1
    || resolveUserRoleLabel(item).toLowerCase().includes('super')
  )).length
  const admins = filteredItems.filter((item) => resolveUserRoleId(item) === 2).length

  return (
    <SectionCard
      eyebrow="Access Control"
      title="Users"
      subtitle={`${filteredItems.length} admin account(s) available in this secure view.`}
      metrics={[
        {
          label: 'Super admins',
          value: superAdmins,
          helper: 'Highest permission level',
          tone: 'danger',
        },
        {
          label: 'Admins',
          value: admins,
          helper: 'Standard admin accounts',
          tone: 'info',
        },
        {
          label: 'Usernames',
          value: filteredItems.filter((item) => item?.username).length,
          helper: 'Named account handles',
          tone: 'warm',
        },
      ]}
      actions={isSuperAdmin ? (
        <button type="button" className="admin-primary-button" onClick={onCreateUser}>
          <i className="fas fa-user-plus" aria-hidden="true"></i>
          Create User
        </button>
      ) : null}
    >
      {!filteredItems.length ? (
        <EmptyState label="users" icon="fa-user-shield" />
      ) : (
        <div className="users-table-wrap">
          <table className="users-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Account</th>
                <th>Role</th>
                <th>Eagles ID</th>
                <th>Created</th>
                <th>Access</th>
                {isSuperAdmin ? <th>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item, index) => {
                const userId = Number(item?.id || item?.user_id || 0)
                const name = item?.name || item?.username || `User ${index + 1}`
                const role = resolveUserRoleLabel(item)
                const accessSummary = userAccessSummary(item, user?.id)
                const isCurrentUser = userId === Number(user?.id || 0)

                return (
                  <tr key={userId || `${item?.username || 'user'}-${index}`}>
                    <td data-label="ID">{userId || 'N/A'}</td>
                    <td data-label="Account">
                      <div className="users-table__identity">
                        <EntityAvatar label={name} />
                        <div className="users-table__identity-copy">
                          <strong>{name}</strong>
                          <span>@{item?.username || 'username-not-set'}</span>
                        </div>
                      </div>
                    </td>
                    <td data-label="Role">
                      <span className={`user-role-badge ${userRoleTone(item)}`}>{role}</span>
                    </td>
                    <td data-label="Eagles ID">{item?.eaglesId || item?.eagles_id || 'Not linked'}</td>
                    <td data-label="Created">{formatDate(item?.createdAt || item?.created_at)}</td>
                    <td data-label="Access">
                      <span className="users-table__access">{accessSummary}</span>
                    </td>
                    {isSuperAdmin ? (
                      <td data-label="Actions">
                        <div className="users-table__actions">
                          <button
                            type="button"
                            className="admin-secondary-button users-table__button"
                            onClick={() => onEditUser?.(item)}
                          >
                            <i className="fas fa-user-gear" aria-hidden="true"></i>
                            Edit Role
                          </button>
                          <button
                            type="button"
                            className="admin-danger-button users-table__button"
                            onClick={() => onDeleteUser?.(item)}
                            disabled={isCurrentUser}
                          >
                            <i className="fas fa-trash-can" aria-hidden="true"></i>
                            Delete
                          </button>
                        </div>
                      </td>
                    ) : null}
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </SectionCard>
  )
}
