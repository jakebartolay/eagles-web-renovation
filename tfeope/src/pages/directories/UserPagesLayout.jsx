import { createElement } from 'react';
import { Home, ShieldCheck, UserRound, Users } from 'lucide-react';
import { navigateTo } from '../../components/HashRouter';
import { openForumApp } from '../../lib/forumAppUrl';
import './userPages.css';

const navItems = [
  { path: '/', label: 'Home', icon: Home },
  { path: '/officers/governors', label: 'Governors', icon: ShieldCheck },
  { path: '/clubs', label: 'Regional Clubs', icon: Users },
  { path: '/member/login', label: 'Member Portal', icon: UserRound, forumPath: '/login' },
];

export function UserPagesNav({ activePath = '' }) {
  return (
    <nav className="user-mini-nav" aria-label="User pages">
      <button className="user-mini-brand" type="button" onClick={() => navigateTo('/')}>
        <img src="/logo.png" alt="TFOE-PE logo" />
        <span>TFOE-PE Inc.</span>
      </button>

      <div className="user-mini-links">
        {navItems.map(({ path, label, icon, forumPath }) => (
          <button
            key={path}
            className={`user-mini-link ${activePath === path ? 'active' : ''}`}
            type="button"
            onClick={() => {
              if (forumPath) {
                openForumApp(forumPath);
                return;
              }

              navigateTo(path);
            }}
          >
            {createElement(icon, { size: 15 })}
            <span>{label}</span>
          </button>
        ))}
      </div>
    </nav>
  );
}

export function UserBreadcrumb({ items }) {
  return (
    <div className="user-breadcrumb" aria-label="Breadcrumb">
      {items.map((item, index) => (
        <span className="user-breadcrumb-item" key={`${item.label}-${index}`}>
          {item.path ? (
            <button type="button" onClick={() => navigateTo(item.path)}>
              {item.label}
            </button>
          ) : (
            <strong>{item.label}</strong>
          )}
          {index < items.length - 1 ? <span className="user-breadcrumb-separator">/</span> : null}
        </span>
      ))}
    </div>
  );
}

export function UserPagesFooter() {
  return (
    <footer className="user-pages-footer">
      <p>
        <strong>TFOE-PE Inc.</strong> - The Fraternal Order of Eagles Philippines Inc.
      </p>
      <p>Copyright 2026. All Rights Reserved.</p>
    </footer>
  );
}

export default function UserPagesLayout({ activePath, breadcrumbItems, showChrome = true, children }) {
  return (
    <div className="user-pages">
      {showChrome ? <UserPagesNav activePath={activePath} /> : null}
      {breadcrumbItems ? <UserBreadcrumb items={breadcrumbItems} /> : null}
      {children}
      {showChrome ? <UserPagesFooter /> : null}
    </div>
  );
}
