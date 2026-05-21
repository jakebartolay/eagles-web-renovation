import { Calendar } from 'lucide-react';
import { useEffect, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';
import { apiUrl } from '../lib/apiUrl';

const DEFAULT_EVENT_IMAGE_PATTERN = /default_event\.png/i;
const EVENT_SKELETON_ITEMS = Array.from({ length: 5 }, (_, idx) => idx);

const getEventDateParts = (event) => {
  if (event.day && event.month) {
    return { day: event.day, month: event.month };
  }

  const parsed = event.date ? new Date(event.date) : null;
  if (!parsed || Number.isNaN(parsed.getTime())) {
    return { day: '00', month: 'Jan' };
  }

  return {
    day: String(parsed.getDate()).padStart(2, '0'),
    month: parsed.toLocaleString('en-US', { month: 'short' }),
  };
};

const resolveEventImage = (event) => {
  const direct = resolveImageFromItem(event, [
    'mediaUrl',
    'media_url',
    'imageUrl',
    'image_url',
    'coverUrl',
    'cover_url',
    'thumbnailUrl',
    'thumbnail_url',
    'media.0.url',
  ]);

  const filename = String(event?.mediaFilename || event?.media_filename || '').trim();
  const hasRealFilename = filename && !DEFAULT_EVENT_IMAGE_PATTERN.test(filename);

  // API often returns default_event.png in mediaUrl even when mediaFilename has the real file.
  if ((!direct || DEFAULT_EVENT_IMAGE_PATTERN.test(direct)) && hasRealFilename) {
    return apiUrl(`media.php?group=event_media&file=${encodeURIComponent(filename)}`);
  }

  return direct;
};

export default function EventsPage() {
  const [events, setEvents] = useState([]);
  const [filter, setFilter] = useState('all');
  const [hasEventsResponse, setHasEventsResponse] = useState(false);

  useEffect(() => {
    setHasEventsResponse(false);

    const endpoint =
      filter === 'upcoming'
        ? API_ENDPOINTS.events.upcoming
        : filter === 'past'
          ? API_ENDPOINTS.events.past
          : API_ENDPOINTS.events.all;

    fetchJson(endpoint)
      .then((data) => {
        setEvents(extractList(data, ['events']));
      })
      .catch(() => {})
      .finally(() => {
        setHasEventsResponse(true);
      });
  }, [filter]);

  return (
    <div className="page events-page">
      <section className="hero events-hero" aria-label="Events background">
        <div className="hero-bg events-hero-bg"></div>
        <div className="hero-content events-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Events Calendar</h1>
          <div className="filter-tabs">
            <button className={`filter-tab ${filter === 'all' ? 'active' : ''}`} onClick={() => setFilter('all')}>
              All Events
            </button>
            <button
              className={`filter-tab ${filter === 'upcoming' ? 'active' : ''}`}
              onClick={() => setFilter('upcoming')}
            >
              Upcoming
            </button>
            <button className={`filter-tab ${filter === 'past' ? 'active' : ''}`} onClick={() => setFilter('past')}>
              Past
            </button>
          </div>
        </div>
      </Reveal>

      <div className="events-timeline">
        {!hasEventsResponse ? (
          EVENT_SKELETON_ITEMS.map((item) => (
            <div key={`event-skeleton-${item}`} className="event-card event-card--skeleton" aria-hidden="true">
              <div className="event-media">
                <Skeleton variant="rectangular" width="100%" height="100%" />
              </div>
              <div className="event-date-badge event-date-badge--skeleton">
                <Skeleton variant="text" width={42} height={34} sx={{ bgcolor: 'rgba(255,255,255,0.55)' }} />
                <Skeleton variant="text" width={40} height={18} sx={{ bgcolor: 'rgba(255,255,255,0.55)' }} />
              </div>
              <div className="event-content">
                <Skeleton variant="text" width="72%" height={36} sx={{ mb: 0.6 }} />
                <Skeleton variant="text" width="95%" height={24} />
                <Skeleton variant="text" width="88%" height={24} sx={{ mb: 1.2 }} />
                <div className="event-details">
                  <Skeleton variant="text" width={180} height={22} />
                  <Skeleton variant="text" width={220} height={22} />
                </div>
              </div>
            </div>
          ))
        ) : events.length > 0 ? (
          events.map((event, idx) => {
            const { day, month } = getEventDateParts(event);
            const eventImage = resolveEventImage(event);

            return (
              <Reveal key={idx} delay={idx * 60}>
                <div className="event-card">
                  {eventImage && (
                    <div className="event-media">
                      <img
                        src={eventImage}
                        alt={event.title || event.name || 'Event image'}
                        loading="lazy"
                        decoding="async"
                      />
                    </div>
                  )}
                  <div className="event-date-badge">
                    <div className="event-day">{day}</div>
                    <div className="event-month">{month}</div>
                  </div>
                  <div className="event-content">
                    <h3 className="event-title">{event.title || event.name || 'Untitled Event'}</h3>
                    <p className="event-description">{event.description || 'No description'}</p>
                    <div className="event-details">
                      <span className="event-time">Time: {event.time || 'TBA'}</span>
                      <span className="event-location">Location: {event.location || event.venue || 'TBA'}</span>
                    </div>
                  </div>
                </div>
              </Reveal>
            );
          })
        ) : hasEventsResponse ? (
          <div className="empty-state">
            <Calendar size={48} />
            <p>No events found</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
