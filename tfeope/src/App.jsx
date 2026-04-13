import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Home from './pages/home'
import News from './pages/News'
import Events from './pages/Events'
// ...

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/"           element={<Home />} />
        <Route path="/news"       element={<News />} />
        <Route path="/events"     element={<Events />} />
        <Route path="/officers"   element={<Officers />} />
        <Route path="/governors"  element={<Governors />} />
        <Route path="/membership" element={<Membership />} />
        <Route path="/magna-carta"element={<MagnaCarta />} />
      </Routes>
    </BrowserRouter>
  )
}