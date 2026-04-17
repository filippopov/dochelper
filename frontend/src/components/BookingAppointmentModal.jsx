import { useEffect } from 'react';

function BookingAppointmentModal({
  open,
  doctorEmail,
  slot,
  reason,
  duration,
  isSubmitting,
  error,
  onReasonChange,
  onDurationChange,
  onClose,
  onSubmit,
}) {
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleKeydown(event) {
      if (event.key === 'Escape' && !isSubmitting) {
        onClose();
      }
    }

    window.addEventListener('keydown', handleKeydown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeydown);
    };
  }, [open, isSubmitting, onClose]);

  if (!open || !slot) {
    return null;
  }

  const dateLabel = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'full',
    timeZone: 'UTC',
  }).format(new Date(slot.startAt));

  const timeLabel = `${new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'UTC',
  }).format(new Date(slot.startAt))} - ${new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'UTC',
  }).format(new Date(slot.endAt))}`;

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
        className="booking-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="booking-modal-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="booking-modal-title">Book Appointment</h3>

        <div className="booking-summary">
          <p>
            <strong>Doctor:</strong> {doctorEmail}
          </p>
          <p>
            <strong>Date:</strong> {dateLabel}
          </p>
          <p>
            <strong>Time:</strong> {timeLabel} (UTC)
          </p>
        </div>

        <form className="booking-form" onSubmit={onSubmit}>
          <label htmlFor="booking-duration">Duration</label>
          <select
            id="booking-duration"
            value={duration}
            onChange={(event) => onDurationChange(event.target.value)}
            disabled={isSubmitting}
          >
            <option value="15">15 minutes</option>
            <option value="30">30 minutes</option>
            <option value="45">45 minutes</option>
            <option value="60">60 minutes</option>
          </select>

          <label htmlFor="booking-reason">Reason</label>
          <textarea
            id="booking-reason"
            rows={4}
            value={reason}
            onChange={(event) => onReasonChange(event.target.value)}
            placeholder="Describe the reason for the appointment"
            minLength={3}
            required
            disabled={isSubmitting}
          />

          {error ? <p className="status-error modal-error">{error}</p> : null}

          <div className="modal-actions">
            <button type="button" className="secondary-button" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button type="submit" className="primary-button" disabled={isSubmitting || reason.trim().length < 3}>
              {isSubmitting ? 'Saving...' : 'Save appointment'}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}

export default BookingAppointmentModal;
