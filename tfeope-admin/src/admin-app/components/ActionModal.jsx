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
    subtitle: 'Update officer profile picture, position, and full position.',
    submitLabel: 'Update Officer',
  },
  editGovernor: {
    eyebrow: 'Leadership Directory',
    title: 'Edit Governor',
    subtitle: 'Update governor name and profile image.',
    submitLabel: 'Update Governor',
  },
  appointed: {
    eyebrow: 'Leadership Directory',
    title: 'Add Appointed Officer',
    subtitle: 'Add appointed officer details with committee and region assignment.',
    submitLabel: 'Add Officer',
  },
  editAppointed: {
    eyebrow: 'Leadership Directory',
    title: 'Edit Appointed Officer',
    subtitle: 'Update appointed officer details, committee, and region assignment.',
    submitLabel: 'Update Officer',
  },
  pastLeader: {
    eyebrow: 'Leadership History',
    title: 'Add Past Leader',
    subtitle: 'Add leadership history entry with term and optional achievements.',
    submitLabel: 'Add Past Leader',
  },
  editPastLeader: {
    eyebrow: 'Leadership History',
    title: 'Edit Past Leader',
    subtitle: 'Update term, achievements, sorting priority, and status.',
    submitLabel: 'Update Past Leader',
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
  regionClub: {
    eyebrow: 'Member Directory',
    title: 'Setup Region + Club',
    subtitle: 'Encode governor, region, and club first so Add Member stays clean and dropdown-only. You can also rename an existing region here.',
    submitLabel: 'Save Setup',
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
  magnaCarta: {
    eyebrow: 'Policy Reference',
    title: 'Create Magna Carta',
    subtitle: 'Create a Magna Carta entry with status and optional cover image.',
    submitLabel: 'Save Magna Carta',
  },
  editMagnaCarta: {
    eyebrow: 'Policy Reference',
    title: 'Edit Magna Carta',
    subtitle: 'Update title, content, status, and cover image for this policy entry.',
    submitLabel: 'Update Magna Carta',
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
          Duplicate member IDs are skipped. Optional photos must use the member ID as filename.
        </small>
        {file ? <strong>Selected file: {file.name}</strong> : null}
      </div>
    </>
  )
}

function CsvDuplicateTable({ duplicates = [], message = '' }) {
  if (!Array.isArray(duplicates) || duplicates.length === 0) {
    return null
  }

  return (
    <div className="csv-duplicate-panel" role="alert">
      <div className="csv-duplicate-panel__header">
        <span>
          <i className="fas fa-triangle-exclamation" aria-hidden="true"></i>
          Duplicate IDs skipped
        </span>
        {message ? <small>{message}</small> : null}
      </div>

      <div className="csv-duplicate-table-wrap">
        <table className="csv-duplicate-table">
          <thead>
            <tr>
              <th>Row</th>
              <th>Eagles ID</th>
              <th>Name</th>
              <th>Club</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            {duplicates.map((duplicate, index) => (
              <tr key={`${duplicate.id || 'duplicate'}-${duplicate.row || index}`}>
                <td>{duplicate.row || '-'}</td>
                <td>{duplicate.id || '-'}</td>
                <td>{duplicate.name || '-'}</td>
                <td>{duplicate.club || '-'}</td>
                <td>{duplicate.reason || 'Duplicate member ID.'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function CsvPhotoReport({ report = null }) {
  if (!report) {
    return null
  }

  const missing = Array.isArray(report.missing) ? report.missing : []
  const unmatched = Array.isArray(report.unmatched) ? report.unmatched : []
  const invalid = Array.isArray(report.invalid) ? report.invalid : []
  const errors = Array.isArray(report.errors) ? report.errors : []
  const reviewItems = [
    ...missing.map((item) => ({
      type: 'Missing',
      id: item.id || '-',
      file: '-',
      reason: 'No matching photo uploaded.',
    })),
    ...unmatched.map((item) => ({
      type: 'Unmatched',
      id: item.expectedId || '-',
      file: item.file || '-',
      reason: item.reason || 'No matching member ID.',
    })),
    ...invalid.map((item) => ({
      type: 'Invalid',
      id: '-',
      file: item.file || '-',
      reason: item.reason || 'Invalid photo.',
    })),
    ...errors.map((item) => ({
      type: 'Error',
      id: item.id || '-',
      file: item.file || '-',
      reason: item.reason || 'Unable to attach photo.',
    })),
  ]

  return (
    <div className="csv-photo-panel" role="status">
      <div className="csv-photo-panel__header">
        <span>
          <i className="fas fa-images" aria-hidden="true"></i>
          Photo matching report
        </span>
        <small>{report.attached || 0} photo(s) attached. Import continues even when photos need review.</small>
      </div>

      {reviewItems.length > 0 ? (
        <div className="csv-duplicate-table-wrap">
          <table className="csv-duplicate-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Eagles ID</th>
                <th>File</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              {reviewItems.map((item, index) => (
                <tr key={`${item.type}-${item.id}-${item.file}-${index}`}>
                  <td>{item.type}</td>
                  <td>{item.id}</td>
                  <td>{item.file}</td>
                  <td>{item.reason}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <small>All uploaded photos matched successfully.</small>
      )}
    </div>
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
      {videoForm?.videoLink ? (
        <div className="admin-modal-note">
          <span>Current video link</span>
          <small>{videoForm.videoLink}</small>
        </div>
      ) : null}

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
  onGovernorSubmit,
  onAppointedSubmit,
  onPastLeaderSubmit,
  onMemberSubmit,
  onRegionClubSubmit,
  onMemberImportSubmit,
  onUserSubmit,
  onMemorandumSubmit,
  onMagnaCartaSubmit,
  newsForm,
  videoForm,
  eventForm,
  officerForm,
  governorForm,
  appointedForm,
  pastLeaderForm,
  memberForm,
  memberIdCheck,
  regionClubForm,
  memberImportForm,
  userForm,
  memorandumForm,
  magnaCartaForm,
  onNewsFieldChange,
  onVideoFieldChange,
  onEventFieldChange,
  onOfficerFieldChange,
  onGovernorFieldChange,
  onAppointedFieldChange,
  onPastLeaderFieldChange,
  onMemberFieldChange,
  onRegionClubFieldChange,
  onMemberImportFieldChange,
  onUserFieldChange,
  onMemorandumFieldChange,
  onMagnaCartaFieldChange,
  submitting,
  regions = [],
  regionClubMap = {},
  governors = [],
  isSuperAdmin,
}) {
  if (!open) return null

  const copy = modalCopy[mode] || modalCopy.news
  const isEditingMember = mode === 'editMember'
  const isEditingVideo = mode === 'editVideo'
  const isEditingUser = mode === 'editUser'
  const isEditingEvent = mode === 'editEvent'
  const isEditingAppointed = mode === 'editAppointed'
  const isEditingPastLeader = mode === 'editPastLeader'
  const currentRegion = String(memberForm?.region || '').trim()
  const currentClub = String(memberForm?.club || '').trim()
  const regionOptions = sortLabels(Array.from(new Set([...regions, currentRegion].filter(Boolean))))
  const clubOptions = currentRegion
    ? sortLabels(Array.from(new Set([...(regionClubMap[currentRegion] || []), currentClub].filter(Boolean))))
    : []
  const governorRegionOptions = []
  ;(Array.isArray(governors) ? governors : []).forEach((governor) => {
    const governorId = Number(governor?.id ?? governor?.governor_id ?? 0) || 0
    const governorName = String(governor?.name || governor?.governor_name || '').trim()
    if (governorId <= 0 || governorName === '') {
      return
    }

    const regionItems = Array.isArray(governor?.regions) ? governor.regions : []
    if (regionItems.length === 0) {
      governorRegionOptions.push({
        value: `${governorId}::0::::${encodeURIComponent(governorName)}`,
        label: `${governorName} - No region encoded`,
      })
      return
    }

    regionItems.forEach((regionItem) => {
      const regionId = Number(regionItem?.id ?? regionItem?.region_id ?? 0) || 0
      const regionName = String(regionItem?.name || regionItem?.region_name || '').trim()
      if (regionId <= 0 && regionName === '') {
        return
      }

      governorRegionOptions.push({
        value: `${governorId}::${regionId}::${encodeURIComponent(regionName)}::${encodeURIComponent(governorName)}`,
        label: `${governorName} - ${regionName || 'No region encoded'}`,
      })
    })
  })

  governorRegionOptions.sort((first, second) => first.label.localeCompare(second.label))
  const selectedGovernorSelection = String(regionClubForm?.governor_selection || '').trim()
  const hasSelectedExistingRegion = (
    selectedGovernorSelection !== '__NEW__'
    && (Number(regionClubForm?.region_id || 0) || 0) > 0
  )
  const selectedSetupAction = String(regionClubForm?.setup_action || 'add_club').trim()
  const selectedRenameAction = hasSelectedExistingRegion && selectedSetupAction === 'rename_region'
  const selectedRegionLocked = hasSelectedExistingRegion && !selectedRenameAction
  const regionClubSubmitLabel = selectedRenameAction ? 'Update Region Name' : copy.submitLabel
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
              <button
                type="submit"
                className="admin-primary-button"
                disabled={submitting || (!isEditingMember && memberIdCheck?.status === 'duplicate')}
              >
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

              <Field label="Video source">
                <select
                  value={videoForm?.sourceType || 'upload'}
                  onChange={(event) => onVideoFieldChange('sourceType', event.target.value)}
                >
                  <option value="upload">Upload MP4/video file</option>
                  <option value="link">YouTube or video link</option>
                </select>
              </Field>

              {videoForm?.sourceType === 'link' ? (
                <Field label="YouTube/video link" helper="Paste a YouTube, Vimeo, or direct video URL.">
                  <input
                    type="url"
                    placeholder="https://www.youtube.com/watch?v=..."
                    value={videoForm?.videoLink || ''}
                    onChange={(event) => onVideoFieldChange('videoLink', event.target.value)}
                    required
                  />
                </Field>
              ) : (
                <Field label="Video file">
                  <input
                    type="file"
                    accept="video/*"
                    onChange={(event) => onVideoFieldChange('video', event.target.files?.[0] || null)}
                    required={!isEditingVideo}
                  />
                </Field>
              )}

              <Field
                label="Thumbnail"
                helper={videoForm?.sourceType === 'link'
                  ? 'Optional image upload for the video card. YouTube videos can still play without this.'
                  : 'Optional. If empty, thumbnail will be auto-generated from the uploaded video.'}
              >
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

              <Field label="Full Position" fullWidth>
                <input
                  type="text"
                  placeholder="Officer full position"
                  value={officerForm?.full_position || ''}
                  onChange={(event) => onOfficerFieldChange('full_position', event.target.value)}
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

        {mode === 'editGovernor' && (
          <form onSubmit={onGovernorSubmit} className="admin-modal-form">
            {governorForm?.imageUrl ? (
              <div className="admin-modal-note media">
                <span>Current governor image</span>
                <div className="admin-modal-media">
                  <img src={governorForm.imageUrl} alt={governorForm?.name || 'Governor image'} />
                  <div>
                    <strong>{governorForm?.imageFilename || 'Existing uploaded image'}</strong>
                  </div>
                </div>
              </div>
            ) : null}

            <div className="admin-modal-grid">
              <Field label="Name" fullWidth>
                <input
                  type="text"
                  placeholder="Governor name"
                  value={governorForm?.name || ''}
                  onChange={(event) => onGovernorFieldChange('name', event.target.value)}
                  required
                />
              </Field>

              <Field label="Photo upload" fullWidth helper="Upload to change existing image or add one if empty.">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onGovernorFieldChange('image', event.target.files?.[0] || null)}
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

        {(mode === 'appointed' || mode === 'editAppointed') && isSuperAdmin && (
          <form onSubmit={onAppointedSubmit} className="admin-modal-form">
            <div className="admin-modal-grid">
              {isEditingAppointed ? (
                <Field label="Record ID">
                  <input type="text" value={appointedForm?.id || ''} readOnly />
                </Field>
              ) : null}

              <Field label="Name" fullWidth>
                <input
                  type="text"
                  placeholder="Officer name"
                  value={appointedForm?.name || ''}
                  onChange={(event) => onAppointedFieldChange('name', event.target.value)}
                  required
                />
              </Field>

              <Field label="Position" fullWidth>
                <input
                  type="text"
                  placeholder="Officer position"
                  value={appointedForm?.position || ''}
                  onChange={(event) => onAppointedFieldChange('position', event.target.value)}
                  required
                />
              </Field>

              <Field label="Committee" fullWidth>
                <input
                  type="text"
                  placeholder="Committee name"
                  value={appointedForm?.committee || ''}
                  onChange={(event) => onAppointedFieldChange('committee', event.target.value)}
                  required
                />
              </Field>

              <Field label="Region" fullWidth>
                <input
                  type="text"
                  placeholder="Region name"
                  value={appointedForm?.region || ''}
                  onChange={(event) => onAppointedFieldChange('region', event.target.value)}
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
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingAppointed ? 'fa-floppy-disk' : 'fa-user-plus'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {(mode === 'pastLeader' || mode === 'editPastLeader') && isSuperAdmin && (
          <form onSubmit={onPastLeaderSubmit} className="admin-modal-form">
            {pastLeaderForm?.photoUrl ? (
              <div className="admin-modal-note media">
                <span>Current photo</span>
                <div className="admin-modal-media">
                  <img src={pastLeaderForm.photoUrl} alt={pastLeaderForm?.name || 'Past leader photo'} />
                  <div>
                    <strong>{pastLeaderForm?.photoFilename || 'Existing uploaded image'}</strong>
                  </div>
                </div>
              </div>
            ) : null}

            <div className="admin-modal-grid">
              {isEditingPastLeader ? (
                <Field label="Record ID">
                  <input type="text" value={pastLeaderForm?.id || ''} readOnly />
                </Field>
              ) : null}

              <Field label="Name" fullWidth>
                <input
                  type="text"
                  placeholder="Leader name"
                  value={pastLeaderForm?.name || ''}
                  onChange={(event) => onPastLeaderFieldChange('name', event.target.value)}
                  required
                />
              </Field>

              <Field label="Position" fullWidth>
                <input
                  type="text"
                  placeholder="Leadership position"
                  value={pastLeaderForm?.position || ''}
                  onChange={(event) => onPastLeaderFieldChange('position', event.target.value)}
                  required
                />
              </Field>

              <Field label="Term Start">
                <input
                  type="number"
                  min="1900"
                  max="2100"
                  step="1"
                  placeholder="e.g. 2022"
                  value={pastLeaderForm?.term_start || ''}
                  onChange={(event) => onPastLeaderFieldChange('term_start', event.target.value)}
                  required
                />
              </Field>

              <Field label="Term End">
                <input
                  type="number"
                  min="1900"
                  max="2100"
                  step="1"
                  placeholder="e.g. 2024"
                  value={pastLeaderForm?.term_end || ''}
                  onChange={(event) => onPastLeaderFieldChange('term_end', event.target.value)}
                  required
                />
              </Field>

              <Field label="Order Priority">
                <input
                  type="number"
                  step="1"
                  placeholder="0"
                  value={pastLeaderForm?.order_priority ?? 0}
                  onChange={(event) => onPastLeaderFieldChange('order_priority', event.target.value)}
                />
              </Field>

              <Field label="Status">
                <select
                  value={String(pastLeaderForm?.is_active ?? '1')}
                  onChange={(event) => onPastLeaderFieldChange('is_active', event.target.value)}
                >
                  <option value="1">ACTIVE</option>
                  <option value="0">ARCHIVED</option>
                </select>
              </Field>

              <Field label="Photo upload" fullWidth helper="Optional. Upload to add or replace current photo.">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onPastLeaderFieldChange('photo', event.target.files?.[0] || null)}
                />
              </Field>

              <Field label="Achievements" fullWidth>
                <textarea
                  placeholder="Major contributions and achievements"
                  value={pastLeaderForm?.achievements || ''}
                  onChange={(event) => onPastLeaderFieldChange('achievements', event.target.value)}
                  rows={5}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : isEditingPastLeader ? 'fa-floppy-disk' : 'fa-user-plus'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : copy.submitLabel}
              </button>
            </div>
          </form>
        )}

        {mode === 'regionClub' && isSuperAdmin && (
          <form onSubmit={onRegionClubSubmit} className="admin-modal-form">
            <div className="admin-modal-note">
              <span>Separate setup flow</span>
              <small>Encode governor, region, and club here before creating member records. Governor options show as "GOVERNOR - REGION".</small>
            </div>

            <div className="admin-modal-grid">
              <Field label="Governor">
                <select
                  value={regionClubForm?.governor_selection || ''}
                  onChange={(event) => onRegionClubFieldChange('governor_selection', event.target.value)}
                  required
                >
                  <option value="" disabled>Select governor and region</option>
                  <option value="__NEW__">+ Add new governor</option>
                  {governorRegionOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </Field>

              {hasSelectedExistingRegion ? (
                <Field
                  label="Setup action"
                  helper="Pick whether you want to add a club or rename the selected region."
                >
                  <select
                    value={selectedRenameAction ? 'rename_region' : 'add_club'}
                    onChange={(event) => onRegionClubFieldChange('setup_action', event.target.value)}
                  >
                    <option value="add_club">Add club under selected region</option>
                    <option value="rename_region">Update selected region name</option>
                  </select>
                </Field>
              ) : null}

              {selectedGovernorSelection === '__NEW__' ? (
                <Field label="New governor name">
                  <input
                    type="text"
                    placeholder="Enter governor name"
                    value={regionClubForm?.governor_name || ''}
                    onChange={(event) => onRegionClubFieldChange('governor_name', event.target.value)}
                    required
                  />
                </Field>
              ) : null}

              <Field
                label="Region name"
                helper={selectedRenameAction
                  ? 'Edit this to rename the selected region.'
                  : selectedRegionLocked
                    ? 'Auto-loaded from selected governor.'
                    : 'Required for region/club setup. Leave blank only if you are adding governor only.'}
              >
                <input
                  type="text"
                  placeholder={selectedRenameAction ? 'Enter updated region name' : 'Enter region name'}
                  value={regionClubForm?.region_name || ''}
                  onChange={(event) => onRegionClubFieldChange('region_name', event.target.value)}
                  readOnly={selectedRegionLocked}
                  required={selectedRenameAction || (!selectedRegionLocked && selectedGovernorSelection !== '__NEW__')}
                />
              </Field>

              <Field
                label="Club name"
                helper={selectedRenameAction
                  ? 'Disabled while renaming region.'
                  : selectedRegionLocked
                    ? 'Add club under the selected governor and region.'
                    : 'Optional. Leave blank if you only want to create governor/region.'}
              >
                <input
                  type="text"
                  placeholder="Enter club name"
                  value={regionClubForm?.club_name || ''}
                  onChange={(event) => onRegionClubFieldChange('club_name', event.target.value)}
                  disabled={selectedRenameAction}
                  required={selectedRegionLocked && !selectedRenameAction}
                />
              </Field>
            </div>

            <div className="admin-modal-actions">
              <button type="button" className="admin-secondary-button" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="admin-primary-button" disabled={submitting}>
                <i
                  className={`fas ${submitting ? 'fa-circle-notch fa-spin' : 'fa-diagram-project'}`}
                  aria-hidden="true"
                ></i>
                {submitting ? 'Saving...' : regionClubSubmitLabel}
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
                  <div className={`member-id-input ${memberIdCheck?.status || 'empty'}`}>
                    <input
                      type="text"
                      placeholder="Eagles ID"
                      value={memberForm?.id || ''}
                      onChange={(event) => onMemberFieldChange('id', event.target.value)}
                      readOnly={isEditingMember}
                      aria-invalid={memberIdCheck?.status === 'duplicate'}
                    />
                    {memberIdCheck?.status && memberIdCheck.status !== 'empty' ? (
                      <span className="member-id-input__icon" aria-hidden="true">
                        <i
                          className={`fas ${
                            memberIdCheck.status === 'checking'
                              ? 'fa-circle-notch fa-spin'
                              : memberIdCheck.status === 'duplicate'
                                ? 'fa-circle-xmark'
                                : 'fa-circle-check'
                          }`}
                        ></i>
                      </span>
                    ) : null}
                  </div>
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
            <CsvDuplicateTable
              duplicates={memberImportForm?.duplicates || []}
              message={memberImportForm?.resultMessage || ''}
            />
            <CsvPhotoReport report={memberImportForm?.photoReport || null} />

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
              <Field
                label="Member photos"
                fullWidth
                helper="Optional. Select many photos. Filename must match Eagles ID, for example EAG_001.png."
              >
                <input
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.jfif,image/*"
                  multiple
                  onChange={(event) => onMemberImportFieldChange('photos', Array.from(event.target.files || []))}
                />
                {memberImportForm?.photos?.length ? (
                  <small>{memberImportForm.photos.length} photo file(s) selected.</small>
                ) : null}
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

        {(mode === 'magnaCarta' || mode === 'editMagnaCarta') && (
          <form onSubmit={onMagnaCartaSubmit} className="admin-modal-form">
            {magnaCartaForm?.imageUrl ? (
              <div className="admin-modal-note media">
                <span>Current image</span>
                <div className="admin-modal-media">
                  <img src={magnaCartaForm.imageUrl} alt={magnaCartaForm?.title || 'Magna Carta image'} />
                  <div>
                    <strong>{magnaCartaForm?.imageFilename || 'Existing uploaded image'}</strong>
                    <small>Uploading a new file will replace the current image.</small>
                  </div>
                </div>
              </div>
            ) : null}

            <div className="admin-modal-grid">
              <Field label="Title" fullWidth>
                <input
                  type="text"
                  placeholder="Magna Carta title"
                  value={magnaCartaForm?.title || ''}
                  onChange={(event) => onMagnaCartaFieldChange('title', event.target.value)}
                  required
                />
              </Field>

              <Field label="Subtitle">
                <input
                  type="text"
                  placeholder="Optional subtitle"
                  value={magnaCartaForm?.subtitle || ''}
                  onChange={(event) => onMagnaCartaFieldChange('subtitle', event.target.value)}
                />
              </Field>

              <Field label="Status">
                <select
                  value={magnaCartaForm?.status || 'Draft'}
                  onChange={(event) => onMagnaCartaFieldChange('status', event.target.value)}
                >
                  <option value="Draft">Draft</option>
                  <option value="Published">Published</option>
                </select>
              </Field>

              <Field label="Image" helper="Optional cover image for this policy entry.">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(event) => onMagnaCartaFieldChange('image', event.target.files?.[0] || null)}
                />
              </Field>

              <Field label="Description" fullWidth>
                <textarea
                  placeholder="Policy description/content"
                  value={magnaCartaForm?.description || ''}
                  onChange={(event) => onMagnaCartaFieldChange('description', event.target.value)}
                  rows={6}
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
      </div>
    </div>
  )
}
