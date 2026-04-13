import { useEffect, useMemo, useState } from 'react';
import {
  createAppointment,
  getDoctorCalendar,
  getDoctors,
  getProfile,
  loginUser,
  logoutUser,
  registerUser,
} from './api/auth';
import BookingAppointmentModal from './components/BookingAppointmentModal';
import DoctorCalendarPanel from './components/DoctorCalendarPanel';
import Footer from './components/Footer';
import Navbar from './components/Navbar';

const TOKEN_KEY = 'dochelper_jwt';

function App() {
  const [activeTab, setActiveTab] = useState('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY));
  const [profile, setProfile] = useState(null);
  const [doctors, setDoctors] = useState([]);
  const [selectedDoctorId, setSelectedDoctorId] = useState('');
  const [calendarData, setCalendarData] = useState(null);
  const [calendarError, setCalendarError] = useState('');
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

  useEffect(() => {
    if (!isAuthenticated) {
      setDoctors([]);
      setSelectedDoctorId('');
      setCalendarData(null);
      setCalendarError('');
      setBookingModalOpen(false);
      setSelectedSlot(null);
      setBookingError('');

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
  }, [isAuthenticated]);

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
    setDoctors([]);
    setSelectedDoctorId('');
    setCalendarData(null);
    setCalendarError('');
    setBookingModalOpen(false);
    setSelectedSlot(null);
    setBookingError('');
    setStatus('Logged out.');
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
        activeTab={activeTab}
        onSelectTab={setActiveTab}
        onLogout={handleLogout}
      />

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

              {profile ? (
                <pre className="json-preview">{JSON.stringify(profile, null, 2)}</pre>
              ) : null}
            </div>
          )}

          {status ? <p className="status-ok">{status}</p> : null}
          {error ? <p className="status-error">{error}</p> : null}
        </section>
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
