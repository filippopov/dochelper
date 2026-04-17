function DoctorCalendarPanel({
  doctors,
  selectedDoctorId,
  onDoctorChange,
  onSlotSelect,
  onDaySelect,
  onReadOnlySlotSelect,
  doctorsLoading,
  calendarLoading,
  calendarData,
  calendarError,
  title = 'Doctor Calendar',
  description = 'Select a doctor to view available and booked consultation slots.',
  readOnly = false,
  showDoctorSelector = true,
  allowDayManagement = false,
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
      hour12: false,
      timeZone: 'UTC',
    }).format(new Date(dateValue));
  }

  function getBookedSlotClass(slot) {
    const status = typeof slot?.appointment?.status === 'string' ? slot.appointment.status.toLowerCase() : '';

    if (status === 'pending') {
      return 'slot-chip slot-booked slot-booked-pending';
    }

    if (status === 'confirmed') {
      return 'slot-chip slot-booked slot-booked-confirmed';
    }

    if (status === 'completed') {
      return 'slot-chip slot-booked slot-booked-completed';
    }

    if (status === 'cancelled') {
      return 'slot-chip slot-booked slot-booked-cancelled';
    }

    return 'slot-chip slot-booked';
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
              <div className="day-card-header">
                <h3>{formatDay(day.date)}</h3>
                {allowDayManagement ? (
                  <button type="button" className="secondary-button day-manage-button" onClick={() => onDaySelect?.(day)}>
                    Manage
                  </button>
                ) : null}
              </div>

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
                      ) : allowDayManagement ? (
                        <button
                          type="button"
                          className={slot.status === 'booked' ? `${getBookedSlotClass(slot)} slot-action` : 'slot-chip slot-action'}
                          onClick={() => onReadOnlySlotSelect?.(day, slot)}
                        >
                          {formatTime(slot.startAt)} - {formatTime(slot.endAt)}
                        </button>
                      ) : (
                        <span className={slot.status === 'booked' ? getBookedSlotClass(slot) : 'slot-chip'}>
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
