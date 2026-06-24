import { ArrowRight, Globe2, MapPin, Search, UserRound, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { navigateTo } from '../../components/HashRouter';
import { API_ENDPOINTS, extractList, fetchJson } from '../../config/api';
import { openForumApp } from '../../lib/forumAppUrl';
import UserPagesLayout from './UserPagesLayout';
import {
  buildStatusSummary,
  buildRegionalClubGroupsFromApi,
  findClubBySlugInGroups,
  membersForClub,
} from './userPagesData';

const getSlugFromPath = (path = '') => path.split('/').filter(Boolean).at(-1);
const shouldShowMiniChrome = (path = '') => path.startsWith('/users') || path.endsWith('.html');
const isRegionalClubRoute = (path = '') =>
  path.startsWith('/clubs/regions/') ||
  path.startsWith('/regional-clubs/regions/') ||
  path.startsWith('/officers/governors/') ||
  path.startsWith('/users/governors/');

export default function UserClubPage({ currentPath }) {
  const [regionGroups, setRegionGroups] = useState([]);
  const [memberRows, setMemberRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState('');
  const isRegionRoute = isRegionalClubRoute(currentPath);
  const regionSlug = isRegionRoute ? getSlugFromPath(currentPath) : '';
  const focusedRegion = regionGroups.find((group) => group.slug === regionSlug || group.governorSlug === regionSlug) || null;
  const isDirectoryRoute = currentPath === '/clubs' || currentPath === '/regional-clubs' || isRegionRoute;
  const club = isDirectoryRoute ? null : findClubBySlugInGroups(regionGroups, getSlugFromPath(currentPath));

  useEffect(() => {
    let cancelled = false;

    Promise.allSettled([
      fetchJson(API_ENDPOINTS.governors),
      fetchJson(API_ENDPOINTS.clubs),
      fetchJson(API_ENDPOINTS.members.all),
    ])
      .then(([governorsResult, clubsResult, membersResult]) => {
        if (cancelled) return;

        const governorRows =
          governorsResult.status === 'fulfilled' ? extractList(governorsResult.value, ['governors']) : [];
        const clubRows = clubsResult.status === 'fulfilled' ? extractList(clubsResult.value, ['clubs']) : [];
        const membersList = membersResult.status === 'fulfilled' ? extractList(membersResult.value, ['members']) : [];

        setMemberRows(membersList);
        setRegionGroups(buildRegionalClubGroupsFromApi(governorRows, clubRows, membersList));
      })
      .catch(() => {
        if (!cancelled) {
          setRegionGroups([]);
          setMemberRows([]);
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

  const filteredRegions = useMemo(() => {
    const regionSource = focusedRegion ? [focusedRegion] : isRegionRoute ? [] : regionGroups;
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return regionSource;

    return regionSource.filter((group) => {
      const clubNames = group.clubs.map((item) => item.name).join(' ');
      return `${group.region} ${group.governorName} ${clubNames}`.toLowerCase().includes(normalizedQuery);
    });
  }, [focusedRegion, isRegionRoute, query, regionGroups]);

  const clubMembers = useMemo(() => membersForClub(memberRows, club?.name), [club?.name, memberRows]);
  const statusSummary = useMemo(() => buildStatusSummary(clubMembers), [clubMembers]);
  const clubMemberCount = clubMembers.length || Number(club?.memberCount || 0);

  const filteredMembers = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return clubMembers;

    return clubMembers.filter((member) =>
      `${member.name} ${member.position} ${member.id} ${member.status}`.toLowerCase().includes(normalizedQuery),
    );
  }, [clubMembers, query]);

  if (isDirectoryRoute) {
    const statSource = focusedRegion ? [focusedRegion] : regionGroups;
    const totalClubs = statSource.reduce((sum, group) => sum + Number(group.clubCount || 0), 0);
    const totalMembers = statSource.reduce((sum, group) => sum + Number(group.memberCount || 0), 0);

    return (
      <UserPagesLayout activePath="/clubs" showChrome={shouldShowMiniChrome(currentPath)}>
        <section className="user-directory-hero">
          <div className="user-hero-badge">Regional Clubs</div>
          <h1>{focusedRegion ? focusedRegion.region : 'Regional Clubs'}</h1>
          <p>
            {focusedRegion
              ? `Clubs operating under ${focusedRegion.governorName}.`
              : 'Browse regions first, then open the clubs operating under each regional governor.'}
          </p>
          <div className="user-hero-stats">
            <div>
              <strong>{statSource.length}</strong>
              <span>Regions</span>
            </div>
            <div>
              <strong>{totalClubs}</strong>
              <span>Clubs</span>
            </div>
            <div>
              <strong>{totalMembers}</strong>
              <span>Members</span>
            </div>
          </div>
        </section>

        <section className="user-directory-tools" aria-label="Regional club search">
          <label className="user-search-field" htmlFor="regional-club-search">
            <Search size={17} />
            <input
              id="regional-club-search"
              type="search"
              placeholder={focusedRegion ? 'Search club in this region...' : 'Search region, governor, or club...'}
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
          </label>
        </section>

        <main className="user-page-shell">
          <div className="user-section-label">
            <span>{loading ? 'Loading Regions and Clubs' : 'Regions and Clubs'}</span>
          </div>

          <div className="user-region-grid">
            {filteredRegions.map((group) => (
              <section className="user-region-card" key={group.slug}>
                <button
                  className="user-region-card-head"
                  type="button"
                  onClick={() => navigateTo(`/clubs/regions/${group.governorSlug}`)}
                >
                  <span>
                    {group.location === 'intl' ? <Globe2 size={16} /> : <MapPin size={16} />}
                    {group.region}
                  </span>
                  <strong>{group.clubCount} clubs</strong>
                </button>

                <div className="user-region-card-body">
                  <p>{group.governorName}</p>

                  {group.clubs.length > 0 ? (
                    <div className="user-region-club-list">
                      {group.clubs.map((item) => (
                        <button key={item.slug} type="button" onClick={() => navigateTo(`/clubs/${item.slug}`)}>
                          <span>
                            <Users size={16} />
                            {item.name}
                          </span>
                          <ArrowRight size={16} />
                        </button>
                      ))}
                    </div>
                  ) : (
                    <div className="user-region-empty">Clubs will be listed here once available.</div>
                  )}
                </div>
              </section>
            ))}
          </div>

          {filteredRegions.length === 0 ? (
            <div className="user-empty-state">
              {loading ? 'Loading regions and clubs...' : 'No regions or clubs found in the database.'}
            </div>
          ) : null}
        </main>
      </UserPagesLayout>
    );
  }

  if (!club) {
    return (
      <UserPagesLayout activePath="/clubs" showChrome={shouldShowMiniChrome(currentPath)}>
        <main className="user-page-shell">
          <div className="user-empty-state">
            {loading ? 'Loading club...' : 'Club not found in the database.'}
          </div>
        </main>
      </UserPagesLayout>
    );
  }

  return (
    <UserPagesLayout
      activePath="/clubs"
      showChrome={shouldShowMiniChrome(currentPath)}
    >
      <section className="user-profile-hero-wrap">
        <div className="user-club-hero">
          <div className="user-club-emblem">
            <img src="/logo.png" alt="" />
          </div>
          <div>
            <p className="user-hero-badge">Eagles Club</p>
            <h1>{club.name}</h1>
            <div className="user-club-meta">
              <span>
                <MapPin size={15} />
                <strong>{club.region}</strong>
              </span>
              <span>
                <UserRound size={15} />
                <strong>{club.governorName}</strong>
              </span>
              <span>
                <Users size={15} />
                <strong>{clubMemberCount} Members</strong>
              </span>
            </div>
          </div>
        </div>
      </section>

      <main className="user-page-shell user-two-column">
        <section className="user-section-card">
          <div className="user-card-head user-card-head-wrap">
            <div>
              <h2>Members</h2>
              <span>{clubMemberCount} members</span>
            </div>
            <label className="user-table-search" htmlFor="club-member-search">
              <Search size={16} />
              <input
                id="club-member-search"
                type="search"
                placeholder="Search member..."
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </label>
          </div>

          <div className="user-table-wrap">
            <table className="user-members-table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Position</th>
                  <th>ID Number</th>
                  <th>ID Status</th>
                </tr>
              </thead>
              <tbody>
                {filteredMembers.map((member) => (
                  <tr key={member.id}>
                    <td>
                      <strong>{member.name}</strong>
                      <small>{member.id}</small>
                    </td>
                    <td>
                      <span className={`user-position-badge ${member.positionKey}`}>{member.position}</span>
                    </td>
                    <td>{member.id}</td>
                    <td>
                      <span className="user-status-dot">
                        <span className={`dot ${member.statusKey}`}></span>
                        {member.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="user-pagination">
            <span>
              Showing {filteredMembers.length} of {clubMemberCount} members
            </span>
          </div>
        </section>

        <aside className="user-sidebar-stack">
          <section className="user-info-card">
            <h2>Club Details</h2>
            <div className="user-info-row">
              <span>Club Name</span>
              <strong>{club.name}</strong>
            </div>
            <div className="user-info-row">
              <span>Region</span>
              <button type="button" onClick={() => navigateTo(`/officers/governors/${club.governorSlug}`)}>
                {club.region}
              </button>
            </div>
            <div className="user-info-row">
              <span>Governor</span>
              <button type="button" onClick={() => navigateTo(`/officers/governors/${club.governorSlug}`)}>
                {club.governorName}
              </button>
            </div>
            <div className="user-mini-stat-grid">
              <div>
                <strong>{clubMemberCount}</strong>
                <span>Members</span>
              </div>
              <div>
                <strong>{club.deliveredCount}</strong>
                <span>IDs Delivered</span>
              </div>
            </div>
          </section>

          <section className="user-info-card">
            <h2>ID Status Summary</h2>
            {statusSummary.length > 0 ? (
              <div className="user-status-summary">
                {statusSummary.map((item) => (
                  <div key={item.key}>
                    <span className="user-status-dot">
                      <span className={`dot ${item.key}`}></span>
                      {item.label}
                    </span>
                    <strong>{item.count}</strong>
                  </div>
                ))}
              </div>
            ) : (
              <div className="user-region-empty">No ID status data available.</div>
            )}
          </section>

          <section className="user-info-card">
            <button className="user-action-primary" type="button" onClick={() => navigateTo('/membership/application')}>
              Apply for Membership ID
            </button>
            <button className="user-action-secondary" type="button" onClick={() => openForumApp('/profile')}>
              Check ID Status
            </button>
          </section>
        </aside>
      </main>
    </UserPagesLayout>
  );
}
