import { useEffect, useMemo, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import useStylesheet from '../hooks/useStylesheet'
import { fetchApiJson } from '../lib/api'
import { PUBLIC_MAGNA_CARTA_ENDPOINT } from '../config'
import magnaCartaStylesheetUrl from '../theme/magna-carta.css?url'

const pillarData = {
  brotherhood: {
    title: 'Brotherhood',
    subtitle: 'Strong bonds',
    description: 'Strong bonds through shared values and experiences.',
    imageUrl: new URL('../static/1.jpg', import.meta.url).href,
  },
  service: {
    title: 'Service',
    subtitle: 'Compassion in action',
    description: 'Serving communities with compassion and action.',
    imageUrl: new URL('../static/2.jpg', import.meta.url).href,
  },
  unity: {
    title: 'Unity',
    subtitle: 'One organization',
    description: 'Standing together as one organization.',
    imageUrl: new URL('../static/3.jpg', import.meta.url).href,
  },
  divine: {
    title: 'Divine Power',
    subtitle: 'Ethics and strength',
    description: 'Guided by ethics, faith, and moral strength.',
    imageUrl: new URL('../static/1.jpg', import.meta.url).href,
  },
}

export default function MagnaCarta() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [activeModal, setActiveModal] = useState(null)

  useStylesheet(magnaCartaStylesheetUrl)
  useBodyClass(activeModal ? 'no-scroll' : '')

  useEffect(() => {
    let cancelled = false

    async function loadItems() {
      try {
        setLoading(true)
        setMessage('')

        const payload = await fetchApiJson(PUBLIC_MAGNA_CARTA_ENDPOINT)
        if (!cancelled) {
          setItems(Array.isArray(payload.data) ? payload.data : [])
        }
      } catch (error) {
        if (!cancelled) {
          setMessage(error.message || 'Unable to load magna carta items right now.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadItems()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveModal(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [])

  const hasEmptyState = useMemo(
    () => !loading && !message && items.length === 0,
    [items.length, loading, message],
  )

  function openItemModal(item) {
    setActiveModal({
      title: item.title,
      subtitle: item.subtitle,
      description: item.description || item.content,
      imageUrl: item.imageUrl,
    })
  }

  function openPillarModal(key) {
    setActiveModal(pillarData[key] || null)
  }

  return (
    <PublicShell>
      <div className={`mc-overlay ${activeModal ? 'show' : ''}`} id="mcOverlay" aria-hidden={activeModal ? 'false' : 'true'} onClick={() => setActiveModal(null)}></div>

      <section className="mc-hero">
        <div className="mc-hero-inner">
          <h1>Magna Carta</h1>
          <p>Click a card to read more.</p>
        </div>
      </section>

      <section className="mc-topics">
        <div className="mc-wrap">
          {message ? (
            <div className="mc-empty" role="status">
              <div className="mc-empty-ic"><i className="fa-regular fa-folder-open"></i></div>
              <div className="mc-empty-title">Unable to load Magna Carta topics</div>
              <div className="mc-empty-sub">{message}</div>
            </div>
          ) : null}

          <div className="mc-grid" role="list">
            {loading ? (
              <div className="mc-empty" role="status">
                <div className="mc-empty-ic"><i className="fa-solid fa-spinner"></i></div>
                <div className="mc-empty-title">Loading Magna Carta topics</div>
                <div className="mc-empty-sub">Please wait while we load the current items.</div>
              </div>
            ) : null}

            {hasEmptyState ? (
              <div className="mc-empty" role="status">
                <div className="mc-empty-ic"><i className="fa-regular fa-folder-open"></i></div>
                <div className="mc-empty-title">No Magna Carta topics available</div>
                <div className="mc-empty-sub">Please add items from the admin panel.</div>
              </div>
            ) : null}

            {!loading && !message ? items.map((item) => (
              <button
                className="mc-card"
                type="button"
                key={item.id}
                role="listitem"
                aria-label={`Open ${item.title}`}
                onClick={() => openItemModal(item)}
              >
                <span className="mc-card__title">{item.title}</span>
                <span className="mc-card__chev" aria-hidden="true">
                  <i className="fa-solid fa-arrow-right"></i>
                </span>
              </button>
            )) : null}
          </div>
        </div>
      </section>

      <section className="pillars" id="pillars">
        <div className="mc-wrap">
          <h2>Our Four Pillars</h2>
          <p className="pillars-subtitle">
            The Four Pillars guide our Brotherhood: Leadership, Brotherhood, Service, and Resilience.
          </p>

          <div className="pillar-list" role="list">
            <button className="pillar-card" type="button" data-pillar="brotherhood" role="listitem" onClick={() => openPillarModal('brotherhood')}>
              <div className="pillar-icon"><i className="fas fa-users"></i></div>
              <h3>Brotherhood</h3>
              <p>Strong bonds through shared values and experiences.</p>
            </button>

            <button className="pillar-card" type="button" data-pillar="service" role="listitem" onClick={() => openPillarModal('service')}>
              <div className="pillar-icon"><i className="fas fa-hand-holding-heart"></i></div>
              <h3>Service</h3>
              <p>Serving communities with compassion and action.</p>
            </button>

            <button className="pillar-card" type="button" data-pillar="unity" role="listitem" onClick={() => openPillarModal('unity')}>
              <div className="pillar-icon"><i className="fas fa-handshake"></i></div>
              <h3>Unity</h3>
              <p>Standing together as one organization.</p>
            </button>

            <button className="pillar-card" type="button" data-pillar="divine" role="listitem" onClick={() => openPillarModal('divine')}>
              <div className="pillar-icon"><i className="fas fa-shield-halved"></i></div>
              <h3>Divine Power</h3>
              <p>Guided by ethics, faith, and moral strength.</p>
            </button>
          </div>
        </div>
      </section>

      <div className={`mc-modal ${activeModal ? 'show' : ''}`} aria-hidden={activeModal ? 'false' : 'true'} role="dialog" aria-modal="true">
        <div className="mc-modal-content" role="document">
          <button className="mc-close" type="button" aria-label="Close" onClick={() => setActiveModal(null)}>
            ×
          </button>

          {activeModal?.imageUrl ? (
            <div className="mc-img mc-img--modal" style={{ backgroundImage: `url('${activeModal.imageUrl}')` }} aria-hidden="true"></div>
          ) : (
            <div className="mc-img-standby mc-img-standby--modal" aria-hidden="true"></div>
          )}

          <h3>{activeModal?.title || ''}</h3>
          {activeModal?.subtitle ? <p className="role">{activeModal.subtitle}</p> : null}
          <p className="speech" style={{ whiteSpace: 'pre-wrap' }}>{activeModal?.description || ''}</p>
        </div>
      </div>
    </PublicShell>
  )
}
