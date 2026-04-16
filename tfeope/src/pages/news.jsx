import { useEffect, useMemo, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import { PUBLIC_NEWS_ENDPOINT, PUBLIC_VIDEOS_ENDPOINT, publicMediaUrl } from '../config'
import { fetchApiJson } from '../lib/api'
import '../theme/news-page.css'

const newsHeroUrl = new URL('../static/news.png', import.meta.url).href

function parseDateValue(value) {
  const text = `${value || ''}`.trim()
  if (!text) {
    return null
  }

  const dateOnlyMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text)
  if (dateOnlyMatch) {
    const [, year, month, day] = dateOnlyMatch
    return new Date(Number(year), Number(month) - 1, Number(day))
  }

  const parsed = new Date(text)
  if (Number.isNaN(parsed.getTime())) {
    return null
  }

  return parsed
}

function formatDisplayDate(value) {
  const parsed = parseDateValue(value)
  if (!parsed) {
    return 'Date unavailable'
  }

  return new Intl.DateTimeFormat('en-PH', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(parsed)
}

function summariseText(value, limit = 180) {
  const text = `${value || ''}`.trim()
  if (!text) {
    return ''
  }

  if (text.length <= limit) {
    return text
  }

  return `${text.slice(0, limit - 3).trimEnd()}...`
}

function isVideoMedia(item) {
  const type = `${item?.fileType || item?.type || ''}`.toLowerCase()
  return type.includes('video')
}

function buildNewsMediaUrl(item) {
  if (!item) {
    return null
  }

  return item.url || publicMediaUrl('news', item.filename) || publicMediaUrl('uploads', item.filename) || publicMediaUrl('media', item.filename)
}

function getNewsMediaItems(item) {
  if (!item) {
    return []
  }

  const baseMedia = Array.isArray(item.media) ? item.media : []
  const normalizedMedia = baseMedia
    .map((mediaItem) => ({
      ...mediaItem,
      url: buildNewsMediaUrl(mediaItem),
      mediaType: isVideoMedia(mediaItem) ? 'video' : 'image',
    }))
    .filter((mediaItem) => mediaItem.url)

  if (normalizedMedia.length > 0) {
    return normalizedMedia
  }

  if (item.imageUrl) {
    return [
      {
        id: `image-${item.id}`,
        url: item.imageUrl,
        mediaType: 'image',
      },
    ]
  }

  return []
}

function getNewsCoverUrl(item) {
  if (!item) {
    return null
  }

  const mediaItems = getNewsMediaItems(item)
  const imageItem = mediaItems.find((mediaItem) => mediaItem.mediaType === 'image')
  return item.imageUrl || imageItem?.url || null
}

function getVideoPosterUrl(item) {
  return item?.thumbnailUrl || publicMediaUrl('media', item?.thumbnailFilename)
}

function SmartImage({ src, alt, className, fallbackClassName, fallbackLabel }) {
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    setFailed(false)
  }, [src])

  if (!src || failed) {
    return <div className={fallbackClassName}>{fallbackLabel}</div>
  }

  return <img src={src} alt={alt} className={className} onError={() => setFailed(true)} />
}

export default function News() {
  const [newsItems, setNewsItems] = useState([])
  const [videos, setVideos] = useState([])
  const [status, setStatus] = useState('loading')
  const [message, setMessage] = useState('')
  const [activeNews, setActiveNews] = useState(null)
  const [activeNewsMediaIndex, setActiveNewsMediaIndex] = useState(0)
  const [activeVideo, setActiveVideo] = useState(null)

  useBodyClass(activeNews || activeVideo ? 'modal-open' : '')

  useEffect(() => {
    let cancelled = false

    async function loadPageData() {
      try {
        setStatus('loading')
        setMessage('')

        const [newsResult, videosResult] = await Promise.allSettled([
          fetchApiJson(PUBLIC_NEWS_ENDPOINT),
          fetchApiJson(PUBLIC_VIDEOS_ENDPOINT),
        ])

        let nextNews = []
        let nextVideos = []
        let nextMessage = ''
        let loadedCount = 0

        if (newsResult.status === 'fulfilled') {
          nextNews = Array.isArray(newsResult.value.data) ? newsResult.value.data : []
          nextNews = [...nextNews].sort((first, second) => {
            const firstDate = parseDateValue(first?.createdAt || first?.created_at)
            const secondDate = parseDateValue(second?.createdAt || second?.created_at)
            const firstTime = firstDate ? firstDate.getTime() : 0
            const secondTime = secondDate ? secondDate.getTime() : 0

            if (firstTime !== secondTime) {
              return secondTime - firstTime
            }

            const firstId = Number(first?.id || first?.news_id || 0) || 0
            const secondId = Number(second?.id || second?.news_id || 0) || 0
            return secondId - firstId
          })
          loadedCount += 1
        } else {
          nextMessage = newsResult.reason?.message || 'Unable to load the latest news.'
        }

        if (videosResult.status === 'fulfilled') {
          nextVideos = Array.isArray(videosResult.value.data) ? videosResult.value.data : []
          loadedCount += 1
        } else {
          const videoMessage = videosResult.reason?.message || 'Unable to load the latest videos.'
          nextMessage = nextMessage ? `${nextMessage} ${videoMessage}` : videoMessage
        }

        if (cancelled) {
          return
        }

        setNewsItems(nextNews)
        setVideos(nextVideos)

        if (loadedCount === 0) {
          setStatus('error')
          setMessage(nextMessage || 'Unable to load page data right now.')
          return
        }

        setStatus('ready')
        setMessage(nextMessage)
      } catch (error) {
        if (!cancelled) {
          setStatus('error')
          setMessage(error.message || 'Unable to load page data right now.')
        }
      }
    }

    loadPageData()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!activeNews && !activeVideo) {
      return undefined
    }

    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveNews(null)
        setActiveVideo(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [activeNews, activeVideo])

  const activeNewsMediaItems = useMemo(() => getNewsMediaItems(activeNews), [activeNews])
  const activeNewsMedia = activeNewsMediaItems[activeNewsMediaIndex] || null

  function openNewsModal(item) {
    const mediaItems = getNewsMediaItems(item)
    const initialIndex = mediaItems.findIndex((mediaItem) => mediaItem.mediaType === 'image')

    setActiveNews(item)
    setActiveNewsMediaIndex(initialIndex >= 0 ? initialIndex : 0)
    setActiveVideo(null)
  }

  function openVideoModal(item) {
    if (!item?.videoUrl) {
      return
    }

    setActiveVideo(item)
    setActiveNews(null)
  }

  const newsEmpty = status !== 'loading' && newsItems.length === 0
  const videosEmpty = status !== 'loading' && videos.length === 0

  return (
    <PublicShell>
      <div className="news-page">
        <section
          className="news-page__hero"
          style={{
            backgroundImage: `linear-gradient(135deg, rgba(11, 28, 56, 0.88), rgba(21, 64, 111, 0.52)), url(${newsHeroUrl})`,
          }}
        >
          <div className="news-page__hero-inner">
            <h1>News and Videos</h1>
            <p>
              Stay updated with the latest announcements, stories, and video features from Ang Agila.
            </p>

            <div className="news-page__hero-actions">
              <a href="#news-section" className="news-page__button news-page__button--primary">
                Read News
              </a>
              <a href="#videos-section" className="news-page__button news-page__button--secondary">
                Watch Videos
              </a>
            </div>

          </div>
        </section>

        {message ? (
          <section className="news-page__notice" role="status">
            <i className="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>{message}</span>
          </section>
        ) : null}

        <section className="news-page__section" id="news-section">
          <div className="news-page__section-head">
            <div>
              <span className="news-page__label">Latest updates</span>
              <h2>Published news</h2>
            </div>
          </div>

          {status === 'loading' ? (
            <div className="news-page__empty" role="status">
              <div className="news-page__spinner" aria-hidden="true"></div>
              <h3>Loading news feed</h3>
              <p>Please wait while we fetch the latest published items.</p>
            </div>
          ) : null}

          {newsEmpty ? (
            <div className="news-page__empty" role="status">
              <i className="fa-regular fa-newspaper" aria-hidden="true"></i>
              <h3>No published news yet</h3>
              <p>Once new stories are published in the admin panel, they will appear here.</p>
            </div>
          ) : null}

          {status !== 'loading' && newsItems.length > 0 ? (
            <div className="news-page__grid">
              {newsItems.map((item) => {
                const coverUrl = getNewsCoverUrl(item)
                const previewText = item.excerpt || summariseText(item.content)
                const mediaCount = getNewsMediaItems(item).length

                return (
                  <article className="news-card-react" key={item.id}>
                    <button
                      type="button"
                      className="news-card-react__media"
                      onClick={() => openNewsModal(item)}
                      aria-label={`Open ${item.title}`}
                    >
                      <SmartImage
                        src={coverUrl}
                        alt={item.title}
                        className="news-card-react__image"
                        fallbackClassName="news-card-react__placeholder"
                        fallbackLabel="No image uploaded"
                      />
                    </button>

                    <div className="news-card-react__body">
                      <div className="news-card-react__meta">
                        <span>{formatDisplayDate(item.createdAt)}</span>
                      </div>
                      <h3>{item.title}</h3>
                      <p>{previewText || 'No preview available for this article yet.'}</p>
                      <button
                        type="button"
                        className="news-card-react__action"
                        onClick={() => openNewsModal(item)}
                      >
                        Read More
                      </button>
                    </div>
                  </article>
                )
              })}
            </div>
          ) : null}
        </section>

        <section className="news-page__section news-page__section--videos" id="videos-section">
          <div className="news-page__section-head">
            <div>
              <span className="news-page__label">Watch the latest</span>
              <h2>Video highlights</h2>
            </div>
          </div>

          {status === 'loading' ? (
            <div className="news-page__empty" role="status">
              <div className="news-page__spinner" aria-hidden="true"></div>
              <h3>Loading videos</h3>
              <p>Fetching the latest published video content from the API.</p>
            </div>
          ) : null}

          {videosEmpty ? (
            <div className="news-page__empty" role="status">
              <i className="fa-regular fa-circle-play" aria-hidden="true"></i>
              <h3>No videos available</h3>
              <p>Publish a video from the admin area and it will show up here.</p>
            </div>
          ) : null}

          {status !== 'loading' && videos.length > 0 ? (
            <div className="news-page__video-grid">
              {videos.map((item) => (
                <article className="video-card-react" key={item.id}>
                  <button
                    type="button"
                    className="video-card-react__media"
                    onClick={() => openVideoModal(item)}
                    disabled={!item.videoUrl}
                    aria-label={`Play ${item.title}`}
                  >
                    <SmartImage
                      src={getVideoPosterUrl(item)}
                      alt={item.title}
                      className="video-card-react__image"
                      fallbackClassName="video-card-react__placeholder"
                      fallbackLabel="No thumbnail"
                    />
                    <span className="video-card-react__play" aria-hidden="true">
                      <i className="fa-solid fa-play"></i>
                    </span>
                  </button>

                  <div className="video-card-react__body">
                    <div className="video-card-react__meta">
                      <span>{formatDisplayDate(item.createdAt)}</span>
                    </div>
                    <h3>{item.title}</h3>
                    <p>{item.excerpt || summariseText(item.description)}</p>
                    <button
                      type="button"
                      className="video-card-react__action"
                      onClick={() => openVideoModal(item)}
                      disabled={!item.videoUrl}
                    >
                      {item.videoUrl ? 'Watch Video' : 'Video Unavailable'}
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : null}
        </section>

        {activeNews ? (
          <div className="news-modal-shell" role="dialog" aria-modal="true" aria-labelledby="news-modal-title">
            <button
              type="button"
              className="news-modal-shell__backdrop"
              aria-label="Close news modal"
              onClick={() => setActiveNews(null)}
            ></button>

            <div className="news-modal-shell__dialog">
              <button
                type="button"
                className="news-modal-shell__close"
                aria-label="Close news modal"
                onClick={() => setActiveNews(null)}
              >
                <i className="fa-solid fa-xmark"></i>
              </button>

              <div className="news-modal-shell__layout">
                <div className="news-modal-shell__media-panel">
                  <div className="news-modal-shell__main-media">
                    {activeNewsMedia ? (
                      activeNewsMedia.mediaType === 'video' ? (
                        <video key={activeNewsMedia.url} controls autoPlay playsInline preload="metadata">
                          <source src={activeNewsMedia.url} />
                        </video>
                      ) : (
                        <SmartImage
                          src={activeNewsMedia.url}
                          alt={activeNews.title}
                          className="news-modal-shell__main-image"
                          fallbackClassName="news-modal-shell__empty-media"
                          fallbackLabel="No media available"
                        />
                      )
                    ) : (
                      <div className="news-modal-shell__empty-media">No media uploaded for this news item.</div>
                    )}
                  </div>

                  {activeNewsMediaItems.length > 1 ? (
                    <div className="news-modal-shell__thumbs" aria-label="News media thumbnails">
                      {activeNewsMediaItems.map((mediaItem, index) => (
                        <button
                          type="button"
                          key={mediaItem.id || `${mediaItem.url}-${index}`}
                          className={`news-modal-shell__thumb ${index === activeNewsMediaIndex ? 'is-active' : ''}`}
                          onClick={() => setActiveNewsMediaIndex(index)}
                          aria-label={`Show media ${index + 1}`}
                        >
                          {mediaItem.mediaType === 'video' ? (
                            <span className="news-modal-shell__thumb-video">
                              <i className="fa-solid fa-play"></i>
                            </span>
                          ) : (
                            <SmartImage
                              src={mediaItem.url}
                              alt=""
                              className="news-modal-shell__thumb-image"
                              fallbackClassName="news-modal-shell__thumb-fallback"
                              fallbackLabel="No media"
                            />
                          )}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </div>

                <div className="news-modal-shell__content-panel">
                  <span className="news-modal-shell__date">{formatDisplayDate(activeNews.createdAt)}</span>
                  <h3 id="news-modal-title">{activeNews.title}</h3>
                  <p>{activeNews.content || 'No content available for this news item.'}</p>
                </div>
              </div>
            </div>
          </div>
        ) : null}

        {activeVideo ? (
          <div className="video-modal-shell" role="dialog" aria-modal="true" aria-labelledby="video-modal-title">
            <button
              type="button"
              className="video-modal-shell__backdrop"
              aria-label="Close video modal"
              onClick={() => setActiveVideo(null)}
            ></button>

            <div className="video-modal-shell__dialog">
              <button
                type="button"
                className="video-modal-shell__close"
                aria-label="Close video modal"
                onClick={() => setActiveVideo(null)}
              >
                <i className="fa-solid fa-xmark"></i>
              </button>

              <div className="video-modal-shell__player">
                <video controls autoPlay playsInline preload="metadata">
                  <source src={activeVideo.videoUrl} />
                </video>
              </div>

              <div className="video-modal-shell__content">
                <span>{formatDisplayDate(activeVideo.createdAt)}</span>
                <h3 id="video-modal-title">{activeVideo.title}</h3>
                <p>{activeVideo.description || 'No description available for this video.'}</p>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </PublicShell>
  )
}
