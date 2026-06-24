import { Lock, Shield, UserRound } from 'lucide-react';
import certifiedStamp from '../assets/certified.gif';
import { resolveImageFromItem } from '../config/api';

export default function MemberVirtualIdCard({ locked = false, member, memberId = '' }) {
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
    'Member Name';
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
  const displayPosition = member?.position || member?.clubPosition || member?.role || 'Member';
  const displayRegion = member?.region || 'Region Unavailable';
  const displayId = memberId || member?.eaglesId || member?.id || 'N/A';
  const shouldBreakClubLine = displayClub.length > 28 && /eagles club/i.test(displayClub);
  const splitClub = shouldBreakClubLine ? displayClub.split(/(EAGLES CLUB)/i) : [displayClub];

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
    <section
      className={`virtual-card${locked ? ' virtual-card--locked' : ''}`}
      onPointerMove={locked ? undefined : updateHologramTilt}
      onPointerLeave={locked ? undefined : resetHologramTilt}
      onPointerCancel={locked ? undefined : resetHologramTilt}
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

      {locked ? (
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
  );
}
