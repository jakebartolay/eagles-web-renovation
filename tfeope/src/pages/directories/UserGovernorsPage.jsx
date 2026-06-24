import { ArrowRight, Globe2, MapPin, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { navigateTo } from '../../components/HashRouter';
import { API_ENDPOINTS, extractList, fetchJson } from '../../config/api';
import UserPagesLayout from './UserPagesLayout';
import { normalizeGovernorsFromApi, normalizeMemberRows } from './userPagesData';

const filters = [
  { value: 'all', label: 'All' },
  { value: 'ph', label: 'Philippines' },
  { value: 'intl', label: 'International' },
];

const GOVERNOR_LOGO_FALLBACK = '/logo.png';
const shouldShowMiniChrome = (path = '') => path.startsWith('/users') || path.endsWith('.html');

export default function UserGovernorsPage({ currentPath }) {
  const [governorItems, setGovernorItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState('');
  const [locationFilter, setLocationFilter] = useState('all');

  useEffect(() => {
    let cancelled = false;

    Promise.allSettled([fetchJson(API_ENDPOINTS.governors), fetchJson(API_ENDPOINTS.members.all)])
      .then(([governorsResult, membersResult]) => {
        if (cancelled) return;

        const governorRows =
          governorsResult.status === 'fulfilled' ? extractList(governorsResult.value, ['governors']) : [];
        const memberRows = membersResult.status === 'fulfilled' ? extractList(membersResult.value, ['members']) : [];
        const normalizedMembers = normalizeMemberRows(memberRows);

        setGovernorItems(normalizeGovernorsFromApi(governorRows, normalizedMembers));
      })
      .catch(() => {
        if (!cancelled) {
          setGovernorItems([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const filteredGovernors = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return governorItems.filter((governor) => {
      const matchesFilter = locationFilter === 'all' || governor.location === locationFilter;
      const matchesQuery = `${governor.name} ${governor.region}`.toLowerCase().includes(normalizedQuery);
      return matchesFilter && matchesQuery;
    });
  }, [governorItems, locationFilter, query]);

  const totalClubs = governorItems.reduce((sum, governor) => sum + Number(governor.clubCount || 0), 0);
  const internationalCount = governorItems.filter((governor) => governor.location === 'intl').length;

  return (
    <UserPagesLayout activePath="/officers/governors" showChrome={shouldShowMiniChrome(currentPath)}>
      <section className="user-directory-hero">
        <div className="user-hero-badge">Leadership</div>
        <h1>Our Governors</h1>
        <p>Meet the Eagles who lead each region of TFOE-PE Inc. across the Philippines and abroad.</p>
        <div className="user-hero-stats">
          <div>
            <strong>{governorItems.length}</strong>
            <span>Governors</span>
          </div>
          <div>
            <strong>{totalClubs}</strong>
            <span>Clubs</span>
          </div>
          <div>
            <strong>{internationalCount}</strong>
            <span>International</span>
          </div>
        </div>
      </section>

      <section className="user-directory-tools" aria-label="Governor search and filters">
        <label className="user-search-field" htmlFor="governor-search">
          <Search size={17} />
          <input
            id="governor-search"
            type="search"
            placeholder="Search governor or region..."
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>

        <div className="user-filter-tabs" role="group" aria-label="Filter governors">
          {filters.map((filter) => (
            <button
              key={filter.value}
              className={locationFilter === filter.value ? 'active' : ''}
              type="button"
              onClick={() => setLocationFilter(filter.value)}
            >
              {filter.label}
            </button>
          ))}
        </div>
      </section>

      <main className="user-page-shell">
        <div className="user-section-label">
          <span>{loading ? 'Loading Governors' : 'All Governors'}</span>
        </div>

        <div className="user-governor-grid">
          {filteredGovernors.map((governor) => (
            <button
              className="user-governor-card"
              key={governor.slug}
              type="button"
              onClick={() => navigateTo(`/clubs/regions/${governor.slug}`)}
            >
              <div className="user-governor-photo">
                <img
                  src={governor.imageUrl || GOVERNOR_LOGO_FALLBACK}
                  alt=""
                  onError={(event) => {
                    if (event.currentTarget.src.endsWith(GOVERNOR_LOGO_FALLBACK)) return;
                    event.currentTarget.src = GOVERNOR_LOGO_FALLBACK;
                  }}
                />
                <span>{governor.region}</span>
              </div>
              <div className="user-governor-body">
                <h2>{governor.name}</h2>
                <p>Governor</p>
                <div className="user-governor-meta">
                  <span>
                    {governor.location === 'intl' ? <Globe2 size={14} /> : <MapPin size={14} />}
                    {governor.clubCount} clubs
                  </span>
                  <ArrowRight size={17} />
                </div>
              </div>
            </button>
          ))}
        </div>

        {filteredGovernors.length === 0 ? (
          <div className="user-empty-state">{loading ? 'Loading governors...' : 'No governors matched your search.'}</div>
        ) : null}
      </main>
    </UserPagesLayout>
  );
}
