import { useState } from 'react';
import { NavLink } from 'react-router-dom';

function Navbar({ isAuthenticated, profileEmail, canAccessAdmin, activeTab, onSelectAuthTab, onLogout }) {
  const [menuOpen, setMenuOpen] = useState(false);

  function closeMenu() {
    setMenuOpen(false);
  }

  function handleAuthTabSelect(tab) {
    onSelectAuthTab(tab);
    closeMenu();
  }

  async function handleLogout() {
    closeMenu();
    await onLogout();
  }

  return (
    <header className="site-nav-wrap">
      <nav className="site-nav" aria-label="Primary">
        <NavLink className="brand" to={isAuthenticated ? '/app' : '/auth'} onClick={closeMenu}>
          <span className="brand-dot" aria-hidden="true" />
          Dochelper
        </NavLink>

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
          {isAuthenticated ? (
            <NavLink
              to="/app"
              className={({ isActive }) => (isActive ? 'nav-pill nav-pill-active' : 'nav-pill')}
              onClick={closeMenu}
            >
              App
            </NavLink>
          ) : null}

          {isAuthenticated ? (
            <NavLink
              to="/profile"
              className={({ isActive }) => (isActive ? 'nav-pill nav-pill-active' : 'nav-pill')}
              onClick={closeMenu}
            >
              Profile
            </NavLink>
          ) : null}

          {!isAuthenticated ? (
            <div className="nav-auth">
              <button
                type="button"
                className={activeTab === 'login' ? 'nav-pill nav-pill-active' : 'nav-pill'}
                onClick={() => handleAuthTabSelect('login')}
              >
                Login
              </button>
              <button
                type="button"
                className={activeTab === 'register' ? 'nav-pill nav-pill-active' : 'nav-pill'}
                onClick={() => handleAuthTabSelect('register')}
              >
                Register
              </button>
            </div>
          ) : (
            <div className="nav-auth nav-auth-user">
              {canAccessAdmin ? (
                <NavLink
                  to="/admin"
                  className={({ isActive }) => (isActive ? 'nav-pill nav-pill-active' : 'nav-pill')}
                  onClick={closeMenu}
                >
                  Admin
                </NavLink>
              ) : null}
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
