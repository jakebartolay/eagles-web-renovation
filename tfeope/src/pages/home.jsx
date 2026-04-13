import { useEffect, useMemo, useState } from 'react'
import './theme/navbar.css'
import './theme/home.css'
import './theme/footer.css'
import './App.css'
import { PUBLIC_BRANDING, PUBLIC_HOME_ENDPOINT, PUBLIC_NEWS_ENDPOINT } from './config'

const fallbackData = {
  stats: {
    members: 0,
    regions: 0,
    clubs: 0,
  },
  latestNews: null,
  memorandums: [],
  events: [],
}

const appLinks = {
  home: '#top',
  about: '#app-overview',
  magnaCarta: '#app-overview',
  news: '#newsMemos',
  officers: '#eventsSection',
  governors: '#eventsSection',
  appointed: '#eventsSection',
  events: '#eventsSection',
  membership: '#newsMemos',
  admin: '/tfeope-admin/',
}

const NAV_SCROLL_OFFSET = 96

function isHashLink(href) {
  return typeof href === 'string' && href.startsWith('#')
}

function scrollToSection(href, behavior = 'smooth') {
  if (!isHashLink(href)) {
    return false
  }

  const targetId = href.slice(1)
  const target = document.getElementById(targetId)

  if (!target) {
    return false
  }

  const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY - NAV_SCROLL_OFFSET)
  window.history.replaceState(null, '', href)
  window.scrollTo({
    top,
    behavior,
  })

  return true
}

function formatDate(value) {
  if (!value) {
    return 'To be announced'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('en-PH', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(date)
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-PH').format(Number(value) || 0)
}

async function fetchApiPayload(url, signal, fallbackMessage) {
  const response = await fetch(url, { signal })
  const text = await response.text()
  const contentType = response.headers.get('content-type') || ''

  if (!contentType.toLowerCase().includes('application/json')) {
    throw new Error(`Expected JSON from ${url} but received ${contentType || 'non-JSON response'}.`)
  }

  let payload

  try {
    payload = JSON.parse(text)
  } catch {
    throw new Error(`Invalid JSON response from ${url}.`)
  }

  if (!response.ok || !payload.ok) {
    throw new Error(payload.message || fallbackMessage)
  }

  return payload
}

function App() {
  const [pageData, setPageData] = useState(fallbackData)
  const [newsItems, setNewsItems] = useState([])
  const [status, setStatus] = useState('loading')
  const [message, setMessage] = useState('')
  const [menuOpen, setMenuOpen] = useState(false)
  const [aboutOpen, setAboutOpen] = useState(false)
  const [officersOpen, setOfficersOpen] = useState(false)
  const [headerFaded, setHeaderFaded] = useState(false)
  const [memoLightbox, setMemoLightbox] = useState(null)
  const [activeVideo, setActiveVideo] = useState(null)

  useEffect(() => {
    const controller = new AbortController()

    async function loadHome() {
      try {
        setStatus('loading')
        setMessage('')
        setNewsItems([])

        const [homeResult, newsResult] = await Promise.allSettled([
          fetchApiPayload(PUBLIC_HOME_ENDPOINT, controller.signal, 'Unable to load public data.'),
          fetchApiPayload(PUBLIC_NEWS_ENDPOINT, controller.signal, 'Unable to load news feed.'),
        ])

        let nextMessage = ''
        let homeLoaded = false
        let newsLoaded = false

        if (homeResult.status === 'fulfilled') {
          setPageData({
            ...fallbackData,
            ...homeResult.value.data,
            stats: {
              ...fallbackData.stats,
              ...(homeResult.value.data?.stats || {}),
            },
          })
          homeLoaded = true
        } else if (homeResult.reason?.name !== 'AbortError') {
          nextMessage = homeResult.reason?.message || 'Unable to load public data.'
          setPageData(fallbackData)
        } else {
          return
        }

        if (newsResult.status === 'fulfilled') {
          setNewsItems(Array.isArray(newsResult.value.data) ? newsResult.value.data : [])
          newsLoaded = true
        } else if (newsResult.reason?.name !== 'AbortError') {
          setNewsItems([])
          nextMessage = nextMessage
            ? `${nextMessage} ${newsResult.reason?.message || 'Unable to load news feed.'}`
            : newsResult.reason?.message || 'Unable to load news feed.'
        } else {
          return
        }

        if (!homeLoaded && !newsLoaded) {
          setStatus('error')
          setMessage(nextMessage || 'Unable to load live data.')
          return
        }

        setStatus('ready')
        setMessage(nextMessage)
      } catch (error) {
        if (error.name === 'AbortError') {
          return
        }

        setStatus('error')
        setMessage(error.message || 'Unable to load live data.')
      }
    }

    loadHome()

    return () => controller.abort()
  }, [])

  useEffect(() => {
    function onScroll() {
      setHeaderFaded(window.scrollY > 120)
    }

    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })

    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    function closeOnOutsideClick(event) {
      if (!event.target.closest('.nav-dropdown')) {
        setAboutOpen(false)
        setOfficersOpen(false)
      }

      if (!event.target.closest('#navbar') && !event.target.closest('#menu-toggle')) {
        setMenuOpen(false)
      }
    }

    document.addEventListener('click', closeOnOutsideClick)

    return () => document.removeEventListener('click', closeOnOutsideClick)
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      document.body.classList.add('loaded')
    }, status === 'loading' ? 1200 : 250)

    return () => window.clearTimeout(timer)
  }, [status])

  useEffect(() => {
    const shouldLock = Boolean(memoLightbox || activeVideo)
    document.body.classList.toggle('modal-open', shouldLock)

    return () => document.body.classList.remove('modal-open')
  }, [activeVideo, memoLightbox])

  useEffect(() => {
    function syncHashScroll() {
      if (!window.location.hash) {
        return
      }

      window.setTimeout(() => {
        scrollToSection(window.location.hash, 'smooth')
      }, 0)
    }

    syncHashScroll()
    window.addEventListener('hashchange', syncHashScroll)

    return () => window.removeEventListener('hashchange', syncHashScroll)
  }, [])

  const branding = PUBLIC_BRANDING
  const isReady = status !== 'loading'
  const hymns = useMemo(
    () => [
      {
        title: 'Eagles Prayer',
        description: 'A prayer reflecting faith, unity, and service.',
        videoUrl: branding.prayerVideoUrl,
      },
      {
        title: 'National Anthem',
        description: 'The Philippine National Anthem.',
        videoUrl: branding.anthemVideoUrl,
      },
      {
        title: 'Eagles Hymn',
        description: 'The official hymn of the Philippine Eagles.',
        videoUrl: branding.hymnVideoUrl,
      },
    ],
    [branding.anthemVideoUrl, branding.hymnVideoUrl, branding.prayerVideoUrl],
  )
  const liveStats = [
    { label: 'Members', value: formatNumber(pageData.stats.members) },
    { label: 'Regions', value: formatNumber(pageData.stats.regions) },
    { label: 'Clubs', value: formatNumber(pageData.stats.clubs) },
  ]
  const newsFeed = newsItems.length > 0 ? newsItems : pageData.latestNews ? [pageData.latestNews] : []
  const featuredNews = newsFeed[0] || null
  const moreNews = featuredNews ? newsFeed.slice(1) : []

  function closeNavigation() {
    setMenuOpen(false)
    setAboutOpen(false)
    setOfficersOpen(false)
  }

  function handleNavLinkClick(event, href) {
    closeNavigation()

    if (!isHashLink(href)) {
      return
    }

    event.preventDefault()
    scrollToSection(href)
  }

  function toggleMenu(event) {
    event.stopPropagation()
    setMenuOpen((current) => !current)
  }

  function toggleAbout(event) {
    event.preventDefault()
    event.stopPropagation()
    setAboutOpen((current) => !current)
    setOfficersOpen(false)
  }

  function toggleOfficers(event) {
    event.preventDefault()
    event.stopPropagation()
    setOfficersOpen((current) => !current)
    setAboutOpen(false)
  }

  function handleAboutHover(nextOpen) {
    if (window.innerWidth <= 900) {
      return
    }

    setAboutOpen(nextOpen)
    if (nextOpen) {
      setOfficersOpen(false)
    }
  }

  function handleOfficersHover(nextOpen) {
    if (window.innerWidth <= 900) {
      return
    }

    setOfficersOpen(nextOpen)
    if (nextOpen) {
      setAboutOpen(false)
    }
  }

  function openMemoLightbox(memo) {
    const pages = memo.pages || []
    if (pages.length === 0) {
      return
    }

    setMemoLightbox({
      memo,
      index: 0,
    })
  }

  function closeMemoLightbox() {
    setMemoLightbox(null)
  }

  function moveMemoPage(step) {
    setMemoLightbox((current) => {
      if (!current) {
        return current
      }

      const pages = current.memo.pages || []
      if (pages.length === 0) {
        return current
      }

      const nextIndex = (current.index + step + pages.length) % pages.length
      return {
        ...current,
        index: nextIndex,
      }
    })
  }

  function openVideoModal(item) {
    setActiveVideo(item)
  }

  function closeVideoModal() {
    setActiveVideo(null)
  }

  const activeMemoPage =
    memoLightbox && memoLightbox.memo.pages
      ? memoLightbox.memo.pages[memoLightbox.index]
      : null

  return (
    <>
      <div id="splash">
        <div className="splash-inner">
          {branding.logoUrl ? <img src={branding.logoUrl} alt="Logo" /> : null}
          <h1>Fraternal Order of Eagles</h1>
          <p>Service Through Strong Brotherhood</p>
        </div>
      </div>

      <header className={`site-header ${headerFaded ? 'is-faded' : ''}`} id="siteHeader">
        <div className="container">
          <div className="logo">
            <a href={appLinks.home} onClick={(event) => handleNavLinkClick(event, appLinks.home)}>
              {branding.logoUrl ? <img src={branding.logoUrl} alt="Logo" /> : null}
            </a>
            <span className="nav-title">Ang Agila</span>
          </div>

          <nav id="navbar" className={menuOpen ? 'active' : ''}>
            <a
              href={appLinks.home}
              className="active"
              onClick={(event) => handleNavLinkClick(event, appLinks.home)}
            >
              Home
            </a>

            <div
              className={`nav-dropdown ${aboutOpen ? 'open' : ''}`}
              id="aboutDropdown"
              onMouseEnter={() => handleAboutHover(true)}
              onMouseLeave={() => handleAboutHover(false)}
            >
              <a href={appLinks.about} className="about-link" onClick={toggleAbout}>
                About Us
              </a>
              <div className="dropdown-menu">
                <a href={appLinks.about} onClick={(event) => handleNavLinkClick(event, appLinks.about)}>
                  History
                </a>
                <a
                  href={appLinks.magnaCarta}
                  onClick={(event) => handleNavLinkClick(event, appLinks.magnaCarta)}
                >
                  Magna Carta
                </a>
              </div>
            </div>

            <a href={appLinks.news} onClick={(event) => handleNavLinkClick(event, appLinks.news)}>
              News &amp; Videos
            </a>

            <div
              className={`nav-dropdown ${officersOpen ? 'open' : ''}`}
              id="officersDropdown"
              onMouseEnter={() => handleOfficersHover(true)}
              onMouseLeave={() => handleOfficersHover(false)}
            >
              <a href={appLinks.officers} className="officers-link" onClick={toggleOfficers}>
                Officers
              </a>
              <div className="dropdown-menu">
                <a
                  href={appLinks.officers}
                  onClick={(event) => handleNavLinkClick(event, appLinks.officers)}
                >
                  National Officers
                </a>
                <a
                  href={appLinks.governors}
                  onClick={(event) => handleNavLinkClick(event, appLinks.governors)}
                >
                  Governors
                </a>
                <a
                  href={appLinks.appointed}
                  onClick={(event) => handleNavLinkClick(event, appLinks.appointed)}
                >
                  Appointed Officers
                </a>
              </div>
            </div>

            <a href={appLinks.events} onClick={(event) => handleNavLinkClick(event, appLinks.events)}>
              Events
            </a>

            <a
              href={appLinks.membership}
              className="cta-btn"
              onClick={(event) => handleNavLinkClick(event, appLinks.membership)}
            >
              Get Started
            </a>
          </nav>

          <div
            id="menu-toggle"
            className="menu-toggle"
            onClick={toggleMenu}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                toggleMenu(event)
              }
            }}
            role="button"
            tabIndex={0}
            aria-label="Toggle navigation"
          >
            &#9776;
          </div>
        </div>
      </header>

      <section className="hero" id="top">
        <div className="hero-overlay"></div>
        {branding.heroUrl ? <img src={branding.heroUrl} alt="Hero Image" /> : null}
      </section>

      <section className="react-live-strip" aria-label="Live summary">
        {liveStats.map((item) => (
          <article key={item.label} className="react-live-card">
            <span>{item.label}</span>
            <strong>{item.value}</strong>
          </article>
        ))}
      </section>

      <section className="react-app-overview" id="app-overview">
        <article className="react-overview-card">
          <h3>Client Side Only</h3>
          <p>
            Ang tfeope ay public frontend lang. Wala na itong direct database
            connection.
          </p>
        </article>
        <article className="react-overview-card">
          <h3>API Driven Data</h3>
          <p>
            Ang news, memorandums, events, at DB content media ay dumadaan na lahat sa
            tfeope-api.
          </p>
        </article>
        <article className="react-overview-card">
          <h3>Separated Admin</h3>
          <p>
            Ang admin area ay hiwalay na sa public app. Buksan ang admin side sa
            <a href={appLinks.admin}> tfeope-admin</a>.
          </p>
        </article>
      </section>

      {message ? (
        <section className="react-api-notice" role="status">
          <strong>Live data notice:</strong> {message}
        </section>
      ) : null}

      <section className="news-memos" id="newsMemos">
        <div className="news-memos-grid">
          <div className={`nm-panel nm-news section-loading ${isReady ? 'is-ready' : ''}`} id="secNews">
            <div className={`section-loader ${isReady ? 'hide' : ''}`} id="loaderNews" aria-label="Loading latest news" role="status">
              <div className="loader-spinner"></div>
              <div className="loader-text">Loading latest news...</div>
            </div>

            <div className="section-content" id="contentNews">
              <h2>Latest News</h2>

              {featuredNews ? (
                <div className="featured-news-card">
                  <div className="featured-news-image">
                    {featuredNews.imageUrl ? (
                      <img
                        src={featuredNews.imageUrl}
                        alt={featuredNews.title}
                        loading="eager"
                      />
                    ) : (
                      <div className="react-image-fallback">No image available</div>
                    )}
                  </div>
                  <div className="featured-news-content">
                    <span className="news-badge">Featured</span>
                    <h3>{featuredNews.title}</h3>
                    <p>{featuredNews.excerpt || featuredNews.content || 'No news description available yet.'}</p>
                    <div className="featured-news-meta">
                      Published {formatDate(featuredNews.createdAt)}
                    </div>
                  </div>
                </div>
              ) : (
                <p>No news available at the moment.</p>
              )}

              {moreNews.length > 0 ? (
                <div className="react-news-feed" id="newsFeedList">
                  <div className="react-news-feed__head">
                    <h3>More Published News</h3>
                    <span>{newsFeed.length} items from API</span>
                  </div>

                  <div className="react-news-list">
                    {moreNews.map((item) => (
                      <article className="react-news-item" key={item.id}>
                        <div className="react-news-item__media">
                          {item.imageUrl ? (
                            <img src={item.imageUrl} alt={item.title} loading="lazy" />
                          ) : (
                            <div className="react-image-fallback compact">No image</div>
                          )}
                        </div>
                        <div className="react-news-item__body">
                          <span className="react-news-date">{formatDate(item.createdAt)}</span>
                          <h4>{item.title}</h4>
                          <p>{item.excerpt || item.content || 'No news description available yet.'}</p>
                        </div>
                      </article>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
          </div>

          <div className={`nm-panel nm-memos section-loading ${isReady ? 'is-ready' : ''}`} id="secMemos">
            <div className={`section-loader ${isReady ? 'hide' : ''}`} id="loaderMemos" aria-label="Loading memorandums" role="status">
              <div className="loader-spinner"></div>
              <div className="loader-text">Loading memorandums...</div>
            </div>

            <div className="section-content" id="contentMemos">
              <h2>Memorandums</h2>

              <div className="memo-container memo-scroller" id="memoScroller">
                {pageData.memorandums.length > 0 ? (
                  pageData.memorandums.map((memo) => (
                    <div className="memo-card" key={memo.id} onClick={() => openMemoLightbox(memo)}>
                      {memo.coverUrl ? (
                        <img src={memo.coverUrl} alt={memo.title} loading="lazy" />
                      ) : (
                        <div className="react-image-fallback tall">No memorandum image</div>
                      )}
                      <h3>{memo.title}</h3>
                      <p>{memo.description || 'No memorandum description available.'}</p>
                    </div>
                  ))
                ) : (
                  <p>No memorandums available.</p>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="events" id="eventsSection">
        <h2>Upcoming Events</h2>
        <div className="event-list">
          {pageData.events.length > 0 ? (
            pageData.events.map((event) => (
              <div className="event-card" key={event.id}>
                {event.mediaUrl ? (
                  event.mediaType === 'video' ? (
                    <video controls preload="metadata">
                      <source src={event.mediaUrl} />
                    </video>
                  ) : (
                    <img src={event.mediaUrl} alt={event.title} />
                  )
                ) : (
                  <div className="react-image-fallback tall">No event media</div>
                )}
                <h4>{event.title}</h4>
                <p>{formatDate(event.date)}</p>
                <p>{event.description || 'No event description available.'}</p>
              </div>
            ))
          ) : (
            <p>No upcoming events available at the moment.</p>
          )}
        </div>

        <div className="react-events-cta">
          <a href="#eventsSection" className="btn-primary">
            View All Events
          </a>
        </div>
      </section>

      <section className="hymnals-section" id="hymnals">
        <div className="hymnals-wrap">
          <div className="hymnals-head">
            <h2 className="hymnals-title">Eagles Hymnals and Prayer</h2>
            <p className="hymnals-sub">
              Sacred songs and prayers that embody faith, patriotism, and brotherhood.
            </p>
          </div>

          <div className="hymnals-grid">
            {hymns.map((item) => (
              <article className="hymnal-card" key={item.title}>
                <div
                  className="video-frame js-video-open"
                  role="button"
                  tabIndex={0}
                  onClick={() => openVideoModal(item)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                      event.preventDefault()
                      openVideoModal(item)
                    }
                  }}
                >
                  <video playsInline preload="metadata" muted>
                    <source src={item.videoUrl} type="video/mp4" />
                  </video>
                  <div className="video-play-badge" aria-hidden="true">
                    <i className="fa-solid fa-play"></i>
                  </div>
                </div>

                <div className="hymnal-content">
                  <h3>{item.title}</h3>
                  <p>{item.description}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <footer id="app-footer">
        <div className="footer-container">
          <div className="footer-brand">
            {branding.logoUrl ? <img src={branding.logoUrl} alt="Logo" /> : null}
            {branding.alphaLogoUrl ? <img src={branding.alphaLogoUrl} alt="Logo 2" /> : null}
            <p>
              Service Through
              <br />
              Strong Brotherhood
            </p>
          </div>

          <div className="footer-links">
            <h4>Quick Links</h4>
            <ul>
              <li>
                <a href={appLinks.about} onClick={(event) => handleNavLinkClick(event, appLinks.about)}>
                  About Us
                </a>
              </li>
              <li>
                <a href={appLinks.news} onClick={(event) => handleNavLinkClick(event, appLinks.news)}>
                  Latest News
                </a>
              </li>
              <li>
                <a
                  href={appLinks.magnaCarta}
                  onClick={(event) => handleNavLinkClick(event, appLinks.magnaCarta)}
                >
                  Magna Carta
                </a>
              </li>
              <li>
                <a href={appLinks.events} onClick={(event) => handleNavLinkClick(event, appLinks.events)}>
                  Events
                </a>
              </li>
            </ul>
          </div>

          <div className="footer-contact">
            <h4>Contact Us</h4>
            <p>Quezon City, Philippines</p>
            <p>Phone: (02) 123-4567</p>
            <p>Email: angagila2026@gmail.com</p>
          </div>

          <div className="footer-social">
            <h4>Follow Us</h4>
            <a
              className="social-btn"
              href="https://www.facebook.com/profile.php?id=61571962082522"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Facebook"
            >
              <i className="fa-brands fa-facebook-f"></i>
            </a>
            <a
              className="social-btn"
              href="https://instagram.com"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Instagram"
            >
              <i className="fa-brands fa-instagram"></i>
            </a>
            <a
              className="social-btn"
              href="https://twitter.com"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="X"
            >
              <i className="fa-brands fa-x-twitter"></i>
            </a>
          </div>
        </div>

        <div className="footer-bottom">
          (c) 2026 Ang Agila | Fraternal Order of Eagles. All Rights Reserved.
        </div>
      </footer>

      <div id="lightbox" className={memoLightbox ? 'is-open' : ''}>
        <span className="close-lightbox" onClick={closeMemoLightbox}>
          x
        </span>
        {memoLightbox && activeMemoPage ? (
          <>
            {memoLightbox.memo.pages.length > 1 ? (
              <button className="arrow left" type="button" onClick={() => moveMemoPage(-1)}>
                {'<'}
              </button>
            ) : null}

            <img src={activeMemoPage} alt={memoLightbox.memo.title} />
            <div className="caption">
              {memoLightbox.memo.title} | Page {memoLightbox.index + 1}
            </div>

            {memoLightbox.memo.pages.length > 1 ? (
              <button className="arrow right" type="button" onClick={() => moveMemoPage(1)}>
                {'>'}
              </button>
            ) : null}
          </>
        ) : null}
      </div>

      <div className={`video-modal ${activeVideo ? 'is-open' : ''}`}>
        <div className="video-modal__backdrop" onClick={closeVideoModal}></div>
        <div className="video-modal__wrap">
          <button className="video-modal__close--outside" type="button" onClick={closeVideoModal}>
            <i className="fa-solid fa-xmark"></i>
          </button>
          <div className="video-modal__dialog">
            <div className="video-modal__player">
              {activeVideo ? (
                <video controls autoPlay>
                  <source src={activeVideo.videoUrl} type="video/mp4" />
                </video>
              ) : null}
            </div>
            {activeVideo ? (
              <div className="react-video-caption">
                <h3>{activeVideo.title}</h3>
                <p>{activeVideo.description}</p>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </>
  )
}

export default App
