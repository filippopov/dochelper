import { useMemo, useState } from 'react';
import { getProfile, loginUser, logoutUser, registerUser } from './api/auth';

const TOKEN_KEY = 'dochelper_jwt';

function App() {
  const [activeTab, setActiveTab] = useState('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY));
  const [profile, setProfile] = useState(null);
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const isAuthenticated = useMemo(() => Boolean(token), [token]);

  async function handleRegister(event) {
    event.preventDefault();
    setLoading(true);
    setError('');
    setStatus('');

    try {
      const user = await registerUser({ email, password });
      setStatus(`Account created for ${user.email}. You can log in now.`);
      setActiveTab('login');
      setPassword('');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleLogin(event) {
    event.preventDefault();
    setLoading(true);
    setError('');
    setStatus('');

    try {
      const data = await loginUser({ email, password });
      localStorage.setItem(TOKEN_KEY, data.token);
      setToken(data.token);
      setStatus('Login successful. Token stored in localStorage.');
      setPassword('');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleLoadProfile() {
    if (!token) {
      return;
    }

    setLoading(true);
    setError('');
    setStatus('');

    try {
      const me = await getProfile();
      setProfile(me);
      setStatus('Protected endpoint reached successfully.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }

    async function handleLogout() {
      await logoutUser();
    setToken(null);
    setProfile(null);
    setStatus('Logged out.');
  }

  return (
    <main className="app-shell">
      <section className="card">
        <p className="eyebrow">Dochelper API Auth</p>
        <h1>JWT Login and Registration</h1>

        {!isAuthenticated ? (
          <>
            <div className="tab-row">
              <button
                className={activeTab === 'login' ? 'tab tab-active' : 'tab'}
                type="button"
                onClick={() => setActiveTab('login')}
              >
                Login
              </button>
              <button
                className={activeTab === 'register' ? 'tab tab-active' : 'tab'}
                type="button"
                onClick={() => setActiveTab('register')}
              >
                Register
              </button>
            </div>

            <form onSubmit={activeTab === 'login' ? handleLogin : handleRegister} className="auth-form">
              <label htmlFor="email">Email</label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
              />

              {activeTab === 'register' ? (
                <p className="helper-text">Public registration currently creates patient accounts.</p>
              ) : null}

              <label htmlFor="password">Password</label>
              <input
                id="password"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                minLength={8}
              />

              <button type="submit" className="primary-button" disabled={loading}>
                {loading ? 'Please wait...' : activeTab === 'login' ? 'Login' : 'Create account'}
              </button>
            </form>
          </>
        ) : (
          <div className="auth-box">
            <p>You are authenticated.</p>
            <div className="button-row">
              <button type="button" className="primary-button" onClick={handleLoadProfile} disabled={loading}>
                Load /api/me
              </button>
              <button type="button" className="secondary-button" onClick={handleLogout}>
                Logout
              </button>
            </div>
            {profile ? (
              <pre className="json-preview">{JSON.stringify(profile, null, 2)}</pre>
            ) : null}
          </div>
        )}

        {status ? <p className="status-ok">{status}</p> : null}
        {error ? <p className="status-error">{error}</p> : null}
      </section>
    </main>
  );
}

export default App;
