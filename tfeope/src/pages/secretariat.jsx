import { useEffect, useState } from 'react'

const API_URL = 'http://localhost/tfeope-api/v1/client/officers/get_all.php?category=secretariat'

export default function Secretariat() {
  const [members, setMembers] = useState([])
  const [loading, setLoading] = useState(true)
  const [active, setActive]   = useState(null)

  useEffect(() => {
    fetch(API_URL)
      .then(r => r.json())
      .then(data => {
        if (data.success) setMembers(data.data)
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
        .sec-hero {
          background: #1a1a18;
          color: #fff;
          text-align: center;
          padding: 60px 24px;
        }
        .sec-hero h1 { font-size: 36px; font-weight: 700; margin: 0 0 8px; }
        .sec-hero p  { font-size: 15px; color: #aaa; margin: 0; }
        .sec-section { max-width: 1000px; margin: 0 auto; padding: 56px 24px; }
        .sec-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 16px;
        }
        .sec-card {
          background: #fff;
          border: 0.5px solid #e0dfd8;
          border-radius: 10px;
          padding: 20px 16px;
          text-align: center;
          cursor: pointer;
          transition: border-color 0.15s;
        }
        .sec-card:hover { border-color: #aaa; }
        .sec-card img {
          width: 72px; height: 72px;
          border-radius: 50%;
          object-fit: cover;
          margin-bottom: 12px;
        }
        .sec-card-name {
          font-size: 13px; font-weight: 500;
          color: #1a1a18; margin: 0 0 4px;
          line-height: 1.3;
        }
        .sec-card-pos {
          font-size: 12px; color: #888;
          line-height: 1.3; margin: 0;
        }
        .modal-backdrop {
          position: fixed; inset: 0;
          background: rgba(0,0,0,0.55);
          z-index: 200;
          display: flex; align-items: center;
          justify-content: center; padding: 24px;
        }
        .modal-box {
          background: #fff; border-radius: 12px;
          padding: 32px; max-width: 400px;
          width: 100%; text-align: center;
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
          border-radius: 50%; object-fit: cover;
          margin-bottom: 16px;
        }
        .modal-box h3 { font-size: 17px; font-weight: 500; margin: 0 0 6px; }
        .modal-box .role { font-size: 13px; color: #888; margin: 0 0 12px; }
        .modal-box .speech { font-size: 14px; color: #444; font-style: italic; margin: 0; }
        @media (max-width: 600px) {
          .sec-hero h1 { font-size: 26px; }
          .sec-grid { grid-template-columns: repeat(2, 1fr); }
        }
      `}</style>

      <section className="sec-hero">
        <h1>National Secretariat</h1>
        <p>Meet the leaders guiding our organization</p>
      </section>

      <section className="sec-section">
        {loading ? (
          <p style={{ textAlign: 'center', color: '#888' }}>Loading...</p>
        ) : (
          <div className="sec-grid">
            {members.length > 0 ? members.map(m => (
              <div key={m.id} className="sec-card" onClick={() => setActive(m)}>
                <img
                  src={m.imageUrl || '/static/placeholder.png'}
                  alt={m.name}
                  onError={e => e.target.src = '/static/placeholder.png'}
                />
                <p className="sec-card-name">{m.name}</p>
                <p className="sec-card-pos">{m.position}</p>
              </div>
            )) : (
              <p style={{ color: '#888', textAlign: 'center', gridColumn: '1/-1' }}>
                No secretariat members found.
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