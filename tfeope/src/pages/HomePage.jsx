import { FileText, Play, Star, Target, Trophy, Users, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { PUBLIC_BRANDING } from '../config';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';

const COUNTER_DURATION_MS = 1300;
const DEFAULT_STATS = {
  members: 500,
  years: 25,
  events: 100,
  clubs: 15,
};
const HOME_STAT_SKELETON_ITEMS = [0, 1, 2, 3];
const MEMORANDUM_SKELETON_ITEMS = [0, 1, 2];
const VIDEO_FILE_PATTERN = /\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i;
const youtubeThumbnail = (videoId) => `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;

const toSafeNumber = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const formatStatNumber = (value) => new Intl.NumberFormat('en-PH').format(toSafeNumber(value));

const normalizeStatText = (value) => String(value || '').trim().replace(/\s+/g, ' ').toUpperCase();

const countUniqueMemberClubs = (members = []) => {
  const unavailableClubNames = new Set(['N/A', 'NA', 'NONE', 'NOT AVAILABLE', 'UNASSIGNED']);
  const clubs = new Set();

  members.forEach((member) => {
    const clubName = normalizeStatText(member?.club || member?.clubName || member?.club_name || member?.chapter);
    if (clubName && !unavailableClubNames.has(clubName)) {
      clubs.add(clubName);
    }
  });

  return clubs.size;
};

const extractCount = (payload, preferredListKeys = []) => {
  if (Array.isArray(payload)) return payload.length;

  const list = extractList(payload, preferredListKeys);
  if (list.length > 0) return list.length;

  const countKeys = ['count', 'total', 'total_count', 'totalCount', 'member_count', 'members_count'];
  for (const key of countKeys) {
    const value = toSafeNumber(payload?.[key]);
    if (value > 0) return value;
  }

  return 0;
};

const easeOutCubic = (value) => 1 - (1 - value) ** 3;

const toEmbedVideoUrl = (url) => {
  try {
    const parsed = new URL(url);
    if (parsed.hostname.includes('youtu.be')) {
      const videoId = parsed.pathname.replace('/', '').trim();
      if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
    }

    if (parsed.hostname.includes('youtube.com')) {
      const videoId = parsed.searchParams.get('v');
      if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
      if (parsed.pathname.includes('/embed/')) return `${parsed.origin}${parsed.pathname}?autoplay=1&rel=0`;
    }
  } catch {
    return '';
  }

  return '';
};

export default function HomePage() {
  const [memorandums, setMemorandums] = useState([]);
  const [activeHymnalVideo, setActiveHymnalVideo] = useState(null);
  const [hasMemorandumsResponse, setHasMemorandumsResponse] = useState(false);
  const [hasStatsResponse, setHasStatsResponse] = useState(false);
  const [stats, setStats] = useState(DEFAULT_STATS);
  const [animatedStats, setAnimatedStats] = useState({
    members: 0,
    years: 0,
    events: 0,
    clubs: 0,
  });

  useEffect(() => {
    setHasMemorandumsResponse(false);

    fetchJson(API_ENDPOINTS.memorandum)
      .then((memorandumData) => {
        setMemorandums(extractList(memorandumData, ['memorandum', 'memorandums']));
      })
      .catch(() => {})
      .finally(() => {
        setHasMemorandumsResponse(true);
      });
  }, []);

  useEffect(() => {
    let isMounted = true;
    setHasStatsResponse(false);

    Promise.allSettled([
      fetchJson(API_ENDPOINTS.members.all),
      fetchJson(API_ENDPOINTS.events.all),
      fetchJson(API_ENDPOINTS.clubs),
    ]).then(([membersResult, eventsResult, clubsResult]) => {
      if (!isMounted) return;

      const memberRows = membersResult.status === 'fulfilled' ? extractList(membersResult.value, ['members', 'member']) : [];
      const membersCount =
        membersResult.status === 'fulfilled'
          ? memberRows.length || extractCount(membersResult.value, ['members', 'member'])
          : 0;
      const eventsCount = eventsResult.status === 'fulfilled' ? extractCount(eventsResult.value, ['events']) : 0;
      const activeClubsCount = countUniqueMemberClubs(memberRows);
      const clubsCount =
        activeClubsCount > 0
          ? activeClubsCount
          : clubsResult.status === 'fulfilled'
            ? extractCount(clubsResult.value, ['clubs'])
            : 0;

      setStats({
        members: membersCount > 0 ? membersCount : DEFAULT_STATS.members,
        years: DEFAULT_STATS.years,
        events: eventsCount > 0 ? eventsCount : DEFAULT_STATS.events,
        clubs: clubsCount > 0 ? clubsCount : DEFAULT_STATS.clubs,
      });
    }).finally(() => {
      if (isMounted) {
        setHasStatsResponse(true);
      }
    });

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    let rafId = 0;
    const start = performance.now();

    const targets = {
      members: Math.max(toSafeNumber(stats.members), 0),
      years: Math.max(toSafeNumber(stats.years), 0),
      events: Math.max(toSafeNumber(stats.events), 0),
      clubs: Math.max(toSafeNumber(stats.clubs), 0),
    };

    const animate = (timestamp) => {
      const progress = Math.min((timestamp - start) / COUNTER_DURATION_MS, 1);
      const eased = easeOutCubic(progress);

      setAnimatedStats({
        members: Math.round(targets.members * eased),
        years: Math.round(targets.years * eased),
        events: Math.round(targets.events * eased),
        clubs: Math.round(targets.clubs * eased),
      });

      if (progress < 1) {
        rafId = window.requestAnimationFrame(animate);
      }
    };

    rafId = window.requestAnimationFrame(animate);
    return () => {
      if (rafId) window.cancelAnimationFrame(rafId);
    };
  }, [stats.members, stats.years, stats.events, stats.clubs]);

  useEffect(() => {
    if (!activeHymnalVideo) return undefined;

    const previousHtmlOverflow = document.documentElement.style.overflow;
    const previousBodyOverflow = document.body.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';

    return () => {
      document.documentElement.style.overflow = previousHtmlOverflow;
      document.body.style.overflow = previousBodyOverflow;
    };
  }, [activeHymnalVideo]);

  const membersLabel = `${formatStatNumber(animatedStats.members)}+`;
  const yearsLabel = `${formatStatNumber(animatedStats.years)}+`;
  const eventsLabel = `${formatStatNumber(animatedStats.events)}+`;
  const clubsLabel = `${formatStatNumber(animatedStats.clubs)}+`;
  const hymnalVideos = useMemo(
    () => [
      {
        title: 'Eagles Prayer',
        description: 'A prayer reflecting faith, unity, and service.',
        videoUrl: PUBLIC_BRANDING.prayerVideoUrl,
        thumbnailUrl: youtubeThumbnail('e0kMQ-cJIEo'),
      },
      {
        title: 'National Anthem',
        description: 'The Philippine National Anthem.',
        videoUrl: PUBLIC_BRANDING.anthemVideoUrl,
        thumbnailUrl: youtubeThumbnail('l6gYeAE0l_Y'),
      },
      {
        title: 'Eagles Hymn',
        description: 'The official hymn of the Philippine Eagles.',
        videoUrl: PUBLIC_BRANDING.hymnVideoUrl,
        thumbnailUrl: youtubeThumbnail('DuZRGwRA0mc'),
      },
    ],
    [],
  );
  const activeHymnalEmbedUrl = activeHymnalVideo ? toEmbedVideoUrl(activeHymnalVideo.videoUrl) : '';
  const activeHymnalFileUrl = activeHymnalVideo?.videoUrl || '';

  return (
    <div className="page home-page">
      <div className="hero home-hero" aria-label="Home background">
        <div className="hero-bg home-hero-bg"></div>
        <div className="hero-content hero-content-spacer"></div>
      </div>

      <div className="stats-section">
        {!hasStatsResponse ? (
          HOME_STAT_SKELETON_ITEMS.map((item) => (
            <div key={`home-stat-skeleton-${item}`} className="stat-card stat-card--skeleton" aria-hidden="true">
              <Skeleton variant="circular" width={56} height={56} sx={{ mx: 'auto', mb: 1.6 }} />
              <Skeleton variant="text" width="52%" height={48} sx={{ mx: 'auto', mb: 0.5 }} />
              <Skeleton variant="text" width="62%" height={24} sx={{ mx: 'auto' }} />
            </div>
          ))
        ) : (
          <>
            <Reveal delay={40}>
              <div className="stat-card">
                <div className="stat-icon">
                  <Users size={42} />
                </div>
                <div className="stat-value">{membersLabel}</div>
                <div className="stat-label">Active Members</div>
              </div>
            </Reveal>
            <Reveal delay={80}>
              <div className="stat-card">
                <div className="stat-icon">
                  <Trophy size={42} />
                </div>
                <div className="stat-value">{yearsLabel}</div>
                <div className="stat-label">Years Legacy</div>
              </div>
            </Reveal>
            <Reveal delay={120}>
              <div className="stat-card">
                <div className="stat-icon">
                  <Target size={42} />
                </div>
                <div className="stat-value">{eventsLabel}</div>
                <div className="stat-label">Annual Events</div>
              </div>
            </Reveal>
            <Reveal delay={160}>
              <div className="stat-card">
                <div className="stat-icon">
                  <Star size={42} />
                </div>
                <div className="stat-value">{clubsLabel}</div>
                <div className="stat-label">Active Clubs</div>
              </div>
            </Reveal>
          </>
        )}
      </div>

      <section className="home-hymnals-section">
        <Reveal>
          <div className="home-hymnals-panel">
            <div className="home-hymnals-header">
              <h2 className="home-hymnals-title">Eagles Hymnals and Prayer</h2>
              <p className="home-hymnals-subtitle">
                Sacred songs and prayers that embody faith, patriotism, and brotherhood.
              </p>
            </div>

            <div className="home-hymnals-grid">
              {hymnalVideos.map((item) => (
                <article className="home-hymnal-card" key={item.title}>
                  <button
                    type="button"
                    className="home-hymnal-video"
                    onClick={() => setActiveHymnalVideo(item)}
                    aria-label={`Play ${item.title}`}
                  >
                    <img
                      className="home-hymnal-thumbnail"
                      src={item.thumbnailUrl}
                      alt=""
                      loading="lazy"
                      decoding="async"
                      aria-hidden="true"
                    />
                    <span className="home-hymnal-play" aria-hidden="true">
                      <Play size={26} fill="currentColor" />
                    </span>
                    <span className="home-hymnal-overlay">
                      <strong>{item.title}</strong>
                      <span>{item.description}</span>
                    </span>
                  </button>
                </article>
              ))}
            </div>
          </div>
        </Reveal>
      </section>

      <section className="memorandum-section">
        <Reveal>
          <div className="memorandum-header">
            <h2 className="memorandum-title">Latest Memorandum</h2>
            <p className="memorandum-subtitle">Important updates and official advisories</p>
          </div>
        </Reveal>

        {!hasMemorandumsResponse ? (
          <div className="memorandum-grid">
            {MEMORANDUM_SKELETON_ITEMS.map((item) => (
              <article key={`memorandum-skeleton-${item}`} className="memorandum-card memorandum-card--skeleton" aria-hidden="true">
                <div className="memorandum-cover">
                  <Skeleton variant="rectangular" width="100%" height="100%" />
                </div>
                <div className="memorandum-content">
                  <Skeleton variant="text" width="78%" height={34} sx={{ mb: 0.8 }} />
                  <Skeleton variant="text" width="95%" height={24} />
                  <Skeleton variant="text" width="88%" height={24} sx={{ mb: 1.2 }} />
                  <div className="memorandum-meta">
                    <Skeleton variant="text" width={112} height={22} />
                    <Skeleton variant="rounded" width={62} height={26} />
                  </div>
                </div>
              </article>
            ))}
          </div>
        ) : memorandums.length > 0 ? (
          <div className="memorandum-grid">
            {memorandums.slice(0, 3).map((memo, idx) => {
              const memoImage = resolveImageFromItem(memo, [
                'coverUrl',
                'cover_url',
                'imageUrl',
                'image_url',
                'pages.0.url',
                'pageUrls.0',
              ]);

              const memoLink = resolveImageFromItem(memo, ['pages.0.url', 'pageUrls.0', 'coverUrl']);

              return (
                <Reveal key={memo.id || idx} delay={idx * 70}>
                  <article className="memorandum-card">
                    <div className="memorandum-cover">
                      {memoImage ? (
                        <img src={memoImage} alt={memo.title || 'Memorandum'} loading="lazy" decoding="async" />
                      ) : (
                        <div className="memorandum-cover-placeholder">
                          <FileText size={40} />
                        </div>
                      )}
                    </div>
                    <div className="memorandum-content">
                      <h3 className="memorandum-card-title">{memo.title || 'Untitled Memorandum'}</h3>
                      <p className="memorandum-description">{memo.description || 'No description available.'}</p>
                      <div className="memorandum-meta">
                        <span>{memo.createdAt || memo.created_at || 'Recent'}</span>
                        {memoLink && (
                          <a className="memorandum-link" href={memoLink} target="_blank" rel="noreferrer">
                            Open
                          </a>
                        )}
                      </div>
                    </div>
                  </article>
                </Reveal>
              );
            })}
          </div>
        ) : hasMemorandumsResponse ? (
          <div className="empty-state">
            <FileText size={48} />
            <p>No memorandum available</p>
          </div>
        ) : null}
      </section>

      {activeHymnalVideo && (
        <div className="home-video-modal" role="dialog" aria-modal="true" aria-label={activeHymnalVideo.title}>
          <button
            type="button"
            className="home-video-modal-backdrop"
            onClick={() => setActiveHymnalVideo(null)}
            aria-label="Close video"
          />
          <div className="home-video-modal-dialog">
            <button
              type="button"
              className="home-video-modal-close"
              onClick={() => setActiveHymnalVideo(null)}
              aria-label="Close video"
            >
              <X size={24} />
            </button>
            {activeHymnalEmbedUrl ? (
              <iframe
                className="home-video-modal-player"
                src={activeHymnalEmbedUrl}
                title={activeHymnalVideo.title}
                allow="autoplay; fullscreen; picture-in-picture"
                allowFullScreen
              />
            ) : VIDEO_FILE_PATTERN.test(activeHymnalFileUrl) ? (
              <video className="home-video-modal-player" controls autoPlay playsInline>
                <source src={activeHymnalFileUrl} type="video/mp4" />
              </video>
            ) : (
              <div className="home-video-modal-player home-video-modal-fallback">
                <p>Unable to play this video source.</p>
                <a href={activeHymnalFileUrl} target="_blank" rel="noreferrer">Open video</a>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
