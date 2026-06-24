import { useEffect, useState } from 'react';
import { navigateTo } from '../components/HashRouter';
import { useAuth } from '../context/AuthContext';
import { API_ENDPOINTS, buildApiUrl, resolveMediaUrl } from '../config/api';

/* ── helpers ─────────────────────────────────────────────────────── */
function InfoRow({ label, value }) {
  if (!value) return null;
  return (
    <div className="profile-info-row">
      <span className="profile-info-row__label">{label}</span>
      <span className="profile-info-row__value">{value}</span>
    </div>
  );
}

function StatusBadge({ status }) {
  const up = String(status || '').toUpperCase();
  const tone = up === 'ACTIVE' ? 'positive' : up ? 'danger' : 'neutral';
  return <span className={`profile-status-badge profile-status-badge--${tone}`}>{up || 'UNKNOWN'}</span>;
}

async function authFetch(url, opts = {}) {
  const res = await fetch(url, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...opts,
  });
  const text = await res.text();
  try { return JSON.parse(text); } catch { throw new Error('Unexpected server response.'); }
}

/* ── Link Eagles ID sub-form ─────────────────────────────────────── */
function LinkEaglesIdForm({ onLinked }) {
  const [eaglesId, setEaglesId] = useState('');
  const [error, setError]       = useState('');
  const [loading, setLoading]   = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    const id = eaglesId.trim().toUpperCase();
    if (!id) { setError('Please enter your Eagles ID.'); return; }
    if (!/^TFOEPE[0-9]{8}$/.test(id)) {
      setError('Format: TFOEPE + 8 digits (e.g. TFOEPE00000001)');
      return;
    }

    setLoading(true);
    try {
      const data = await authFetch(buildApiUrl(API_ENDPOINTS.auth.linkEaglesId), {
        method: 'POST',
        body: JSON.stringify({ eaglesId: id }),
      });
      if (!data?.success) throw new Error(data?.message || 'Failed to link Eagles ID.');
      onLinked(data.data);
    } catch (err) {
      setError(err.message || 'Something went wrong.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="profile-link-box">
      <div className="profile-link-box__header">
        <span className="profile-link-box__icon">🦅</span>
        <div>
          <h3 className="profile-link-box__title">Link Your Eagles ID</h3>
          <p className="profile-link-box__desc">
            Connect your account to your member record. Your ID must be <strong>ACTIVE</strong>.
          </p>
        </div>
      </div>

      {error && <div className="auth-alert auth-alert--error" role="alert">{error}</div>}

      <form className="profile-link-box__form" onSubmit={handleSubmit} noValidate>
        <input
          type="text"
          className="auth-form__input"
          value={eaglesId}
          onChange={(e) => setEaglesId(e.target.value.toUpperCase())}
          placeholder="e.g. TFOEPE00000001"
          disabled={loading}
          style={{ textTransform: 'uppercase' }}
        />
        <button type="submit" className="auth-form__btn auth-form__btn--sm" disabled={loading}>
          {loading ? 'Verifying…' : 'Link ID'}
        </button>
      </form>
    </div>
  );
}

/* ── Main Profile Page ───────────────────────────────────────────── */
export default function ProfilePage() {
  const { user, logout, ready, setUser } = useAuth();
  const [member, setMember]     = useState(null);
  const [fetching, setFetching] = useState(false);
  const [fetchErr, setFetchErr] = useState('');

  // Redirect if not logged in
  useEffect(() => {
    if (ready && !user) navigateTo('/login');
  }, [ready, user]);

  // Fetch member data once Eagles ID is available
  useEffect(() => {
    if (!user?.eaglesId) return;

    setFetching(true);
    setFetchErr('');

    fetch(buildApiUrl(`${API_ENDPOINTS.members.single}?id=${encodeURIComponent(user.eaglesId)}`), {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((data) => {
        if (data?.success && data?.data) setMember(data.data);
        else setFetchErr('Could not load your member profile.');
      })
      .catch(() => setFetchErr('Network error while loading your profile.'))
      .finally(() => setFetching(false));
  }, [user?.eaglesId]);

  const handleLogout = async () => {
    await logout();
    navigateTo('/');
  };

  const handleLinked = (linkData) => {
    if (typeof setUser === 'function') {
      setUser((prev) => ({ ...prev, eaglesId: linkData.eaglesId, name: linkData.name }));
    }
  };

  if (!ready || !user) {
    return (
      <div className="auth-page">
        <div className="auth-card" style={{ textAlign: 'center', padding: '2rem' }}>
          <p style={{ color: '#94a3b8' }}>Loading…</p>
        </div>
      </div>
    );
  }

  const photoUrl = member?.picUrl ? resolveMediaUrl(member.picUrl) : null;
  const fullName = member
    ? `${member.firstName || ''} ${member.lastName || ''}`.trim()
    : user.name;

  return (
    <div className="auth-page profile-page">
      <div className="profile-card">

        {/* ── Header ── */}
        <div className="profile-card__header">
          <div className="profile-avatar-wrap">
            {photoUrl ? (
              <img src={photoUrl} alt={fullName} className="profile-avatar" />
            ) : (
              <div className="profile-avatar profile-avatar--placeholder">
                {fullName?.charAt(0)?.toUpperCase() || '?'}
              </div>
            )}
          </div>
          <div className="profile-card__header-info">
            <h1 className="profile-name">{fullName}</h1>
            {member && <StatusBadge status={member.status} />}
            <p className="profile-username">@{user.username}</p>
          </div>
        </div>

        {/* ── No Eagles ID: soft prompt ── */}
        {!user.eaglesId && (
          <LinkEaglesIdForm onLinked={handleLinked} />
        )}

        {/* ── Has Eagles ID: member data ── */}
        {user.eaglesId && (
          <>
            {fetching && <p className="profile-loading">Loading member profile…</p>}
            {fetchErr && !fetching && (
              <div className="auth-alert auth-alert--error">{fetchErr}</div>
            )}
            {member && !fetching && (
              <div className="profile-info-grid">
                <InfoRow label="Eagles ID"         value={member.id} />
                <InfoRow label="Position"          value={member.position} />
                <InfoRow label="Regional Position" value={member.regionalPosition} />
                <InfoRow label="Club"              value={member.club} />
                <InfoRow label="Region"            value={member.region} />
                <InfoRow label="Member Since"      value={member.dateAdded
                  ? new Date(member.dateAdded).toLocaleDateString('en-PH', {
                      year: 'numeric', month: 'long', day: 'numeric',
                    })
                  : null}
                />
              </div>
            )}
          </>
        )}

        {/* ── Actions ── */}
        <div className="profile-actions">
          <button className="auth-form__btn auth-form__btn--outline" onClick={handleLogout}>
            Sign Out
          </button>
        </div>

      </div>
    </div>
  );
}
