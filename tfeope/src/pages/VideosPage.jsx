import { Camera, Play, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem, resolveMediaUrl } from '../config/api';

const VIDEOS_PAGE_SIZE = 9;
const VIDEO_SOURCE_KEYS = [
  'videoUrl',
  'video_url',
  'url',
  'link',
  'source',
  'src',
  'mediaUrl',
  'media_url',
  'fileUrl',
  'file_url',
  'video',
  'media.0.url',
  'media.1.url',
];
const VIDEO_FILE_PATTERN = /\.(mp4|webm|ogg|mov|m4v|m3u8)(\?.*)?$/i;
const STREAMING_PATTERN = /(youtube\.com|youtu\.be|vimeo\.com)/i;
const VIDEO_SKELETON_ITEMS = Array.from({ length: VIDEOS_PAGE_SIZE }, (_, idx) => idx);

const readByPath = (source, path) => {
  if (!path.includes('.')) return source?.[path];
  return path.split('.').reduce((acc, part) => {
    if (acc === null || acc === undefined) return undefined;
    if (/^\d+$/.test(part)) return acc[Number(part)];
    return acc[part];
  }, source);
};

const looksLikeVideoUrl = (value) => {
  if (!value || typeof value !== 'string') return false;
  return VIDEO_FILE_PATTERN.test(value) || STREAMING_PATTERN.test(value);
};

const videoSourceType = (value) => {
  const normalized = String(value || '').toLowerCase();
  if (normalized.includes('youtube.com') || normalized.includes('youtu.be')) return 'youtube';
  if (normalized.includes('vimeo.com')) return 'vimeo';
  if (VIDEO_FILE_PATTERN.test(normalized)) return 'file';
  return 'link';
};

const resolveVideoSource = (item) => {
  for (const key of VIDEO_SOURCE_KEYS) {
    const raw = readByPath(item, key);
    if (typeof raw === 'string' && raw.trim()) {
      const resolved = resolveMediaUrl(raw.trim());
      if (looksLikeVideoUrl(resolved)) return resolved;
    }
  }

  const mediaItems = Array.isArray(item?.media) ? item.media : [];
  for (const mediaItem of mediaItems) {
    const candidate = mediaItem?.url || mediaItem?.src || mediaItem?.link;
    if (typeof candidate === 'string' && candidate.trim()) {
      const resolved = resolveMediaUrl(candidate.trim());
      if (looksLikeVideoUrl(resolved)) return resolved;
    }
  }

  return '';
};

const toEmbedUrl = (url) => {
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

    if (parsed.hostname.includes('vimeo.com')) {
      const idMatch = parsed.pathname.match(/\/(\d+)/);
      if (idMatch?.[1]) return `https://player.vimeo.com/video/${idMatch[1]}?autoplay=1`;
    }
  } catch {
    return '';
  }

  return '';
};

export default function VideosPage() {
  const [videos, setVideos] = useState([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [activeVideo, setActiveVideo] = useState(null);
  const [hasVideosResponse, setHasVideosResponse] = useState(false);

  useEffect(() => {
    setHasVideosResponse(false);

    fetchJson(API_ENDPOINTS.videos)
      .then((data) => {
        setVideos(extractList(data, ['videos']));
        setCurrentPage(1);
      })
      .catch(() => {})
      .finally(() => {
        setHasVideosResponse(true);
      });
  }, []);

  const totalPages = Math.max(1, Math.ceil(videos.length / VIDEOS_PAGE_SIZE));
  const startIndex = (currentPage - 1) * VIDEOS_PAGE_SIZE;
  const visibleVideos = useMemo(
    () => videos.slice(startIndex, startIndex + VIDEOS_PAGE_SIZE),
    [videos, startIndex],
  );

  useEffect(() => {
    if (!activeVideo) return undefined;

    const scrollY = window.scrollY;
    const previousHtmlOverflow = document.documentElement.style.overflow;
    const previousBodyOverflow = document.body.style.overflow;
    const previousBodyPosition = document.body.style.position;
    const previousBodyTop = document.body.style.top;
    const previousBodyWidth = document.body.style.width;
    const previousPaddingRight = document.body.style.paddingRight;
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = `${scrollbarWidth}px`;
    }

    const handleEsc = (event) => {
      if (event.key === 'Escape') {
        setActiveVideo(null);
      }
    };

    window.addEventListener('keydown', handleEsc);
    return () => {
      window.removeEventListener('keydown', handleEsc);
      document.documentElement.style.overflow = previousHtmlOverflow;
      document.body.style.overflow = previousBodyOverflow;
      document.body.style.position = previousBodyPosition;
      document.body.style.top = previousBodyTop;
      document.body.style.width = previousBodyWidth;
      document.body.style.paddingRight = previousPaddingRight;
      window.scrollTo(0, scrollY);
    };
  }, [activeVideo]);

  const handlePlay = (videoUrl) => {
    if (!videoUrl) return;
    setActiveVideo({ url: videoUrl });
  };

  const activeVideoUrl = activeVideo?.url || '';
  const activeEmbedUrl = activeVideoUrl ? toEmbedUrl(activeVideoUrl) : '';
  const isDirectVideoFile = VIDEO_FILE_PATTERN.test(activeVideoUrl);

  return (
    <div className="page videos-page">
      <section className="hero videos-hero" aria-label="Eagles media background">
        <div className="hero-bg videos-hero-bg"></div>
        <div className="hero-content videos-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Eagles Media</h1>
          <p className="page-subtitle">Watch our latest videos and highlights</p>
        </div>
      </Reveal>

      <div className="videos-grid">
        {!hasVideosResponse ? (
          VIDEO_SKELETON_ITEMS.map((item) => (
            <div key={`video-skeleton-${item}`} className="video-card video-card--skeleton" aria-hidden="true">
              <div className="video-thumbnail">
                <Skeleton variant="rectangular" width="100%" height="100%" />
              </div>
              <div className="video-info">
                <Skeleton variant="text" width="78%" height={34} sx={{ mb: 0.5 }} />
                <Skeleton variant="text" width="92%" height={24} />
                <Skeleton variant="text" width="84%" height={24} sx={{ mb: 1.1 }} />
                <div className="video-meta">
                  <Skeleton variant="text" width={96} height={20} />
                  <Skeleton variant="text" width={60} height={20} />
                </div>
              </div>
            </div>
          ))
        ) : visibleVideos.length > 0 ? (
          visibleVideos.map((video, idx) => {
            const videoImage = resolveImageFromItem(video, [
              'thumbnailUrl',
              'thumbnail_url',
              'thumbnail',
              'imageUrl',
              'image_url',
              'image',
              'cover',
              'media.0.url',
            ]);
            const videoSource = resolveVideoSource(video);
            const hasPlayableSource = Boolean(videoSource);
            const sourceType = videoSourceType(videoSource);

            return (
              <Reveal key={video.id || `${currentPage}-${idx}`} delay={idx * 60}>
                <div className={`video-card video-card--${sourceType}`}>
                  <div className="video-thumbnail">
                    {videoImage ? (
                      <img src={videoImage} alt={video.title} loading="lazy" decoding="async" />
                    ) : (
                      <div className="thumbnail-placeholder">
                        <Camera size={48} />
                      </div>
                    )}
                    <button
                      type="button"
                      className="play-overlay"
                      onClick={() => handlePlay(videoSource)}
                      disabled={!hasPlayableSource}
                      title={hasPlayableSource ? 'Play video' : 'No playable video URL from API'}
                    >
                      {hasPlayableSource ? <Play size={26} fill="currentColor" /> : 'N/A'}
                    </button>
                    {hasPlayableSource ? (
                      <span className="video-source-badge">
                        Posted by Admin
                      </span>
                    ) : null}
                    <div className="video-card-overlay">
                      <h3 className="video-title">{video.title || 'Untitled Video'}</h3>
                      <p className="video-description">{video.description || ''}</p>
                    </div>
                  </div>
                </div>
              </Reveal>
            );
          })
        ) : hasVideosResponse ? (
          <div className="empty-state">
            <Camera size={48} />
            <p>No videos available</p>
          </div>
        ) : null}
      </div>

      {totalPages > 1 && (
        <div className="videos-pagination">
          <button
            className="videos-page-btn"
            onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
            disabled={currentPage === 1}
          >
            Prev
          </button>
          {Array.from({ length: totalPages }, (_, idx) => idx + 1).map((pageNumber) => (
            <button
              key={pageNumber}
              className={`videos-page-btn ${currentPage === pageNumber ? 'active' : ''}`}
              onClick={() => setCurrentPage(pageNumber)}
            >
              {pageNumber}
            </button>
          ))}
          <button
            className="videos-page-btn"
            onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </button>
        </div>
      )}

      {activeVideo && (
        <div className="video-modal" role="dialog" aria-modal="true" aria-label="Video player" onClick={() => setActiveVideo(null)}>
          <div className="video-modal-content" onClick={(event) => event.stopPropagation()}>
            <button type="button" className="video-modal-close" onClick={() => setActiveVideo(null)} aria-label="Close video">
              <X size={20} />
            </button>

            {activeEmbedUrl ? (
              <iframe
                className="video-modal-frame"
                src={activeEmbedUrl}
                title="Video player"
                allow="autoplay; fullscreen; picture-in-picture"
                allowFullScreen
              />
            ) : isDirectVideoFile ? (
              <video className="video-modal-frame" src={activeVideoUrl} controls autoPlay playsInline />
            ) : (
              <div className="video-modal-fallback">
                <p>No embedded player available for this source.</p>
                <a href={activeVideoUrl} target="_blank" rel="noreferrer">
                  Open video in new tab
                </a>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
