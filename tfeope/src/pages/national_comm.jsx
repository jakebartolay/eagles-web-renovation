import StaticOfficerGridPage from '../components/StaticOfficerGridPage'
import nationalCommStylesheetUrl from '../../old_system/Styles/national_comm.css?url'
const officer1Url = new URL('../../old_system/officers/officer1.jpg', import.meta.url).href
const officer2Url = new URL('../../old_system/officers/officer2.jpg', import.meta.url).href
const officer3Url = new URL('../../old_system/officers/officer3.jpg', import.meta.url).href
const placeholderUrl = new URL('../../old_system/static/placeholder.png', import.meta.url).href

const sections = [
  {
    className: 'chart-level others',
    items: [
      {
        name: 'Erwin Torrefiel',
        role: 'Comission On Membership (COME)',
        speech: 'Unity begins within.',
        imageUrl: officer1Url,
      },
      {
        name: 'Andy Paul Quitoria',
        role: 'Comission on Extension (COMEX)',
        speech: 'Service without borders.',
        imageUrl: officer2Url,
      },
      {
        name: 'Conrado Supangan JR.',
        role: 'Comission on Personal Relation',
        speech: 'Organization is key to success.',
        imageUrl: officer3Url,
      },
      {
        name: 'Jezreel S. Ayupan',
        role: 'Comission on Community Service (COMSERV)',
        speech: 'Transparency builds trust.',
        imageUrl: placeholderUrl,
      },
      {
        name: 'Russel Jocson',
        role: 'Comission On Awards and Recognition',
        speech: 'Managing resources responsibly.',
        imageUrl: placeholderUrl,
      },
    ],
  },
]

export default function NationalCommissions() {
  return (
    <StaticOfficerGridPage
      stylesheetUrl={nationalCommStylesheetUrl}
      title="National Commissions"
      subtitle="Meet the leaders guiding our organization"
      sections={sections}
    />
  )
}
