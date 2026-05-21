import { Award } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson, resolveImageFromItem } from '../config/api';

const OFFICER_GROUPS = {
  national: {
    label: 'National Officers',
    endpoint: `${API_ENDPOINTS.officers}?category=national_officers`,
  },
  governors: {
    label: 'Governors',
    endpoint: API_ENDPOINTS.governors,
  },
  appointed: {
    label: 'Appointed Officers',
    endpoint: API_ENDPOINTS.appointed,
  },
  pastLeaders: {
    label: 'Past Leaders / Leadership History',
    endpoint: API_ENDPOINTS.pastLeaders,
  },
};
const GROUP_SUBTITLES = {
  national: 'Meet the leaders guiding our organization',
  governors: 'Meet the governors leading each region',
  appointed: 'Meet the appointed officers serving key committees',
  pastLeaders: 'Honoring the leaders who shaped our leadership history',
};
const APPOINTED_PAGE_SIZE = 12;
const OFFICER_SKELETON_COUNT = 8;

const buildNationalChart = (items) => {
  const remaining = Array.isArray(items) ? [...items] : [];
  const top = remaining.shift() || null;
  const secondLeft = remaining.shift() || null;
  const secondCenter = remaining.shift() || null;
  const thirdRow = remaining.splice(0, 3);
  const fourthRow = remaining.splice(0, 3);
  const overflow = remaining;

  const chart = { top, secondLeft, secondCenter, thirdRow, fourthRow, overflow };

  const normalizeName = (officer) => String(officer?.name || '').toLowerCase();
  const getFromSlot = (slot) => {
    if (!slot) return null;
    if (slot.row === 'secondLeft') return chart.secondLeft;
    if (slot.row === 'secondCenter') return chart.secondCenter;
    if (slot.row === 'third') return chart.thirdRow[slot.index];
    if (slot.row === 'fourth') return chart.fourthRow[slot.index];
    if (slot.row === 'overflow') return chart.overflow[slot.index];
    return null;
  };
  const setToSlot = (slot, value) => {
    if (!slot) return;
    if (slot.row === 'secondLeft') chart.secondLeft = value;
    if (slot.row === 'secondCenter') chart.secondCenter = value;
    if (slot.row === 'third') chart.thirdRow[slot.index] = value;
    if (slot.row === 'fourth') chart.fourthRow[slot.index] = value;
    if (slot.row === 'overflow') chart.overflow[slot.index] = value;
  };
  const findSlot = (matcher) => {
    if (matcher(chart.secondLeft)) return { row: 'secondLeft' };
    if (matcher(chart.secondCenter)) return { row: 'secondCenter' };
    for (let idx = 0; idx < chart.thirdRow.length; idx += 1) {
      if (matcher(chart.thirdRow[idx])) return { row: 'third', index: idx };
    }
    for (let idx = 0; idx < chart.fourthRow.length; idx += 1) {
      if (matcher(chart.fourthRow[idx])) return { row: 'fourth', index: idx };
    }
    for (let idx = 0; idx < chart.overflow.length; idx += 1) {
      if (matcher(chart.overflow[idx])) return { row: 'overflow', index: idx };
    }
    return null;
  };

  const includesText = (value, text) => String(value || '').toLowerCase().includes(text);

  const erwinOfficer = getFromSlot(findSlot((officer) => normalizeName(officer).includes('abital')));
  const rommelOfficer = getFromSlot(findSlot((officer) => normalizeName(officer).includes('aquillo')));
  const ramiroOfficer = getFromSlot(findSlot((officer) => normalizeName(officer).includes('torrefiel')));

  const erwinRoleSlot = findSlot((officer) => includesText(officer?.position, 'secretary general'));
  const rommelRoleSlot = findSlot((officer) => includesText(officer?.position, 'executive national vice president'));
  const ramiroRoleSlot = findSlot((officer) => includesText(officer?.position, 'vice president for luzon'));

  if (erwinOfficer && rommelOfficer && ramiroOfficer && erwinRoleSlot && rommelRoleSlot && ramiroRoleSlot) {
    setToSlot(ramiroRoleSlot, erwinOfficer);
    setToSlot(rommelRoleSlot, ramiroOfficer);
    setToSlot(erwinRoleSlot, rommelOfficer);
  }

  return chart;
};

const flattenGovernors = (payload) => {
  const governors = extractList(payload, ['governors']);
  return governors.map((governor) => ({
    id: governor.id,
    name: governor.name || 'Unknown Governor',
    position: 'Governor',
    term: governor.regions?.map((region) => region.name).filter(Boolean).join(' • ') || 'Regional Coverage',
    imageUrl: governor.imageUrl || '',
    imageFilename: governor.imageFilename || '',
  }));
};

const flattenAppointed = (payload) => {
  const regions = extractList(payload, ['appointed']);
  const entries = [];

  regions.forEach((region) => {
    const committees = Array.isArray(region?.committees) ? region.committees : [];
    committees.forEach((committee) => {
      const officers = Array.isArray(committee?.officers) ? committee.officers : [];
      officers.forEach((officer) => {
        entries.push({
          id: officer.id,
          name: officer.name || 'Unknown Officer',
          position: officer.position || committee.name || 'Appointed Officer',
          term: [officer.region || region.name, officer.committee || committee.name].filter(Boolean).join(' • '),
        });
      });
    });
  });

  return entries;
};

const flattenPastLeaders = (payload) => {
  const leaders = extractList(payload, ['past_leaders', 'pastLeaders', 'past_leader']);

  return leaders.map((leader) => {
    const termStart = String(leader?.termStart || leader?.term_start || '').trim();
    const termEnd = String(leader?.termEnd || leader?.term_end || '').trim();
    const termRange = [termStart, termEnd].filter(Boolean).join(' - ') || 'Leadership Term';

    return {
      id: leader?.id,
      name: leader?.name || 'Unknown Leader',
      position: leader?.position || 'Past Leader',
      term: termRange,
      achievements: String(leader?.achievements || '').trim(),
      imageUrl: leader?.photoUrl || leader?.imageUrl || '',
      photo: leader?.photo || leader?.photoFilename || '',
    };
  });
};

const normalizeOfficers = (groupKey, payload) => {
  if (groupKey === 'governors') return flattenGovernors(payload);
  if (groupKey === 'appointed') return flattenAppointed(payload);
  if (groupKey === 'pastLeaders') return flattenPastLeaders(payload);
  const nationalOfficers = extractList(payload, ['officers']);
  return nationalOfficers
    .slice()
    .sort((a, b) => Number(a?.id ?? Number.MAX_SAFE_INTEGER) - Number(b?.id ?? Number.MAX_SAFE_INTEGER));
};

export default function OfficersPage({ groupKey = 'national' }) {
  const group = useMemo(() => (OFFICER_GROUPS[groupKey] ? groupKey : 'national'), [groupKey]);
  const [officers, setOfficers] = useState([]);
  const [hasOfficersResponse, setHasOfficersResponse] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const orgChartRef = useRef(null);
  const orgLinesRef = useRef(null);

  const currentGroup = useMemo(() => OFFICER_GROUPS[group], [group]);

  useEffect(() => {
    setHasOfficersResponse(false);
    setCurrentPage(1);
    setOfficers([]);

    fetchJson(currentGroup.endpoint)
      .then((data) => {
        setOfficers(normalizeOfficers(group, data));
      })
      .catch(() => {})
      .finally(() => {
        setHasOfficersResponse(true);
      });
  }, [group, currentGroup.endpoint]);

  const isPagedGroup = group === 'appointed' || group === 'pastLeaders';
  const skeletonCount = isPagedGroup ? APPOINTED_PAGE_SIZE : OFFICER_SKELETON_COUNT;
  const skeletonItems = useMemo(
    () => Array.from({ length: skeletonCount }, (_, idx) => idx),
    [skeletonCount],
  );
  const totalPages = isPagedGroup ? Math.max(1, Math.ceil(officers.length / APPOINTED_PAGE_SIZE)) : 1;
  const startIndex = isPagedGroup ? (currentPage - 1) * APPOINTED_PAGE_SIZE : 0;

  const visibleOfficers = useMemo(() => {
    if (!isPagedGroup) return officers;
    return officers.slice(startIndex, startIndex + APPOINTED_PAGE_SIZE);
  }, [isPagedGroup, officers, startIndex]);

  const pageNumbers = useMemo(() => {
    if (!isPagedGroup) return [];
    return Array.from({ length: totalPages }, (_, idx) => idx + 1);
  }, [isPagedGroup, totalPages]);

  const nationalChart = useMemo(() => {
    if (group !== 'national') return null;
    return buildNationalChart(visibleOfficers);
  }, [group, visibleOfficers]);

  useEffect(() => {
    if (group !== 'national') return undefined;
    if (!nationalChart) return undefined;

    const chart = orgChartRef.current;
    const svg = orgLinesRef.current;
    if (!chart || !svg) return undefined;

    const getEl = (id) => document.getElementById(id);
    const ptCenterTop = (el, rootRect) => {
      const r = el.getBoundingClientRect();
      return { x: (r.left + r.right) / 2 - rootRect.left, y: r.top - rootRect.top };
    };
    const ptCenterBottom = (el, rootRect) => {
      const r = el.getBoundingClientRect();
      return { x: (r.left + r.right) / 2 - rootRect.left, y: r.bottom - rootRect.top };
    };
    const ptRightCenter = (el, rootRect, yRatio = 0.5) => {
      const r = el.getBoundingClientRect();
      return { x: r.right - rootRect.left, y: r.top + r.height * yRatio - rootRect.top };
    };
    const line = (x1, y1, x2, y2) => {
      const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      l.setAttribute('x1', String(x1));
      l.setAttribute('y1', String(y1));
      l.setAttribute('x2', String(x2));
      l.setAttribute('y2', String(y2));
      l.setAttribute('class', 'org-line');
      return l;
    };

    const draw = () => {
      try {
      const stacked = window.matchMedia('(max-width: 1100px)').matches;
      if (stacked) {
        svg.innerHTML = '';
        return;
      }

      const rootRect = chart.getBoundingClientRect();
      const w = rootRect.width;
      const h = rootRect.height;
      svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
      svg.setAttribute('width', String(w));
      svg.setAttribute('height', String(h));
      svg.innerHTML = '';

      const president = getEl('card-president');
      const secgen = getEl('card-secgen');
      const execvp = getEl('card-execvp');
      const vpL = getEl('card-vp-luzon');
      const vpV = getEl('card-vp-visayas');
      const vpM = getEl('card-vp-mindanao');
      const floor = getEl('card-floorleader');
      const treas = getEl('card-treasurer');

      if (!president || !execvp) return;

      const pB = ptCenterBottom(president, rootRect);
      const eT = ptCenterTop(execvp, rootRect);
      if (![pB.x, pB.y, eT.x, eT.y].every(Number.isFinite)) return;

      let junctionY;
      if (secgen) {
        // Slightly above center so Erwin's connector appears higher on desktop.
        const sR = ptRightCenter(secgen, rootRect, 0.38);
        junctionY = sR.y;
        svg.appendChild(line(sR.x, sR.y, pB.x, junctionY));
      } else {
        junctionY = (pB.y + eT.y) / 2;
      }

      svg.appendChild(line(pB.x, pB.y, pB.x, junctionY));
      svg.appendChild(line(pB.x, junctionY, eT.x, eT.y));

      const eB = ptCenterBottom(execvp, rootRect);
      if (![eB.x, eB.y].every(Number.isFinite)) return;
      const vpCards = [vpL, vpV, vpM].filter(Boolean);
      if (!vpCards.length) return;

      const tops = vpCards.map((el) => ptCenterTop(el, rootRect).y);
      const barY = Math.min(...tops) - 18;
      if (!Number.isFinite(barY)) return;
      svg.appendChild(line(eB.x, eB.y, eB.x, barY));

      const centersX = vpCards.map((el) => ptCenterTop(el, rootRect).x).sort((a, b) => a - b);
      svg.appendChild(line(centersX[0], barY, centersX[centersX.length - 1], barY));

      vpCards.forEach((el) => {
        const t = ptCenterTop(el, rootRect);
        svg.appendChild(line(t.x, barY, t.x, t.y));
      });

      if (vpL && floor) {
        const vpLb = ptCenterBottom(vpL, rootRect);
        const flT = ptCenterTop(floor, rootRect);
        if (![vpLb.x, vpLb.y, flT.x, flT.y].every(Number.isFinite)) return;
        svg.appendChild(line(vpLb.x, vpLb.y, flT.x, flT.y));
      }

      if (vpV && treas) {
        const vpVb = ptCenterBottom(vpV, rootRect);
        const trT = ptCenterTop(treas, rootRect);
        if (![vpVb.x, vpVb.y, trT.x, trT.y].every(Number.isFinite)) return;
        svg.appendChild(line(vpVb.x, vpVb.y, trT.x, trT.y));
      }
      } catch (_) {
        svg.innerHTML = '';
      }
    };

    let rafId = 0;
    const scheduleDraw = () => {
      if (rafId) window.cancelAnimationFrame(rafId);
      rafId = window.requestAnimationFrame(draw);
    };

    const timerId = window.setTimeout(scheduleDraw, 60);
    const onResize = () => scheduleDraw();
    window.addEventListener('resize', onResize);

    const images = Array.from(chart.querySelectorAll('img'));
    const imageHandlers = images
      .filter((img) => !img.complete)
      .map((img) => {
        const handler = () => scheduleDraw();
        img.addEventListener('load', handler, { once: true });
        return { img, handler };
      });

    return () => {
      if (rafId) window.cancelAnimationFrame(rafId);
      window.clearTimeout(timerId);
      window.removeEventListener('resize', onResize);
      imageHandlers.forEach(({ img, handler }) => img.removeEventListener('load', handler));
    };
  }, [group, nationalChart]);

  const renderOfficerCard = (officer, idx, variant = 'default', cardId = '') => {
    const isOrgVariant = variant === 'org';
    const isPastVariant = variant === 'past';
    const cardVariantClass = isOrgVariant ? 'officer-card-org' : isPastVariant ? 'officer-card-past' : '';
    const avatarVariantClass = isOrgVariant ? 'officer-avatar-org' : isPastVariant ? 'officer-avatar-past' : '';

    const officerImage = resolveImageFromItem(officer, [
      'imageUrl',
      'photoUrl',
      'photo_url',
      'image',
      'photo',
      'avatar',
      'media.0.url',
    ]);

    return (
      <Reveal key={`${group}-${variant}-${officer?.id || idx}-${cardId || 'card'}`} delay={idx * 45}>
        <div id={cardId || undefined} className={`officer-card ${cardVariantClass}`}>
          <div className={`officer-avatar ${avatarVariantClass}`}>
            {officerImage ? <img src={officerImage} alt={officer.name} loading="lazy" decoding="async" /> : <Award size={40} />}
          </div>
          <div className={`officer-info ${isPastVariant ? 'officer-info-overlay' : ''}`}>
            <h3 className="officer-name">{officer.name || 'Unknown Officer'}</h3>
            <p className="officer-position">{officer.position || 'Officer'}</p>
            <p className={`officer-term ${isPastVariant ? 'officer-term-overlay' : ''}`}>
              {officer.term || (isPastVariant ? 'Leadership Term' : 'Current Term')}
            </p>
            {group === 'pastLeaders' && !isPastVariant && String(officer?.achievements || '').trim() !== '' ? (
              <p className="officer-term">{officer.achievements}</p>
            ) : null}
          </div>
        </div>
      </Reveal>
    );
  };

  const renderOfficerSkeletonCard = (key, variant = 'default') => {
    const isOrgVariant = variant === 'org';
    const isPastVariant = variant === 'past';
    const cardVariantClass = isOrgVariant ? 'officer-card-org' : isPastVariant ? 'officer-card-past' : '';
    const avatarVariantClass = isOrgVariant ? 'officer-avatar-org' : isPastVariant ? 'officer-avatar-past' : '';

    if (isPastVariant) {
      return (
        <div
          key={key}
          className={`officer-card ${cardVariantClass} officer-card--skeleton officer-card-past-skeleton`}
          aria-hidden="true"
        >
          <Skeleton className="officer-past-skeleton-media" variant="rectangular" animation="wave" />
          <div className="officer-info officer-info-overlay officer-info-overlay-skeleton">
            <Skeleton variant="text" animation="wave" width="84%" height={34} sx={{ mb: 0.2 }} />
            <Skeleton variant="text" animation="wave" width="72%" height={24} sx={{ mb: 0.2 }} />
            <Skeleton variant="text" animation="wave" width="56%" height={20} />
          </div>
        </div>
      );
    }

    return (
      <div key={key} className={`officer-card ${cardVariantClass} officer-card--skeleton`} aria-hidden="true">
        <div className={`officer-avatar ${avatarVariantClass}`}>
          <Skeleton variant="rounded" animation="wave" width="100%" height="100%" />
        </div>
        <>
          <Skeleton variant="text" animation="wave" width="72%" height={40} sx={{ mx: 'auto', mb: 0.5 }} />
          <Skeleton variant="text" animation="wave" width="84%" height={30} sx={{ mx: 'auto', mb: 0.4 }} />
          <Skeleton variant="text" animation="wave" width="64%" height={24} sx={{ mx: 'auto' }} />
        </>
      </div>
    );
  };

  const listCardVariant = group === 'pastLeaders' ? 'past' : 'org';

  return (
    <div className="page officers-page">
      <section className="hero officers-hero" aria-label="Officers background">
        <div className="hero-bg officers-hero-bg"></div>
        <div className="hero-content officers-content-spacer officers-hero-content">
          <div className="officers-hero-copy">
            <h1 className="officers-hero-title">Leadership Team</h1>
            <p className="officers-hero-subtitle">Meet the Eagles who lead our organization</p>
          </div>
        </div>
      </section>

      {group === 'national' ? (
        <section className="officers-org-chart" aria-label="National officers organizational chart">
          <div className="officers-org-header">
            <h2 className="officers-org-title">National Officers</h2>
            <p className="officers-org-subtitle">Meet the leaders guiding our organization</p>
          </div>

          {!hasOfficersResponse ? (
            <div className="officers-grid">
              {skeletonItems.map((item) => (
                <div key={`national-skeleton-${item}`}>
                  {renderOfficerSkeletonCard(`national-skeleton-card-${item}`)}
                </div>
              ))}
            </div>
          ) : visibleOfficers.length > 0 && nationalChart ? (
            <div className="officers-org-tree org-v2" id="orgChart" ref={orgChartRef}>
              <svg className="org-lines" id="orgLines" ref={orgLinesRef} aria-hidden="true"></svg>

              <div className="org-row row-1">
                <div className="slot center">
                  {nationalChart.top && renderOfficerCard(nationalChart.top, 0, 'org', 'card-president')}
                </div>
              </div>

              <div className="org-row row-2">
                <div className="slot left">
                  {nationalChart.secondLeft && renderOfficerCard(nationalChart.secondLeft, 1, 'org', 'card-secgen')}
                </div>
                <div className="slot center">
                  {nationalChart.secondCenter && renderOfficerCard(nationalChart.secondCenter, 2, 'org', 'card-execvp')}
                </div>
                <div className="slot right slot-empty" aria-hidden="true"></div>
              </div>

              <div className="org-row row-3">
                <div className="slot col">
                  {nationalChart.thirdRow[0] && renderOfficerCard(nationalChart.thirdRow[0], 3, 'org', 'card-vp-luzon')}
                </div>
                <div className="slot col">
                  {nationalChart.thirdRow[1] && renderOfficerCard(nationalChart.thirdRow[1], 4, 'org', 'card-vp-visayas')}
                </div>
                <div className="slot col">
                  {nationalChart.thirdRow[2] && renderOfficerCard(nationalChart.thirdRow[2], 5, 'org', 'card-vp-mindanao')}
                </div>
              </div>

              <div className="org-row row-4">
                <div className="slot col">
                  {nationalChart.fourthRow[0] && renderOfficerCard(nationalChart.fourthRow[0], 6, 'org', 'card-floorleader')}
                </div>
                <div className="slot col">
                  {nationalChart.fourthRow[1] && renderOfficerCard(nationalChart.fourthRow[1], 7, 'org', 'card-treasurer')}
                </div>
                <div className="slot col slot-empty" aria-hidden="true"></div>
              </div>
            </div>
          ) : hasOfficersResponse ? (
            <div className="empty-state">
              <Award size={48} />
              <p>No {currentGroup.label.toLowerCase()} data</p>
            </div>
          ) : null}

          {nationalChart && nationalChart.overflow.length > 0 && (
            <div className="officers-grid officers-grid-overflow">
              {nationalChart.overflow.map((officer, idx) => (
                <div key={officer?.id || `overflow-${idx}`}>{renderOfficerCard(officer, idx + 9, 'org')}</div>
              ))}
            </div>
          )}
        </section>
      ) : (
        <section className="officers-org-chart officers-list-section" aria-label={`${currentGroup.label} list`}>
          <div className="officers-org-header">
            <h2 className="officers-org-title">{currentGroup.label}</h2>
            <p className="officers-org-subtitle">{GROUP_SUBTITLES[group] || GROUP_SUBTITLES.national}</p>
          </div>

          <div className={`officers-grid officers-grid-extended ${group === 'pastLeaders' ? 'officers-grid-past' : ''}`}>
            {!hasOfficersResponse ? (
              skeletonItems.map((item) => (
                <div key={`${group}-skeleton-${item}`}>
                  {renderOfficerSkeletonCard(`${group}-skeleton-card-${item}`, listCardVariant)}
                </div>
              ))
            ) : visibleOfficers.length > 0 ? (
              visibleOfficers.map((officer, idx) => (
                <div key={officer?.id || `default-${idx}`}>{renderOfficerCard(officer, idx, listCardVariant)}</div>
              ))
            ) : hasOfficersResponse ? (
              <div className="empty-state">
                <Award size={48} />
                <p>No {currentGroup.label.toLowerCase()} data</p>
              </div>
            ) : null}
          </div>
        </section>
      )}

      {isPagedGroup && totalPages > 1 && (
        <div className="officers-pagination">
          <button
            className="officers-page-btn"
            onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
            disabled={currentPage === 1}
          >
            Prev
          </button>

          {pageNumbers.map((pageNumber) => (
            <button
              key={pageNumber}
              className={`officers-page-btn ${currentPage === pageNumber ? 'active' : ''}`}
              onClick={() => setCurrentPage(pageNumber)}
            >
              {pageNumber}
            </button>
          ))}

          <button
            className="officers-page-btn"
            onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
