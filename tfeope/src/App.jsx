import { BrowserRouter, Route, Routes, useLocation } from 'react-router-dom'
import { useEffect } from 'react'
import Home from './pages/home.jsx'
import AboutUs from './pages/aboutUs.jsx'
import News from './pages/news.jsx'
import Officers from './pages/officers.jsx'
import Events from './pages/events.jsx'
import Governors from './pages/governor.jsx'
import AppointedOfficers from './pages/appointed_ofc.jsx'
import BoardOfTrustees from './pages/board_of_trustees.jsx'
import NationalCommissions from './pages/national_comm.jsx'
import NationalExecutives from './pages/national_exec.jsx'
import Membership from './pages/membership.jsx'
import MagnaCarta from './pages/magna-carta.jsx'
import Secretariat from './pages/secretariat.jsx'
import PeilDirectors from './pages/peil_directors.jsx'

function ScrollToTop() {
  const location = useLocation()

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'auto' })
  }, [location.pathname])

  return null
}

export default function App() {
  return (
    <BrowserRouter>
      <ScrollToTop />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/about-us" element={<AboutUs />} />
        <Route path="/news" element={<News />} />
        <Route path="/officers" element={<Officers />} />
        <Route path="/events" element={<Events />} />
        <Route path="/governors" element={<Governors />} />
        <Route path="/appointed-ofc" element={<AppointedOfficers />} />
        <Route path="/board-of-trustees" element={<BoardOfTrustees />} />
        <Route path="/national-commissions" element={<NationalCommissions />} />
        <Route path="/national-executives" element={<NationalExecutives />} />
        <Route path="/membership" element={<Membership />} />
        <Route path="/magna-carta" element={<MagnaCarta />} />
        <Route path="/secretariat" element={<Secretariat />} />
        <Route path="/peil-directors" element={<PeilDirectors />} />
        <Route path="*" element={<Home />} />
      </Routes>
    </BrowserRouter>
  )
}
