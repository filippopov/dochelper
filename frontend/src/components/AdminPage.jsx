import DoctorCalendarPanel from './DoctorCalendarPanel';

function AdminPage({
  profile,
  doctors,
  selectedDoctorId,
  onDoctorChange,
  onDaySelect,
  onSlotSelect,
  doctorsLoading,
  calendarLoading,
  calendarData,
  calendarError,
  onBackToApp,
}) {
  const roleType = typeof profile?.roleType === 'string' ? profile.roleType.toLowerCase() : '';
  const roles = Array.isArray(profile?.roles) ? profile.roles : [];
  const isDoctorView = roleType === 'doctor';
  const selectedDoctor = doctors.find((doctor) => String(doctor.id) === String(selectedDoctorId)) ?? null;

  return (
    <div className="admin-page">
      <h2>{isDoctorView ? 'Your Availability' : 'Doctor Availability Console'}</h2>
      <p>
        {isDoctorView
          ? 'You can view your own configured availability here.'
          : 'Choose a doctor from the list to inspect their availability schedule.'}
      </p>
      <p className="helper-text">
        Role type: <strong>{roleType || 'unknown'}</strong>
      </p>
      <p className="helper-text">Granted roles: {roles.length > 0 ? roles.join(', ') : 'none'}</p>

      {!isDoctorView && selectedDoctor ? (
        <p className="helper-text">
          Viewing: <strong>{selectedDoctor.email}</strong>
        </p>
      ) : null}

      <DoctorCalendarPanel
        doctors={doctors}
        selectedDoctorId={selectedDoctorId}
        onDoctorChange={onDoctorChange}
        onDaySelect={onDaySelect}
        onReadOnlySlotSelect={onSlotSelect}
        onSlotSelect={() => {}}
        doctorsLoading={doctorsLoading}
        calendarLoading={calendarLoading}
        calendarData={calendarData}
        calendarError={calendarError}
        title={isDoctorView ? 'Your Weekly Availability' : 'Selected Doctor Availability'}
        description={
          isDoctorView
            ? 'Click a day or slot to manage date-specific availability overrides for that day.'
            : 'Click a day or slot to manage date-specific availability overrides for the selected doctor.'
        }
        readOnly={true}
        showDoctorSelector={!isDoctorView}
        allowDayManagement={true}
      />

      <div className="button-row">
        <button type="button" className="secondary-button" onClick={onBackToApp}>
          Back to app
        </button>
      </div>
    </div>
  );
}

export default AdminPage;
