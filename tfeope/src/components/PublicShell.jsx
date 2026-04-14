import { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import '../theme/navbar.css'
import '../theme/footer.css'
import { PUBLIC_BRANDING } from '../config'

const aboutPaths = ['/about-us', '/magna-carta']
const officerPaths = [
  '/officers',
  '/governors',
  '/appointed-ofc',
]

function isDesktop() {
  return window.matchMedia('(min-width: 901px)').matches
}

export default function PublicShell({ children }) {
  const location = useLocation()
  const branding = PUBLIC_BRANDING
  const [menuOpen, setMenuOpen] = useState(false)
  const [aboutOpen, setAboutOpen] = useState(false)
  const [officersOpen, setOfficersOpen] = useState(false)
  const [headerFaded, setHeaderFaded] = useState(false)

  const isAboutActive = useMemo(
    () => aboutPaths.includes(location.pathname),
    [location.pathname],
  )
  const isOfficersActive = useMemo(
    () => officerPaths.includes(location.pathname),
    [location.pathname],
  )

  useEffect(() => {
    function handleScroll() {
      setHeaderFaded(window.scrollY > 90)
    }

    handleScroll()
    window.addEventListener('scroll', handleScroll, { passive: true })

    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  useEffect(() => {
    function handleOutsideClick(event) {
      if (!event.target.closest('.nav-dropdown')) {
        setAboutOpen(false)
        setOfficersOpen(false)
      }

      if (!event.target.closest('#navbar') && !event.target.closest('#menu-toggle')) {
        setMenuOpen(false)
      }
    }

    document.addEventListener('click', handleOutsideClick)

    return () => document.removeEventListener('click', handleOutsideClick)
  }, [])

  function closeNavigation() {
    setMenuOpen(false)
    setAboutOpen(false)
    setOfficersOpen(false)
  }

  function toggleMenu(event) {
    event.stopPropagation()
    setMenuOpen((current) => !current)
  }

  function toggleAbout(event) {
    event.preventDefault()
    event.stopPropagation()
    setAboutOpen((current) => !current)
    setOfficersOpen(false)
  }

  function toggleOfficers(event) {
    event.preventDefault()
    event.stopPropagation()
    setOfficersOpen((current) => !current)
    setAboutOpen(false)
  }

  function onAboutHover(nextOpen) {
    if (!isDesktop()) {
      return
    }

    setAboutOpen(nextOpen)
    if (nextOpen) {
      setOfficersOpen(false)
    }
  }

  function onOfficersHover(nextOpen) {
    if (!isDesktop()) {
      return
    }

    setOfficersOpen(nextOpen)
    if (nextOpen) {
      setAboutOpen(false)
    }
  }

  return (
    <>
      <style>{`
        nav#navbar .nav-trigger {
          font-family: "Inter", sans-serif;
          font-weight: 800;
          font-size: 15px;
          color: var(--textTop);
          text-decoration: none;
          position: relative;
          padding: 6px 0;
          letter-spacing: 0.3px;
          transition: color 0.20s linear, opacity 0.20s linear;
          white-space: nowrap;
          text-shadow: var(--textGlow);
          background: none;
          border: 0;
          cursor: pointer;
        }

        header.site-header.is-faded nav#navbar .nav-trigger {
          color: var(--textFaded);
          text-shadow: none;
        }

        nav#navbar .nav-trigger::after {
          content: "";
          position: absolute;
          bottom: -5px;
          left: 0;
          width: 0%;
          height: 3px;
          border-radius: 2px;
          transition: width 0.25s ease;
          background: linear-gradient(90deg, var(--brandBlue), var(--brandBlue2));
          opacity: 0.95;
        }

        nav#navbar .nav-trigger:hover::after,
        nav#navbar .nav-trigger.active::after {
          width: 100%;
        }

        nav#navbar .dropdown-menu a.is-active {
          background: var(--ddItemActive);
        }
      `}</style>

      <header className={`site-header ${headerFaded ? 'is-faded' : ''}`} id="siteHeader">
        <div className="container">
          <div className="logo">
            <Link to="/" onClick={closeNavigation}>
              {branding.logoUrl ? <img src={branding.logoUrl} alt="Logo" /> : null}
            </Link>
            <span className="nav-title">Ang Agila</span>
          </div>

          <nav id="navbar" className={menuOpen ? 'active' : ''}>
            <NavLink to="/" end onClick={closeNavigation}>
              Home
            </NavLink>

            <div
              className={`nav-dropdown ${aboutOpen ? 'open' : ''}`}
              id="aboutDropdown"
              onMouseEnter={() => onAboutHover(true)}
              onMouseLeave={() => onAboutHover(false)}
            >
              <button
                type="button"
                className={`about-link nav-trigger ${isAboutActive ? 'active' : ''}`}
                onClick={toggleAbout}
                aria-expanded={aboutOpen}
              >
                About Us
              </button>
              <div className="dropdown-menu">
                <NavLink
                  to="/about-us"
                  className={({ isActive }) => (isActive ? 'is-active' : '')}
                  onClick={closeNavigation}
                >
                  History
                </NavLink>
                <NavLink
                  to="/magna-carta"
                  className={({ isActive }) => (isActive ? 'is-active' : '')}
                  onClick={closeNavigation}
                >
                  Magna Carta
                </NavLink>
              </div>
            </div>

            <NavLink to="/news" onClick={closeNavigation}>
              News &amp; Videos
            </NavLink>

            <div
              className={`nav-dropdown ${officersOpen ? 'open' : ''}`}
              id="officersDropdown"
              onMouseEnter={() => onOfficersHover(true)}
              onMouseLeave={() => onOfficersHover(false)}
            >
              <button
                type="button"
                className={`officers-link nav-trigger ${isOfficersActive ? 'active' : ''}`}
                onClick={toggleOfficers}
                aria-expanded={officersOpen}
              >
                Officers
              </button>
              <div className="dropdown-menu">
                <NavLink
                  to="/officers"
                  className={({ isActive }) => (isActive ? 'is-active' : '')}
                  onClick={closeNavigation}
                >
                  National Officers
                </NavLink>
                <NavLink
                  to="/governors"
                  className={({ isActive }) => (isActive ? 'is-active' : '')}
                  onClick={closeNavigation}
                >
                  Governors
                </NavLink>
                <NavLink
                  to="/appointed-ofc"
                  className={({ isActive }) => (isActive ? 'is-active' : '')}
                  onClick={closeNavigation}
                >
                  Appointed Officers
                </NavLink>
              </div>
            </div>

            <NavLink to="/events" onClick={closeNavigation}>
              Events
            </NavLink>

            <NavLink to="/membership" className="cta-btn" onClick={closeNavigation}>
              Get Started
            </NavLink>
          </nav>

          <div
            id="menu-toggle"
            className="menu-toggle"
            onClick={toggleMenu}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                toggleMenu(event)
              }
            }}
            role="button"
            tabIndex={0}
            aria-label="Toggle navigation"
          >
            &#9776;
          </div>
        </div>
      </header>

      {children}

      <footer id="app-footer">
        <div className="footer-container">
          <div className="footer-brand">
            {branding.logoUrl ? <img src={branding.logoUrl} alt="Logo" /> : null}
            {branding.alphaLogoUrl ? <img src={branding.alphaLogoUrl} alt="Logo 2" /> : null}
            <p>
              Service Through
              <br />
              Strong Brotherhood
            </p>
          </div>

          <div className="footer-links">
            <h4>Quick Links</h4>
            <ul>
              <li>
                <Link to="/about-us">About Us</Link>
              </li>
              <li>
                <Link to="/news">Latest News</Link>
              </li>
              <li>
                <Link to="/magna-carta">Magna Carta</Link>
              </li>
              <li>
                <Link to="/events">Events</Link>
              </li>
            </ul>
          </div>

          <div className="footer-contact">
            <h4>Contact Us</h4>
            <p>Quezon City, Philippines</p>
            <p>Phone: (02) 123-4567</p>
            <p>Email: angagila2026@gmail.com</p>
          </div>

          <div className="footer-social">
            <h4>Follow Us</h4>
            <a
              className="social-btn"
              href="https://www.facebook.com/profile.php?id=61571962082522"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Facebook"
            >
              <i className="fa-brands fa-facebook-f"></i>
            </a>
            <a
              className="social-btn"
              href="https://instagram.com"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Instagram"
            >
              <i className="fa-brands fa-instagram"></i>
            </a>
            <a
              className="social-btn"
              href="https://twitter.com"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="X"
            >
              <i className="fa-brands fa-x-twitter"></i>
            </a>
          </div>
        </div>

        <div className="footer-bottom">
          (c) 2026 Ang Agila | Fraternal Order of Eagles. All Rights Reserved.
        </div>
      </footer>
    </>
  )
}
