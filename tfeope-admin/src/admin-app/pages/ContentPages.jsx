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

function formatDateOnly(value) {
  if (!value) return 'Not available'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString()
}

function countByStatus(items, keyword) {
  return items.filter((item) => String(item?.status || '').toLowerCase().includes(keyword)).length
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

function EmptyState({ label, icon = 'fa-box-open' }) {
  return (
    <div className="content-empty-state">
      <i className={`fas ${icon}`} aria-hidden="true"></i>
      <p>No {label} found.</p>
    </div>
  )
}

function StatusBadge({ value }) {
  const text = String(value || 'Unknown')
  const normalized = text.toLowerCase()
  const className = normalized.includes('publish')
    ? 'published'
    : normalized.includes('draft')
      ? 'draft'
      : normalized.includes('upcoming')
        ? 'upcoming'
        : 'default'

  return <span className={`content-status-badge ${className}`}>{text}</span>
}

function CardMedia({ src, alt, icon = 'fa-image' }) {
  if (!src) {
    return (
      <div className="content-item-image-placeholder">
        <i className={`fas ${icon}`} aria-hidden="true"></i>
      </div>
    )
  }

  return <img src={src} alt={alt} className="content-item-image" />
}

function DetailTag({ icon, children }) {
  return (
    <span className="content-item-tag">
      <i className={`fas ${icon}`} aria-hidden="true"></i>
      {children}
    </span>
  )
}

function SimpleGrid({ items, renderItem, emptyLabel, emptyIcon }) {
  if (!items.length) {
    return <EmptyState label={emptyLabel} icon={emptyIcon} />
  }

  return (
    <div className="content-grid">
      {items.map((item, index) => (
        <article key={item?.id || `${emptyLabel}-${index}`} className="content-item-card">
          {renderItem(item, index)}
        </article>
      ))}
    </div>
  )
}

export function NewsPage({
  items = [],
  query = '',
  onCreateNews,
  onEditNews,
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
    <SectionCard
      eyebrow="Editorial Queue"
      title="News"
      subtitle={`${filteredItems.length} news item(s) ready for review and publishing.`}
      metrics={[
        {
          label: 'Published',
          value: countByStatus(filteredItems, 'publish'),
          helper: 'Live stories',
          tone: 'positive',
        },
        {
          label: 'Drafts',
          value: countByStatus(filteredItems, 'draft'),
          helper: 'Waiting for edits',
          tone: 'warm',
        },
        {
          label: 'With media',
          value: filteredItems.filter((item) => item?.imageUrl || Array.isArray(item?.media)).length,
          helper: 'Cards with visual cover',
          tone: 'info',
        },
      ]}
      actions={(
        <button type="button" className="admin-primary-button" onClick={onCreateNews}>
          <i className="fas fa-plus" aria-hidden="true"></i>
          Create News
        </button>
      )}
    >
      {!filteredItems.length ? (
        <EmptyState label="news" icon="fa-newspaper" />
      ) : (
        <div className="news-table-wrap">
          <table className="news-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>News</th>
                <th>Summary</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Author</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {visibleItems.map((item, index) => {
                const mediaItems = Array.isArray(item?.media) ? item.media : []
                const image = item?.imageUrl || mediaItems.find((media) =>
                  String(media?.fileType || '').toLowerCase().includes('image'),
                )?.url
                const title = item?.title || 'Untitled news'
                const summary = item?.content || item?.description || 'No content available.'
                const author = item?.author || 'Editorial post'

                return (
                  <tr key={item?.id || `news-${pageStart + index}`}>
                    <td data-label="ID">{item?.id || 'N/A'}</td>
                    <td data-label="News">
                      <div className="news-table__identity">
                        {image ? (
                          <img src={image} alt={title} className="news-table__thumb" />
                        ) : (
                          <span className="news-table__thumb news-table__thumb--fallback">
                            <i className="fas fa-newspaper" aria-hidden="true"></i>
                          </span>
                        )}
                        <div className="news-table__identity-copy">
                          <strong>{title}</strong>
                        </div>
                      </div>
                    </td>
                    <td data-label="Summary">
                      <p className="news-table__summary">{summary}</p>
                    </td>
                    <td data-label="Status">
                      <StatusBadge value={item?.status || 'Draft'} />
                    </td>
                    <td data-label="Updated">{formatDate(item?.updated_at || item?.created_at)}</td>
                    <td data-label="Author">
                      <span className="news-table__author">{author}</span>
                    </td>
                    <td data-label="Actions">
                      <div className="news-table__actions">
                        <button
                          type="button"
                          className="admin-secondary-button news-table__button"
                          onClick={() => onEditNews?.(item)}
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
    </SectionCard>
  )
}

export function VideosPage({
  items = [],
  query = '',
  onCreateVideo,
  onEditVideo,
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
    <SectionCard
      eyebrow="Media Library"
      title="Videos"
      subtitle={`${filteredItems.length} video item(s) in the admin content library.`}
      metrics={[
        {
          label: 'Published',
          value: countByStatus(filteredItems, 'publish') || filteredItems.length,
          helper: 'Visible entries',
          tone: 'positive',
        },
        {
          label: 'Thumbnails',
          value: filteredItems.filter((item) => item?.thumbnailUrl || item?.imageUrl).length,
          helper: 'Cards with preview images',
          tone: 'info',
        },
        {
          label: 'Linked',
          value: filteredItems.filter((item) => item?.url).length,
          helper: 'External video links',
          tone: 'warm',
        },
      ]}
      actions={(
        <button type="button" className="admin-primary-button" onClick={onCreateVideo}>
          <i className="fas fa-video" aria-hidden="true"></i>
          Upload Video
        </button>
      )}
    >
      {!filteredItems.length ? (
        <EmptyState label="videos" icon="fa-video" />
      ) : (
        <div className="videos-table-wrap">
          <table className="videos-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Video</th>
                <th>Description</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {visibleItems.map((item, index) => {
                const thumbnail = String(item?.thumbnailUrl || item?.imageUrl || '').trim()
                const title = item?.title || 'Untitled video'
                const description = item?.description || item?.content || 'No description available.'

                return (
                  <tr key={item?.id || `video-${pageStart + index}`}>
                    <td data-label="ID">{item?.id || 'N/A'}</td>
                    <td data-label="Video">
                      <div className="videos-table__identity">
                        {thumbnail ? (
                          <img src={thumbnail} alt={title} className="videos-table__thumb" />
                        ) : (
                          <span className="videos-table__thumb videos-table__thumb--fallback">
                            <i className="fas fa-video" aria-hidden="true"></i>
                          </span>
                        )}
                        <div className="videos-table__identity-copy">
                          <strong>{title}</strong>
                        </div>
                      </div>
                    </td>
                    <td data-label="Description">
                      <p className="videos-table__description">{description}</p>
                    </td>
                    <td data-label="Status">
                      <StatusBadge value={item?.status || 'Published'} />
                    </td>
                    <td data-label="Uploaded">{formatDateOnly(item?.createdAt || item?.created_at)}</td>
                    <td data-label="Actions">
                      <div className="videos-table__actions">
                        {item?.videoUrl ? (
                          <a className="admin-secondary-button videos-table__button" href={item.videoUrl} target="_blank" rel="noreferrer">
                            <i className="fas fa-play" aria-hidden="true"></i>
                            Open
                          </a>
                        ) : null}
                        <button
                          type="button"
                          className="admin-secondary-button videos-table__button"
                          onClick={() => onEditVideo?.(item)}
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
    </SectionCard>
  )
}

export function EventsPage({
  items = [],
  query = '',
  onCreateEvent,
  onEditEvent,
  onDeleteEvent,
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
    <SectionCard
      eyebrow="Schedule Desk"
      title="Events"
      subtitle={`${filteredItems.length} event item(s) currently tracked.`}
      metrics={[
        {
          label: 'Upcoming',
          value: filteredItems.filter((item) => String(item?.type || '').toLowerCase() === 'upcoming').length,
          helper: 'Scheduled events',
          tone: 'positive',
        },
        {
          label: 'Past',
          value: filteredItems.filter((item) => String(item?.type || '').toLowerCase() === 'past').length,
          helper: 'Archived events',
          tone: 'info',
        },
        {
          label: 'With media',
          value: filteredItems.filter((item) => String(item?.mediaUrl || '').trim() !== '').length,
          helper: 'Events with attachment',
          tone: 'warm',
        },
      ]}
      actions={(
        <button type="button" className="admin-primary-button" onClick={onCreateEvent}>
          <i className="fas fa-calendar-plus" aria-hidden="true"></i>
          Create Event
        </button>
      )}
    >
      {!filteredItems.length ? (
        <EmptyState label="events" icon="fa-calendar-days" />
      ) : (
        <div className="events-table-wrap">
          <table className="events-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Event</th>
                <th>Details</th>
                <th>Type</th>
                <th>Date</th>
                <th>Media</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {visibleItems.map((item, index) => {
                const title = item?.title || item?.name || 'Untitled event'
                const description = item?.description || item?.content || 'No details available.'
                const type = String(item?.type || 'upcoming').trim().toLowerCase() === 'past'
                  ? 'Past'
                  : 'Upcoming'
                const mediaUrl = String(item?.mediaUrl || '').trim()
                const mediaType = String(item?.mediaType || '').trim().toLowerCase()
                const showPreview = mediaUrl !== '' && mediaType.startsWith('image')

                return (
                  <tr key={item?.id || `event-${pageStart + index}`}>
                    <td data-label="ID">{item?.id || 'N/A'}</td>
                    <td data-label="Event">
                      <div className="events-table__identity">
                        {showPreview ? (
                          <img src={mediaUrl} alt={title} className="events-table__thumb" />
                        ) : (
                          <span className="events-table__thumb events-table__thumb--fallback">
                            <i className="fas fa-calendar-days" aria-hidden="true"></i>
                          </span>
                        )}
                        <div className="events-table__identity-copy">
                          <strong>{title}</strong>
                        </div>
                      </div>
                    </td>
                    <td data-label="Details">
                      <p className="events-table__description">{description}</p>
                    </td>
                    <td data-label="Type">
                      <StatusBadge value={type} />
                    </td>
                    <td data-label="Date">{formatDateOnly(item?.date || item?.event_date || item?.createdAt)}</td>
                    <td data-label="Media">
                      {mediaUrl ? (
                        <a
                          className="admin-secondary-button events-table__button"
                          href={mediaUrl}
                          target="_blank"
                          rel="noreferrer"
                        >
                          <i className="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                          Open
                        </a>
                      ) : (
                        <span className="events-table__media-empty">No media</span>
                      )}
                    </td>
                    <td data-label="Actions">
                      <div className="events-table__actions">
                        <button
                          type="button"
                          className="admin-secondary-button events-table__button"
                          onClick={() => onEditEvent?.(item)}
                        >
                          <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                          Edit
                        </button>
                        <button
                          type="button"
                          className="admin-danger-button events-table__button"
                          onClick={() => onDeleteEvent?.(item)}
                        >
                          <i className="fas fa-trash-can" aria-hidden="true"></i>
                          Delete
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
    </SectionCard>
  )
}

export function MemorandumPage({
  items = [],
  query = '',
  onCreateMemorandum,
  onEditMemorandum,
  onDeleteMemorandum,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const filteredItems = items.filter((item) => matchesQuery(item, query))
  const pageCount = filteredItems.reduce((total, item) => (
    total + (Array.isArray(item?.pages) ? item.pages.length : 0)
  ), 0)
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
    <SectionCard
      eyebrow="Document Center"
      title="Memorandum"
      subtitle={`${filteredItems.length} memorandum item(s) ready for management.`}
      metrics={[
        {
          label: 'Published',
          value: countByStatus(filteredItems, 'publish'),
          helper: 'Live memorandums',
          tone: 'positive',
        },
        {
          label: 'Drafts',
          value: countByStatus(filteredItems, 'draft'),
          helper: 'Still in review',
          tone: 'warm',
        },
        {
          label: 'Uploaded pages',
          value: pageCount,
          helper: 'Attached files total',
          tone: 'info',
        },
      ]}
      actions={(
        <button
          type="button"
          className="admin-primary-button"
          onClick={onCreateMemorandum}
        >
          <i className="fas fa-plus" aria-hidden="true"></i>
          Create Memorandum
        </button>
      )}
    >
      {!filteredItems.length ? (
        <EmptyState label="memorandums" icon="fa-file-lines" />
      ) : (
        <div className="memorandum-table-wrap">
          <table className="memorandum-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Memorandum</th>
                <th>Description</th>
                <th>Status</th>
                <th>Pages</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {visibleItems.map((item, index) => {
                const title = item?.title || 'Untitled memorandum'
                const description = item?.description || 'No description available.'
                const pages = Array.isArray(item?.pages) ? item.pages.length : 0
                const coverUrl = String(item?.coverUrl || item?.pages?.[0]?.url || '').trim()

                return (
                  <tr key={item?.id || `memo-${pageStart + index}`}>
                    <td data-label="ID">{item?.id || 'N/A'}</td>
                    <td data-label="Memorandum">
                      <div className="memorandum-table__identity">
                        {coverUrl ? (
                          <img src={coverUrl} alt={title} className="memorandum-table__thumb" />
                        ) : (
                          <span className="memorandum-table__thumb memorandum-table__thumb--fallback">
                            <i className="fas fa-file-lines" aria-hidden="true"></i>
                          </span>
                        )}
                        <div className="memorandum-table__identity-copy">
                          <strong>{title}</strong>
                        </div>
                      </div>
                    </td>
                    <td data-label="Description">
                      <p className="memorandum-table__description">{description}</p>
                    </td>
                    <td data-label="Status">
                      <StatusBadge value={item?.status || 'Draft'} />
                    </td>
                    <td data-label="Pages">
                      <span className="memorandum-table__pages">{pages} page(s)</span>
                    </td>
                    <td data-label="Updated">{formatDateOnly(item?.updated_at || item?.created_at)}</td>
                    <td data-label="Actions">
                      <div className="memorandum-table__actions">
                        <button
                          type="button"
                          className="admin-secondary-button memorandum-table__button"
                          onClick={() => onEditMemorandum?.(item)}
                        >
                          <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                          Edit
                        </button>
                        <button
                          type="button"
                          className="admin-danger-button memorandum-table__button"
                          onClick={() => onDeleteMemorandum?.(item)}
                        >
                          <i className="fas fa-trash-can" aria-hidden="true"></i>
                          Delete
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
    </SectionCard>
  )
}

export function MagnaCartaPage({ items = [], query = '' }) {
  const filteredItems = items.filter((item) => matchesQuery(item, query))

  return (
    <SectionCard
      eyebrow="Policy Reference"
      title="Magna Carta"
      subtitle={`${filteredItems.length} policy item(s) available for quick reference.`}
      metrics={[
        {
          label: 'Entries',
          value: filteredItems.length,
          helper: 'Searchable records',
          tone: 'info',
        },
        {
          label: 'With descriptions',
          value: filteredItems.filter((item) => item?.description || item?.content).length,
          helper: 'Documented policy notes',
          tone: 'positive',
        },
        {
          label: 'Structured titles',
          value: filteredItems.filter((item) => item?.title || item?.heading).length,
          helper: 'Named content blocks',
          tone: 'warm',
        },
      ]}
    >
      <SimpleGrid
        items={filteredItems}
        emptyLabel="Magna Carta items"
        emptyIcon="fa-book"
        renderItem={(item) => (
          <>
            <div className="content-item-topline">
              <StatusBadge value="Reference" />
            </div>

            <h3>{item?.title || item?.heading || 'Untitled item'}</h3>
            <p className="content-item-text">
              {item?.description || item?.content || 'No details available.'}
            </p>

            <div className="content-item-tags">
              <DetailTag icon="fa-book-open-reader">Policy reference</DetailTag>
              <DetailTag icon="fa-layer-group">Static content block</DetailTag>
            </div>
          </>
        )}
      />
    </SectionCard>
  )
}
