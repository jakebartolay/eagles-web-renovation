import { useEffect, useMemo, useRef, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import useStylesheet from '../hooks/useStylesheet'
import { PUBLIC_OFFICERS_ENDPOINT, publicMediaUrl } from '../config'
import { fetchApiJson } from '../lib/api'
import officersStylesheetUrl from '../../old_system/Styles/officers.css?url'

const placeholderUrl = new URL('../static/placeholder.png', import.meta.url).href

function normalizePosition(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
}

function resolveOfficerImage(url, filename) {
  return url || publicMediaUrl('media', filename) || placeholderUrl
}

function pickOfficer(map, positions) {
  for (const position of positions) {
    const item = map.get(normalizePosition(position))
    if (item) {
      return item
    }
  }

  return null
}

function buildLineElement(svg, x1, y1, x2, y2) {
  const line = document.createElementNS('http://www.w3.org/2000/svg', 'line')
  line.setAttribute('x1', `${x1}`)
  line.setAttribute('y1', `${y1}`)
  line.setAttribute('x2', `${x2}`)
  line.setAttribute('y2', `${y2}`)
  line.setAttribute('class', 'org-line')
  svg.appendChild(line)
}

function OfficerCard({ officer, cardId, onOpen }) {
  if (!officer) {
    return null
  }

  return (
    <div
      className="officer-card"
      id={cardId}
      role="button"
      tabIndex={0}
      onClick={() => onOpen(officer)}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          onOpen(officer)
        }
      }}
    >
      <img className="officer-photo" src={officer.imageUrl} alt={officer.name} />
      <div className="officer-info">
        <h4>{officer.name}</h4>
        <p>{officer.position}</p>
      </div>
    </div>
  )
}

export default function Officers() {
  const [officers, setOfficers] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [activeOfficer, setActiveOfficer] = useState(null)
  const chartRef = useRef(null)
  const svgRef = useRef(null)

  useStylesheet(officersStylesheetUrl)
  useBodyClass(activeOfficer ? 'modal-open' : '')

  useEffect(() => {
    document.documentElement.classList.add('loaded')

    return () => {
      document.documentElement.classList.remove('loaded')
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadOfficers() {
      try {
        setLoading(true)
        setMessage('')

        const query = new URLSearchParams({
          category: 'national_officers',
        })
        const payload = await fetchApiJson(`${PUBLIC_OFFICERS_ENDPOINT}?${query.toString()}`)

        if (!cancelled) {
          const nextOfficers = Array.isArray(payload.data) ? payload.data : []
          setOfficers(nextOfficers)
        }
      } catch (error) {
        if (!cancelled) {
          setMessage(error.message || 'Unable to load national officers right now.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadOfficers()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setActiveOfficer(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [])

  const preparedOfficers = useMemo(
    () =>
      officers.map((officer) => ({
        ...officer,
        imageUrl: resolveOfficerImage(officer.imageUrl, officer.imageFilename),
        speechImageUrl:
          resolveOfficerImage(officer.speechImageUrl, officer.speechImageFilename) ||
          resolveOfficerImage(officer.imageUrl, officer.imageFilename),
      })),
    [officers],
  )

  const officerMap = useMemo(() => {
    const nextMap = new Map()

    for (const officer of preparedOfficers) {
      nextMap.set(normalizePosition(officer.fullPosition || officer.position), officer)
    }

    return nextMap
  }, [preparedOfficers])

  const layout = useMemo(
    () => ({
      president: pickOfficer(officerMap, ['National President']),
      secgen: pickOfficer(officerMap, ['Secretary General']),
      execvp: pickOfficer(officerMap, ['Executive National Vice President']),
      vpLuzon: pickOfficer(officerMap, ['Vice President for Luzon']),
      vpVisayas: pickOfficer(officerMap, [
        'Appointed Vice President for Visayas',
        'Vice President for Visayas',
      ]),
      vpMindanao: pickOfficer(officerMap, ['Vice President for Mindanao']),
      floorLeader: pickOfficer(officerMap, ['National Assembly Floor Leader']),
      treasurer: pickOfficer(officerMap, ['National Assembly Treasurer']),
    }),
    [officerMap],
  )

  useEffect(() => {
    const chart = chartRef.current
    const svg = svgRef.current

    if (!chart || !svg || loading) {
      return undefined
    }

    let frame = 0

    function scheduleDraw() {
      if (frame) {
        window.cancelAnimationFrame(frame)
      }

      frame = window.requestAnimationFrame(drawLines)
    }

    function getPoint(type, element, rootRect) {
      const rect = element.getBoundingClientRect()

      if (type === 'top') {
        return {
          x: (rect.left + rect.right) / 2 - rootRect.left,
          y: rect.top - rootRect.top,
        }
      }

      if (type === 'bottom') {
        return {
          x: (rect.left + rect.right) / 2 - rootRect.left,
          y: rect.bottom - rootRect.top,
        }
      }

      return {
        x: rect.right - rootRect.left,
        y: (rect.top + rect.bottom) / 2 - rootRect.top,
      }
    }

    function drawLines() {
      if (window.matchMedia('(max-width: 1100px)').matches) {
        svg.innerHTML = ''
        return
      }

      const rootRect = chart.getBoundingClientRect()
      svg.setAttribute('viewBox', `0 0 ${rootRect.width} ${rootRect.height}`)
      svg.setAttribute('width', `${rootRect.width}`)
      svg.setAttribute('height', `${rootRect.height}`)
      svg.innerHTML = ''

      const president = chart.querySelector('#card-president')
      const secgen = chart.querySelector('#card-secgen')
      const execvp = chart.querySelector('#card-execvp')
      const vpLuzon = chart.querySelector('#card-vp-luzon')
      const vpVisayas = chart.querySelector('#card-vp-visayas')
      const vpMindanao = chart.querySelector('#card-vp-mindanao')
      const floorLeader = chart.querySelector('#card-floorleader')
      const treasurer = chart.querySelector('#card-treasurer')

      if (!president || !execvp) {
        return
      }

      const presidentBottom = getPoint('bottom', president, rootRect)
      const execvpTop = getPoint('top', execvp, rootRect)

      let junctionY = (presidentBottom.y + execvpTop.y) / 2
      if (secgen) {
        const secgenRight = getPoint('right', secgen, rootRect)
        junctionY = secgenRight.y
        buildLineElement(svg, secgenRight.x, secgenRight.y, presidentBottom.x, junctionY)
      }

      buildLineElement(svg, presidentBottom.x, presidentBottom.y, presidentBottom.x, junctionY)
      buildLineElement(svg, presidentBottom.x, junctionY, execvpTop.x, execvpTop.y)

      const execvpBottom = getPoint('bottom', execvp, rootRect)
      const vicePresidents = [vpLuzon, vpVisayas, vpMindanao].filter(Boolean)

      if (vicePresidents.length > 0) {
        const topPoints = vicePresidents.map((item) => getPoint('top', item, rootRect))
        const barY = Math.min(...topPoints.map((item) => item.y)) - 18
        const centers = topPoints.map((item) => item.x).sort((first, second) => first - second)

        buildLineElement(svg, execvpBottom.x, execvpBottom.y, execvpBottom.x, barY)
        buildLineElement(svg, centers[0], barY, centers[centers.length - 1], barY)

        for (const point of topPoints) {
          buildLineElement(svg, point.x, barY, point.x, point.y)
        }

        if (vpLuzon && floorLeader) {
          const vpBottom = getPoint('bottom', vpLuzon, rootRect)
          const floorTop = getPoint('top', floorLeader, rootRect)
          buildLineElement(svg, vpBottom.x, vpBottom.y, floorTop.x, floorTop.y)
        }

        if (vpVisayas && treasurer) {
          const vpBottom = getPoint('bottom', vpVisayas, rootRect)
          const treasurerTop = getPoint('top', treasurer, rootRect)
          buildLineElement(svg, vpBottom.x, vpBottom.y, treasurerTop.x, treasurerTop.y)
        }
      }
    }

    scheduleDraw()

    const images = Array.from(chart.querySelectorAll('img'))
    for (const image of images) {
      image.addEventListener('load', scheduleDraw)
      image.addEventListener('error', scheduleDraw)
    }

    window.addEventListener('resize', scheduleDraw)

    return () => {
      if (frame) {
        window.cancelAnimationFrame(frame)
      }

      window.removeEventListener('resize', scheduleDraw)
      for (const image of images) {
        image.removeEventListener('load', scheduleDraw)
        image.removeEventListener('error', scheduleDraw)
      }
    }
  }, [layout, loading])

  const activeOfficerImage = activeOfficer?.speechImageUrl || activeOfficer?.imageUrl || placeholderUrl

  return (
    <PublicShell>
      <style>{`
        .officers-empty-state {
          width: min(880px, calc(100% - 40px));
          margin: 0 auto;
          padding: 28px 22px;
          border-radius: 18px;
          background: rgba(255, 255, 255, 0.88);
          box-shadow: 0 14px 34px rgba(0, 0, 0, 0.12);
          text-align: center;
          color: #29476d;
        }

        .officers-empty-state h3 {
          margin: 0 0 8px;
          font-family: "Merriweather", serif;
        }

        .officers-empty-state p {
          margin: 0;
          line-height: 1.7;
        }
      `}</style>

      <div className="page-wrapper">
        <section className="officers-hero">
          <h1>National Officers</h1>
          <p>Meet the leaders guiding our organization</p>
        </section>

        {loading ? (
          <div className="officers-empty-state" role="status">
            <h3>Loading officers</h3>
            <p>Please wait while we fetch the national officers from the API.</p>
          </div>
        ) : null}

        {!loading && message ? (
          <div className="officers-empty-state" role="status">
            <h3>Unable to load officers</h3>
            <p>{message}</p>
          </div>
        ) : null}

        {!loading && !message ? (
          <section className="org-chart org-v2" id="orgChart" ref={chartRef}>
            <svg className="org-lines" id="orgLines" ref={svgRef} aria-hidden="true"></svg>

            <div className="org-row row-1">
              <div className="slot center">
                <OfficerCard officer={layout.president} cardId="card-president" onOpen={setActiveOfficer} />
              </div>
            </div>

            <div className="org-row row-2">
              <div className="slot left">
                <OfficerCard officer={layout.secgen} cardId="card-secgen" onOpen={setActiveOfficer} />
              </div>
              <div className="slot center">
                <OfficerCard officer={layout.execvp} cardId="card-execvp" onOpen={setActiveOfficer} />
              </div>
              <div className="slot right slot-empty" aria-hidden="true"></div>
            </div>

            <div className="org-row row-3">
              <div className="slot col">
                <OfficerCard officer={layout.vpLuzon} cardId="card-vp-luzon" onOpen={setActiveOfficer} />
              </div>
              <div className="slot col">
                <OfficerCard officer={layout.vpVisayas} cardId="card-vp-visayas" onOpen={setActiveOfficer} />
              </div>
              <div className="slot col">
                <OfficerCard officer={layout.vpMindanao} cardId="card-vp-mindanao" onOpen={setActiveOfficer} />
              </div>
            </div>

            <div className="org-row row-4">
              <div className="slot col">
                <OfficerCard officer={layout.floorLeader} cardId="card-floorleader" onOpen={setActiveOfficer} />
              </div>
              <div className="slot col">
                <OfficerCard officer={layout.treasurer} cardId="card-treasurer" onOpen={setActiveOfficer} />
              </div>
              <div className="slot col slot-empty" aria-hidden="true"></div>
            </div>
          </section>
        ) : null}
      </div>

      <div
        className={`modal-overlay ${activeOfficer ? 'show' : ''}`}
        aria-hidden={activeOfficer ? 'false' : 'true'}
        onClick={() => setActiveOfficer(null)}
      ></div>

      {activeOfficer ? (
        <div className="detail-panel show" role="dialog" aria-modal="true" aria-label={activeOfficer.name}>
          <button
            className="close"
            type="button"
            aria-label="Close"
            onClick={() => setActiveOfficer(null)}
          >
            x
          </button>

          <div className="detail-body">
            <img className="speech-photo" src={activeOfficerImage} alt={activeOfficer.name} />
            <h3>{activeOfficer.name}</h3>
            <p className="role">{activeOfficer.fullPosition || activeOfficer.position}</p>
            <p className="speech">
              "{activeOfficer.speech || 'No message available for this officer yet.'}"
            </p>
          </div>
        </div>
      ) : null}
    </PublicShell>
  )
}
