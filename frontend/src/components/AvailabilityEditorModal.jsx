import { useEffect, useMemo, useState } from 'react';

function AvailabilityEditorModal({
  open,
  doctorEmail,
  selectedDate,
  selectedSlot,
  intervals,
  source,
  loading,
  submitting,
  error,
  onClose,
  onCreate,
  onUpdate,
  onDelete,
}) {
  const [startTime, setStartTime] = useState('09:00');
  const [endTime, setEndTime] = useState('09:30');
  const [editingId, setEditingId] = useState(null);

  const dateLabel = useMemo(() => {
    if (!selectedDate) {
      return '';
    }

    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'full',
      timeZone: 'UTC',
    }).format(new Date(`${selectedDate}T00:00:00Z`));
  }, [selectedDate]);

  const usesDateOverrides = source === 'date_override';

  useEffect(() => {
    if (!open) {
      return;
    }

    if (selectedSlot?.startAt && selectedSlot?.endAt) {
      setStartTime(extractTime(selectedSlot.startAt));
      setEndTime(extractTime(selectedSlot.endAt));
      setEditingId(null);

      return;
    }

    const first = intervals[0];
    if (first && usesDateOverrides) {
      setStartTime(first.startTime);
      setEndTime(first.endTime);
      setEditingId(first.id);

      return;
    }

    setStartTime('09:00');
    setEndTime('09:30');
    setEditingId(null);
  }, [open, intervals, selectedSlot, usesDateOverrides]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleKeydown(event) {
      if (event.key === 'Escape' && !submitting) {
        onClose();
      }
    }

    window.addEventListener('keydown', handleKeydown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeydown);
    };
  }, [open, onClose, submitting]);

  if (!open || !selectedDate) {
    return null;
  }

  const normalizedStartTime = startTime.trim();
  const normalizedEndTime = endTime.trim();
  const hasValidTimes = isValidTime(normalizedStartTime) && isValidTime(normalizedEndTime);
  const canSubmit = !loading && !submitting && hasValidTimes && normalizedStartTime < normalizedEndTime;

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onClick={() => {
        if (!submitting) {
          onClose();
        }
      }}
    >
      <section
        className="booking-modal availability-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="availability-modal-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="availability-modal-title">Manage Availability</h3>

        <div className="booking-summary">
          <p>
            <strong>Doctor:</strong> {doctorEmail}
          </p>
          <p>
            <strong>Day:</strong> {dateLabel}
          </p>
          <p className="helper-text">
            {usesDateOverrides
              ? 'You are editing date-specific overrides for this day.'
              : 'No date-specific overrides yet. Weekly fallback intervals are shown below.'}
          </p>
        </div>

        <div className="availability-intervals">
          <p className="helper-text">Existing intervals</p>
          {loading ? <p className="helper-text">Loading intervals...</p> : null}
          {!loading && intervals.length === 0 ? <p className="helper-text">No intervals configured.</p> : null}

          {!loading && intervals.length > 0 ? (
            <ul className="slot-list">
              {intervals.map((interval) => (
                <li key={interval.id} className="availability-item">
                  <button
                    type="button"
                    className={editingId === interval.id ? 'slot-chip slot-action interval-active' : 'slot-chip slot-action'}
                    onClick={() => {
                      if (!usesDateOverrides) {
                        return;
                      }
                      setEditingId(interval.id);
                      setStartTime(interval.startTime);
                      setEndTime(interval.endTime);
                    }}
                    disabled={submitting}
                  >
                    {interval.startTime} - {interval.endTime}
                  </button>
                  {usesDateOverrides ? (
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={() => onDelete(interval.id)}
                      disabled={submitting}
                    >
                      Delete
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : null}
        </div>

        <form
          className="booking-form"
          onSubmit={(event) => {
            event.preventDefault();
            if (!canSubmit) {
              return;
            }

            if (editingId !== null && usesDateOverrides) {
              onUpdate(editingId, { startTime: normalizedStartTime, endTime: normalizedEndTime });
              return;
            }

            onCreate({ startTime: normalizedStartTime, endTime: normalizedEndTime });
          }}
        >
          <label htmlFor="availability-start">Start time</label>
          <input
            id="availability-start"
            type="text"
            inputMode="numeric"
            value={startTime}
            onChange={(event) => setStartTime(event.target.value)}
            placeholder="09:30"
            title="Use 24-hour format HH:MM"
            maxLength={5}
            required
            disabled={submitting}
          />

          <label htmlFor="availability-end">End time</label>
          <input
            id="availability-end"
            type="text"
            inputMode="numeric"
            value={endTime}
            onChange={(event) => setEndTime(event.target.value)}
            placeholder="10:30"
            title="Use 24-hour format HH:MM"
            maxLength={5}
            required
            disabled={submitting}
          />

          <p className="helper-text">Use 24-hour format: HH:MM (example: 09:30)</p>
          {!hasValidTimes ? <p className="status-error modal-error">Time format must be HH:MM (24-hour).</p> : null}
          {hasValidTimes && normalizedEndTime <= normalizedStartTime ? (
            <p className="status-error modal-error">End time must be later than start time.</p>
          ) : null}

          {error ? <p className="status-error modal-error">{error}</p> : null}

          <div className="modal-actions">
            <button
              type="button"
              className="secondary-button"
              disabled={submitting}
              onClick={() => {
                setEditingId(null);
                setStartTime('09:00');
                setEndTime('09:30');
              }}
            >
              New interval
            </button>
            <button type="button" className="secondary-button" onClick={onClose} disabled={submitting}>
              Close
            </button>
            <button type="submit" className="primary-button" disabled={!canSubmit}>
              {submitting ? 'Saving...' : editingId !== null ? 'Update interval' : 'Create interval'}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}

function extractTime(value) {
  return new Date(value).toISOString().slice(11, 16);
}

function isValidTime(value) {
  return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

export default AvailabilityEditorModal;
