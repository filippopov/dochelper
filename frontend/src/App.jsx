import { useEffect, useMemo, useState } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import {
  createAppointment,
  getDoctorCalendar,
  getDoctors,
  getProfile,
  loginUser,
  logoutUser,
  registerUser,
} from './api/auth';
import AdminPage from './components/AdminPage';
import BookingAppointmentModal from './components/BookingAppointmentModal';
import DoctorCalendarPanel from './components/DoctorCalendarPanel';
import Footer from './components/Footer';
import Navbar from './components/Navbar';

const TOKEN_KEY = 'dochelper_jwt';

function hasAnyRole(profile, expectedRoles) {
  const grantedRoles = Array.isArray(profile?.roles) ? profile.roles : [];

  return expectedRoles.some((role) => grantedRoles.includes(role));
}

function App() {
  const navigate = useNavigate();
  const location = useLocation();
  const [activeTab, setActiveTab] = useState('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY));
  const [profile, setProfile] = useState(null);
  const [authBootstrapLoading, setAuthBootstrapLoading] = useState(() => Boolean(localStorage.getItem(TOKEN_KEY)));
  const [doctors, setDoctors] = useState([]);
  const [selectedDoctorId, setSelectedDoctorId] = useState('');
  const [calendarData, setCalendarData] = useState(null);
  const [calendarError, setCalendarError] = useState('');
  const [adminDoctors, setAdminDoctors] = useState([]);
  const [selectedAdminDoctorId, setSelectedAdminDoctorId] = useState('');
  const [adminCalendarData, setAdminCalendarData] = useState(null);
  const [adminCalendarError, setAdminCalendarError] = useState('');
  const [adminDoctorsLoading, setAdminDoctorsLoading] = useState(false);
  const [adminCalendarLoading, setAdminCalendarLoading] = useState(false);
  const [bookingModalOpen, setBookingModalOpen] = useState(false);
  const [selectedSlot, setSelectedSlot] = useState(null);
  const [bookingReason, setBookingReason] = useState('Consultation');
  const [bookingDuration, setBookingDuration] = useState('30');
  const [bookingError, setBookingError] = useState('');
  const [bookingSubmitting, setBookingSubmitting] = useState(false);
  const [calendarReloadToken, setCalendarReloadToken] = useState(0);
  const [doctorsLoading, setDoctorsLoading] = useState(false);
  const [calendarLoading, setCalendarLoading] = useState(false);
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const isAuthenticated = useMemo(() => Boolean(token), [token]);
  const canAccessAdmin = useMemo(() => {
    if (!isAuthenticated || profile === null) {
      return false;
    }

    const roleType = typeof profile.roleType === 'string' ? profile.roleType.toLowerCase() : '';
    if (roleType === 'doctor' || roleType === 'admin') {
      return true;
    }

    return hasAnyRole(profile, ['ROLE_DOCTOR', 'ROLE_ADMIN']);
  }, [isAuthenticated, profile]);
  const isAdminUser = useMemo(() => {
    if (profile === null) {
      return false;
    }

    const roleType = typeof profile.roleType === 'string' ? profile.roleType.toLowerCase() : '';

    return roleType === 'admin' || hasAnyRole(profile, ['ROLE_ADMIN']);
  }, [profile]);
  const isDoctorUser = useMemo(() => {
    if (profile === null) {
      return false;
    }

    const roleType = typeof profile.roleType === 'string' ? profile.roleType.toLowerCase() : '';

    return roleType === 'doctor' || hasAnyRole(profile, ['ROLE_DOCTOR']);
  }, [profile]);

  useEffect(() => {
    if (!isAuthenticated) {
      setAuthBootstrapLoading(false);
      setProfile(null);

      return;
    }

    let ignore = false;

    async function bootstrapProfile() {
      setAuthBootstrapLoading(true);

      try {
        const me = await getProfile();

        if (!ignore) {
          setProfile(me);
        }
      } catch {
        if (!ignore) {
          await logoutUser();
          setToken(null);
          setProfile(null);
          setStatus('Session expired. Please log in again.');
          navigate('/auth', { replace: true });
        }
      } finally {
        if (!ignore) {
          setAuthBootstrapLoading(false);
        }
      }
    }

    bootstrapProfile();

    return () => {
      ignore = true;
    };
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    if (!isAuthenticated) {
      if (location.pathname !== '/auth') {
        navigate('/auth', { replace: true });
      }

      return;
    }

    if (authBootstrapLoading) {
      return;
    }

    if (location.pathname === '/auth' || location.pathname === '/') {
      navigate('/app', { replace: true });

      return;
    }

    if (location.pathname === '/admin' && !canAccessAdmin) {
      navigate('/app', { replace: true });
    }
  }, [authBootstrapLoading, canAccessAdmin, isAuthenticated, location.pathname, navigate]);

  useEffect(() => {
    if (!isAuthenticated || authBootstrapLoading || profile === null) {
      setDoctors([]);
      setSelectedDoctorId('');
      setCalendarData(null);
      setCalendarError('');
      setBookingModalOpen(false);
      setSelectedSlot(null);
      setBookingError('');

      return;
    }

    if (isDoctorUser) {
      return;
    }

    let ignore = false;

    async function loadDoctors() {
      setDoctorsLoading(true);

      try {
        const response = await getDoctors();

        if (ignore) {
          return;
        }

        const items = Array.isArray(response.items) ? response.items : [];
        setDoctors(items);

        if (items.length > 0) {
          setSelectedDoctorId(String(items[0].id));
        }
      } catch (requestError) {
        if (!ignore) {
          setCalendarError(requestError.message);
        }
      } finally {
        if (!ignore) {
          setDoctorsLoading(false);
        }
      }
    }

    loadDoctors();

    return () => {
      ignore = true;
    };
  }, [authBootstrapLoading, isAuthenticated, isDoctorUser, profile]);

  useEffect(() => {
    if (!isAuthenticated || selectedDoctorId === '') {
      return;
    }

    let ignore = false;

    async function loadCalendar() {
      const startDate = new Date();
      const endDate = new Date();
      endDate.setDate(startDate.getDate() + 6);

      const format = (dateValue) => dateValue.toISOString().slice(0, 10);

      setCalendarLoading(true);
      setCalendarError('');

      try {
        const response = await getDoctorCalendar(selectedDoctorId, {
          startDate: format(startDate),
          endDate: format(endDate),
        });

        if (!ignore) {
          setCalendarData(response);
        }
      } catch (requestError) {
        if (!ignore) {
          setCalendarData(null);
          setCalendarError(requestError.message);
        }
      } finally {
        if (!ignore) {
          setCalendarLoading(false);
        }
      }
    }

    loadCalendar();

    return () => {
      ignore = true;
    };
  }, [isAuthenticated, selectedDoctorId, calendarReloadToken]);

  useEffect(() => {
    if (!isAuthenticated || !canAccessAdmin || authBootstrapLoading || profile === null) {
      setAdminDoctors([]);
      setSelectedAdminDoctorId('');
      setAdminCalendarData(null);
      setAdminCalendarError('');
      setAdminDoctorsLoading(false);

      return;
    }

    if (isDoctorUser && profile.id != null) {
      setAdminDoctors([
        {
          id: profile.id,
          email: profile.email,
        },
      ]);
      setSelectedAdminDoctorId(String(profile.id));

      return;
    }

    if (!isAdminUser) {
      return;
    }

    let ignore = false;

    async function loadAdminDoctors() {
      setAdminDoctorsLoading(true);
      setAdminCalendarError('');

      try {
        const response = await getDoctors();

        if (ignore) {
          return;
        }

        const items = Array.isArray(response.items) ? response.items : [];
        setAdminDoctors(items);

        if (items.length > 0) {
          setSelectedAdminDoctorId((currentId) => {
            const currentExists = items.some((doctor) => String(doctor.id) === String(currentId));

            return currentExists ? currentId : String(items[0].id);
          });
        }
      } catch (requestError) {
        if (!ignore) {
          setAdminDoctors([]);
          setSelectedAdminDoctorId('');
          setAdminCalendarError(requestError.message);
        }
      } finally {
        if (!ignore) {
          setAdminDoctorsLoading(false);
        }
      }
    }

    loadAdminDoctors();

    return () => {
      ignore = true;
    };
  }, [authBootstrapLoading, canAccessAdmin, isAdminUser, isAuthenticated, isDoctorUser, profile]);

  useEffect(() => {
    if (!isAuthenticated || !canAccessAdmin || selectedAdminDoctorId === '') {
      return;
    }

    let ignore = false;

    async function loadAdminCalendar() {
      const startDate = new Date();
      const endDate = new Date();
      endDate.setDate(startDate.getDate() + 6);

      const format = (dateValue) => dateValue.toISOString().slice(0, 10);

      setAdminCalendarLoading(true);
      setAdminCalendarError('');

      try {
        const response = await getDoctorCalendar(selectedAdminDoctorId, {
          startDate: format(startDate),
          endDate: format(endDate),
        });

        if (!ignore) {
          setAdminCalendarData(response);
        }
      } catch (requestError) {
        if (!ignore) {
          setAdminCalendarData(null);
          setAdminCalendarError(requestError.message);
        }
      } finally {
        if (!ignore) {
          setAdminCalendarLoading(false);
        }
      }
    }

    loadAdminCalendar();

    return () => {
      ignore = true;
    };
  }, [canAccessAdmin, isAuthenticated, selectedAdminDoctorId]);

  function openBookingModal(slot) {
    if (!selectedDoctorId) {
      return;
    }

    setSelectedSlot(slot);
    setBookingReason('Consultation');
    setBookingDuration('30');
    setBookingError('');
    setBookingModalOpen(true);
  }

  function closeBookingModal() {
    if (bookingSubmitting) {
      return;
    }

    setBookingModalOpen(false);
    setSelectedSlot(null);
    setBookingError('');
  }

  async function handleBookingSubmit(event) {
    event.preventDefault();

    if (!selectedDoctorId || !selectedSlot) {
      return;
    }

    setError('');
    setStatus('');
    setBookingError('');
    setBookingSubmitting(true);

    try {
      await createAppointment({
        doctorId: selectedDoctorId,
        scheduledAt: selectedSlot.startAt,
        durationMinutes: Number(bookingDuration),
        reason: bookingReason.trim(),
      });

      setStatus(
        `Appointment booked for ${new Intl.DateTimeFormat(undefined, {
          dateStyle: 'medium',
          timeStyle: 'short',
          timeZone: 'UTC',
        }).format(new Date(selectedSlot.startAt))}.`
      );
      setBookingModalOpen(false);
      setSelectedSlot(null);
      setCalendarReloadToken((current) => current + 1);
    } catch (requestError) {
      setBookingError(requestError.message);
    } finally {
      setBookingSubmitting(false);
    }
  }

  async function handleRegister(event) {
    event.preventDefault();
    setLoading(true);
    setError('');
    setStatus('');

    try {
      const user = await registerUser({ email, password });
      setStatus(`Account created for ${user.email}. You can log in now.`);
      setActiveTab('login');
      navigate('/auth', { replace: true });
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
      setProfile(data.user ?? null);
      setStatus('Login successful. Token stored in localStorage.');
      navigate('/app', { replace: true });
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
    setActiveTab('login');
    setDoctors([]);
    setSelectedDoctorId('');
    setCalendarData(null);
    setCalendarError('');
    setAdminDoctors([]);
    setSelectedAdminDoctorId('');
    setAdminCalendarData(null);
    setAdminCalendarError('');
    setBookingModalOpen(false);
    setSelectedSlot(null);
    setBookingError('');
    setStatus('Logged out.');
    navigate('/auth', { replace: true });
  }

  function handleSelectAuthTab(tab) {
    setActiveTab(tab);
    navigate('/auth');
  }

  const selectedDoctor = useMemo(
    () => doctors.find((doctor) => String(doctor.id) === String(selectedDoctorId)) ?? null,
    [doctors, selectedDoctorId]
  );

  return (
    <div id="top" className="page-shell">
      <Navbar
        isAuthenticated={isAuthenticated}
        profileEmail={profile?.email ?? email}
        canAccessAdmin={canAccessAdmin}
        activeTab={activeTab}
        onSelectAuthTab={handleSelectAuthTab}
        onLogout={handleLogout}
      />

      <main className="app-shell">
        <Routes>
          <Route
            path="/auth"
            element={
              <section className="card">
                <p className="eyebrow">Dochelper API Auth</p>
                <h1>JWT Login and Registration</h1>

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

                {status ? <p className="status-ok">{status}</p> : null}
                {error ? <p className="status-error">{error}</p> : null}
              </section>
            }
          />
          <Route
            path="/app"
            element={
              isAuthenticated ? (
                <section className="card">
                  <p className="eyebrow">Dochelper App</p>
                  <h1>Appointments and Calendar</h1>
                  <div className="auth-box">
                    {authBootstrapLoading ? <p>Loading your session...</p> : <p>You are authenticated.</p>}
                    <div className="button-row">
                      <button type="button" className="primary-button" onClick={handleLoadProfile} disabled={loading}>
                        Refresh profile
                      </button>
                    </div>

                    <DoctorCalendarPanel
                      doctors={doctors}
                      selectedDoctorId={selectedDoctorId}
                      onDoctorChange={setSelectedDoctorId}
                      onSlotSelect={openBookingModal}
                      doctorsLoading={doctorsLoading}
                      calendarLoading={calendarLoading}
                      calendarData={calendarData}
                      calendarError={calendarError}
                    />

                    {profile ? <pre className="json-preview">{JSON.stringify(profile, null, 2)}</pre> : null}
                  </div>

                  {status ? <p className="status-ok">{status}</p> : null}
                  {error ? <p className="status-error">{error}</p> : null}
                </section>
              ) : (
                <Navigate to="/auth" replace />
              )
            }
          />
          <Route
            path="/admin"
            element={
              isAuthenticated && canAccessAdmin ? (
                <section className="card">
                  <p className="eyebrow">Dochelper Admin</p>
                  <h1>Admin Workspace</h1>
                  <AdminPage
                    profile={profile}
                    doctors={adminDoctors}
                    selectedDoctorId={selectedAdminDoctorId}
                    onDoctorChange={setSelectedAdminDoctorId}
                    doctorsLoading={adminDoctorsLoading}
                    calendarLoading={adminCalendarLoading}
                    calendarData={adminCalendarData}
                    calendarError={adminCalendarError}
                    onBackToApp={() => navigate('/app')}
                  />
                  {status ? <p className="status-ok">{status}</p> : null}
                  {error ? <p className="status-error">{error}</p> : null}
                </section>
              ) : (
                <Navigate to={isAuthenticated ? '/app' : '/auth'} replace />
              )
            }
          />
          <Route path="*" element={<Navigate to={isAuthenticated ? '/app' : '/auth'} replace />} />
        </Routes>
      </main>

      <Footer />

      <BookingAppointmentModal
        open={bookingModalOpen}
        doctorEmail={selectedDoctor?.email ?? 'Unknown doctor'}
        slot={selectedSlot}
        reason={bookingReason}
        duration={bookingDuration}
        isSubmitting={bookingSubmitting}
        error={bookingError}
        onReasonChange={setBookingReason}
        onDurationChange={setBookingDuration}
        onClose={closeBookingModal}
        onSubmit={handleBookingSubmit}
      />
    </div>
  );
}

export default App;
