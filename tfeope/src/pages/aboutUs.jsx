import { useEffect, useRef } from 'react'
import PublicShell from '../components/PublicShell'

const timelineEvents = [
  {
    year: '1920 - Foundation',
    desc: 'The Fraternal Order of Eagles was founded, emphasizing service and brotherhood.',
  },
  {
    year: '1950 - Expansion',
    desc: 'The organization expanded nationwide, opening chapters across provinces.',
  },
  {
    year: '1980 - Community Outreach',
    desc: 'Large-scale community programs including disaster relief and education.',
  },
  {
    year: '2000 - Modern Era',
    desc: 'Digital platforms and technology strengthened member connection.',
  },
  {
    year: '2026 - Today',
    desc: 'Continuing a legacy of unity, service, and integrity.',
  },
]

const acronym = [
  { letter: 'E', meaning: 'Enlightened and innovative humanitarians' },
  { letter: 'A', meaning: 'Animated primarily by a strong bond of brotherhood and fraternal ties' },
  { letter: 'G', meaning: 'God-fearing God-conscious non-sectarian' },
  { letter: 'L', meaning: 'Law-abiding liberty-oriented' },
  { letter: 'E', meaning: 'Emblazed with intense mission of' },
  { letter: 'S', meaning: 'Service to country, its people and its Community' },
]

const aboutHeroUrl = new URL('../static/about_us.jpg', import.meta.url).href
const aboutHeaderUrl = new URL('../static/aboutHeader.png', import.meta.url).href

export default function AboutUs() {
  const timelineRef = useRef(null)
  const timelineBgRef = useRef(null)
  const eagleSectionRef = useRef(null)
  const eagleImageRef = useRef(null)
  const eagleTitleRef = useRef(null)
  const eagleTextRef = useRef(null)

  useEffect(() => {
    const reduceMotion =
      window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

    const timeline = timelineRef.current
    const timelineBg = timelineBgRef.current
    const eagleSection = eagleSectionRef.current
    const eagleImage = eagleImageRef.current
    const eagleTitle = eagleTitleRef.current
    const eagleText = eagleTextRef.current

    let frame = 0

    function updateTimelineBackground() {
      if (!timeline || !timelineBg) {
        return
      }

      const rect = timeline.getBoundingClientRect()
      const viewportHeight = window.innerHeight
      const progress = Math.max(0, Math.min(1, (viewportHeight - rect.top) / (viewportHeight + rect.height)))
      const posY = 10 + progress * 75
      const shift = Math.max(0, Math.min(1, rect.top / viewportHeight)) * 120

      timelineBg.style.backgroundPosition = `50% ${posY.toFixed(1)}%`
      timelineBg.style.transform = `translate3d(0, ${shift.toFixed(0)}px, 0)`
    }

    function updateEagleSection() {
      if (!eagleSection || !eagleImage || !eagleTitle || !eagleText) {
        return
      }

      const rect = eagleSection.getBoundingClientRect()
      const viewportHeight = window.innerHeight
      const inView = rect.top < viewportHeight * 0.78 && rect.bottom > viewportHeight * 0.22

      eagleSection.classList.toggle('is-hidden', !inView)
      eagleSection.classList.toggle('revealed', inView)
      eagleTitle.classList.toggle('in', inView)
      eagleText.classList.toggle('in', inView)

      if (!reduceMotion && rect.bottom > 0 && rect.top < viewportHeight) {
        const center = rect.top + rect.height / 2
        const progress = (center - viewportHeight / 2) / (viewportHeight / 2)
        const scale = 1.14 + Math.min(1, Math.abs(progress)) * 0.08
        eagleImage.style.transform = `translate3d(0, 0, 0) scale(${scale})`
      }
    }

    function onScroll() {
      if (frame) {
        return
      }

      frame = window.requestAnimationFrame(() => {
        frame = 0
        updateTimelineBackground()
        updateEagleSection()
      })
    }

    updateTimelineBackground()
    updateEagleSection()

    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)

    return () => {
      if (frame) {
        window.cancelAnimationFrame(frame)
      }
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
    }
  }, [])

  return (
    <PublicShell>
      <style>{`
        .about-page {
          background: linear-gradient(180deg, #f4f7fc 0%, #eef3fb 40%, #f8fbff 100%);
          color: #17263d;
        }

        .about-history-hero {
          position: relative;
          min-height: 340px;
          display: flex;
          align-items: center;
          justify-content: center;
          text-align: center;
          color: #fff;
          background: center/cover no-repeat;
          overflow: hidden;
        }

        .about-history-hero::after {
          content: "";
          position: absolute;
          inset: 0;
          background: rgba(4, 15, 34, 0.5);
        }

        .about-history-hero h1 {
          position: relative;
          z-index: 1;
          margin: 0;
          padding: 0 20px;
          font-family: "Merriweather", serif;
          font-size: clamp(38px, 5vw, 58px);
          font-weight: 900;
        }

        .about-timeline {
          position: relative;
          overflow: hidden;
          padding: 90px 24px;
        }

        .about-timeline-bg {
          position: absolute;
          inset: 0;
          background:
            linear-gradient(rgba(10, 23, 46, 0.62), rgba(10, 23, 46, 0.45)),
            url("${aboutHeaderUrl}") center/cover no-repeat;
          opacity: 0.2;
          will-change: transform, background-position;
        }

        .about-timeline-inner {
          position: relative;
          z-index: 1;
          max-width: 980px;
          margin: 0 auto;
          display: grid;
          gap: 18px;
        }

        .about-timeline-card {
          padding: 24px 26px;
          border-radius: 20px;
          background: rgba(255, 255, 255, 0.9);
          border: 1px solid rgba(20, 61, 107, 0.08);
          box-shadow: 0 18px 40px rgba(16, 45, 81, 0.08);
          backdrop-filter: blur(10px);
        }

        .about-timeline-card h3 {
          margin: 0 0 10px;
          font-family: "Merriweather", serif;
          font-size: 22px;
          color: #0f2e57;
        }

        .about-timeline-card p {
          margin: 0;
          line-height: 1.8;
          color: #375275;
        }

        .about-eagleism {
          position: relative;
          min-height: 560px;
          overflow: hidden;
          background: #071830;
        }

        .about-eagleism__image {
          position: absolute;
          inset: 0;
          width: 100%;
          height: 100%;
          object-fit: cover;
          transform: scale(1.14);
          transition: transform 0.35s ease;
        }

        .about-eagleism__overlay {
          position: absolute;
          inset: 0;
          background: rgba(3, 16, 34, 0.58);
        }

        .about-eagleism__content {
          position: relative;
          z-index: 1;
          max-width: 980px;
          margin: 0 auto;
          padding: 110px 24px;
          text-align: center;
          color: #fff;
        }

        .about-eagleism__title {
          margin: 0 0 18px;
          font-family: "Merriweather", serif;
          font-size: clamp(34px, 5vw, 60px);
          line-height: 1.05;
          opacity: 0;
          transform: translateX(-32px);
          transition: transform 0.6s ease, opacity 0.6s ease;
        }

        .about-eagleism__text {
          max-width: 860px;
          margin: 0 auto;
          font-size: 18px;
          line-height: 1.9;
          color: rgba(255, 255, 255, 0.92);
          opacity: 0;
          transform: translateX(32px);
          transition: transform 0.6s ease 0.12s, opacity 0.6s ease 0.12s;
        }

        .about-eagleism.revealed .about-eagleism__title,
        .about-eagleism.revealed .about-eagleism__text,
        .about-eagleism__title.in,
        .about-eagleism__text.in {
          opacity: 1;
          transform: translateX(0);
        }

        .about-eagleism.is-hidden .about-eagleism__title,
        .about-eagleism.is-hidden .about-eagleism__text {
          opacity: 0;
        }

        .about-shall-be {
          padding: 90px 24px;
        }

        .about-shall-be__card {
          max-width: 1100px;
          margin: 0 auto;
          padding: 40px;
          border-radius: 28px;
          background: #fff;
          box-shadow: 0 24px 60px rgba(16, 45, 81, 0.08);
          border: 1px solid rgba(20, 61, 107, 0.08);
        }

        .about-shall-be__layout {
          display: grid;
          grid-template-columns: minmax(220px, 0.85fr) minmax(0, 1.15fr);
          gap: 34px;
        }

        .about-shall-be__title {
          margin: 0;
          font-family: "Merriweather", serif;
          font-size: clamp(30px, 4vw, 44px);
          line-height: 1.08;
          color: #0f2e57;
        }

        .about-shall-be__subtitle {
          margin: 12px 0 0;
          color: #44617f;
          font-size: 18px;
          font-weight: 700;
        }

        .about-acronym-list {
          display: grid;
          gap: 14px;
        }

        .about-acronym-item {
          display: grid;
          grid-template-columns: 34px minmax(0, 1fr);
          gap: 14px;
          align-items: start;
          color: #283d5a;
          line-height: 1.8;
        }

        .about-acronym-item strong {
          display: inline-block;
          font-size: 28px;
          line-height: 1;
          color: #1a4f88;
        }

        @media (max-width: 800px) {
          .about-timeline {
            padding: 72px 16px;
          }

          .about-eagleism {
            min-height: 500px;
          }

          .about-eagleism__content,
          .about-shall-be {
            padding-left: 16px;
            padding-right: 16px;
          }

          .about-shall-be__card {
            padding: 28px 22px;
          }

          .about-shall-be__layout {
            grid-template-columns: 1fr;
            gap: 22px;
          }
        }
      `}</style>

      <div className="about-page">
        <section className="about-history-hero" style={{ backgroundImage: `url(${aboutHeroUrl})` }}>
          <h1>Our History</h1>
        </section>

        <section className="about-timeline" ref={timelineRef}>
          <div className="about-timeline-bg" ref={timelineBgRef} aria-hidden="true"></div>
          <div className="about-timeline-inner">
            {timelineEvents.map((eventItem) => (
              <article className="about-timeline-card" key={eventItem.year}>
                <h3>{eventItem.year}</h3>
                <p>{eventItem.desc}</p>
              </article>
            ))}
          </div>
        </section>

        <section className="about-eagleism is-hidden" ref={eagleSectionRef}>
          <img
            className="about-eagleism__image"
            ref={eagleImageRef}
            src={aboutHeaderUrl}
            alt="Philippine Eagles"
          />
          <div className="about-eagleism__overlay"></div>
          <div className="about-eagleism__content">
            <h2 className="about-eagleism__title" ref={eagleTitleRef}>
              EAGLEISM
            </h2>
            <p className="about-eagleism__text" ref={eagleTextRef}>
              <strong>Eagleism</strong> is fraternalism, or that state of relationship characteristic of
              brothers. In the Philippine Eagles, members must have primordially developed a deep sense of
              brotherhood among them. It is the primacy of their relationship is brotherhood.
            </p>
          </div>
        </section>

        <section className="about-shall-be">
          <div className="about-shall-be__card">
            <div className="about-shall-be__layout">
              <div>
                <h2 className="about-shall-be__title">The Philippine Eagles</h2>
                <p className="about-shall-be__subtitle">shall be:</p>
              </div>

              <div className="about-acronym-list">
                {acronym.map((item) => (
                  <div className="about-acronym-item" key={`${item.letter}-${item.meaning}`}>
                    <strong>{item.letter}</strong>
                    <span>{item.meaning}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>
      </div>
    </PublicShell>
  )
}
