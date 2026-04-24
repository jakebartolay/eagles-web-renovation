import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';

const MAGNA_SKELETON_ITEMS = Array.from({ length: 6 }, (_, idx) => idx);
const MAGNA_FALLBACK_IMAGE = '/assets/magna-carta.png';

export default function MagnaCartaPage() {
  const [entries, setEntries] = useState([]);
  const [hasEntriesResponse, setHasEntriesResponse] = useState(false);
  const [brokenImages, setBrokenImages] = useState({});

  useEffect(() => {
    setHasEntriesResponse(false);

    fetchJson(API_ENDPOINTS.magnaCarta)
      .then((data) => {
        setEntries(extractList(data, ['magna_carta', 'magnaCarta', 'magna']));
      })
      .catch(() => {})
      .finally(() => {
        setHasEntriesResponse(true);
      });
  }, []);

  useEffect(() => {
    setBrokenImages({});
  }, [entries]);

  return (
    <div className="page magna-carta-page">
      <section className="hero magna-carta-hero" aria-label="Magna Carta background">
        <div className="hero-bg magna-carta-hero-bg"></div>
        <div className="hero-content magna-carta-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Magna Carta</h1>
          <p className="page-subtitle">Core principles, standards, and member duties</p>
        </div>
      </Reveal>

      <div className="about-grid">
        {!hasEntriesResponse ? (
          MAGNA_SKELETON_ITEMS.map((item) => (
            <article key={`magna-skeleton-${item}`} className="about-card about-card--skeleton" aria-hidden="true">
              <div className="about-card-image-wrap">
                <Skeleton variant="rectangular" width="100%" height="100%" />
              </div>
              <Skeleton variant="text" width="72%" height={34} sx={{ mb: 0.5 }} />
              <Skeleton variant="text" width="95%" height={24} />
              <Skeleton variant="text" width="88%" height={24} />
            </article>
          ))
        ) : entries.length > 0 ? (
          entries.map((item, idx) => {
            const entryImage = resolveImageFromItem(item, [
              'imageUrl',
              'image_url',
              'coverUrl',
              'cover_url',
              'thumbnailUrl',
              'thumbnail_url',
              'media.0.url',
            ]);
            const itemKey = String(item.id || idx);
            const title = item.title || item.name || `Magna Carta Entry ${idx + 1}`;
            const content = item.content || item.description || item.body || 'No details available.';
            const resolvedImage = !entryImage || brokenImages[itemKey]
              ? MAGNA_FALLBACK_IMAGE
              : entryImage;

            return (
              <Reveal key={item.id || idx} delay={idx * 60}>
                <article className="about-card">
                  <div className="about-card-image-wrap">
                    <img
                      src={resolvedImage}
                      alt={title}
                      className="about-card-image"
                      loading="lazy"
                      decoding="async"
                      onError={() => {
                        setBrokenImages((current) => (
                          current[itemKey]
                            ? current
                            : { ...current, [itemKey]: true }
                        ));
                      }}
                    />
                  </div>
                  <h3 className="about-card-title">{title}</h3>
                  <p className="about-card-text">{content}</p>
                </article>
              </Reveal>
            );
          })
        ) : hasEntriesResponse ? (
          <div className="empty-state">
            <FileText size={48} />
            <p>No magna carta entries available</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
