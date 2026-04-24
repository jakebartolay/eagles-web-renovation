import { Home, Lock, Shield, UserRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import certifiedStamp from '../assets/certified.gif';
import { navigateTo } from '../components/HashRouter';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';

const normalizeMemberPayload = (payload) => {
  const fromList = extractList(payload, ['members', 'member']);
  if (fromList.length > 0) return fromList[0];

  if (payload?.member && typeof payload.member === 'object') return payload.member;
  if (payload?.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) return payload.data;
  if (payload && typeof payload === 'object' && !Array.isArray(payload)) return payload;
  return null;
};

export default function MemberSearchPage() {
  const [member, setMember] = useState(null);
  const [loading, setLoading] = useState(false);

  const memberId = useMemo(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('id')?.trim() || '';
  }, []);

  useEffect(() => {
    if (!memberId) {
      setMember(null);
      return;
    }

    setLoading(true);

    fetchJson(`${API_ENDPOINTS.members.single}?id=${encodeURIComponent(memberId)}`)
      .then((data) => {
        const normalized = normalizeMemberPayload(data);
        if (!normalized) {
          setMember(null);
        } else {
          setMember(normalized);
        }
      })
      .catch(() => {
        setMember(null);
      })
      .finally(() => setLoading(false));
  }, [memberId]);

  if (loading) {
    return (
      <div className="page member-search-page member-search-page-simple">
        <div className="member-search-verify-header">
          <div className="member-search-status member-search-status--loading">
            <span className="member-search-status-dot" aria-hidden="true"></span>
            <div>
              <strong>Verifying</strong>
              <p>Checking member record.</p>
            </div>
          </div>
        </div>
        <section className="virtual-card virtual-card--skeleton" aria-hidden="true">
          <div className="virtual-card-main">
            <div className="virtual-card-skeleton">
              <Skeleton variant="text" width="28%" height={28} />
              <Skeleton variant="rounded" width="30%" height={190} sx={{ mb: 1.5 }} />
              <Skeleton variant="text" width="72%" height={46} />
              <Skeleton variant="text" width="56%" height={40} />
              <Skeleton variant="text" width="92%" height={28} />
              <Skeleton variant="text" width="48%" height={28} />
              <Skeleton variant="text" width="58%" height={28} />
            </div>
          </div>
        </section>
        <button className="member-search-home-link" type="button" onClick={() => navigateTo('/')}>
          <Home size={18} />
          <span>Back to Home</span>
        </button>
      </div>
    );
  }

  const profileImage = resolveImageFromItem(member, [
    'picUrl',
    'pic_url',
    'imageUrl',
    'image_url',
    'photoUrl',
    'photo_url',
    'profilePic',
    'profile_pic',
    'avatar',
    'media.0.url',
  ]);

  const fallbackName =
    member?.fullName ||
    member?.full_name ||
    member?.name ||
    [member?.firstName, member?.lastName].filter(Boolean).join(' ').trim() ||
    (loading ? 'Loading...' : 'Member Name');
  const normalizedFallbackName = fallbackName.replace(/\s+/g, ' ').trim();
  const fallbackTokens = normalizedFallbackName ? normalizedFallbackName.split(' ') : [];

  const displayFirstName =
    member?.firstName ||
    member?.first_name ||
    (fallbackTokens.length > 1 ? fallbackTokens.slice(0, -1).join(' ') : fallbackTokens[0]) ||
    'Member';

  const displayLastName =
    member?.lastName ||
    member?.last_name ||
    (fallbackTokens.length > 1 ? fallbackTokens[fallbackTokens.length - 1] : '');

  const displayName = [displayFirstName, displayLastName].filter(Boolean).join(' ');

  const displayClub = member?.club || member?.chapter || 'Club Unavailable';
  const displayPosition = member?.position || member?.role || 'Member';
  const displayRegion = member?.region || 'Region Unavailable';
  const displayId = memberId || member?.id || 'N/A';
  const shouldBreakClubLine = displayClub.length > 28 && /eagles club/i.test(displayClub);
  const splitClub = shouldBreakClubLine ? displayClub.split(/(EAGLES CLUB)/i) : [displayClub];
  const showMissingOverlay = Boolean(!loading && !member);

  const updateHologramTilt = (event) => {
    const card = event.currentTarget;
    const bounds = card.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width;
    const y = (event.clientY - bounds.top) / bounds.height;
    const rotateY = (x - 0.5) * 10;
    const rotateX = (0.5 - y) * 7;
    const tiltStrength = Math.min(1, Math.abs(x - 0.5) * 2);
    const shinePosition = 100 - x * 100;

    card.style.setProperty('--shine-x', `${Math.max(0, Math.min(100, shinePosition))}%`);
    card.style.setProperty('--tilt-x', `${rotateX}deg`);
    card.style.setProperty('--tilt-y', `${rotateY}deg`);
    card.style.setProperty('--holo-opacity', `${0.28 + tiltStrength * 0.68}`);
    card.style.setProperty('--holo-saturation', `${0.45 + tiltStrength * 1.55}`);
    card.style.setProperty('--holo-brightness', `${0.88 + tiltStrength * 0.3}`);
    card.style.setProperty('--shine-opacity', `${tiltStrength * 0.92}`);
  };

  const resetHologramTilt = (event) => {
    const card = event.currentTarget;

    card.style.setProperty('--shine-x', '50%');
    card.style.setProperty('--tilt-x', '0deg');
    card.style.setProperty('--tilt-y', '0deg');
    card.style.setProperty('--holo-opacity', '0.28');
    card.style.setProperty('--holo-saturation', '0.45');
    card.style.setProperty('--holo-brightness', '0.88');
    card.style.setProperty('--shine-opacity', '0');
  };

  return (
    <div className="page member-search-page member-search-page-simple">
      {!showMissingOverlay ? (
        <div className="member-search-verify-header">
          <div className="member-search-status member-search-status--verified">
            <span className="member-search-status-dot" aria-hidden="true"></span>
            <div>
              <strong>VERIFIED</strong>
            </div>
          </div>
        </div>
      ) : null}
      <section
        className={`virtual-card${showMissingOverlay ? ' virtual-card--locked' : ''}`}
        onPointerMove={showMissingOverlay ? undefined : updateHologramTilt}
        onPointerLeave={showMissingOverlay ? undefined : resetHologramTilt}
        onPointerCancel={showMissingOverlay ? undefined : resetHologramTilt}
        aria-label="Member virtual ID card"
      >
        <div className="virtual-card-id-number">{displayId}</div>

        <div className="virtual-card-main">
          <div className="virtual-card-avatar">
            {profileImage ? (
              <img src={profileImage} alt={displayName} loading="lazy" decoding="async" />
            ) : (
              <UserRound size={60} />
            )}
          </div>
          <img
            src={certifiedStamp}
            alt="Certified"
            className="virtual-card-certified"
            loading="lazy"
            decoding="async"
          />
          <img
            src="/hologram.png"
            alt=""
            className="virtual-card-hologram"
            aria-hidden="true"
            loading="eager"
            decoding="async"
          />

          <div className="virtual-card-info">
            <h2>{displayLastName || displayFirstName}</h2>
            {displayLastName ? <h3>{displayFirstName}</h3> : null}
            <p className="virtual-card-club">
              {shouldBreakClubLine ? (
                <>
                  {(splitClub[0] || '').trim()}
                  <br />
                  {splitClub.slice(1).join('').trim()}
                </>
              ) : (
                displayClub
              )}
            </p>
            <p className="virtual-card-position">{displayPosition}</p>
            <p className="virtual-card-region">{displayRegion}</p>
          </div>
        </div>

        {showMissingOverlay ? (
          <div className="virtual-card-overlay" role="status" aria-live="polite">
            <Shield className="virtual-card-overlay__shield" size={230} aria-hidden="true" />
            <div className="virtual-card-overlay__content">
              <div className="virtual-card-overlay__lock" aria-hidden="true">
                <Lock size={30} />
              </div>
              <div className="virtual-card-overlay__status">
                <p>Member Search Not Found</p>
              </div>
              <small>No matching Eagle member record.</small>
            </div>
          </div>
        ) : null}
      </section>
      <button className="member-search-home-link" type="button" onClick={() => navigateTo('/')}>
        <Home size={18} />
        <span>Back to Home</span>
      </button>
    </div>
  );
}
