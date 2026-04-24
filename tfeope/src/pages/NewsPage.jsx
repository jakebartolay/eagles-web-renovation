import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';

const NEWS_SKELETON_ITEMS = Array.from({ length: 6 }, (_, idx) => idx);

export default function NewsPage() {
  const [news, setNews] = useState([]);
  const [hasNewsResponse, setHasNewsResponse] = useState(false);

  useEffect(() => {
    setHasNewsResponse(false);

    fetchJson(API_ENDPOINTS.news)
      .then((data) => {
        setNews(extractList(data, ['news']));
      })
      .catch(() => {})
      .finally(() => {
        setHasNewsResponse(true);
      });
  }, []);

  return (
    <div className="page news-page">
      <section className="hero news-hero" aria-label="News background">
        <div className="hero-bg news-hero-bg"></div>
        <div className="hero-content news-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Latest News</h1>
          <p className="page-subtitle">Stay updated with Eagles announcements and stories</p>
        </div>
      </Reveal>

      <div className="news-grid">
        {!hasNewsResponse ? (
          NEWS_SKELETON_ITEMS.map((item) => (
            <div key={`news-skeleton-${item}`} className="news-card news-card--skeleton" aria-hidden="true">
              <div className="news-image-wrap">
                <Skeleton variant="rectangular" width="100%" height="100%" />
              </div>
              <Skeleton variant="rounded" width={64} height={24} sx={{ mb: 1.4 }} />
              <Skeleton variant="text" width="88%" height={36} sx={{ mb: 0.5 }} />
              <Skeleton variant="text" width="95%" height={24} />
              <Skeleton variant="text" width="82%" height={24} sx={{ mb: 1.3 }} />
              <div className="news-meta">
                <Skeleton variant="text" width={108} height={22} />
                <Skeleton variant="text" width={84} height={22} />
              </div>
            </div>
          ))
        ) : news.length > 0 ? (
          news.map((item, idx) => {
            const newsImage = resolveImageFromItem(item, [
              'imageUrl',
              'image_url',
              'thumbnailUrl',
              'thumbnail_url',
              'image',
              'thumbnail',
              'photo',
              'cover',
              'media.0.url',
            ]);

            return (
              <Reveal key={idx} delay={idx * 60}>
                <div className="news-card">
                  {newsImage && (
                    <div className="news-image-wrap">
                      <img
                        src={newsImage}
                        alt={item.title || 'News image'}
                        className="news-image"
                        loading="lazy"
                        decoding="async"
                      />
                    </div>
                  )}
                  <div className="news-tag">NEWS</div>
                  <h3 className="news-title">{item.title || 'Untitled News'}</h3>
                  <p className="news-excerpt">{item.content || item.description || 'No description available'}</p>
                  <div className="news-meta">
                    <span className="news-date">{item.date || item.created_at || 'Recent'}</span>
                    <button className="news-read-more">Read More -&gt;</button>
                  </div>
                </div>
              </Reveal>
            );
          })
        ) : hasNewsResponse ? (
          <div className="empty-state">
            <FileText size={48} />
            <p>No news available</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
