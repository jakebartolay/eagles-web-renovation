import { useEffect, useState } from 'react'
import PublicShell from './PublicShell'
import useStylesheet from '../hooks/useStylesheet'

export default function StaticOfficerGridPage({
  stylesheetUrl,
  title,
  subtitle,
  sections,
}) {
  const [activeOfficer, setActiveOfficer] = useState(null)

  useStylesheet(stylesheetUrl)

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveOfficer(null)
      }
    }

    window.addEventListener('keydown', handleKeyDown)

    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [])

  return (
    <PublicShell>
      <div
        className={`modal-overlay ${activeOfficer ? 'show' : ''}`}
        onClick={() => setActiveOfficer(null)}
        aria-hidden="true"
      />

      <section className="officers-hero">
        <h1>{title}</h1>
        <p>{subtitle}</p>
      </section>

      <section className="org-chart">
        {sections.map((section) => (
          <div className={section.className} key={section.className}>
            {section.items.map((officer) => (
              <div
                key={`${section.className}-${officer.name}`}
                className={officer.cardClassName || 'officer-card'}
                role="button"
                tabIndex={0}
                onClick={() => setActiveOfficer(officer)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    setActiveOfficer(officer)
                  }
                }}
              >
                <img src={officer.imageUrl} alt={officer.name} />
                <div className="officer-info">
                  <h4>
                    <span className="officer-eagle">Eagle</span>
                    {officer.name}
                  </h4>
                  <p>{officer.role}</p>
                </div>
              </div>
            ))}
          </div>
        ))}
      </section>

      {activeOfficer ? (
        <div
          className="detail-panel show"
          role="dialog"
          aria-modal="true"
          aria-label={activeOfficer.name}
        >
          <img
            src={activeOfficer.modalImageUrl || activeOfficer.imageUrl}
            alt={activeOfficer.name}
          />
          <h3>
            <span className="officer-eagle">Eagle</span>
            {activeOfficer.name}
          </h3>
          <p className="role">{activeOfficer.role}</p>
          <p className="speech">"{activeOfficer.speech}"</p>
          <button
            type="button"
            className="close"
            onClick={() => setActiveOfficer(null)}
            aria-label="Close modal"
          >
            ×
          </button>
        </div>
      ) : null}
    </PublicShell>
  )
}
