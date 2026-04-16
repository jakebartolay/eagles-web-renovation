import { useEffect, useState } from 'react'

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

export function OfficersPage({
  items = [],
  query = '',
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
          <span>Clubs</span>
          <strong>{countUnique(filteredItems, ['club'])}</strong>
          <small>Represented club groups</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Regions</span>
          <strong>{countUnique(filteredItems, ['region'])}</strong>
          <small>Regional coverage</small>
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
                  <th>Club</th>
                  <th>Region</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed officer')
                  const position = item?.position || item?.designation || 'No position available.'
                  const club = item?.club || 'Club not set'
                  const region = item?.region || 'Region not set'
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
                      <td data-label="Club">{club}</td>
                      <td data-label="Region">{region}</td>
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

export function GovernorsPage({ items = [], query = '' }) {
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

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership Directory</p>
          <h2>Governors</h2>
          <p>{filteredItems.length} governor record(s) currently tracked.</p>
        </div>
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Districts</span>
          <strong>{countUnique(filteredItems, ['district', 'region'])}</strong>
          <small>Coverage areas listed</small>
        </article>
        <article className="content-summary-chip warm">
          <span>Assignments</span>
          <strong>{countUnique(filteredItems, ['position', 'designation', 'area'])}</strong>
          <small>Role variations</small>
        </article>
        <article className="content-summary-chip positive">
          <span>Updated</span>
          <strong>{filteredItems.filter((item) => item?.updated_at || item?.created_at).length}</strong>
          <small>Entries with timestamps</small>
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
                  <th>Assignment</th>
                  <th>District / Region</th>
                  <th>Updated</th>
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed governor')
                  const assignment = item?.position || item?.designation || item?.area || 'No designation available.'
                  const district = item?.region || item?.district || 'District not set'
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
                      <td data-label="Assignment">
                        <p className="governors-table__position">{assignment}</p>
                      </td>
                      <td data-label="District / Region">{district}</td>
                      <td data-label="Updated">{formatDate(item?.updatedAt || item?.updated_at || item?.createdAt || item?.created_at)}</td>
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

export function AppointedPage({ items = [], query = '' }) {
  const [selectedRegion, setSelectedRegion] = useState('all')
  const [selectedCommittee, setSelectedCommittee] = useState('all')
  const filteredItems = items.filter((item) => matchesQuery(item, query))
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
  const visibleItems = selectedCommittee === 'all'
    ? regionFilteredItems
    : regionFilteredItems.filter((item) => {
      const committee = String(item?.committee || item?.club || '').trim()
      return committee === selectedCommittee
    })

  useEffect(() => {
    setSelectedRegion('all')
    setSelectedCommittee('all')
  }, [query])

  useEffect(() => {
    setSelectedCommittee('all')
  }, [selectedRegion])

  return (
    <section className="content-section-card">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Leadership Directory</p>
          <h2>Appointed Officers</h2>
          <p>{filteredItems.length} appointed officer record(s) available.</p>
        </div>
      </div>

      <div className="content-summary-strip">
        <article className="content-summary-chip info">
          <span>Clubs</span>
          <strong>{countUnique(filteredItems, ['club'])}</strong>
          <small>Teams represented</small>
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
                <p className="members-toolbar__info">{visibleItems.length} record(s)</p>
              </div>
            </div>

            {!visibleItems.length ? (
              <div className="content-empty-state">
                <i className="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                <p>No appointed officers match your dropdown filters.</p>
              </div>
            ) : (
              <div className="content-grid">
                {visibleItems.map((item, index) => {
                  const name = personName(item, 'Unnamed appointed officer')
                  const position = item?.position || item?.designation || 'No position available.'
                  const club = item?.club || item?.committee || 'Club not set'
                  const region = item?.region || 'Region not set'
                  const photoUrl = String(item?.photoUrl || item?.imageUrl || '').trim()

                  return (
                    <article key={item?.id || `appointed-${index}`} className="content-item-card entity-card leadership-card">
                      <div className="entity-card__header">
                        <Avatar name={name} src={photoUrl} />
                        <div className="entity-card__heading">
                          <strong>{name}</strong>
                          <small>{position}</small>
                        </div>
                      </div>
                      <div className="content-item-tags">
                        <DetailPill icon="fa-users">Club: {club}</DetailPill>
                        <DetailPill icon="fa-location-dot">Region: {region}</DetailPill>
                        <DetailPill icon="fa-clock">Updated: {formatDate(item?.updatedAt || item?.updated_at || item?.createdAt || item?.created_at)}</DetailPill>
                      </div>
                    </article>
                  )
                })}
              </div>
            )}
          </>
        )}
      </div>
    </section>
  )
}
