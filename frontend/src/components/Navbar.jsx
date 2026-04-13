import { useState } from 'react';

function Navbar({ isAuthenticated, profileEmail, activeTab, onSelectTab, onLogout }) {
  const [menuOpen, setMenuOpen] = useState(false);

  function closeMenu() {
    setMenuOpen(false);
  }

  function handleTabSelect(tab) {
    onSelectTab(tab);
    closeMenu();
  }

  async function handleLogout() {
    closeMenu();
    await onLogout();
  }

  return (
    <header className="site-nav-wrap">
      <nav className="site-nav" aria-label="Primary">
        <a className="brand" href="#top" onClick={closeMenu}>
          <span className="brand-dot" aria-hidden="true" />
          Dochelper
        </a>

        <button
          type="button"
          className="nav-toggle"
          aria-expanded={menuOpen}
          aria-controls="primary-menu"
          aria-label="Toggle navigation menu"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span />
          <span />
          <span />
        </button>

        <div id="primary-menu" className={menuOpen ? 'nav-menu nav-menu-open' : 'nav-menu'}>
          <a className="nav-link" href="#top" onClick={closeMenu}>
            Home
          </a>

          {!isAuthenticated ? (
            <div className="nav-auth">
              <button
                type="button"
                className={activeTab === 'login' ? 'nav-pill nav-pill-active' : 'nav-pill'}
                onClick={() => handleTabSelect('login')}
              >
                Login
              </button>
              <button
                type="button"
                className={activeTab === 'register' ? 'nav-pill nav-pill-active' : 'nav-pill'}
                onClick={() => handleTabSelect('register')}
              >
                Register
              </button>
            </div>
          ) : (
            <div className="nav-auth nav-auth-user">
              <span className="user-chip" title={profileEmail || 'Authenticated user'}>
                {profileEmail || 'Signed in'}
              </span>
              <button type="button" className="nav-pill" onClick={handleLogout}>
                Logout
              </button>
            </div>
          )}
        </div>
      </nav>
    </header>
  );
}

export default Navbar;
