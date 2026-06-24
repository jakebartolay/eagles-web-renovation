import { ChevronDown, Menu, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { openForumApp } from '../lib/forumAppUrl';

const navItems = [
  { path: '/membership/application', label: 'ID Application' },
  { path: '/clubs', label: 'Regional Clubs' },
  { path: '/forum', label: 'Forum', forumPath: '/forum' },
];
const OFFICERS_DROPDOWN_ITEMS = [
  { path: '/officers/national', label: 'National Officers' },
  { path: '/officers/governors', label: 'Governors' },
  { path: '/officers/appointed', label: 'Appointed Officers' },
  { path: '/officers/past-leaders', label: 'Past Leaders / Leadership History' },
];

export default function Navigation({ currentPath, onNavigate, menuOpen, setMenuOpen }) {
  const [scrolled, setScrolled] = useState(false);
  const [aboutOpen, setAboutOpen] = useState(false);
  const [updatesOpen, setUpdatesOpen] = useState(false);
  const [officersOpen, setOfficersOpen] = useState(false);
  const aboutDropdownRef = useRef(null);
  const updatesDropdownRef = useRef(null);
  const officersDropdownRef = useRef(null);
  const isAboutRoute = currentPath === '/history' || currentPath === '/magna-carta';
  const isUpdatesRoute = currentPath === '/news' || currentPath === '/events' || currentPath === '/videos';
  const isRegionalClubsRoute =
    currentPath === '/clubs' ||
    currentPath.startsWith('/clubs/') ||
    currentPath === '/regional-clubs' ||
    currentPath.startsWith('/regional-clubs/') ||
    currentPath.startsWith('/officers/governors/');
  const isOfficersRoute =
    currentPath === '/officers' ||
    currentPath === '/officers/national' ||
    currentPath === '/officers/governors' ||
    currentPath === '/officers/appointed' ||
    currentPath === '/officers/past-leaders';

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 80);
    };

    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    const handleOutsideClick = (event) => {
      if (!aboutDropdownRef.current?.contains(event.target)) {
        setAboutOpen(false);
      }
      if (!updatesDropdownRef.current?.contains(event.target)) {
        setUpdatesOpen(false);
      }
      if (!officersDropdownRef.current?.contains(event.target)) {
        setOfficersOpen(false);
      }
    };

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  const handleNavClick = (path, forumPath) => {
    setMenuOpen(false);
    setAboutOpen(false);
    setUpdatesOpen(false);
    setOfficersOpen(false);

    if (forumPath) {
      openForumApp(forumPath);
      return;
    }

    onNavigate(path);
  };

  return (
    <>
      <nav className={`navigation ${scrolled ? 'stick-top scrolled' : ''}`}>
        <div className="nav-container">
          <div className="nav-brand" onClick={() => handleNavClick('/')}>
            <img className="eagle-emblem" src="/logo.png" alt="Ang Agila logo 1" />
          </div>

          <button className="menu-toggle" onClick={() => setMenuOpen(!menuOpen)}>
            {menuOpen ? <X size={24} /> : <Menu size={24} />}
          </button>

          <div className={`nav-links ${menuOpen ? 'open' : ''}`}>
            <button
              onClick={() => handleNavClick('/')}
              className={`nav-link ${currentPath === '/' && !aboutOpen ? 'active' : ''}`}
            >
              <span>Home</span>
            </button>
            <div className={`nav-dropdown ${aboutOpen ? 'open' : ''}`} ref={aboutDropdownRef}>
              <button
                className={`nav-link nav-link-dropdown ${aboutOpen || isAboutRoute ? 'active' : ''}`}
                onClick={() => {
                  setAboutOpen((isOpen) => !isOpen);
                  setUpdatesOpen(false);
                  setOfficersOpen(false);
                }}
                aria-haspopup="menu"
                aria-expanded={aboutOpen}
              >
                <span>About Us</span>
                <ChevronDown size={16} className="nav-dropdown-chevron" />
              </button>
              <div className="nav-dropdown-menu" role="menu" aria-label="About Us">
                <button className="nav-dropdown-item" onClick={() => handleNavClick('/history')}>
                  History
                </button>
                <button className="nav-dropdown-item" onClick={() => handleNavClick('/magna-carta')}>
                  Magna Carta
                </button>
              </div>
            </div>
            <div className={`nav-dropdown ${updatesOpen ? 'open' : ''}`} ref={updatesDropdownRef}>
              <button
                className={`nav-link nav-link-dropdown ${updatesOpen || isUpdatesRoute ? 'active' : ''}`}
                onClick={() => {
                  setUpdatesOpen((isOpen) => !isOpen);
                  setAboutOpen(false);
                  setOfficersOpen(false);
                }}
                aria-haspopup="menu"
                aria-expanded={updatesOpen}
              >
                <span>Updates</span>
                <ChevronDown size={16} className="nav-dropdown-chevron" />
              </button>
              <div className="nav-dropdown-menu" role="menu" aria-label="Updates">
                <button className="nav-dropdown-item" onClick={() => handleNavClick('/news')}>
                  News
                </button>
                <button className="nav-dropdown-item" onClick={() => handleNavClick('/events')}>
                  Events
                </button>
                <button className="nav-dropdown-item" onClick={() => handleNavClick('/videos')}>
                  Videos
                </button>
              </div>
            </div>
            <div className={`nav-dropdown ${officersOpen ? 'open' : ''}`} ref={officersDropdownRef}>
              <button
                className={`nav-link nav-link-dropdown ${officersOpen || isOfficersRoute ? 'active' : ''}`}
                onClick={() => {
                  setOfficersOpen((isOpen) => !isOpen);
                  setAboutOpen(false);
                  setUpdatesOpen(false);
                }}
                aria-haspopup="menu"
                aria-expanded={officersOpen}
              >
                <span>Officers</span>
                <ChevronDown size={16} className="nav-dropdown-chevron" />
              </button>
              <div className="nav-dropdown-menu" role="menu" aria-label="Officers">
                {OFFICERS_DROPDOWN_ITEMS.map(({ path, label }) => (
                  <button key={path} className="nav-dropdown-item" onClick={() => handleNavClick(path)}>
                    {label}
                  </button>
                ))}
              </div>
            </div>
            {navItems.map(({ path, label, forumPath }) => (
              <button
                key={path}
                onClick={() => handleNavClick(path, forumPath)}
                className={`nav-link ${path === '/clubs' ? (isRegionalClubsRoute ? 'active' : '') : currentPath === path ? 'active' : ''}`}
              >
                <span>{label}</span>
              </button>
            ))}
          </div>
        </div>
      </nav>
      <div className={`nav-spacer ${currentPath === '/' ? 'home' : ''}`} />

      {menuOpen && <div className="nav-overlay show" onClick={() => setMenuOpen(false)} />}
    </>
  );
}
