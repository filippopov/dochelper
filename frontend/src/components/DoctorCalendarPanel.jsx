function DoctorCalendarPanel({
  doctors,
  selectedDoctorId,
  onDoctorChange,
  onSlotSelect,
  doctorsLoading,
  calendarLoading,
  calendarData,
  calendarError,
}) {
  const hasDoctors = doctors.length > 0;

  function formatDay(dateValue) {
    return new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(
      new Date(`${dateValue}T00:00:00`)
    );
  }

  function formatTime(dateValue) {
    return new Intl.DateTimeFormat(undefined, {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'UTC',
    }).format(new Date(dateValue));
  }

  return (
    <section className="calendar-panel" aria-live="polite">
      <h2>Doctor Calendar</h2>
      <p className="helper-text">Select a doctor to view available and booked consultation slots.</p>

      <div className="calendar-controls">
        <label htmlFor="doctor-select">Doctor</label>
        <select
          id="doctor-select"
          value={selectedDoctorId}
          onChange={(event) => onDoctorChange(event.target.value)}
          disabled={doctorsLoading || !hasDoctors}
        >
          <option value="">{doctorsLoading ? 'Loading doctors...' : 'Choose a doctor'}</option>
          {doctors.map((doctor) => (
            <option key={doctor.id} value={doctor.id}>
              {doctor.email}
            </option>
          ))}
        </select>
      </div>

      {!hasDoctors && !doctorsLoading ? (
        <p className="status-error">No doctors are currently available.</p>
      ) : null}

      {calendarLoading ? <p className="helper-text">Loading calendar...</p> : null}
      {calendarError ? <p className="status-error">{calendarError}</p> : null}

      {calendarData && !calendarLoading ? (
        <div className="calendar-grid">
          {calendarData.days.map((day) => (
            <article className="day-card" key={day.date}>
              <h3>{formatDay(day.date)}</h3>

              {day.slots.length === 0 ? (
                <p className="helper-text">No slots configured</p>
              ) : (
                <ul className="slot-list">
                  {day.slots.map((slot) => (
                    <li key={slot.startAt}>
                      {slot.status === 'available' ? (
                        <button
                          type="button"
                          className="slot-chip slot-action"
                          onClick={() => onSlotSelect(slot)}
                        >
                          {`${formatTime(slot.startAt)} - ${formatTime(slot.endAt)}`}
                        </button>
                      ) : (
                        <span className="slot-chip slot-booked">
                          {formatTime(slot.startAt)} - {formatTime(slot.endAt)}
                        </span>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </article>
          ))}
        </div>
      ) : null}
    </section>
  );
}

export default DoctorCalendarPanel;
