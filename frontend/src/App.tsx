import { useLayoutEffect } from 'react'
import { BrowserRouter, Route, Routes, useLocation } from 'react-router-dom'

import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import Shop from '@/pages/Shop/Shop'
import About from '@/pages/About/About'
import Account from '@/pages/Account/Account'
import Cart from '@/pages/Cart/Cart'

function Home() {
  const location = useLocation()

  useLayoutEffect(() => {
    if (location.pathname === '/shop' && location.hash === '#shop') {
      document.getElementById('shop')?.scrollIntoView({ behavior: 'instant', block: 'start' })
    }
  }, [location.pathname, location.hash, location.key])

  return (
    <>
      <Hero />
      <About />
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
          <Route path="/shop" element={<Home />} />
          <Route path="/about" element={<Home />} />
          <Route path="/account" element={<Account />} />
          <Route path="/cart" element={<Cart />} />
        </Routes>
      </div>
    </BrowserRouter>
  )
}

export default App
