import { useEffect, useMemo, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import useStylesheet from '../hooks/useStylesheet'
import { fetchApiJson } from '../lib/api'
import { PUBLIC_APPOINTED_ENDPOINT } from '../config'
import appointedStylesheetUrl from '../theme/appt_ofc.css?url'

function slugify(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    || 'region'
}

export default function AppointedOfficers() {
  const [regions, setRegions] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [openRegions, setOpenRegions] = useState({})
  const [filters, setFilters] = useState({})

  useStylesheet(appointedStylesheetUrl)
  useBodyClass('appt-body')

  useEffect(() => {
    let cancelled = false

    async function loadAppointments() {
      try {
        setLoading(true)
        setMessage('')

        const payload = await fetchApiJson(PUBLIC_APPOINTED_ENDPOINT)
        if (cancelled) {
          return
        }

        const nextRegions = Array.isArray(payload.data) ? payload.data : []
        setRegions(nextRegions)
        setOpenRegions(
          nextRegions.reduce((accumulator, region, index) => {
            accumulator[region.id || slugify(region.name)] = index === 0
            return accumulator
          }, {}),
        )
        setFilters(
          nextRegions.reduce((accumulator, region) => {
            accumulator[region.id || slugify(region.name)] = 'all'
            return accumulator
          }, {}),
        )
      } catch (error) {
        if (!cancelled) {
          setMessage(error.message || 'Unable to load appointed officers right now.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadAppointments()

    return () => {
      cancelled = true
    }
  }, [])

  const regionCards = useMemo(
    () => regions.map((region) => {
      const regionId = region.id || slugify(region.name)
      const selectedCommittee = filters[regionId] || 'all'
      const flattenedRows = (region.committees || []).flatMap((committee) => {
        const committeeId = committee.id || slugify(committee.name)
        return (committee.officers || []).map((officer) => ({
          ...officer,
          committeeId,
        }))
      })

      const visibleRows = flattenedRows.filter((row) => (
        selectedCommittee === 'all' || row.committeeId === selectedCommittee
      ))

      return {
        ...region,
        regionId,
        selectedCommittee,
        visibleRows,
      }
    }),
    [filters, regions],
  )

  function toggleRegion(regionId) {
    setOpenRegions((current) => ({
      ...current,
      [regionId]: !current[regionId],
    }))
  }

  function handleFilterChange(regionId, value) {
    setFilters((current) => ({
      ...current,
      [regionId]: value,
    }))
  }

  return (
    <PublicShell>
      <div className="appt-shell">
        <main className="appt-main">
          <div className="appt-wrap">
            {loading ? (
              <section className="appt-card">
                <div className="appt-card-head">
                  <div className="appt-text">
                    <div className="appt-kicker">Appointed Officers</div>
                    <h1 className="appt-title">Loading regions...</h1>
                    <p className="appt-desc">Please wait while we load the appointed officers list.</p>
                  </div>
                </div>
              </section>
            ) : null}

            {!loading && message ? (
              <section className="appt-card">
                <div className="appt-card-head">
                  <div className="appt-text">
                    <div className="appt-kicker">Appointed Officers</div>
                    <h1 className="appt-title">Unable to load data</h1>
                    <p className="appt-desc">{message}</p>
                  </div>
                </div>
              </section>
            ) : null}

            {!loading && !message && regionCards.length === 0 ? (
              <section className="appt-card">
                <div className="appt-card-head">
                  <div className="appt-text">
                    <div className="appt-kicker">Appointed Officers</div>
                    <h1 className="appt-title">No appointed officers found</h1>
                    <p className="appt-desc">There are no appointed officers available yet.</p>
                  </div>
                </div>
              </section>
            ) : null}

            {!loading && !message ? regionCards.map((region) => {
              const isOpen = Boolean(openRegions[region.regionId])

              return (
                <section className="appt-card" id={`${region.regionId}Card`} key={region.regionId}>
                  <div className="appt-card-head">
                    <div className="appt-text">
                      <div className="appt-kicker">Appointed Officers</div>
                      <h1 className="appt-title">{region.name}</h1>
                      <p className="appt-desc">
                        View the list of appointed officers and committee members for the {region.name} region.
                      </p>
                    </div>

                    <button
                      className="appt-toggle"
                      type="button"
                      aria-expanded={isOpen}
                      aria-controls={`${region.regionId}Drop`}
                      onClick={() => toggleRegion(region.regionId)}
                    >
                      <span className="appt-chev" aria-hidden="true"></span>
                    </button>
                  </div>

                  <div
                    className="appt-drop"
                    id={`${region.regionId}Drop`}
                    style={{ maxHeight: isOpen ? '2400px' : '0px' }}
                    aria-hidden={!isOpen}
                  >
                    <div className="dash-in">
                      <div className="dash-top">
                        <div className="dash-top-row">
                          <h2 className="dash-title">Appointed Officers in {region.name}</h2>

                          <div className="dash-filter">
                            <label className="dash-filter-label" htmlFor={`${region.regionId}Filter`}>
                              Filter:
                            </label>
                            <select
                              id={`${region.regionId}Filter`}
                              className="dash-filter-select"
                              value={region.selectedCommittee}
                              onChange={(event) => handleFilterChange(region.regionId, event.target.value)}
                            >
                              <option value="all">All Committees</option>
                              {(region.committees || []).map((committee) => (
                                <option
                                  value={committee.id || slugify(committee.name)}
                                  key={committee.id || slugify(committee.name)}
                                >
                                  {committee.name}
                                </option>
                              ))}
                            </select>
                          </div>
                        </div>
                      </div>

                      <div className="dash-tablewrap">
                        <table
                          className="dash-table"
                          id={`${region.regionId}Table`}
                          aria-label={`${region.name} appointments table`}
                        >
                          <thead>
                            <tr>
                              <th>Committee</th>
                              <th>Position</th>
                              <th>Name</th>
                              <th>Location</th>
                            </tr>
                          </thead>
                          <tbody>
                            {region.visibleRows.length > 0 ? region.visibleRows.map((row, index) => (
                              <tr
                                key={`${region.regionId}-${row.id || `${row.committee}-${row.name}`}`}
                                className={index % 2 === 1 ? 'zebra' : ''}
                                data-committee={row.committeeId}
                              >
                                <td className="td-strong" data-label="Committee">{row.committee}</td>
                                <td data-label="Position">{row.position}</td>
                                <td className="name-cell" data-label="Name">
                                  <span className="name-wrap">
                                    <span className="officer-eagle">Eagle</span>
                                    <span className="officer-name">{row.name}</span>
                                  </span>
                                </td>
                                <td className="location-text td-location" data-label="Location">{row.region}</td>
                              </tr>
                            )) : (
                              <tr>
                                <td colSpan="4" style={{ textAlign: 'center' }}>
                                  No officers found for this committee selection.
                                </td>
                              </tr>
                            )}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </section>
              )
            }) : null}
          </div>
        </main>
      </div>
    </PublicShell>
  )
}
