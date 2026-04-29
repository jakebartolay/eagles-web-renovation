import { useEffect, useMemo, useState } from 'react'
import Skeleton from '@mui/material/Skeleton'
import { requestJson } from '../utils'

function formatBytes(value) {
  const size = Number(value || 0)
  if (!Number.isFinite(size) || size <= 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB']
  const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1)
  return `${(size / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`
}

function parentPath(path) {
  const parts = String(path || '').split('/').filter(Boolean)
  parts.pop()
  return parts.join('/')
}

function itemIcon(item) {
  if (item?.type === 'folder') return 'fa-folder'

  const extension = String(item?.extension || '').toLowerCase()
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'].includes(extension)) return 'fa-file-image'
  if (['mp4', 'webm', 'mov'].includes(extension)) return 'fa-file-video'
  if (['pdf'].includes(extension)) return 'fa-file-pdf'
  if (['zip', 'rar', '7z'].includes(extension)) return 'fa-file-zipper'
  if (['js', 'jsx', 'css', 'html', 'json', 'xml', 'sql'].includes(extension)) return 'fa-file-code'

  return 'fa-file-lines'
}

function FileManagerLoading() {
  return (
    <section className="content-section-card file-manager-page">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Super Admin Tools</p>
          <h2>File Manager</h2>
          <p>Loading allowed file roots...</p>
        </div>
      </div>
      <div className="file-manager-toolbar">
        <Skeleton variant="rounded" height={48} />
        <Skeleton variant="rounded" height={48} />
      </div>
      <div className="file-manager-list">
        {Array.from({ length: 8 }).map((_, index) => (
          <Skeleton key={`file-manager-loading-${index}`} variant="rounded" height={58} />
        ))}
      </div>
    </section>
  )
}

export default function FileManagerPage({ endpoint, loading = false, onError, onNotice }) {
  const [roots, setRoots] = useState([])
  const [rootId, setRootId] = useState('')
  const [path, setPath] = useState('')
  const [items, setItems] = useState([])
  const [busy, setBusy] = useState(false)
  const [selected, setSelected] = useState(null)
  const [editor, setEditor] = useState(null)
  const [folderName, setFolderName] = useState('')
  const [renameName, setRenameName] = useState('')
  const [destinationRootId, setDestinationRootId] = useState('')
  const [destinationPath, setDestinationPath] = useState('')
  const [selectedPaths, setSelectedPaths] = useState([])
  const [localQuery, setLocalQuery] = useState('')

  const selectedRoot = roots.find((root) => root.id === rootId) || roots[0] || null
  const breadcrumbs = useMemo(() => {
    const parts = String(path || '').split('/').filter(Boolean)
    const crumbs = [{ label: selectedRoot?.label || 'Files', path: '' }]
    parts.forEach((part, index) => {
      crumbs.push({
        label: part,
        path: parts.slice(0, index + 1).join('/'),
      })
    })
    return crumbs
  }, [path, selectedRoot])

  const visibleItems = items.filter((item) => {
    if (!localQuery) return true
    return JSON.stringify(item).toLowerCase().includes(localQuery.toLowerCase())
  })

  async function loadRoots() {
    const payload = await requestJson(`${endpoint}?action=roots`, {
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    const nextRoots = Array.isArray(payload?.data) ? payload.data : []
    setRoots(nextRoots)
    setRootId((current) => current || nextRoots[0]?.id || '')
    setDestinationRootId((current) => current || nextRoots[0]?.id || '')
    return nextRoots
  }

  async function loadDirectory(nextRootId = rootId, nextPath = path) {
    if (!nextRootId) return

    setBusy(true)
    try {
      const params = new URLSearchParams({
        action: 'list',
        root: nextRootId,
        path: nextPath || '',
      })
      const payload = await requestJson(`${endpoint}?${params.toString()}`, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })

      setItems(Array.isArray(payload?.data?.items) ? payload.data.items : [])
      setPath(payload?.data?.path || '')
      setSelected(null)
      setEditor(null)
      setRenameName('')
      setSelectedPaths([])
    } catch (error) {
      onError?.(error.message || 'Unable to load files.')
    } finally {
      setBusy(false)
    }
  }

  useEffect(() => {
    let active = true

    async function hydrate() {
      try {
        const nextRoots = await loadRoots()
        if (!active || !nextRoots[0]?.id) return
        await loadDirectory(nextRoots[0].id, '')
      } catch (error) {
        if (active) onError?.(error.message || 'Unable to load file manager.')
      }
    }

    hydrate()
    return () => {
      active = false
    }
    // The first load should run once; later folder changes are user-driven.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function postAction(action, body = {}) {
    const payload = await requestJson(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        action,
        root: rootId,
        path,
        ...body,
      }),
    })

    onNotice?.(payload?.message || 'File manager updated.')
    await loadDirectory(rootId, path)
    return payload
  }

  async function openEditor(item) {
    const params = new URLSearchParams({
      action: 'read',
      root: rootId,
      path: item.path,
    })

    setBusy(true)
    try {
      const payload = await requestJson(`${endpoint}?${params.toString()}`, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      setEditor(payload?.data || null)
      setSelected(item)
    } catch (error) {
      onError?.(error.message || 'Unable to open file.')
    } finally {
      setBusy(false)
    }
  }

  async function saveEditor() {
    if (!editor) return
    setBusy(true)
    try {
      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          action: 'save',
          root: rootId,
          path: editor.path,
          content: editor.content,
        }),
      })
      onNotice?.('File saved.')
      await loadDirectory(rootId, path)
    } catch (error) {
      onError?.(error.message || 'Unable to save file.')
    } finally {
      setBusy(false)
    }
  }

  async function uploadFiles(event) {
    const files = Array.from(event.target.files || [])
    if (!files.length) return

    const formData = new FormData()
    formData.append('action', 'upload')
    formData.append('root', rootId)
    formData.append('path', path)
    files.forEach((file) => formData.append('files[]', file))

    setBusy(true)
    try {
      const payload = await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      })
      onNotice?.(payload?.message || 'Upload completed.')
      await loadDirectory(rootId, path)
    } catch (error) {
      onError?.(error.message || 'Unable to upload files.')
    } finally {
      event.target.value = ''
      setBusy(false)
    }
  }

  async function createFolder(event) {
    event.preventDefault()
    const name = folderName.trim()
    if (!name) return

    setBusy(true)
    try {
      await postAction('mkdir', { name })
      setFolderName('')
    } catch (error) {
      onError?.(error.message || 'Unable to create folder.')
    } finally {
      setBusy(false)
    }
  }

  async function renameSelected(event) {
    event.preventDefault()
    if (!selected || !renameName.trim()) return

    setBusy(true)
    try {
      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          action: 'rename',
          root: rootId,
          path: selected.path,
          name: renameName.trim(),
        }),
      })
      onNotice?.('Item renamed.')
      await loadDirectory(rootId, path)
    } catch (error) {
      onError?.(error.message || 'Unable to rename item.')
    } finally {
      setBusy(false)
    }
  }

  async function deleteSelected() {
    if (!selected) return

    const confirmed = window.confirm(`Delete "${selected.name}"? This cannot be undone.`)
    if (!confirmed) return

    setBusy(true)
    try {
      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          action: 'delete',
          root: rootId,
          path: selected.path,
        }),
      })
      onNotice?.('Item deleted.')
      await loadDirectory(rootId, path)
    } catch (error) {
      onError?.(error.message || 'Unable to delete item.')
    } finally {
      setBusy(false)
    }
  }

  function selectItem(item) {
    setSelected(item)
    setRenameName(item?.name || '')
    setDestinationRootId(rootId)
    setDestinationPath(path)
    if (item?.type !== 'file') {
      setEditor(null)
    }
  }

  function toggleSelectedPath(item) {
    setSelectedPaths((current) => (
      current.includes(item.path)
        ? current.filter((selectedPath) => selectedPath !== item.path)
        : [...current, item.path]
    ))
    selectItem(item)
  }

  function selectVisibleItems() {
    setSelectedPaths(visibleItems.map((item) => item.path))
    if (visibleItems[0]) {
      selectItem(visibleItems[0])
    }
  }

  async function moveSelected(event) {
    event.preventDefault()
    const pathsToMove = selectedPaths.length > 0 ? selectedPaths : selected ? [selected.path] : []
    if (!pathsToMove.length || !destinationRootId) return

    const targetLabel = roots.find((root) => root.id === destinationRootId)?.label || 'selected root'
    const moveLabel = pathsToMove.length === 1 && selected
      ? `"${selected.name}"`
      : `${pathsToMove.length} selected item(s)`
    const confirmed = window.confirm(`Move ${moveLabel} to ${targetLabel}${destinationPath ? `/${destinationPath}` : ''}?`)
    if (!confirmed) return

    setBusy(true)
    try {
      await requestJson(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          action: 'move',
          root: rootId,
          path: pathsToMove[0],
          paths: pathsToMove,
          destinationRoot: destinationRootId,
          destinationPath,
        }),
      })
      onNotice?.('Item moved.')
      setSelectedPaths([])
      await loadDirectory(rootId, path)
    } catch (error) {
      onError?.(error.message || 'Unable to move item.')
    } finally {
      setBusy(false)
    }
  }

  if (loading && !roots.length) {
    return <FileManagerLoading />
  }

  return (
    <section className="content-section-card file-manager-page">
      <div className="content-section-card__header">
        <div>
          <p className="page-kicker">Super Admin Tools</p>
          <h2>File Manager</h2>
          <p>Manage allowed project folders, uploads, and editable text assets.</p>
        </div>
        <div className="content-section-card__actions">
          <label className={`admin-primary-button file-manager-upload ${busy ? 'disabled' : ''}`}>
            <i className="fas fa-upload" aria-hidden="true"></i>
            Upload
            <input type="file" multiple onChange={uploadFiles} disabled={busy || !rootId} />
          </label>
          <button type="button" className="admin-secondary-button" onClick={() => loadDirectory(rootId, path)} disabled={busy || !rootId}>
            <i className={`fas ${busy ? 'fa-circle-notch fa-spin' : 'fa-rotate'}`} aria-hidden="true"></i>
            Refresh
          </button>
        </div>
      </div>

      <div className="file-manager-toolbar">
        <label className="admin-modal-field">
          <span>Root</span>
          <select
            value={rootId}
            onChange={(event) => {
              const nextRoot = event.target.value
              setRootId(nextRoot)
              loadDirectory(nextRoot, '')
            }}
            disabled={busy}
          >
            {roots.map((root) => (
              <option value={root.id} key={root.id}>{root.label}</option>
            ))}
          </select>
        </label>

        <label className="admin-modal-field">
          <span>Search</span>
          <input
            type="search"
            value={localQuery}
            onChange={(event) => setLocalQuery(event.target.value)}
            placeholder="Search current folder"
          />
        </label>

        <form className="file-manager-folder-form" onSubmit={createFolder}>
          <label className="admin-modal-field">
            <span>New Folder</span>
            <input
              type="text"
              value={folderName}
              onChange={(event) => setFolderName(event.target.value)}
              placeholder="Folder name"
              disabled={busy}
            />
          </label>
          <button type="submit" className="admin-secondary-button" disabled={busy || !folderName.trim()}>
            <i className="fas fa-folder-plus" aria-hidden="true"></i>
            Create
          </button>
        </form>
      </div>

      <div className="file-manager-breadcrumbs" aria-label="Current folder">
        {breadcrumbs.map((crumb, index) => (
          <button
            type="button"
            key={`${crumb.path}-${index}`}
            onClick={() => loadDirectory(rootId, crumb.path)}
            disabled={busy}
          >
            {crumb.label}
          </button>
        ))}
      </div>

      <div className="file-manager-selection-bar">
        <span>{selectedPaths.length} selected</span>
        <button type="button" className="admin-secondary-button" onClick={selectVisibleItems} disabled={busy || visibleItems.length === 0}>
          <i className="fas fa-list-check" aria-hidden="true"></i>
          Select Visible
        </button>
        <button type="button" className="admin-secondary-button" onClick={() => setSelectedPaths([])} disabled={busy || selectedPaths.length === 0}>
          Clear
        </button>
      </div>

      <div className="file-manager-layout">
        <div className="file-manager-list">
          {path ? (
            <button type="button" className="file-manager-row" onClick={() => loadDirectory(rootId, parentPath(path))} disabled={busy}>
              <span className="file-manager-row__check" aria-hidden="true"><i className="fas fa-level-up-alt"></i></span>
              <span className="file-manager-row__icon"><i className="fas fa-arrow-turn-up" aria-hidden="true"></i></span>
              <span className="file-manager-row__main"><strong>Parent folder</strong><small>Go up one level</small></span>
            </button>
          ) : null}

          {visibleItems.length === 0 ? (
            <div className="content-empty-state">
              <i className="fas fa-folder-open" aria-hidden="true"></i>
              <p>No files or folders found.</p>
            </div>
          ) : visibleItems.map((item) => (
            <button
              type="button"
              className={`file-manager-row ${selected?.path === item.path ? 'active' : ''} ${selectedPaths.includes(item.path) ? 'checked' : ''}`}
              key={item.path || item.name}
              onDoubleClick={() => item.type === 'folder' ? loadDirectory(rootId, item.path) : item.editable ? openEditor(item) : null}
              onClick={() => selectItem(item)}
            >
              <span
                className="file-manager-row__check"
                role="checkbox"
                aria-checked={selectedPaths.includes(item.path)}
                tabIndex={0}
                onClick={(event) => {
                  event.stopPropagation()
                  toggleSelectedPath(item)
                }}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    event.stopPropagation()
                    toggleSelectedPath(item)
                  }
                }}
              >
                <i className={`fas ${selectedPaths.includes(item.path) ? 'fa-check' : 'fa-plus'}`} aria-hidden="true"></i>
              </span>
              <span className="file-manager-row__icon">
                <i className={`fas ${itemIcon(item)}`} aria-hidden="true"></i>
              </span>
              <span className="file-manager-row__main">
                <strong>{item.name}</strong>
                <small>{item.type === 'folder' ? 'Folder' : `${formatBytes(item.size)} | ${item.extension || 'file'}`}</small>
              </span>
              <span className="file-manager-row__meta">{item.modifiedAt || ''}</span>
            </button>
          ))}
        </div>

        <aside className="file-manager-detail">
          {selected ? (
            <>
              <div className="file-manager-detail__header">
                <span className="file-manager-row__icon">
                  <i className={`fas ${itemIcon(selected)}`} aria-hidden="true"></i>
                </span>
                <div>
                  <strong>{selected.name}</strong>
                  <small>{selected.path || selected.name}</small>
                </div>
              </div>

              <div className="file-manager-detail__actions">
                {selected.type === 'folder' ? (
                  <button type="button" className="admin-primary-button" onClick={() => loadDirectory(rootId, selected.path)} disabled={busy}>
                    <i className="fas fa-folder-open" aria-hidden="true"></i>
                    Open
                  </button>
                ) : null}
                {selected.editable ? (
                  <button type="button" className="admin-secondary-button" onClick={() => openEditor(selected)} disabled={busy}>
                    <i className="fas fa-pen-to-square" aria-hidden="true"></i>
                    Edit
                  </button>
                ) : null}
                {selected.downloadUrl ? (
                  <a className="admin-secondary-button" href={selected.downloadUrl}>
                    <i className="fas fa-download" aria-hidden="true"></i>
                    Download
                  </a>
                ) : null}
                {selected.publicUrl ? (
                  <a className="admin-secondary-button" href={selected.publicUrl} target="_blank" rel="noreferrer">
                    <i className="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    View
                  </a>
                ) : null}
              </div>

              <form className="file-manager-rename" onSubmit={renameSelected}>
                <label className="admin-modal-field">
                  <span>Rename</span>
                  <input value={renameName} onChange={(event) => setRenameName(event.target.value)} disabled={busy} />
                </label>
                <button type="submit" className="admin-secondary-button" disabled={busy || !renameName.trim()}>
                  Rename
                </button>
              </form>

              <form className="file-manager-move" onSubmit={moveSelected}>
                <label className="admin-modal-field">
                  <span>Move To Root</span>
                  <select
                    value={destinationRootId}
                    onChange={(event) => setDestinationRootId(event.target.value)}
                    disabled={busy}
                  >
                    {roots.map((root) => (
                      <option value={root.id} key={`destination-${root.id}`}>{root.label}</option>
                    ))}
                  </select>
                </label>
                <label className="admin-modal-field">
                  <span>Destination Folder</span>
                  <input
                    value={destinationPath}
                    onChange={(event) => setDestinationPath(event.target.value)}
                    placeholder="Example: storage/uploads/news"
                    disabled={busy}
                  />
                </label>
                <div className="file-manager-move__actions">
                  <button
                    type="button"
                    className="admin-secondary-button"
                    onClick={() => {
                      setDestinationRootId(rootId)
                      setDestinationPath(path)
                    }}
                    disabled={busy}
                  >
                    <i className="fas fa-location-dot" aria-hidden="true"></i>
                    Current Folder
                  </button>
                  <button type="submit" className="admin-primary-button" disabled={busy || !destinationRootId}>
                    <i className="fas fa-share-from-square" aria-hidden="true"></i>
                    {selectedPaths.length > 1 ? `Move ${selectedPaths.length}` : 'Move'}
                  </button>
                </div>
              </form>

              <button type="button" className="admin-danger-button" onClick={deleteSelected} disabled={busy}>
                <i className="fas fa-trash-can" aria-hidden="true"></i>
                Delete
              </button>
            </>
          ) : (
            <div className="content-empty-state">
              <i className="fas fa-arrow-pointer" aria-hidden="true"></i>
              <p>Select a file or folder to manage it.</p>
            </div>
          )}
        </aside>
      </div>

      {editor ? (
        <div className="file-manager-editor">
          <div className="dashboard-panel__header compact">
            <div>
              <p className="page-kicker">Text Editor</p>
              <h3>{editor.name}</h3>
            </div>
            <button type="button" className="admin-primary-button" onClick={saveEditor} disabled={busy}>
              <i className="fas fa-floppy-disk" aria-hidden="true"></i>
              Save
            </button>
          </div>
          <textarea
            value={editor.content || ''}
            onChange={(event) => setEditor((current) => ({ ...current, content: event.target.value }))}
            spellCheck="false"
          />
        </div>
      ) : null}
    </section>
  )
}
