import Reveal from '../components/Reveal';
import { useEffect, useRef } from 'react';

const HISTORY_EVENTS = [
  {
    year: '1920',
    title: 'Foundation',
    description: 'The Fraternal Order of Eagles was founded, emphasizing service and brotherhood.',
  },
  {
    year: '1950',
    title: 'Expansion',
    description: 'The organization expanded nationwide, opening chapters across provinces.',
  },
  {
    year: '1980',
    title: 'Community Outreach',
    description: 'Large-scale community programs including disaster relief and education.',
  },
  {
    year: '2000',
    title: 'Modern Era',
    description: 'Digital platforms and technology strengthened member connection.',
  },
  {
    year: '2026',
    title: 'Today',
    description: 'Continuing a legacy of unity, service, and integrity.',
  },
];

const EAGLES_ACRONYM = [
  'E - Enlightened and innovative humanitarians',
  'A - Animated primarily by a strong bond of brotherhood and fraternal ties',
  'G - God-fearing God-conscious non-sectarian',
  'L - Law-abiding liberty-oriented',
  'E - Emblazed with intense mission of',
  'S - Service to country, its people and its Community',
];

export default function HistoryPage() {
  const timelineRef = useRef(null);
  const eagleismRef = useRef(null);

  useEffect(() => {
    const container = timelineRef.current;
    if (!container) return undefined;

    const items = Array.from(container.querySelectorAll('.history-timeline-item'));
    const reduceMotion =
      typeof window !== 'undefined' &&
      window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) {
      items.forEach((item) => item.classList.add('in-view'));
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.2, rootMargin: '0px 0px -8% 0px' },
    );

    items.forEach((item) => observer.observe(item));
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const section = eagleismRef.current;
    if (!section) return undefined;

    const reduceMotion =
      typeof window !== 'undefined' &&
      window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) return undefined;

    let raf = 0;
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    const updateParallax = () => {
      const rect = section.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;

      // 0..1 progress while section moves through viewport
      const progress = clamp((vh - rect.top) / (vh + rect.height), 0, 1);
      const posY = 22 + progress * 56;
      section.style.setProperty('--eagleism-bg-y', `${posY.toFixed(1)}%`);
    };

    const onScroll = () => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        updateParallax();
      });
    };

    updateParallax();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);

    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
      section.style.removeProperty('--eagleism-bg-y');
    };
  }, []);

  return (
    <div className="page history-page">
      <section className="hero history-hero" aria-label="History background">
        <div className="hero-bg history-hero-bg"></div>
        <div className="hero-content history-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Our History</h1>
          <p className="page-subtitle">Journey of brotherhood, service, and leadership through the years</p>
        </div>
      </Reveal>

      <section className="history-timeline-section" aria-label="History timeline" ref={timelineRef}>
        <div className="history-timeline-line" aria-hidden="true"></div>
        {HISTORY_EVENTS.map((event, idx) => (
          <Reveal key={event.year} delay={idx * 60}>
            <article className={`history-timeline-item ${idx % 2 === 0 ? 'left' : 'right'}`}>
              <div className="history-timeline-dot" aria-hidden="true"></div>
              <div className="history-timeline-card">
                <h3>
                  {event.year} - {event.title}
                </h3>
                <p>{event.description}</p>
              </div>
            </article>
          </Reveal>
        ))}
      </section>

      <section className="history-eagleism-section" aria-label="Eagleism statement" ref={eagleismRef}>
        <Reveal>
          <div className="history-eagleism-panel">
            <h2>EAGLEISM</h2>
            <p>
              Eagleism is fraternalism, or that state of relationship characteristic of brothers. In the Philippine
              Eagles, members must have primordially developed a deep sense of brotherhood among them. It is the
              primacy of their relationship as brothers.
            </p>
          </div>
        </Reveal>
      </section>

      <section className="history-shall-section" aria-label="The Philippine Eagles shall be">
        <Reveal>
          <div className="history-shall-card">
            <div className="history-shall-left">
              <h2>The Philippine Eagles</h2>
              <p>shall be:</p>
            </div>
            <div className="history-shall-right">
              {EAGLES_ACRONYM.map((line) => (
                <p key={line}>{line}</p>
              ))}
            </div>
          </div>
        </Reveal>
      </section>
    </div>
  );
}
