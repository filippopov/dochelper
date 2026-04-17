function AppointmentActionModal({
  open,
  slot,
  doctorEmail,
  isSubmitting,
  error,
  onApprove,
  onCancelAppointment,
  onClose,
}) {
  if (!open || !slot?.appointment) {
    return null;
  }

  const appointment = slot.appointment;
  const canApprove = appointment.status === 'pending';
  const canCancel = appointment.status === 'pending' || appointment.status === 'confirmed';

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onClick={() => {
        if (!isSubmitting) {
          onClose();
        }
      }}
    >
      <section
        className="booking-modal appointment-action-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="appointment-action-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="appointment-action-title">Manage Appointment</h3>

        <div className="booking-summary">
          <p>
            <strong>Doctor:</strong> {doctorEmail}
          </p>
          <p>
            <strong>Patient:</strong> {appointment.patientEmail}
          </p>
          <p>
            <strong>Time:</strong> {formatTimeRange(slot.startAt, slot.endAt)}
          </p>
          <p>
            <strong>Status:</strong> {appointment.status}
          </p>
        </div>

        <p className="helper-text">Approve maps to the confirmed status.</p>
        {error ? <p className="status-error modal-error">{error}</p> : null}

        <div className="modal-actions">
          <button type="button" className="secondary-button" onClick={onClose} disabled={isSubmitting}>
            Close
          </button>
          <button
            type="button"
            className="secondary-button"
            onClick={() => onCancelAppointment(appointment.id)}
            disabled={!canCancel || isSubmitting}
          >
            {isSubmitting ? 'Working...' : 'Cancel'}
          </button>
          <button
            type="button"
            className="primary-button"
            onClick={() => onApprove(appointment.id)}
            disabled={!canApprove || isSubmitting}
          >
            {isSubmitting ? 'Working...' : 'Approve'}
          </button>
        </div>
      </section>
    </div>
  );
}

function formatTimeRange(startAt, endAt) {
  const format = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    hour12: false,
    timeZone: 'UTC',
  });

  return `${format.format(new Date(startAt))} - ${new Intl.DateTimeFormat(undefined, {
    timeStyle: 'short',
    hour12: false,
    timeZone: 'UTC',
  }).format(new Date(endAt))}`;
}

export default AppointmentActionModal;
