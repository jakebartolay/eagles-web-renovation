import { useEffect, useState } from 'react'

const API_URL = 'http://localhost/tfeope-api/v1/client/officers/get_all.php?category=peil_directors'

export default function PeilDirectors() {
  const [directors, setDirectors] = useState([])
  const [loading, setLoading]     = useState(true)
  const [active, setActive]       = useState(null)

  useEffect(() => {
    fetch(API_URL)
      .then(r => r.json())
      .then(data => {
        if (data.success) setDirectors(data.data)
        setLoading(false)
      })
      .catch(() => setLoading(false))
  }, [])

  useEffect(() => {
    function onKey(e) { if (e.key === 'Escape') setActive(null) }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  return (
    <>
      <style>{`
        .peil-hero {
          background: #1a1a18;
          color: #fff;
          text-align: center;
          padding: 60px 24px;
        }
        .peil-hero h1 { font-size: 36px; font-weight: 700; margin: 0 0 8px; }
        .peil-hero p  { font-size: 15px; color: #aaa; margin: 0; }
        .peil-section { max-width: 1000px; margin: 0 auto; padding: 56px 24px; }
        .peil-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 16px;
        }
        .peil-card {
          background: #fff;
          border: 0.5px solid #e0dfd8;
          border-radius: 10px;
          padding: 20px 16px;
          text-align: center;
          cursor: pointer;
          transition: border-color 0.15s;
        }
        .peil-card:hover { border-color: #aaa; }
        .peil-card img {
          width: 72px; height: 72px;
          border-radius: 50%;
          object-fit: cover;
          margin-bottom: 12px;
        }
        .peil-card-name {
          font-size: 13px;
          font-weight: 500;
          color: #1a1a18;
          margin: 0 0 4px;
          line-height: 1.3;
        }
        .peil-card-pos {
          font-size: 12px;
          color: #888;
          line-height: 1.3;
          margin: 0;
        }
        .modal-backdrop {
          position: fixed; inset: 0;
          background: rgba(0,0,0,0.55);
          z-index: 200;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 24px;
        }
        .modal-box {
          background: #fff;
          border-radius: 12px;
          padding: 32px;
          max-width: 400px;
          width: 100%;
          text-align: center;
          position: relative;
        }
        .modal-close-btn {
          position: absolute; top: 12px; right: 16px;
          background: none; border: none;
          font-size: 22px; cursor: pointer;
          color: #888; line-height: 1;
        }
        .modal-box img {
          width: 88px; height: 88px;
          border-radius: 50%;
          object-fit: cover;
          margin-bottom: 16px;
        }
        .modal-box h3 { font-size: 17px; font-weight: 500; margin: 0 0 6px; }
        .modal-box .role { font-size: 13px; color: #888; margin: 0 0 12px; }
        .modal-box .speech {
          font-size: 14px; color: #444;
          font-style: italic; margin: 0;
        }
        @media (max-width: 600px) {
          .peil-hero h1 { font-size: 26px; }
          .peil-grid { grid-template-columns: repeat(2, 1fr); }
        }
      `}</style>

      <section className="peil-hero">
        <h1>PEIL Directors</h1>
        <p>Meet the leaders guiding our organization</p>
      </section>

      <section className="peil-section">
        {loading ? (
          <p style={{ textAlign: 'center', color: '#888' }}>Loading...</p>
        ) : (
          <div className="peil-grid">
            {directors.length > 0 ? directors.map(d => (
              <div
                key={d.id}
                className="peil-card"
                onClick={() => setActive(d)}
              >
                <img
                  src={d.imageUrl || '/static/placeholder.png'}
                  alt={d.name}
                  onError={e => e.target.src = '/static/placeholder.png'}
                />
                <p className="peil-card-name">{d.name}</p>
                <p className="peil-card-pos">{d.position}</p>
              </div>
            )) : (
              <p style={{ color: '#888', textAlign: 'center', gridColumn: '1/-1' }}>
                No directors found.
              </p>
            )}
          </div>
        )}
      </section>

      {active && (
        <div className="modal-backdrop" onClick={() => setActive(null)}>
          <div className="modal-box" onClick={e => e.stopPropagation()}>
            <button className="modal-close-btn" onClick={() => setActive(null)}>×</button>
            <img
              src={active.imageUrl || '/static/placeholder.png'}
              alt={active.name}
              onError={e => e.target.src = '/static/placeholder.png'}
            />
            <h3>{active.name}</h3>
            <p className="role">{active.fullPosition}</p>
            {active.speech && (
              <p className="speech">"{active.speech}"</p>
            )}
          </div>
        </div>
      )}
    </>
  )
}