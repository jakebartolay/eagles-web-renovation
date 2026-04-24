import { navigateTo } from './HashRouter';

export default function Footer() {
  const handleNavigate = (event, path) => {
    event.preventDefault();
    navigateTo(path);
  };

  return (
    <footer className="footer">
      <div className="footer-content">
        <div className="footer-section">
          <div className="footer-logos" aria-label="Organization logos">
            <img src="/logo.png" alt="Ang Agila logo 1" className="footer-logo" loading="lazy" decoding="async" />
            <img src="/aes.png" alt="Ang Agila logo 2" className="footer-logo" loading="lazy" decoding="async" />
          </div>
          <p>
            Service Through
            <br />
            Strong Brotherhood
          </p>
        </div>

        <div className="footer-section footer-section-quick">
          <h4>Quick Links</h4>
          <div className="footer-links-inline footer-links-quick">
            <a className="footer-text-link" href="/" onClick={(event) => handleNavigate(event, '/')}>
              About Us
            </a>
            <a className="footer-text-link" href="/news" onClick={(event) => handleNavigate(event, '/news')}>
              Latest News
            </a>
            <a className="footer-text-link" href="/magna-carta" onClick={(event) => handleNavigate(event, '/magna-carta')}>
              Magna Carta
            </a>
            <a className="footer-text-link" href="/events" onClick={(event) => handleNavigate(event, '/events')}>
              Events
            </a>
            <a className="footer-text-link" href="#contact">
              Contact Us
            </a>
          </div>
        </div>

        <div className="footer-section">
          <h4>Contact Us</h4>
          <p>Quezon City, Philippines</p>
          <p>Phone: (02) 123-4567</p>
          <p>Email: angagila2026@gmail.com</p>
        </div>

        <div className="footer-section">
          <h4>Follow Us</h4>
          <div className="footer-social-icons">
            <a
              href="https://www.facebook.com/p/Ang-Agila-61571962082522/"
              aria-label="Facebook"
              title="Facebook"
              target="_blank"
              rel="noreferrer"
            >
              <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path
                  fill="currentColor"
                  d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.3-1.5 1.6-1.5h1.7V5c-.3 0-1.4-.1-2.6-.1-2.6 0-4.3 1.6-4.3 4.5V11H7v3h2.9v8h3.6Z"
                />
              </svg>
            </a>
            <a
              href="https://www.youtube.com/@angagila2026"
              aria-label="YouTube"
              title="YouTube"
              target="_blank"
              rel="noreferrer"
            >
              <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path
                  fill="currentColor"
                  d="M21.6 8.2a2.7 2.7 0 0 0-1.9-1.9C18 5.8 12 5.8 12 5.8s-6 0-7.7.5a2.7 2.7 0 0 0-1.9 1.9A28.4 28.4 0 0 0 1.9 12c0 1.3.2 2.5.5 3.8a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9c.3-1.3.5-2.5.5-3.8s-.2-2.5-.5-3.8ZM10 15.2V8.8l5.5 3.2L10 15.2Z"
                />
              </svg>
            </a>
          </div>
        </div>
      </div>

      <div className="footer-bottom">
        <p>© 2026 Ang Agila | Fraternal Order of Eagles. All Rights Reserved.</p>
      </div>
    </footer>
  );
}
