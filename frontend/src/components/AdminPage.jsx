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
  adminUsers,
  adminUsersSearch,
  adminUsersLoading,
  adminUsersError,
  onAdminUsersSearchChange,
  onEditUserRole,
  onBackToApp,
}) {
  const roleType = typeof profile?.roleType === 'string' ? profile.roleType.toLowerCase() : '';
  const roles = Array.isArray(profile?.roles) ? profile.roles : [];
  const isDoctorView = roleType === 'doctor';
  const isAdminView = roleType === 'admin';
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

      {isAdminView ? (
        <section className="admin-users-section" aria-label="User management">
          <h3>User Management</h3>
          <p className="helper-text">Search users by name or email and assign roles.</p>

          <div className="admin-users-search-row">
            <label htmlFor="admin-users-search">Search users</label>
            <input
              id="admin-users-search"
              type="text"
              value={adminUsersSearch}
              onChange={(event) => onAdminUsersSearchChange(event.target.value)}
              placeholder="Search by name or email"
            />
          </div>

          {adminUsersLoading ? <p className="helper-text">Loading users...</p> : null}
          {adminUsersError ? <p className="status-error">{adminUsersError}</p> : null}

          {!adminUsersLoading && !adminUsersError ? (
            <div className="admin-users-table-wrap">
              <table className="admin-users-table">
                <thead>
                  <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {adminUsers.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="admin-users-empty">
                        No users found.
                      </td>
                    </tr>
                  ) : (
                    adminUsers.map((userItem) => {
                      const fullName = `${userItem.firstName ?? ''} ${userItem.lastName ?? ''}`.trim() || 'Unknown user';

                      return (
                        <tr key={userItem.id}>
                          <td>{fullName}</td>
                          <td>{userItem.email}</td>
                          <td>
                            <span className="admin-user-role-badge">{userItem.roleType}</span>
                          </td>
                          <td>
                            <button
                              type="button"
                              className="secondary-button"
                              onClick={() => onEditUserRole(userItem)}
                            >
                              Edit role
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          ) : null}
        </section>
      ) : null}

      <div className="button-row">
        <button type="button" className="secondary-button" onClick={onBackToApp}>
          Back to app
        </button>
      </div>
    </div>
  );
}

export default AdminPage;
