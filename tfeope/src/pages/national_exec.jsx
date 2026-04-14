import StaticOfficerGridPage from '../components/StaticOfficerGridPage'
import nationalExecStylesheetUrl from '../../old_system/Styles/national_exec.css?url'
const officer1Url = new URL('../../old_system/officers/officer1.jpg', import.meta.url).href
const officer2Url = new URL('../../old_system/officers/officer2.jpg', import.meta.url).href
const officer3Url = new URL('../../old_system/officers/officer3.jpg', import.meta.url).href
const placeholderUrl = new URL('../../old_system/static/placeholder.png', import.meta.url).href

const sections = [
  {
    className: 'chart-level president',
    items: [
      {
        name: 'Cesar Y. Yamuta',
        role: 'Deputy Secretary General',
        speech: 'Leadership is service, not authority.',
        imageUrl: officer1Url,
      },
    ],
  },
  {
    className: 'chart-level others',
    items: [
      {
        name: 'Atty. Jesus D. Poquiz',
        role: 'Secretary General',
        speech: 'Unity begins within.',
        imageUrl: officer1Url,
      },
      {
        name: 'Rich Nicollie Z. Torrefiel',
        role: 'Information Secretary',
        speech: 'Service without borders.',
        imageUrl: officer2Url,
      },
      {
        name: 'Joey A. Valencia',
        role: 'Finance Secretary',
        speech: 'Organization is key to success.',
        imageUrl: officer3Url,
      },
      {
        name: 'Imrushsharif G. Imam',
        role: 'National Treasurer',
        speech: 'Managing resources responsibly.',
        imageUrl: placeholderUrl,
      },
      {
        name: 'Pete Gerald Javier',
        role: 'Auditor General',
        speech: 'Transparency builds trust.',
        imageUrl: placeholderUrl,
      },
      {
        name: 'Jose Philip F. Calderon, JR.',
        role: 'National Comptroller',
        speech: 'Transparency builds trust.',
        imageUrl: placeholderUrl,
      },
    ],
  },
]

export default function NationalExecutives() {
  return (
    <StaticOfficerGridPage
      stylesheetUrl={nationalExecStylesheetUrl}
      title="National Executives"
      subtitle="Meet the leaders guiding our organization"
      sections={sections}
    />
  )
}
