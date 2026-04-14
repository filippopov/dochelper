function DoctorCalendarPanel({
  doctors,
  selectedDoctorId,
  onDoctorChange,
  onSlotSelect,
  doctorsLoading,
  calendarLoading,
  calendarData,
  calendarError,
  title = 'Doctor Calendar',
  description = 'Select a doctor to view available and booked consultation slots.',
  readOnly = false,
  showDoctorSelector = true,
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
      <h2>{title}</h2>
      <p className="helper-text">{description}</p>

      {showDoctorSelector ? (
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
      ) : null}

      {!hasDoctors && !doctorsLoading && showDoctorSelector ? (
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
                      {slot.status === 'available' && !readOnly ? (
                        <button
                          type="button"
                          className="slot-chip slot-action"
                          onClick={() => onSlotSelect(slot)}
                        >
                          {`${formatTime(slot.startAt)} - ${formatTime(slot.endAt)}`}
                        </button>
                      ) : (
                        <span className={slot.status === 'booked' ? 'slot-chip slot-booked' : 'slot-chip'}>
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
