import { BookOpen, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import CircularProgress from '@mui/material/CircularProgress';
import Skeleton from '@mui/material/Skeleton';
import Reveal from '../components/Reveal';
import { API_ENDPOINTS, extractList, fetchJson } from '../config/api';

const CLUBS_PAGE_SIZE = 9;
const SEARCH_DELAY_MS = 3000;
const CLUB_SKELETON_ITEMS = Array.from({ length: CLUBS_PAGE_SIZE }, (_, idx) => idx);
const normalizeClubName = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

export default function ClubsPage() {
  const [clubs, setClubs] = useState([]);
  const [hasClubsResponse, setHasClubsResponse] = useState(false);
  const [membersByClub, setMembersByClub] = useState({});
  const [currentPage, setCurrentPage] = useState(1);
  const [searchInput, setSearchInput] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchLoading, setSearchLoading] = useState(false);
  const [selectedClub, setSelectedClub] = useState(null);

  useEffect(() => {
    setHasClubsResponse(false);

    Promise.allSettled([fetchJson(API_ENDPOINTS.clubs), fetchJson(API_ENDPOINTS.members.all)])
      .then(([clubsResult, membersResult]) => {
        const clubItems = clubsResult.status === 'fulfilled' ? extractList(clubsResult.value, ['clubs']) : [];
        const members = membersResult.status === 'fulfilled' ? extractList(membersResult.value, ['members', 'member']) : [];

        const groupedMembers = members.reduce((acc, member) => {
          const key = normalizeClubName(member?.club || member?.clubName || member?.club_name);
          if (!key) return acc;
          if (!acc[key]) acc[key] = [];
          acc[key].push(member);
          return acc;
        }, {});

        setMembersByClub(groupedMembers);
        setClubs(clubItems);
        setCurrentPage(1);
      })
      .catch(() => {})
      .finally(() => {
        setHasClubsResponse(true);
      });
  }, []);

  useEffect(() => {
    if (!hasClubsResponse) {
      setSearchLoading(false);
      return undefined;
    }

    if (searchInput.trim() === searchQuery.trim()) {
      setSearchLoading(false);
      return undefined;
    }

    setSearchLoading(true);

    const timeoutId = window.setTimeout(() => {
      setSearchQuery(searchInput);
      setSearchLoading(false);
      setCurrentPage(1);
    }, SEARCH_DELAY_MS);

    return () => window.clearTimeout(timeoutId);
  }, [searchInput, searchQuery, hasClubsResponse]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchQuery]);

  const filteredClubs = useMemo(() => {
    const keyword = searchQuery.trim().toLowerCase();
    if (!keyword) return clubs;

    return clubs.filter((club) => {
      const name = String(club?.name || '').toLowerCase();
      const description = String(club?.description || '').toLowerCase();
      return name.includes(keyword) || description.includes(keyword);
    });
  }, [clubs, searchQuery]);

  const totalPages = Math.max(1, Math.ceil(filteredClubs.length / CLUBS_PAGE_SIZE));
  const startIndex = (currentPage - 1) * CLUBS_PAGE_SIZE;
  const visibleClubs = useMemo(
    () => filteredClubs.slice(startIndex, startIndex + CLUBS_PAGE_SIZE),
    [filteredClubs, startIndex],
  );
  const selectedClubMembers = useMemo(() => {
    if (!selectedClub) return [];
    const key = normalizeClubName(selectedClub.name);
    return membersByClub[key] || [];
  }, [selectedClub, membersByClub]);

  return (
    <div className="page clubs-page">
      <section className="hero clubs-hero" aria-label="Eagles clubs background">
        <div className="hero-bg clubs-hero-bg"></div>
        <div className="hero-content clubs-content-spacer"></div>
      </section>

      <Reveal>
        <div className="page-header">
          <h1 className="page-title">Eagles Clubs</h1>
          <p className="page-subtitle">Explore our diverse community organizations</p>
          <div className="search-bar search-bar--with-icon">
            <span className="search-bar__icon" aria-hidden="true">
              <Search size={18} />
            </span>
            <input
              type="text"
              className="search-input search-input--with-icon"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search clubs..."
              aria-label="Search clubs"
            />
            {searchLoading ? (
              <span className="search-bar__spinner" aria-hidden="true">
                <CircularProgress size={16} thickness={5} sx={{ color: 'var(--eagle-primary)' }} />
              </span>
            ) : null}
          </div>
        </div>
      </Reveal>

      <div className="clubs-grid">
        {!hasClubsResponse ? (
          CLUB_SKELETON_ITEMS.map((item) => (
            <div key={`club-skeleton-${item}`} className="club-card club-card--skeleton" aria-hidden="true">
              <Skeleton variant="rounded" width={96} height={34} sx={{ mx: 'auto', mb: 2 }} />
              <Skeleton variant="text" width="72%" height={38} sx={{ mx: 'auto', mb: 1 }} />
              <Skeleton variant="text" width="92%" height={24} sx={{ mx: 'auto' }} />
              <Skeleton variant="text" width="86%" height={24} sx={{ mx: 'auto', mb: 3 }} />
              <div className="club-meta">
                <Skeleton variant="text" width={112} height={24} />
                <Skeleton variant="rounded" width={104} height={36} />
              </div>
            </div>
          ))
        ) : searchLoading ? (
          <div className="clubs-search-loading" role="status" aria-live="polite">
            <CircularProgress size={34} thickness={4.4} sx={{ color: 'var(--eagle-primary)' }} />
            <p>Searching clubs, please wait...</p>
          </div>
        ) : visibleClubs.length > 0 ? (
          visibleClubs.map((club, idx) => (
            <Reveal key={club.id || `${currentPage}-${idx}`} delay={idx * 60}>
              {(() => {
                const clubKey = normalizeClubName(club.name);
                const linkedMembers = membersByClub[clubKey] || [];
                const clubMemberCount = linkedMembers.length || Number(club.member_count || 0);

                return (
                  <div className="club-card">
                    <div className="club-icon">EAGLE</div>
                    <h3 className="club-name">{club.name || 'Untitled Club'}</h3>
                    <p className="club-description">{club.description || 'No description available'}</p>
                    <div className="club-meta">
                      <span className="club-members">{clubMemberCount} members</span>
                      <button className="club-join-btn" onClick={() => setSelectedClub(club)}>
                        View List
                      </button>
                    </div>
                  </div>
                );
              })()}
            </Reveal>
          ))
        ) : hasClubsResponse ? (
          <div className="empty-state">
            <BookOpen size={48} />
            <p>{searchQuery.trim() ? 'No clubs match your search' : 'No clubs available'}</p>
          </div>
        ) : null}
      </div>

      {!searchLoading && totalPages > 1 && (
        <div className="clubs-pagination">
          <button
            className="clubs-page-btn"
            onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
            disabled={currentPage === 1}
          >
            Prev
          </button>
          {Array.from({ length: totalPages }, (_, idx) => idx + 1).map((pageNumber) => (
            <button
              key={pageNumber}
              className={`clubs-page-btn ${currentPage === pageNumber ? 'active' : ''}`}
              onClick={() => setCurrentPage(pageNumber)}
            >
              {pageNumber}
            </button>
          ))}
          <button
            className="clubs-page-btn"
            onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </button>
        </div>
      )}

      {selectedClub && (
        <div className="clubs-modal-overlay" onClick={() => setSelectedClub(null)}>
          <div className="clubs-modal" onClick={(event) => event.stopPropagation()}>
            <button className="clubs-modal-close" onClick={() => setSelectedClub(null)} aria-label="Close member list">
              ×
            </button>
            <h3 className="clubs-modal-title">{selectedClub.name || 'Club Members'}</h3>
            <p className="clubs-modal-subtitle">{selectedClubMembers.length} members</p>
            {selectedClubMembers.length > 0 ? (
              <div className="clubs-member-list">
                {selectedClubMembers.map((member, idx) => (
                  <div key={member?.id || idx} className="clubs-member-item">
                    {member?.fullName || [member?.firstName, member?.lastName].filter(Boolean).join(' ') || 'Unknown Member'}
                  </div>
                ))}
              </div>
            ) : (
              <p className="clubs-member-empty">No linked members found for this club.</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
