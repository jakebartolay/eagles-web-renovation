import { useEffect, useState } from 'react'

function formatDate(value) {
  if (!value) return 'No timestamp'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString()
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

function countByKeyword(items, keyword) {
  return items.filter((item) => String(item?.actionType || '').toLowerCase().includes(keyword)).length
}

export default function ActivityPage({ dashboard, user, query }) {
  const [currentPage, setCurrentPage] = useState(1)
  const activities = Array.isArray(dashboard?.activity) ? dashboard.activity : []
  const isSuperAdmin = Number(user?.roleId || user?.role_id || 0) === 1
  const currentUsername = String(user?.username || '').trim().toLowerCase()
  const ownActivities = isSuperAdmin
    ? activities
    : activities.filter((item) => (
      String(item?.adminUsername || '').trim().toLowerCase() === currentUsername
    ))

  const filtered = ownActivities.filter((item) => {
    if (!query) return true
    return JSON.stringify(item).toLowerCase().includes(query.toLowerCase())
  })
  const pageSize = 10
  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const visibleItems = filtered.slice(pageStart, pageStart + pageSize)
  const displayStart = filtered.length ? pageStart + 1 : 0
  const displayEnd = filtered.length ? Math.min(pageStart + pageSize, filtered.length) : 0

  useEffect(() => {
    setCurrentPage(1)
  }, [query, currentUsername])

  useEffect(() => {
    setCurrentPage((page) => (page > totalPages ? totalPages : page))
  }, [totalPages])

  return (
    <section className="activity-page">
      <section className="content-section-card">
        <div className="content-section-card__header">
          <div>
            <p className="page-kicker">Audit Trail</p>
            <h2>{isSuperAdmin ? 'All Activity Logs' : 'My Activity Logs'}</h2>
            <p>
              {isSuperAdmin
                ? `${filtered.length} action record(s) across all admin accounts.`
                : `${filtered.length} action record(s) for your current account.`}
            </p>
          </div>
        </div>

        <div className="content-summary-strip">
          <article className="content-summary-chip positive">
            <span>Created</span>
            <strong>{countByKeyword(filtered, 'create')}</strong>
            <small>New records or submissions</small>
          </article>

          <article className="content-summary-chip info">
            <span>Updated</span>
            <strong>{countByKeyword(filtered, 'update')}</strong>
            <small>Edited content and changes</small>
          </article>

          <article className="content-summary-chip danger">
            <span>Deleted</span>
            <strong>{countByKeyword(filtered, 'delete')}</strong>
            <small>Removed records</small>
          </article>
        </div>

        <div className="content-section-card__body">
          {filtered.length === 0 ? (
            <div className="content-empty-state">
              <i className="fas fa-clock-rotate-left" aria-hidden="true"></i>
              <p>{isSuperAdmin ? 'No activity logs found.' : 'No activity logs found for your account.'}</p>
            </div>
          ) : (
            <div className="users-table-wrap activity-table-wrap">
              <table className="users-table activity-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {visibleItems.map((item, index) => {
                    const tone = actionTone(item?.actionType)
                    const rowNumber = pageStart + index + 1

                    return (
                      <tr key={`${item?.createdAt || 'activity'}-${rowNumber}`}>
                        <td data-label="#">{rowNumber}</td>
                        <td data-label="Name">{item?.adminUsername || user?.username || 'Unknown admin'}</td>
                        <td data-label="Action">
                          <span className={`activity-card__type ${tone}`}>
                            <i className={`fas ${actionIcon(item?.actionType)}`} aria-hidden="true"></i>
                            {' '}
                            {item?.actionType || 'Action'}
                          </span>
                        </td>
                        <td data-label="Description">
                          <p className="news-table__summary">{item?.description || 'No description available.'}</p>
                        </td>
                        <td data-label="IP">{item?.ipAddress || 'N/A'}</td>
                        <td data-label="Date">{formatDate(item?.createdAt)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>

              <div className="table-pagination">
                <div className="table-pagination__slot table-pagination__slot--left">
                  <p className="table-pagination__info">
                    Showing {displayStart}-{displayEnd} of {filtered.length}
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
    </section>
  )
}
