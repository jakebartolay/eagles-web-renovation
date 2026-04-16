const modalCopy = {
  news: {
    eyebrow: 'Content Studio',
    title: 'Create News',
    subtitle: 'Prepare a new story with title, body, status, and optional cover image.',
    submitLabel: 'Save News',
  },
  editNews: {
    eyebrow: 'Content Studio',
    title: 'Edit News',
    subtitle: 'Update the story copy, publishing status, or replace the existing cover.',
    submitLabel: 'Update News',
  },
  video: {
    eyebrow: 'Media Library',
    title: 'Upload Video',
    subtitle: 'Add a video entry.',
    submitLabel: 'Save Video',
  },
  editVideo: {
    eyebrow: 'Media Library',
    title: 'Edit Video',
    subtitle: 'Update video details.',
    submitLabel: 'Update Video',
  },
  editOfficer: {
    eyebrow: 'Leadership Directory',
    title: 'Edit Officer',
    subtitle: 'Update officer profile picture and position.',
    submitLabel: 'Update Officer',
  },
  event: {
    eyebrow: 'Schedule Desk',
    title: 'Create Event',
    subtitle: 'Add an event with date, type, details, and optional media.',
    submitLabel: 'Save Event',
  },
  editEvent: {
    eyebrow: 'Schedule Desk',
    title: 'Edit Event',
    subtitle: 'Update event details, date, type, and media.',
    submitLabel: 'Update Event',
  },
  member: {
    eyebrow: 'Member Directory',
    title: 'Create Member',
    subtitle: 'Add a member.',
    submitLabel: 'Save Member',
  },
  editMember: {
    eyebrow: 'Member Directory',
    title: 'Edit Member',
    subtitle: 'Update member details.',
    submitLabel: 'Update Member',
  },
  memberImport: {
    eyebrow: 'Member Directory',
    title: 'Import Members CSV',
    subtitle: 'Upload a CSV using your existing layout to create or refresh member records in bulk.',
    submitLabel: 'Import CSV',
  },
  user: {
    eyebrow: 'Access Control',
    title: 'Create User',
    subtitle: 'Add a new admin account and choose whether it should be Admin or Super Admin.',
    submitLabel: 'Create User',
  },
  editUser: {
    eyebrow: 'Access Control',
    title: 'Edit User',
    subtitle: 'Update role and account details.',
    submitLabel: 'Update User',
  },
  memorandum: {
    eyebrow: 'Document Center',
    title: 'Create Memorandum',
    subtitle: 'Upload a memorandum with description, status, and supporting pages.',
    submitLabel: 'Save Memorandum',
  },
  editMemorandum: {
    eyebrow: 'Document Center',
    title: 'Edit Memorandum',
    subtitle: 'Adjust memorandum details and add more supporting pages when needed.',
    submitLabel: 'Update Memorandum',
  },
}

function initialsFromName(name) {
  return String(name || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('') || 'NA'
}

function memberDisplayName(form) {
  return `${form?.first_name || ''} ${form?.last_name || ''}`.trim() || 'New member'
}

function sortLabels(items) {
  return [...items].sort((first, second) => first.localeCompare(second))
}

function toLocalIsoDate(value) {
  const date = value instanceof Date ? value : new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function Field({ label, children, fullWidth = false, helper }) {
  return (
    <label className={`admin-modal-field ${fullWidth ? 'admin-modal-field--span-2' : ''}`}>
      <span>{label}</span>
      {children}
      {helper ? <small>{helper}</small> : null}
    </label>
  )
}

function ExistingFiles({ items = [] }) {
  if (!items.length) {
    return null
  }

  return (
    <div className="admin-modal-note">
      <span>Current attachments</span>
      <div className="admin-modal-pill-row">
        {items.map((item, index) => (
          <span className="admin-modal-pill" key={`${item?.id || item?.url || 'page'}-${index}`}>
            {item?.url ? (
              <a href={item.url} target="_blank" rel="noreferrer">
                {item?.title || item?.name || item?.filename || `Page ${index + 1}`}
              </a>
            ) : (
              item?.title || item?.name || item?.filename || `Page ${index + 1}`
            )}
          </span>
        ))}
      </div>
    </div>
  )
}

function MemorandumPagesPreview({ items = [] }) {
  if (!items.length) {
    return null
  }

  return (
    <div className="admin-modal-note media">
      <span>Current memorandum images</span>
      <div className="memorandum-pages-preview">
        {items.map((item, index) => {
          const label = item?.title || item?.name || item?.filename || `Page ${index + 1}`
          const url = String(item?.url || '').trim()

          if (!url) {
            return (
              <span className="admin-modal-pill" key={`${label}-${index}`}>
                {label}
              </span>
            )
          }

          return (
            <a
              key={`${url}-${index}`}
              href={url}
              target="_blank"
              rel="noreferrer"
              className="memorandum-pages-preview__item"
              title={label}
            >
              <img src={url} alt={label} />
              <small>{label}</small>
            </a>
          )
        })}
      </div>
    </div>
  )
}

function MemberPreview({ memberForm, isEditingMember }) {
  const displayName = memberDisplayName(memberForm)
  const photoUrl = String(memberForm?.photoUrl || '').trim()
  const status = String(memberForm?.status || 'ACTIVE').trim() || 'ACTIVE'
  const club = String(memberForm?.club || '').trim() || 'Club not set'
  const region = String(memberForm?.region || '').trim() || 'Region not set'

  return (
    <aside className="member-editor-preview">
      <div className="member-editor-preview__media">
        {photoUrl ? (
          <img src={photoUrl} alt={displayName} className="member-editor-preview__image" />
        ) : (
          <span className="member-editor-preview__fallback">{initialsFromName(displayName)}</span>
        )}
      </div>

      <div className="member-editor-preview__body">
        <strong>{displayName}</strong>
        <span>
          {isEditingMember
            ? `Eagles ID: ${memberForm?.id || 'Not available'}`
            : `Eagles ID: ${memberForm?.id || 'Pending'}`}
        </span>
        <div className="admin-modal-pill-row">
          <span className="admin-modal-pill">{status}</span>
          <span className="admin-modal-pill">{club}</span>
          <span className="admin-modal-pill">{region}</span>
        </div>
      </div>
    </aside>
  )
}

function CsvTemplateNote({ file }) {
  return (
    <>
      <div className="admin-modal-note">
        <span>Required CSV layout</span>
        <small>
          Use this exact header structure from your sample file:
          {' '}
          `ID, First Name, Last Name, Position, Club, Region, Status`
        </small>
      </div>

      <div className="csv-template-preview">
        <span className="csv-template-preview__label">Header preview</span>
        <code>ID,First Name,Last Name,Position,Club,Region,Status</code>
        <small>
          Existing member IDs will be updated. New IDs will be created as new member records.
        </small>
        {file ? <strong>Selected file: {file.name}</strong> : null}
      </div>
    </>
  )
}

function VideoFilesNote({ videoForm, isEditingVideo }) {
  const fileItems = [
    videoForm?.videoFilename ? { filename: videoForm.videoFilename, name: 'Current video' } : null,
    videoForm?.thumbnailFilename ? { filename: videoForm.thumbnailFilename, name: 'Current thumbnail' } : null,
  ].filter(Boolean)

  if (!isEditingVideo && !videoForm?.thumbnailUrl && fileItems.length === 0) {
    return null
  }

  return (
    <>
      {videoForm?.thumbnailUrl ? (
        <div className="admin-modal-note media">
          <span>Current thumbnail</span>
          <div className="admin-modal-media">
            <img src={videoForm.thumbnailUrl} alt={videoForm?.title || 'Video thumbnail'} />
            <div>
              <strong>{videoForm?.thumbnailFilename || 'Existing uploaded thumbnail'}</strong>
            </div>
          </div>
        </div>
      ) : null}
      <ExistingFiles items={fileItems} />
    </>
  )
}

export default function ActionModal({
  mode,
  open,
  onClose,
  onNewsSubmit,
  onVideoSubmit,
  onEventSubmit,
  onOfficerSubmit,
  onMemberSubmit,
  onMemberImportSubmit,
  onUserSubmit,
  onMemorandumSubmit,
  newsForm,
  videoForm,
  eventForm,
  officerForm,
  memberForm,
  memberImportForm,
  userForm,
  memorandumForm,
  onNewsFieldChange,
  onVideoFieldChange,
  onEventFieldChange,
  onOfficerFieldChange,
  onMemberFieldChange,
  onMemberImportFieldChange,
  onUserFieldChange,
  onMemorandumFieldChange,
  submitting,
  regions = [],
  regionClubMap = {},
  isSuperAdmin,
}) {
  if (!open) return null

  const copy = modalCopy[mode] || modalCopy.news
  const isEditingMember = mode === 'editMember'
  const isEditingVideo = mode === 'editVideo'
  const isEditingUser = mode === 'editUser'
  const isEditingEvent = mode === 'editEvent'
  const currentRegion = String(memberForm?.region || '').trim()
  const currentClub = String(memberForm?.club || '').trim()
  const regionOptions = sortLabels(Array.from(new Set([...regions, currentRegion].filter(Boolean))))
  const clubOptions = currentRegion
    ? sortLabels(Array.from(new Set([...(regionClubMap[currentRegion] || []), currentClub].filter(Boolean))))
    : []
  const now = new Date()
  const localToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const localYesterday = new Date(localToday)
  localYesterday.setDate(localYesterday.getDate() - 1)
  const todayIso = toLocalIsoDate(localToday)
  const yesterdayIso = toLocalIsoDate(localYesterday)
  const eventType = String(eventForm?.type || 'upcoming').trim().toLowerCase()
  const eventDateMin = eventType === 'past' ? '2000-01-01' : todayIso
  const eventDateMax = eventType === 'past' ? yesterdayIso : '2027-12-31'

  return (
    <div className="admin-modal-backdrop">
      <div className="admin-modal" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">
        <div className="admin-modal-header">
          <div>
            <p className="admin-modal-eyebrow">{copy.eyebrow}</p>
            <h2 id="admin-modal-title">{copy.title}</h2>
            <p className="admin-modal-subtitle">{copy.subtitle}</p>
          </div>

          <button type="button" className="admin-icon-button" onClick={onClose} aria-label="Close modal">
            <i className="fas fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        {(mode === 'news' || mode === 'editNews') && (
          <form onSubmit={onNewsSubmit} className="admin-modal-form">
            {newsForm?.imageUrl ? (
              <div className="admin-modal-note media">
                <span>Current cover image</span>
                <div className="admin-modal-media">
                  <img src={newsForm.imageUrl} alt={newsForm?.title || 'Current cover'} />
                  <div>
                    <strong>{newsForm?.imageFilename || 'Existing uploaded image'}</strong>
                    <small>Uploading a new file will replace the current cover.</small>
                  </div>
                </div>
              </div>
            ) : null}

            <div className="admin-modal-grid">
              <Field label="Title" fullWidth>
                <input
                  type="text"
                  placeholder="Enter the news title"
                  value={newsForm?.title || ''}
                  onChange={(event) => onNewsFieldChange('title', event.target.value)}
                  required
                />
              </Field>

              <Field label="Status">
                <select
                  value={newsForm?.status || 'Published'}
                  onChange={(event) => onNewsFieldChange('status', event.target.value)}
                >
                  <option value="Published">Published</option>
                  <option value="Draft">Draft</option>
                </select>
              </Field>

              <Field label="Published date">
                <input
                  type="date"
                  value={newsForm?.publishedDate || ''}
                  onChange={(event) => onNewsFieldChange('publishedDate', event.target.value)}
                />
              </Field>

              <Field label="Cover image" helper="Optional image upload for the story card.">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onNewsFieldChange('image', event.target.files?.[0] || null)}
                />
              </Field>

              <Field label="Content" fullWidth>
                <textarea
                  placeholder="Write the news content"
                  value={newsForm?.content || ''}
                  onChange={(event) => onNewsFieldChange('content', event.target.value)}
                  rows={8}
                  required
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'video' || mode === 'editVideo') && (
          <form onSubmit={onVideoSubmit} className="admin-modal-form">
            <VideoFilesNote videoForm={videoForm} isEditingVideo={isEditingVideo} />

            <div className="admin-modal-grid">
              <Field label="Title" fullWidth>
                <input
                  type="text"
                  placeholder="Video title"
                  value={videoForm?.title || ''}
                  onChange={(event) => onVideoFieldChange('title', event.target.value)}
                  required
                />
              </Field>

              <Field label="Status">
                <select
                  value={videoForm?.status || 'Published'}
                  onChange={(event) => onVideoFieldChange('status', event.target.value)}
                >
                  <option value="Published">Published</option>
                  <option value="Draft">Draft</option>
                </select>
              </Field>

              <Field label="Video file">
                <input
                  type="file"
                  accept="video/*"
                  onChange={(event) => onVideoFieldChange('video', event.target.files?.[0] || null)}
                  required={!isEditingVideo}
                />
              </Field>

              <Field label="Thumbnail" helper="Optional. If empty, thumbnail will be auto-generated from the uploaded video.">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onVideoFieldChange('thumbnail', event.target.files?.[0] || null)}
                />
              </Field>

              <Field label="Description" fullWidth>
                <textarea
                  placeholder="Short video description"
                  value={videoForm?.description || ''}
                  onChange={(event) => onVideoFieldChange('description', event.target.value)}
                  rows={6}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingVideo ? 'fa-floppy-disk' : 'fa-video'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'event' || mode === 'editEvent') && (
          <form onSubmit={onEventSubmit} className="admin-modal-form">
            <div className="admin-modal-grid">
              {isEditingEvent ? (
                <Field label="Event ID">
                  <input type="text" value={eventForm?.id || ''} readOnly />
                </Field>
              ) : null}

              <Field label="Event title" fullWidth>
                <input
                  type="text"
                  placeholder="Event title"
                  value={eventForm?.title || ''}
                  onChange={(event) => onEventFieldChange('title', event.target.value)}
                  required
                />
              </Field>

              <Field label="Event date">
                <input
                  type="date"
                  value={eventForm?.date || ''}
                  onChange={(event) => onEventFieldChange('date', event.target.value)}
                  min={eventDateMin}
                  max={eventDateMax}
                  required
                />
              </Field>

              <Field label="Type">
                <select
                  value={eventForm?.type || 'upcoming'}
                  onChange={(event) => onEventFieldChange('type', event.target.value)}
                >
                  <option value="upcoming">Upcoming</option>
                  <option value="past">Past</option>
                </select>
                <small>
                  {eventType === 'past'
                    ? 'Past dates allowed from year 2000 up to yesterday.'
                    : 'Upcoming dates allowed from today up to December 31, 2027.'}
                </small>
              </Field>

              <Field label="Event media">
                <input
                  type="file"
                  accept="image/*,video/*"
                  onChange={(event) => onEventFieldChange('media', event.target.files?.[0] || null)}
                />
              </Field>

              <Field label="Description" fullWidth>
                <textarea
                  placeholder="Short event details"
                  value={eventForm?.description || ''}
                  onChange={(event) => onEventFieldChange('description', event.target.value)}
                  rows={6}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingEvent ? 'fa-floppy-disk' : 'fa-calendar-plus'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {mode === 'editOfficer' && (
          <form onSubmit={onOfficerSubmit} className="admin-modal-form">
            {officerForm?.imageUrl ? (
              <div className="admin-modal-note media">
                <span>Current officer image</span>
                <div className="admin-modal-media">
                  <img src={officerForm.imageUrl} alt={officerForm?.name || 'Officer image'} />
                  <div>
                    <strong>{officerForm?.imageFilename || 'Existing uploaded image'}</strong>
                  </div>
                </div>
              </div>
            ) : null}

            <div className="admin-modal-grid">
              <Field label="Name" fullWidth>
                <input
                  type="text"
                  placeholder="Officer name"
                  value={officerForm?.name || ''}
                  onChange={(event) => onOfficerFieldChange('name', event.target.value)}
                  required
                />
              </Field>

              <Field label="Position" fullWidth>
                <input
                  type="text"
                  placeholder="Officer position"
                  value={officerForm?.position || ''}
                  onChange={(event) => onOfficerFieldChange('position', event.target.value)}
                  required
                />
              </Field>

              <Field label="Photo upload" fullWidth>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onOfficerFieldChange('image', event.target.files?.[0] || null)}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'member' || mode === 'editMember') && isSuperAdmin && (
          <form onSubmit={onMemberSubmit} className="admin-modal-form">
            <div className="member-editor-layout">
              <MemberPreview memberForm={memberForm} isEditingMember={isEditingMember} />

              <div className="admin-modal-grid member-editor-grid">
                {/* <Field label="Eagles ID">
                  <div style={{ position: 'relative' }}>
                    <input
                      type="text"
                      placeholder="Eagles ID"
                      value={memberForm?.id || ''}
                      readOnly
                      style={{
                        backgroundColor: '#f3f4f6',
                        color: '#6b7280',
                        paddingRight: '35px'
                      }}
                    />
                    <span
                      style={{
                        position: 'absolute',
                        right: '10px',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        color: '#9ca3af',
                        fontSize: '14px'
                      }}
                    >
                      🔒
                    </span>
                  </div>
                </Field> */}
                <Field label="Eagles ID" helper={isEditingMember ? 'Member ID cannot be changed while editing.' : 'Optional. Leave blank to auto-generate ID.'}>
                  <input
                    type="text"
                    placeholder="Eagles ID"
                    value={memberForm?.id || ''}
                    onChange={(event) => onMemberFieldChange('id', event.target.value)}
                    readOnly={isEditingMember}
                  />
                </Field>

                <Field label="Status">
                  <select
                    value={memberForm?.status || 'ACTIVE'}
                    onChange={(event) => onMemberFieldChange('status', event.target.value)}
                  >
                    <option value="ACTIVE">ACTIVE</option>
                    <option value="RENEWAL">RENEWAL</option>
                  </select>
                </Field>

                <Field label="First name">
                  <input
                    type="text"
                    placeholder="First name"
                    value={memberForm?.first_name || ''}
                    onChange={(event) => onMemberFieldChange('first_name', event.target.value)}
                    required
                  />
                </Field>

                <Field label="Last name">
                  <input
                    type="text"
                    placeholder="Last name"
                    value={memberForm?.last_name || ''}
                    onChange={(event) => onMemberFieldChange('last_name', event.target.value)}
                    required
                  />
                </Field>

                <Field label="Position">
                  <input
                    type="text"
                    placeholder="Member position"
                    value={memberForm?.position || ''}
                    onChange={(event) => onMemberFieldChange('position', event.target.value)}
                    required
                  />
                </Field>

                <Field label="Photo upload">
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(event) => onMemberFieldChange('photo', event.target.files?.[0] || null)}
                  />
                </Field>

                <Field label="Region">
                  <select
                    value={memberForm?.region || ''}
                    onChange={(event) => onMemberFieldChange('region', event.target.value)}
                    required
                  >
                    <option value="" disabled>
                      {regionOptions.length ? 'Select region' : 'No regions available'}
                    </option>
                    {regionOptions.map((region) => (
                      <option key={region} value={region}>
                        {region}
                      </option>
                    ))}
                  </select>
                </Field>

                <Field label="Club">
                  <select
                    value={memberForm?.club || ''}
                    onChange={(event) => onMemberFieldChange('club', event.target.value)}
                    disabled={!currentRegion}
                    required
                  >
                    <option value="" disabled>
                      {currentRegion
                        ? clubOptions.length
                          ? 'Select club'
                          : 'No clubs available for this region'
                        : 'Select region first'}
                    </option>
                    {clubOptions.map((club) => (
                      <option key={club} value={club}>
                        {club}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingMember ? 'fa-floppy-disk' : 'fa-user-plus'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {mode === 'memberImport' && isSuperAdmin && (
          <form onSubmit={onMemberImportSubmit} className="admin-modal-form">
            <CsvTemplateNote file={memberImportForm?.file || null} />

            <div className="admin-modal-grid">
              <Field
                label="CSV file"
                fullWidth
                helper="Accepts .csv files that follow the sample Thailand Eagles Club layout."
              >
                <input
                  type="file"
                  accept=".csv,text/csv"
                  onChange={(event) => onMemberImportFieldChange('file', event.target.files?.[0] || null)}
                  required
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : 'fa-file-arrow-up'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Importing...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'user' || mode === 'editUser') && isSuperAdmin && (
          <form onSubmit={onUserSubmit} className="admin-modal-form">
            <div className="admin-modal-note">
              <span>Restricted action</span>
              <small>Only super admins can create, edit, and delete admin login accounts.</small>
            </div>

            <div className="admin-modal-grid">
              {isEditingUser ? (
                <Field label="User ID">
                  <input type="text" value={userForm?.id || ''} readOnly />
                </Field>
              ) : null}

              <Field label="Full name">
                <input
                  type="text"
                  placeholder="Admin full name"
                  value={userForm?.name || ''}
                  onChange={(event) => onUserFieldChange('name', event.target.value)}
                  required
                />
              </Field>

              <Field label="Username">
                <input
                  type="text"
                  placeholder="Login username"
                  autoComplete="off"
                  value={userForm?.username || ''}
                  onChange={(event) => onUserFieldChange('username', event.target.value)}
                  required
                  readOnly={isEditingUser}
                />
              </Field>

              <Field label="Role">
                <select
                  value={userForm?.roleId || '2'}
                  onChange={(event) => onUserFieldChange('roleId', event.target.value)}
                >
                  <option value="2">Admin</option>
                  <option value="1">Super Admin</option>
                </select>
              </Field>

              <Field label="Eagles ID" helper="Optional staff or member reference ID.">
                <input
                  type="text"
                  placeholder="Optional Eagles ID"
                  value={userForm?.eaglesId || ''}
                  onChange={(event) => onUserFieldChange('eaglesId', event.target.value)}
                />
              </Field>

              <Field label="Password">
                <input
                  type="password"
                  placeholder={isEditingUser ? 'Leave blank to keep current password' : 'Temporary password'}
                  autoComplete="new-password"
                  value={userForm?.password || ''}
                  onChange={(event) => onUserFieldChange('password', event.target.value)}
                  required={!isEditingUser}
                />
              </Field>

              <Field label="Confirm password">
                <input
                  type="password"
                  placeholder={isEditingUser ? 'Retype only if changing password' : 'Retype password'}
                  autoComplete="new-password"
                  value={userForm?.confirmPassword || ''}
                  onChange={(event) => onUserFieldChange('confirmPassword', event.target.value)}
                  required={!isEditingUser || String(userForm?.password || '').trim() !== ''}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingUser ? 'fa-floppy-disk' : 'fa-user-plus'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? (isEditingUser ? 'Updating...' : 'Creating...') : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'memorandum' || mode === 'editMemorandum') && (
          <form onSubmit={onMemorandumSubmit} className="admin-modal-form">
            <MemorandumPagesPreview items={memorandumForm?.currentPages || []} />

            <div className="admin-modal-grid">
              <Field label="Title" fullWidth>
                <input
                  type="text"
                  placeholder="Memorandum title"
                  value={memorandumForm?.title || ''}
                  onChange={(event) => onMemorandumFieldChange('title', event.target.value)}
                  required
                />
              </Field>

              <Field label="Status">
                <select
                  value={memorandumForm?.status || 'Draft'}
                  onChange={(event) => onMemorandumFieldChange('status', event.target.value)}
                >
                  <option value="Draft">Draft</option>
                  <option value="Published">Published</option>
                </select>
              </Field>

              <Field label="Attachments" helper="Accepts PDF or image files.">
                <input
                  type="file"
                  multiple
                  accept=".pdf,image/*"
                  onChange={(event) =>
                    onMemorandumFieldChange('pages', Array.from(event.target.files || []))
                  }
                />
              </Field>

              <Field label="Description" fullWidth>
                <textarea
                  placeholder="Short memorandum description"
                  value={memorandumForm?.description || ''}
                  onChange={(event) => onMemorandumFieldChange('description', event.target.value)}
                  rows={6}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : 'fa-file-arrow-up'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
