import { useEffect, useMemo, useRef, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useStylesheet from '../hooks/useStylesheet'
import useBodyClass from '../hooks/useBodyClass'
import { fetchApiJson } from '../lib/api'
import { PUBLIC_GOVERNORS_ENDPOINT } from '../config'
import governorsStylesheetUrl from '../theme/governors.css?url'

const placeholderUrl = new URL('../../old_system/governors/placeholder.png', import.meta.url).href

function joinGovernorRegions(regions) {
  const names = (regions || [])
    .map((region) => region.name)
    .filter(Boolean)

  return names.length > 0 ? names.join(' • ') : 'No region assigned'
}

export default function Governors() {
  const [governors, setGovernors] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [activeGovernor, setActiveGovernor] = useState(null)
  const backgroundRef = useRef(null)

  useStylesheet(governorsStylesheetUrl)
  useBodyClass(activeGovernor ? 'modal-open' : '')

  useEffect(() => {
    let cancelled = false

    async function loadGovernors() {
      try {
        setLoading(true)
        setMessage('')

        const payload = await fetchApiJson(PUBLIC_GOVERNORS_ENDPOINT)
        if (!cancelled) {
          setGovernors(Array.isArray(payload.data) ? payload.data : [])
        }
      } catch (error) {
        if (!cancelled) {
          setMessage(error.message || 'Unable to load governors right now.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadGovernors()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveGovernor(null)
      }
    }

    function updateBackground() {
      const background = backgroundRef.current
      if (!background) {
        return
      }

      const fullAt = 0.8
      const maxScroll = Math.max(
        1,
        document.documentElement.scrollHeight - window.innerHeight,
      )
      const progress = Math.max(0, Math.min(1, (window.scrollY / maxScroll) / fullAt))
      background.style.setProperty('--bgProgress', progress.toFixed(4))
    }

    updateBackground()
    document.addEventListener('keydown', handleKeyDown)
    window.addEventListener('scroll', updateBackground, { passive: true })
    window.addEventListener('resize', updateBackground)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('scroll', updateBackground)
      window.removeEventListener('resize', updateBackground)
    }
  }, [])

  const preparedGovernors = useMemo(
    () => governors.map((governor) => ({
      ...governor,
      imageUrl: governor.imageUrl || placeholderUrl,
      regionsLabel: joinGovernorRegions(governor.regions),
    })),
    [governors],
  )

  return (
    <PublicShell>
      <div className="page-background" ref={backgroundRef} aria-hidden="true"></div>

      <section className="officers-hero">
        <h1>Regional Governors</h1>
        <p>E.Y. 2026</p>
      </section>

      <section className="org-chart">
        <div className="chart-level">
          {loading ? (
            <div className="empty-state-center">
              <i className="fas fa-user-shield" style={{ fontSize: 48, color: '#9ca3af' }}></i>
              <p className="empty-message">Loading governors...</p>
            </div>
          ) : null}

          {!loading && message ? (
            <div className="empty-state-center">
              <i className="fas fa-triangle-exclamation" style={{ fontSize: 48, color: '#9ca3af' }}></i>
              <p className="empty-message">{message}</p>
            </div>
          ) : null}

          {!loading && !message && preparedGovernors.length === 0 ? (
            <div className="empty-state-center">
              <i className="fas fa-user-shield" style={{ fontSize: 48, color: '#9ca3af' }}></i>
              <p className="empty-message">No governors found.</p>
            </div>
          ) : null}

          {!loading && !message ? preparedGovernors.map((governor) => (
            <div
              className="officer-card"
              key={governor.id}
              role="button"
              tabIndex={0}
              onClick={() => setActiveGovernor(governor)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault()
                  setActiveGovernor(governor)
                }
              }}
            >
              <img
                className="gov-img"
                src={governor.imageUrl}
                alt={governor.name}
                onError={(event) => {
                  event.currentTarget.src = placeholderUrl
                }}
              />

              <div className="officer-info">
                <h4 className="gov-name">
                  <span className="eagle-prefix">Eagle</span>
                  <span>{governor.name}</span>
                </h4>
                <p>{governor.regionsLabel}</p>
              </div>
            </div>
          )) : null}
        </div>
      </section>

      <div
        className={`modal-overlay ${activeGovernor ? 'show' : ''}`}
        id="modalOverlay"
        aria-hidden={activeGovernor ? 'false' : 'true'}
        onClick={() => setActiveGovernor(null)}
      ></div>

      <div
        className={`detail-panel ${activeGovernor ? 'show' : ''}`}
        id="govModal"
        aria-hidden={activeGovernor ? 'false' : 'true'}
      >
        <div className="panel-head">
          <img
            className="panel-avatar"
            src={activeGovernor?.imageUrl || placeholderUrl}
            alt=""
            id="modalAvatar"
            onError={(event) => {
              event.currentTarget.src = placeholderUrl
            }}
          />
          <div className="panel-head-text">
            <h3 className="gov-name">
              <span className="eagle-prefix">Eagle</span>
              <span id="modalName">{activeGovernor?.name || 'Governor'}</span>
            </h3>
            <p className="role" id="modalRegions">{activeGovernor?.regionsLabel || ''}</p>
          </div>
        </div>

        <div className="panel-body">
          {activeGovernor ? (
            activeGovernor.regions?.length ? activeGovernor.regions.map((region) => (
              <div className="region-block" key={`${activeGovernor.id}-${region.id || region.name}`}>
                <h4 className="region-title">{region.name}</h4>
                <div className="clubs-list">
                  {region.clubs?.length ? region.clubs.map((club) => (
                    <div className="club-row" key={`${region.id || region.name}-${club.id || club.name}`}>
                      <div className="club-name">{club.name}</div>
                      <div className="club-sub">
                        President:{' '}
                        <span className="club-president">
                          {club.presidents?.[0]?.name || 'Not assigned'}
                        </span>
                      </div>
                    </div>
                  )) : (
                    <p className="empty">No clubs found under this region.</p>
                  )}
                </div>
              </div>
            )) : (
              <p className="empty">No clubs found under this governor.</p>
            )
          ) : (
            <div className="panel-loading">Select a governor to view their assigned clubs.</div>
          )}
        </div>

        <button
          className="close"
          type="button"
          id="modalClose"
          aria-label="Close"
          onClick={() => setActiveGovernor(null)}
        >
          ×
        </button>
      </div>
    </PublicShell>
  )
}
