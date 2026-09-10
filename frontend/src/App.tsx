import { BrowserRouter, Route, Routes } from 'react-router-dom'

import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import Shop from '@/pages/Shop/Shop'
import About from '@/pages/About/About'

function Home() {
  return (
    <>
      <Hero />
      <Shop />
    </>
  )
}

function App() {
  return (
    <BrowserRouter>
      <div className="app">
        <Header />

        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/shop" element={<Shop />} />
          <Route path="/about" element={<About />} />
        </Routes>
      </div>
    </BrowserRouter>
  )
}

export default App