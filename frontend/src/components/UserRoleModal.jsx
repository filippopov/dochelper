import { useEffect, useState } from 'react';

const ROLE_OPTIONS = ['patient', 'doctor', 'admin'];

function UserRoleModal({ open, user, isSubmitting, error, onClose, onSave }) {
  const [selectedRole, setSelectedRole] = useState('patient');

  useEffect(() => {
    if (!open || !user) {
      return;
    }

    setSelectedRole(user.roleType ?? 'patient');
  }, [open, user]);

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
  }, [isSubmitting, onClose, open]);

  if (!open || !user) {
    return null;
  }

  const fullName = `${user.firstName ?? ''} ${user.lastName ?? ''}`.trim() || 'Unknown user';
  const canSave = !isSubmitting && selectedRole !== user.roleType;

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
        className="booking-modal user-role-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="user-role-modal-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="user-role-modal-title">Change User Role</h3>

        <div className="booking-summary">
          <p>
            <strong>Name:</strong> {fullName}
          </p>
          <p>
            <strong>Email:</strong> {user.email}
          </p>
          <p>
            <strong>Current role:</strong> {user.roleType}
          </p>
        </div>

        <label htmlFor="user-role-select">New role</label>
        <select
          id="user-role-select"
          value={selectedRole}
          onChange={(event) => setSelectedRole(event.target.value)}
          disabled={isSubmitting}
        >
          {ROLE_OPTIONS.map((role) => (
            <option key={role} value={role}>
              {role}
            </option>
          ))}
        </select>

        {error ? <p className="status-error modal-error">{error}</p> : null}

        <div className="modal-actions">
          <button type="button" className="secondary-button" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </button>
          <button
            type="button"
            className="primary-button"
            disabled={!canSave}
            onClick={() => onSave(user.id, selectedRole)}
          >
            {isSubmitting ? 'Saving...' : 'Save role'}
          </button>
        </div>
      </section>
    </div>
  );
}

export default UserRoleModal;
