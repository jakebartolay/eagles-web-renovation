import { useEffect, useState } from 'react'
import Skeleton from '@mui/material/Skeleton'

function matchesQuery(item, query) {
  if (!query) return true
  return JSON.stringify(item).toLowerCase().includes(query.toLowerCase())
}

function formatDate(value) {
  if (!value) return 'Not available'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString()
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

function initialsFromName(name) {
  return String(name || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('') || 'NA'
}

function personName(item, fallback) {
  return item?.name || item?.fullName || item?.title || fallback
}

function SectionWrapper({ eyebrow, title, subtitle, metrics = [], items, emptyLabel, renderItem }) {
  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">{eyebrow}</p>
          <h2>{title}</h2>
          <p>{subtitle}</p>
        </div>
      </div>

      <div className="content-summary-strip">
        {metrics.map((metric) => (
          <article className={`content-summary-chip ${metric.tone || 'default'}`} key={metric.label}>
            <span>{metric.label}</span>
            <strong>{metric.value}</strong>
            <small>{metric.helper}</small>
          </article>
        ))}
      </div>

      <div className="content-section-card__body">
        {!items.length ? (
          <div className="content-empty-state">
            <i className="fas fa-sitemap" aria-hidden="true"></i>
            <p>No {emptyLabel} found.</p>
          </div>
        ) : (
          <div className="content-grid">
            {items.map((item, index) => (
              <article
                key={item?.id || item?.user_id || `${title}-${index}`}
                className="content-item-card entity-card leadership-card"
              >
                {renderItem(item)}
              </article>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}

function Avatar({ name, src }) {
  if (src) {
    return <img src={src} alt={name} className="entity-avatar entity-avatar--photo" />
  }

  return <span className="entity-avatar">{initialsFromName(name)}</span>
}

function DetailPill({ icon, children }) {
  return (
    <span className="content-item-tag">
      <i className={`fas ${icon}`} aria-hidden="true"></i>
      {children}
    </span>
  )
}

function LoadingSummaryValue() {
  return <Skeleton variant="text" width={44} height={30} />
}

function LoadingSummaryHelper(width = 122) {
  return <Skeleton variant="text" width={width} height={16} />
}

function LoadingRows({ count = 8 }) {
  return (
    <div style={{ display: 'grid', gap: '0.65rem' }}>
      {Array.from({ length: count }).map((_, index) => (
        <Skeleton key={`leadership-row-skeleton-${index}`} variant="rounded" height={52} />
      ))}
    </div>
  )
}

export function OfficersPage({
  items = [],
  query = '',
  loading = false,
  onEditOfficer,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const filteredItems = items.filter((item) => matchesQuery(item, query))
  const pageSize = 10
  const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const visibleItems = filteredItems.slice(pageStart, pageStart + pageSize)
  const displayStart = filteredItems.length ? pageStart + 1 : 0
  const displayEnd = filteredItems.length ? Math.min(pageStart + pageSize, filteredItems.length) : 0

  useEffect(() => {
    setCurrentPage(1)
  }, [query])

  useEffect(() => {
    setCurrentPage((page) => (page > totalPages ? totalPages : page))
  }, [totalPages])

  if (loading) {
    return (
      <section className="content-section-card">
        <div className="content-section-card__header">
          <div>
            <p className="page-kicker">Leadership Directory</p>
            <h2>National Officers</h2>
            <p>Loading officer records...</p>
          </div>
        </div>

        <div className="content-summary-strip">
          <article className="content-summary-chip info">
            <span>Officers</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={132} /></small>
          </article>
          <article className="content-summary-chip warm">
            <span>Full Positions</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={144} /></small>
          </article>
          <article className="content-summary-chip positive">
            <span>Roles</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={122} /></small>
          </article>
        </div>

        <div className="content-section-card__body">
          <LoadingRows />
        </div>
      </section>
    )
  }

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership Directory</p>
          <h2>National Officers</h2>
          <p>{filteredItems.length} officer record(s) in the current view.</p>
        </div>
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Officers</span>
          <strong>{filteredItems.length}</strong>
          <small>Officer records in view</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Full Positions</span>
          <strong>{countUnique(filteredItems, ['fullPosition', 'full_position'])}</strong>
          <small>Distinct full-position titles</small>
        </article>
        <article className="content-summary-chip positive">
          <span>Roles</span>
          <strong>{countUnique(filteredItems, ['position', 'designation'])}</strong>
          <small>Distinct officer functions</small>
        </article>
      </div>

      <div className="content-section-card__body">
        {!filteredItems.length ? (
          <div className="content-empty-state">
            <i className="fas fa-sitemap" aria-hidden="true"></i>
            <p>No officers found.</p>
          </div>
        ) : (
          <div className="officers-table-wrap">
            <table className="officers-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Officer</th>
                  <th>Position</th>
                  <th>Full Position</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed officer')
                  const position = item?.position || item?.designation || 'No position available.'
                  const fullPosition = item?.fullPosition || item?.full_position || position || 'No full position available.'
                  const photoUrl = String(item?.photoUrl || item?.imageUrl || '').trim()

                  return (
                    <tr key={item?.id || item?.user_id || `officer-${pageStart + index}`}>
                      <td data-label="ID">{item?.id || 'N/A'}</td>
                      <td data-label="Officer">
                        <div className="officers-table__identity">
                          <Avatar name={name} src={photoUrl} />
                          <div className="officers-table__identity-copy">
                            <strong>{name}</strong>
                          </div>
                        </div>
                      </td>
                      <td data-label="Position">
                        <p className="officers-table__position">{position}</p>
                      </td>
                      <td data-label="Full Position">{fullPosition}</td>
                      <td data-label="Updated">{formatDate(item?.updatedAt || item?.updated_at || item?.createdAt)}</td>
                      <td data-label="Actions">
                        <div className="officers-table__actions">
                          <button
                            type="button"
                            className="admin-secondary-button officers-table__button"
                            onClick={() => onEditOfficer?.(item)}
                          >
                            <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                            Edit
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>

            <div className="table-pagination">
              <div className="table-pagination__slot table-pagination__slot--left">
                <p className="table-pagination__info">
                  Showing {displayStart}-{displayEnd} of {filteredItems.length}
                </p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--center">
                <p className="table-pagination__info">10 rows per page</p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--right table-pagination__actions">
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
        )}
      </div>
    </section>
  )
}

export function GovernorsPage({
  items = [],
  query = '',
  loading = false,
  isSuperAdmin = false,
  canManageRegionClubs = false,
  onCreateRegionClub,
  onEditGovernor,
  onDeleteGovernor,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const filteredItems = items.filter((item) => matchesQuery(item, query))
  const canManageGovernors = isSuperAdmin && (typeof onEditGovernor === 'function' || typeof onDeleteGovernor === 'function')
  const allRegionNames = filteredItems.flatMap((item) => (
    (Array.isArray(item?.regions) ? item.regions : [])
      .map((regionItem) => String(regionItem?.name || regionItem?.region_name || '').trim())
      .filter(Boolean)
  ))
  const uniqueRegionCount = new Set(allRegionNames).size
  const governorsWithRegionCount = filteredItems.filter((item) => (
    (Array.isArray(item?.regions) ? item.regions : []).some((regionItem) => String(regionItem?.name || regionItem?.region_name || '').trim() !== '')
  )).length
  const pageSize = 10
  const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const visibleItems = filteredItems.slice(pageStart, pageStart + pageSize)
  const displayStart = filteredItems.length ? pageStart + 1 : 0
  const displayEnd = filteredItems.length ? Math.min(pageStart + pageSize, filteredItems.length) : 0

  useEffect(() => {
    setCurrentPage(1)
  }, [query])

  useEffect(() => {
    setCurrentPage((page) => (page > totalPages ? totalPages : page))
  }, [totalPages])

  if (loading) {
    return (
      <section className="content-section-card">
        <div className="content-section-card__header">
          <div>
            <p className="page-kicker">Leadership Directory</p>
            <h2>Governors</h2>
            <p>Loading governor records...</p>
          </div>
          {canManageRegionClubs ? (
            <div className="content-section-card__actions">
              <Skeleton variant="rounded" width={182} height={40} />
            </div>
          ) : null}
        </div>

        <div className="content-summary-strip">
          <article className="content-summary-chip info">
            <span>Governors</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={116} /></small>
          </article>
          <article className="content-summary-chip warm">
            <span>Regions</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={120} /></small>
          </article>
          <article className="content-summary-chip positive">
            <span>With Region</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={114} /></small>
          </article>
        </div>

        <div className="content-section-card__body">
          <LoadingRows />
        </div>
      </section>
    )
  }

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership Directory</p>
          <h2>Governors</h2>
          <p>{filteredItems.length} governor record(s) currently tracked.</p>
        </div>
        {canManageRegionClubs ? (
          <div className="content-section-card__actions">
            <button type="button" className="admin-secondary-button" onClick={onCreateRegionClub}>
              <i className="fas fa-map-location-dot" aria-hidden="true"></i>
              Setup Region + Club
            </button>
          </div>
        ) : null}
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Governors</span>
          <strong>{filteredItems.length}</strong>
          <small>Governor records in view</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Regions</span>
          <strong>{uniqueRegionCount}</strong>
          <small>Distinct regions assigned</small>
        </article>
        <article className="content-summary-chip positive">
          <span>With Region</span>
          <strong>{governorsWithRegionCount}</strong>
          <small>Governors with region records</small>
        </article>
      </div>

      <div className="content-section-card__body">
        {!filteredItems.length ? (
          <div className="content-empty-state">
            <i className="fas fa-sitemap" aria-hidden="true"></i>
            <p>No governors found.</p>
          </div>
        ) : (
          <div className="governors-table-wrap">
            <table className="governors-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Governor</th>
                  <th>Region</th>
                  {canManageGovernors ? <th>Actions</th> : null}
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed governor')
                  const handledRegions = (
                    (Array.isArray(item?.regions) ? item.regions : [])
                      .map((regionItem) => String(regionItem?.name || regionItem?.region_name || '').trim())
                      .filter(Boolean)
                      .join(', ')
                  ) || 'No region assigned'
                  const photoUrl = String(item?.photoUrl || item?.imageUrl || '').trim()

                  return (
                    <tr key={item?.id || `governor-${pageStart + index}`}>
                      <td data-label="ID">{item?.id || 'N/A'}</td>
                      <td data-label="Governor">
                        <div className="governors-table__identity">
                          <Avatar name={name} src={photoUrl} />
                          <div className="governors-table__identity-copy">
                            <strong>{name}</strong>
                          </div>
                        </div>
                      </td>
                      <td data-label="Region">{handledRegions}</td>
                      {canManageGovernors ? (
                        <td data-label="Actions">
                          <div className="officers-table__actions">
                            {typeof onEditGovernor === 'function' ? (
                              <button
                                type="button"
                                className="admin-secondary-button officers-table__button"
                                onClick={() => onEditGovernor(item)}
                              >
                                <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                                Edit
                              </button>
                            ) : null}
                            {typeof onDeleteGovernor === 'function' ? (
                              <button
                                type="button"
                                className="admin-danger-button officers-table__button"
                                onClick={() => onDeleteGovernor(item)}
                              >
                                <i className="fas fa-trash" aria-hidden="true"></i>
                                Delete
                              </button>
                            ) : null}
                          </div>
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
                  Showing {displayStart}-{displayEnd} of {filteredItems.length}
                </p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--center">
                <p className="table-pagination__info">10 rows per page</p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--right table-pagination__actions">
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
        )}
      </div>
    </section>
  )
}

export function AppointedPage({
  items = [],
  query = '',
  loading = false,
  isSuperAdmin = false,
  onCreateAppointed,
  onEditAppointed,
  onDeleteAppointed,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedRegion, setSelectedRegion] = useState('all')
  const [selectedCommittee, setSelectedCommittee] = useState('all')
  const filteredItems = items.filter((item) => matchesQuery(item, query))
  const canManageAppointed = isSuperAdmin
    && (typeof onCreateAppointed === 'function' || typeof onEditAppointed === 'function' || typeof onDeleteAppointed === 'function')
  const regionOptions = Array.from(
    new Set(filteredItems.map((item) => String(item?.region || '').trim()).filter(Boolean)),
  ).sort((a, b) => a.localeCompare(b))
  const regionFilteredItems = selectedRegion === 'all'
    ? filteredItems
    : filteredItems.filter((item) => String(item?.region || '').trim() === selectedRegion)
  const committeeOptions = Array.from(
    new Set(
      regionFilteredItems
        .map((item) => String(item?.committee || item?.club || '').trim())
        .filter(Boolean),
    ),
  ).sort((a, b) => a.localeCompare(b))
  const regionCommitteeFilteredItems = selectedCommittee === 'all'
    ? regionFilteredItems
    : regionFilteredItems.filter((item) => {
      const committee = String(item?.committee || item?.club || '').trim()
      return committee === selectedCommittee
    })
  const pageSize = 10
  const totalPages = Math.max(1, Math.ceil(regionCommitteeFilteredItems.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const visibleItems = regionCommitteeFilteredItems.slice(pageStart, pageStart + pageSize)
  const displayStart = regionCommitteeFilteredItems.length ? pageStart + 1 : 0
  const displayEnd = regionCommitteeFilteredItems.length
    ? Math.min(pageStart + pageSize, regionCommitteeFilteredItems.length)
    : 0

  useEffect(() => {
    setSelectedRegion('all')
    setSelectedCommittee('all')
    setCurrentPage(1)
  }, [query])

  useEffect(() => {
    setSelectedCommittee('all')
    setCurrentPage(1)
  }, [selectedRegion])

  useEffect(() => {
    setCurrentPage((page) => (page > totalPages ? totalPages : page))
  }, [totalPages])

  if (loading) {
    return (
      <section className="content-section-card">
        <div className="content-section-card__header">
          <div>
            <p className="page-kicker">Leadership Directory</p>
            <h2>Appointed Officers</h2>
            <p>Loading appointed officer records...</p>
          </div>
          {canManageAppointed ? (
            <div className="content-section-card__actions">
              <Skeleton variant="rounded" width={188} height={40} />
            </div>
          ) : null}
        </div>

        <div className="content-summary-strip">
          <article className="content-summary-chip info">
            <span>Committees</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={108} /></small>
          </article>
          <article className="content-summary-chip warm">
            <span>Regions</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={118} /></small>
          </article>
          <article className="content-summary-chip positive">
            <span>Positions</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={120} /></small>
          </article>
        </div>

        <div className="content-section-card__body">
          <div className="members-toolbar members-toolbar--inline">
            <div className="members-toolbar__line">
              <Skeleton variant="rounded" width={164} height={44} />
              <Skeleton variant="rounded" width={164} height={44} />
              <p className="members-toolbar__info"><Skeleton variant="text" width={110} height={18} /></p>
            </div>
          </div>
          <LoadingRows />
        </div>
      </section>
    )
  }

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership Directory</p>
          <h2>Appointed Officers</h2>
          <p>{filteredItems.length} appointed officer record(s) available.</p>
        </div>
        {canManageAppointed ? (
          <div className="content-section-card__actions">
            <button type="button" className="admin-secondary-button" onClick={onCreateAppointed}>
              <i className="fas fa-user-plus" aria-hidden="true"></i>
              Add Appointed Officer
            </button>
          </div>
        ) : null}
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Committees</span>
          <strong>{countUnique(filteredItems, ['committee', 'club'])}</strong>
          <small>Committee assignments</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Regions</span>
          <strong>{countUnique(filteredItems, ['region'])}</strong>
          <small>Regional appointments</small>
        </article>
        <article className="content-summary-chip positive">
          <span>Positions</span>
          <strong>{countUnique(filteredItems, ['position', 'designation'])}</strong>
          <small>Appointment types</small>
        </article>
      </div>

      <div className="content-section-card__body">
        {!filteredItems.length ? (
          <div className="content-empty-state">
            <i className="fas fa-sitemap" aria-hidden="true"></i>
            <p>No appointed officers found.</p>
          </div>
        ) : (
          <>
            <div className="members-toolbar members-toolbar--inline">
              <div className="members-toolbar__line">
                <div className="table-select">
                  <label htmlFor="appointed-region-filter">Region</label>
                  <select
                    id="appointed-region-filter"
                    value={selectedRegion}
                    onChange={(event) => setSelectedRegion(event.target.value)}
                  >
                    <option value="all">All regions</option>
                    {regionOptions.map((region) => (
                      <option key={region} value={region}>
                        {region}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="table-select">
                  <label htmlFor="appointed-committee-filter">Committee</label>
                  <select
                    id="appointed-committee-filter"
                    value={selectedCommittee}
                    onChange={(event) => setSelectedCommittee(event.target.value)}
                  >
                    <option value="all">All committees</option>
                    {committeeOptions.map((committee) => (
                      <option key={committee} value={committee}>
                        {committee}
                      </option>
                    ))}
                  </select>
                </div>
                <p className="members-toolbar__info">{regionCommitteeFilteredItems.length} record(s)</p>
              </div>
            </div>

            {!regionCommitteeFilteredItems.length ? (
              <div className="content-empty-state">
                <i className="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                <p>No appointed officers match your dropdown filters.</p>
              </div>
            ) : (
              <div className="appointed-table-wrap">
                <table className="appointed-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Position</th>
                      <th>Committee</th>
                      <th>Region</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visibleItems.map((item, index) => {
                      const name = personName(item, 'Unnamed appointed officer')
                      const position = item?.position || item?.designation || 'No position available.'
                      const committee = item?.committee || item?.club || 'Committee not set'
                      const region = item?.region || 'Region not set'
                      const photoUrl = String(item?.photoUrl || item?.imageUrl || '').trim()

                      return (
                        <tr key={item?.id || `appointed-${pageStart + index}`}>
                          <td data-label="Name">
                            <div className="appointed-table__identity">
                              <Avatar name={name} src={photoUrl} />
                              <div className="appointed-table__identity-copy">
                                <strong>{name}</strong>
                              </div>
                            </div>
                          </td>
                          <td data-label="Position">
                            <p className="appointed-table__position">{position}</p>
                          </td>
                          <td data-label="Committee">{committee}</td>
                          <td data-label="Region">{region}</td>
                          <td data-label="Action">
                            <div className="officers-table__actions">
                              {canManageAppointed && typeof onEditAppointed === 'function' ? (
                                <button
                                  type="button"
                                  className="admin-secondary-button officers-table__button"
                                  onClick={() => onEditAppointed(item)}
                                >
                                  <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                                  Edit
                                </button>
                              ) : null}
                              {canManageAppointed && typeof onDeleteAppointed === 'function' ? (
                                <button
                                  type="button"
                                  className="admin-danger-button officers-table__button"
                                  onClick={() => onDeleteAppointed(item)}
                                >
                                  <i className="fas fa-trash" aria-hidden="true"></i>
                                  Delete
                                </button>
                              ) : null}
                              {!canManageAppointed ? <span>View only</span> : null}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>

                <div className="table-pagination">
                  <div className="table-pagination__slot table-pagination__slot--left">
                    <p className="table-pagination__info">
                      Showing {displayStart}-{displayEnd} of {regionCommitteeFilteredItems.length}
                    </p>
                  </div>
                  <div className="table-pagination__slot table-pagination__slot--center">
                    <p className="table-pagination__info">10 rows per page</p>
                  </div>
                  <div className="table-pagination__slot table-pagination__slot--right table-pagination__actions">
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
            )}
          </>
        )}
      </div>
    </section>
  )
}

export function PastLeadersPage({
  items = [],
  query = '',
  loading = false,
  isSuperAdmin = false,
  onCreatePastLeader,
  onEditPastLeader,
  onDeletePastLeader,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const filteredItems = items.filter((item) => matchesQuery(item, query))
  const canManagePastLeaders = isSuperAdmin
    && (typeof onCreatePastLeader === 'function' || typeof onEditPastLeader === 'function' || typeof onDeletePastLeader === 'function')
  const pageSize = 10
  const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const visibleItems = filteredItems.slice(pageStart, pageStart + pageSize)
  const displayStart = filteredItems.length ? pageStart + 1 : 0
  const displayEnd = filteredItems.length ? Math.min(pageStart + pageSize, filteredItems.length) : 0
  const activeCount = filteredItems.filter((item) => (Number(item?.is_active ?? item?.isActive ?? 1) || 0) === 1).length
  const archivedCount = Math.max(0, filteredItems.length - activeCount)

  useEffect(() => {
    setCurrentPage(1)
  }, [query])

  useEffect(() => {
    setCurrentPage((page) => (page > totalPages ? totalPages : page))
  }, [totalPages])

  if (loading) {
    return (
      <section className="content-section-card">
        <div className="content-section-card__header">
          <div>
            <p className="page-kicker">Leadership History</p>
            <h2>Past Leaders</h2>
            <p>Loading past leaders records...</p>
          </div>
          {canManagePastLeaders ? (
            <div className="content-section-card__actions">
              <Skeleton variant="rounded" width={180} height={40} />
            </div>
          ) : null}
        </div>

        <div className="content-summary-strip">
          <article className="content-summary-chip info">
            <span>Leaders</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={126} /></small>
          </article>
          <article className="content-summary-chip positive">
            <span>Active</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={98} /></small>
          </article>
          <article className="content-summary-chip warm">
            <span>Archived</span>
            <strong><LoadingSummaryValue /></strong>
            <small><LoadingSummaryHelper width={108} /></small>
          </article>
        </div>

        <div className="content-section-card__body">
          <LoadingRows />
        </div>
      </section>
    )
  }

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership History</p>
          <h2>Past Leaders</h2>
          <p>{filteredItems.length} past leader record(s) available.</p>
        </div>
        {canManagePastLeaders ? (
          <div className="content-section-card__actions">
            <button type="button" className="admin-secondary-button" onClick={onCreatePastLeader}>
              <i className="fas fa-user-plus" aria-hidden="true"></i>
              Add Past Leader
            </button>
          </div>
        ) : null}
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Leaders</span>
          <strong>{filteredItems.length}</strong>
          <small>Historical records in view</small>
        </article>
        <article className="content-summary-chip positive">
          <span>Active</span>
          <strong>{activeCount}</strong>
          <small>Shown on client leadership page</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Archived</span>
          <strong>{archivedCount}</strong>
          <small>Soft-deleted history entries</small>
        </article>
      </div>

      <div className="content-section-card__body">
        {!filteredItems.length ? (
          <div className="content-empty-state">
            <i className="fas fa-landmark" aria-hidden="true"></i>
            <p>No past leaders found.</p>
          </div>
        ) : (
          <div className="officers-table-wrap">
            <table className="officers-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Position</th>
                  <th>Term</th>
                  <th>Achievements</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed past leader')
                  const position = String(item?.position || 'Past Leader').trim()
                  const termStart = String(item?.termStart || item?.term_start || '').trim()
                  const termEnd = String(item?.termEnd || item?.term_end || '').trim()
                  const term = [termStart, termEnd].filter(Boolean).join(' - ') || 'N/A'
                  const achievements = String(item?.achievements || '').trim() || 'No achievements listed.'
                  const priority = Number(item?.orderPriority ?? item?.order_priority ?? 0) || 0
                  const isActive = (Number(item?.is_active ?? item?.isActive ?? 1) || 0) === 1
                  const photoUrl = String(item?.photoUrl || item?.imageUrl || '').trim()

                  return (
                    <tr key={item?.id || `past-leader-${pageStart + index}`}>
                      <td data-label="Name">
                        <div className="officers-table__identity">
                          <Avatar name={name} src={photoUrl} />
                          <div className="officers-table__identity-copy">
                            <strong>{name}</strong>
                          </div>
                        </div>
                      </td>
                      <td data-label="Position">
                        <p className="officers-table__position">{position}</p>
                      </td>
                      <td data-label="Term">{term}</td>
                      <td data-label="Achievements">
                        <p className="officers-table__position">{achievements}</p>
                      </td>
                      <td data-label="Priority">{priority}</td>
                      <td data-label="Status">
                        <span className={`member-status-badge ${isActive ? 'positive' : 'danger'}`}>
                          {isActive ? 'ACTIVE' : 'ARCHIVED'}
                        </span>
                      </td>
                      <td data-label="Action">
                        <div className="officers-table__actions">
                          {canManagePastLeaders && typeof onEditPastLeader === 'function' ? (
                            <button
                              type="button"
                              className="admin-secondary-button officers-table__button"
                              onClick={() => onEditPastLeader(item)}
                            >
                              <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                              Edit
                            </button>
                          ) : null}
                          {canManagePastLeaders && typeof onDeletePastLeader === 'function' ? (
                            <button
                              type="button"
                              className="admin-danger-button officers-table__button"
                              onClick={() => onDeletePastLeader(item)}
                            >
                              <i className="fas fa-trash" aria-hidden="true"></i>
                              Delete
                            </button>
                          ) : null}
                          {!canManagePastLeaders ? <span>View only</span> : null}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>

            <div className="table-pagination">
              <div className="table-pagination__slot table-pagination__slot--left">
                <p className="table-pagination__info">
                  Showing {displayStart}-{displayEnd} of {filteredItems.length}
                </p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--center">
                <p className="table-pagination__info">10 rows per page</p>
              </div>
              <div className="table-pagination__slot table-pagination__slot--right table-pagination__actions">
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
        )}
      </div>
    </section>
  )
}
