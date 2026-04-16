import StaticOfficerGridPage from '../components/StaticOfficerGridPage'
import boardStylesheetUrl from '../theme/boft.css?url'
const officer1Url = new URL('../../old_system/officers/officer1.jpg', import.meta.url).href
const officer2Url = new URL('../../old_system/officers/officer2.jpg', import.meta.url).href
const officer3Url = new URL('../../old_system/officers/officer3.jpg', import.meta.url).href
const placeholderUrl = new URL('../../old_system/static/placeholder.png', import.meta.url).href
const speechJojoUrl = new URL('../../old_system/officers/speech/1771823232_speech_jojo.png', import.meta.url).href

const sections = [
  {
    className: 'chart-level president',
    items: [
      {
        name: 'Atty. Michael Florentino R. Dumlao III',
        role: 'Chairman of the Board of Trustees',
        speech: 'Leadership is service, not authority.',
        imageUrl: officer1Url,
        modalImageUrl: speechJojoUrl,
      },
    ],
  },
  {
    className: 'chart-level others',
    items: [
      {
        name: 'Lucio F. Ceniza, PHD',
        role: 'Chairman Emeritus',
        speech: 'Unity begins within.',
        imageUrl: officer1Url,
      },
      {
        name: 'Jose "Jojo" P. Calderon',
        role: 'Board of Trustees',
        speech: 'Service without borders.',
        imageUrl: officer2Url,
      },
      {
        name: 'Erwin J. Torrefiel',
        role: 'Board of Trustees',
        speech: 'Organization is key to success.',
        imageUrl: officer3Url,
      },
      {
        name: 'Cesar Yamuta',
        role: 'Board of Trustees',
        speech: 'Managing resources responsibly.',
        imageUrl: officer1Url,
      },
      {
        name: 'Mgen Romeo V. Calizo PA (RET.)',
        role: 'Board of Trustees',
        speech: 'Transparency builds trust.',
        imageUrl: officer2Url,
      },
      {
        name: 'Jocil B. Labial',
        role: 'Board of Trustees',
        speech: 'Transparency builds trust.',
        imageUrl: officer3Url,
      },
      {
        name: 'Jaime P. Gellor, JR.',
        role: 'Board of Trustees',
        speech: 'Transparency builds trust.',
        imageUrl: officer1Url,
      },
      {
        name: 'Arnel N. Bautista',
        role: 'Board of Trustees',
        speech: 'Details will be posted soon.',
        imageUrl: placeholderUrl,
        cardClassName: 'officer-card officer-card--tba',
      },
    ],
  },
]

export default function BoardOfTrustees() {
  return (
    <StaticOfficerGridPage
      stylesheetUrl={boardStylesheetUrl}
      title="Board Of Trustees"
      subtitle="Meet the leaders guiding our organization"
      sections={sections}
    />
  )
}
