import { useEffect, useMemo, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import {
  PUBLIC_PAST_EVENTS_ENDPOINT,
  PUBLIC_UPCOMING_EVENTS_ENDPOINT,
  publicMediaUrl,
} from '../config'
import { fetchApiJson } from '../lib/api'
import '../theme/events-page.css'

const eventsHeroUrl = new URL('../static/events.png', import.meta.url).href

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

function startOfDay(date) {
  const nextDate = new Date(date)
  nextDate.setHours(0, 0, 0, 0)
  return nextDate
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

function toDateKey(value) {
  const parsed = parseDateValue(value)
  if (!parsed) {
    return null
  }

  const year = parsed.getFullYear()
  const month = `${parsed.getMonth() + 1}`.padStart(2, '0')
  const day = `${parsed.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

function uniqueStrings(items) {
  return Array.from(new Set(items.filter(Boolean)))
}

function compareEventsByDateAsc(first, second) {
  const firstDate = parseDateValue(first?.date)
  const secondDate = parseDateValue(second?.date)

  if (!firstDate && !secondDate) {
    return 0
  }

  if (!firstDate) {
    return 1
  }

  if (!secondDate) {
    return -1
  }

  return firstDate - secondDate
}

function compareEventsByDateDesc(first, second) {
  return compareEventsByDateAsc(second, first)
}

function mergeEventItems(currentItem, incomingItem) {
  if (!currentItem) {
    return incomingItem
  }

  return {
    ...currentItem,
    ...incomingItem,
    description: incomingItem.description || currentItem.description,
    mediaUrl: incomingItem.mediaUrl || currentItem.mediaUrl,
    mediaFilename: incomingItem.mediaFilename || currentItem.mediaFilename,
    type: incomingItem.type || currentItem.type,
  }
}

function splitEventsByDate(upcomingItems, pastItems, today) {
  const dedupedItems = new Map()

  for (const item of [...upcomingItems, ...pastItems]) {
    if (!item?.id) {
      continue
    }

    dedupedItems.set(item.id, mergeEventItems(dedupedItems.get(item.id), item))
  }

  const normalizedUpcoming = []
  const normalizedPast = []

  for (const item of dedupedItems.values()) {
    const parsedDate = parseDateValue(item.date)

    if (parsedDate) {
      if (startOfDay(parsedDate) >= today) {
        normalizedUpcoming.push({
          ...item,
          type: 'upcoming',
        })
      } else {
        normalizedPast.push({
          ...item,
          type: 'past',
        })
      }
      continue
    }

    if (item.type === 'past') {
      normalizedPast.push(item)
    } else {
      normalizedUpcoming.push(item)
    }
  }

  return {
    upcoming: normalizedUpcoming.sort(compareEventsByDateAsc),
    past: normalizedPast.sort(compareEventsByDateDesc),
  }
}

function buildCalendarDays(viewDate, highlightedDates, todayKey) {
  const year = viewDate.getFullYear()
  const month = viewDate.getMonth()
  const firstDay = new Date(year, month, 1).getDay()
  const lastDate = new Date(year, month + 1, 0).getDate()
  const cells = []

  for (let index = 0; index < firstDay; index += 1) {
    cells.push({
      id: `empty-${index}`,
      empty: true,
    })
  }

  for (let day = 1; day <= lastDate; day += 1) {
    const dateKey = `${year}-${`${month + 1}`.padStart(2, '0')}-${`${day}`.padStart(2, '0')}`
    cells.push({
      id: dateKey,
      day,
      dateKey,
      isToday: dateKey === todayKey,
      isHighlighted: highlightedDates.has(dateKey),
    })
  }

  while (cells.length % 7 !== 0) {
    cells.push({
      id: `tail-${cells.length}`,
      empty: true,
    })
  }

  return cells
}

function EventMedia({ event, variant = 'card' }) {
  const candidateSources = useMemo(
    () =>
      uniqueStrings([
        event?.mediaUrl,
        publicMediaUrl('event_media', event?.mediaFilename),
        publicMediaUrl('media', event?.mediaFilename),
      ]),
    [event?.mediaFilename, event?.mediaUrl],
  )
  const [sourceIndex, setSourceIndex] = useState(0)

  useEffect(() => {
    setSourceIndex(0)
  }, [candidateSources])

  const source = candidateSources[sourceIndex] || null
  const isVideo = `${event?.mediaType || ''}`.toLowerCase() === 'video'

  if (!source) {
    return (
      <div className={`events-page__media-placeholder events-page__media-placeholder--${variant}`}>
        No media uploaded
      </div>
    )
  }

  if (isVideo) {
    return variant === 'modal' ? (
      <video
        key={source}
        className="events-page__media events-page__media--modal"
        controls
        autoPlay
        playsInline
        preload="metadata"
        onError={() => setSourceIndex((current) => current + 1)}
      >
        <source src={source} />
      </video>
    ) : (
      <video
        key={source}
        className={`events-page__media events-page__media--${variant}`}
        muted
        playsInline
        preload="metadata"
        onError={() => setSourceIndex((current) => current + 1)}
      >
        <source src={source} />
      </video>
    )
  }

  return (
    <img
      src={source}
      alt={event?.title || 'Event media'}
      className={`events-page__media events-page__media--${variant}`}
      onError={() => setSourceIndex((current) => current + 1)}
    />
  )
}

export default function Events() {
  const today = useMemo(() => startOfDay(new Date()), [])
  const [upcomingEvents, setUpcomingEvents] = useState([])
  const [pastEvents, setPastEvents] = useState([])
  const [status, setStatus] = useState('loading')
  const [message, setMessage] = useState('')
  const [viewDate, setViewDate] = useState(() => new Date())
  const [activeEvent, setActiveEvent] = useState(null)

  useBodyClass(activeEvent ? 'modal-open' : '')

  useEffect(() => {
    let cancelled = false

    async function loadEvents() {
      try {
        setStatus('loading')
        setMessage('')

        const [upcomingResult, pastResult] = await Promise.allSettled([
          fetchApiJson(PUBLIC_UPCOMING_EVENTS_ENDPOINT),
          fetchApiJson(PUBLIC_PAST_EVENTS_ENDPOINT),
        ])

        let nextUpcoming = []
        let nextPast = []
        let nextMessage = ''
        let loadedCount = 0

        if (upcomingResult.status === 'fulfilled') {
          nextUpcoming = Array.isArray(upcomingResult.value.data) ? upcomingResult.value.data : []
          loadedCount += 1
        } else {
          nextMessage = upcomingResult.reason?.message || 'Unable to load upcoming events.'
        }

        if (pastResult.status === 'fulfilled') {
          nextPast = Array.isArray(pastResult.value.data) ? pastResult.value.data : []
          loadedCount += 1
        } else {
          const pastMessage = pastResult.reason?.message || 'Unable to load past events.'
          nextMessage = nextMessage ? `${nextMessage} ${pastMessage}` : pastMessage
        }

        if (cancelled) {
          return
        }

        const normalizedEvents = splitEventsByDate(nextUpcoming, nextPast, today)
        setUpcomingEvents(normalizedEvents.upcoming)
        setPastEvents(normalizedEvents.past)

        if (loadedCount === 0) {
          setStatus('error')
          setMessage(nextMessage || 'Unable to load event data right now.')
          return
        }

        setStatus('ready')
        setMessage(nextMessage)
      } catch (error) {
        if (!cancelled) {
          setStatus('error')
          setMessage(error.message || 'Unable to load event data right now.')
        }
      }
    }

    loadEvents()

    return () => {
      cancelled = true
    }
  }, [today])

  useEffect(() => {
    if (!activeEvent) {
      return undefined
    }

    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveEvent(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [activeEvent])

  const todayKey = useMemo(() => toDateKey(today), [today])
  const highlightedDates = useMemo(
    () => new Set(upcomingEvents.map((item) => toDateKey(item.date)).filter(Boolean)),
    [upcomingEvents],
  )
  const calendarDays = useMemo(
    () => buildCalendarDays(viewDate, highlightedDates, todayKey),
    [highlightedDates, todayKey, viewDate],
  )

  const monthLabel = useMemo(
    () =>
      new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
      }).format(viewDate),
    [viewDate],
  )

  const currentMonthHasEvents = useMemo(
    () =>
      upcomingEvents.some((item) => {
        const parsedDate = parseDateValue(item.date)
        return (
          parsedDate &&
          parsedDate.getMonth() === viewDate.getMonth() &&
          parsedDate.getFullYear() === viewDate.getFullYear()
        )
      }),
    [upcomingEvents, viewDate],
  )

  function moveMonth(step) {
    setViewDate((current) => new Date(current.getFullYear(), current.getMonth() + step, 1))
  }

  const upcomingEmpty = status !== 'loading' && upcomingEvents.length === 0
  const pastEmpty = status !== 'loading' && pastEvents.length === 0

  return (
    <PublicShell>
      <div className="events-page">
        <section
          className="events-page__hero"
          style={{
            backgroundImage: `linear-gradient(135deg, rgba(11, 27, 55, 0.85), rgba(12, 60, 101, 0.48)), url(${eventsHeroUrl})`,
          }}
        >
          <div className="events-page__hero-inner">
            <h1>Upcoming and Past Events</h1>
            <p>
              Browse live event data from the public API, highlighted with a calendar view and full event details.
            </p>

            <div className="events-page__hero-actions">
              <a href="#upcoming-events" className="events-page__button events-page__button--primary">
                Upcoming Events
              </a>
              <a href="#past-events" className="events-page__button events-page__button--secondary">
                Past Events
              </a>
            </div>

          </div>
        </section>

        {message ? (
          <section className="events-page__notice" role="status">
            <i className="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>{message}</span>
          </section>
        ) : null}

        <section className="events-page__section" id="upcoming-events">
          <div className="events-page__section-head">
            <div>
              <span className="events-page__label">See what is next</span>
              <h2>Upcoming events</h2>
            </div>
          </div>

          <div className="events-page__layout">
            <aside className="events-page__calendar">
              <div className="events-page__calendar-header">
                <button type="button" onClick={() => moveMonth(-1)} aria-label="Previous month">
                  &#10094;
                </button>
                <h3>{monthLabel}</h3>
                <button type="button" onClick={() => moveMonth(1)} aria-label="Next month">
                  &#10095;
                </button>
              </div>

              <div className="events-page__calendar-weekdays">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
              </div>

              <div className="events-page__calendar-grid">
                {calendarDays.map((cell) =>
                  cell.empty ? (
                    <span key={cell.id} className="events-page__day events-page__day--empty"></span>
                  ) : (
                    <span
                      key={cell.id}
                      className={`events-page__day ${cell.isHighlighted ? 'events-page__day--highlighted' : ''} ${cell.isToday ? 'events-page__day--today' : ''}`}
                    >
                      {cell.day}
                    </span>
                  ),
                )}
              </div>

              <p className="events-page__calendar-note">
                {currentMonthHasEvents
                  ? 'Highlighted dates mark events for the selected month.'
                  : 'No upcoming events scheduled for the selected month.'}
              </p>
            </aside>

            <div className="events-page__upcoming-list">
              {status === 'loading' ? (
                <div className="events-page__empty" role="status">
                  <div className="events-page__spinner" aria-hidden="true"></div>
                  <h3>Loading upcoming events</h3>
                  <p>Please wait while we fetch the current schedule.</p>
                </div>
              ) : null}

              {upcomingEmpty ? (
                <div className="events-page__empty" role="status">
                  <i className="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                  <h3>No upcoming events</h3>
                  <p>The API does not have any upcoming items to show yet.</p>
                </div>
              ) : null}

              {status !== 'loading' && upcomingEvents.length > 0
                ? upcomingEvents.map((item) => {
                    const parsedDate = parseDateValue(item.date)
                    const isCurrentMonth =
                      parsedDate &&
                      parsedDate.getMonth() === viewDate.getMonth() &&
                      parsedDate.getFullYear() === viewDate.getFullYear()

                    return (
                      <button
                        type="button"
                        className={`events-page__event-card ${isCurrentMonth ? 'is-active' : 'is-dim'}`}
                        key={item.id}
                        onClick={() => setActiveEvent(item)}
                      >
                        <div className="events-page__event-media-wrap">
                          <EventMedia event={item} variant="card" />
                        </div>
                        <div className="events-page__event-body">
                          <span className="events-page__event-date">{formatDisplayDate(item.date)}</span>
                          <h3>{item.title}</h3>
                          <p>{item.description || 'No description available for this event.'}</p>
                        </div>
                      </button>
                    )
                  })
                : null}
            </div>
          </div>
        </section>

        <section className="events-page__section events-page__section--past" id="past-events">
          <div className="events-page__section-head">
            <div>
              <span className="events-page__label">Archive</span>
              <h2>Past events</h2>
            </div>
            <p>Review previous activities and open any card to read the full event details.</p>
          </div>

          {status === 'loading' ? (
            <div className="events-page__empty" role="status">
              <div className="events-page__spinner" aria-hidden="true"></div>
              <h3>Loading past events</h3>
              <p>Fetching archived activities from the public API.</p>
            </div>
          ) : null}

          {pastEmpty ? (
            <div className="events-page__empty" role="status">
              <i className="fa-regular fa-folder-open" aria-hidden="true"></i>
              <h3>No past events yet</h3>
              <p>Completed events will appear here once they are available in the API.</p>
            </div>
          ) : null}

          {status !== 'loading' && pastEvents.length > 0 ? (
            <div className="events-page__past-grid">
              {pastEvents.map((item) => (
                <button
                  type="button"
                  className="events-page__past-card"
                  key={item.id}
                  onClick={() => setActiveEvent(item)}
                >
                  <div className="events-page__past-media-wrap">
                    <EventMedia event={item} variant="past" />
                  </div>
                  <div className="events-page__past-body">
                    <span className="events-page__event-date">{formatDisplayDate(item.date)}</span>
                    <h3>{item.title}</h3>
                    <p>{item.description || 'No description available for this event.'}</p>
                  </div>
                </button>
              ))}
            </div>
          ) : null}
        </section>

        {activeEvent ? (
          <div className="event-modal-shell" role="dialog" aria-modal="true" aria-labelledby="event-modal-title">
            <button
              type="button"
              className="event-modal-shell__backdrop"
              aria-label="Close event modal"
              onClick={() => setActiveEvent(null)}
            ></button>

            <div className="event-modal-shell__dialog">
              <button
                type="button"
                className="event-modal-shell__close"
                aria-label="Close event modal"
                onClick={() => setActiveEvent(null)}
              >
                <i className="fa-solid fa-xmark"></i>
              </button>

              <div className="event-modal-shell__layout">
                <div className="event-modal-shell__media-panel">
                  <EventMedia event={activeEvent} variant="modal" />
                </div>

                <div className="event-modal-shell__content-panel">
                  <span className="event-modal-shell__date">{formatDisplayDate(activeEvent.date)}</span>
                  <h3 id="event-modal-title">{activeEvent.title}</h3>
                  <p>{activeEvent.description || 'No description available for this event.'}</p>
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </PublicShell>
  )
}
