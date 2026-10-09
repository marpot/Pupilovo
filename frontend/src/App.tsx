import { useLayoutEffect } from 'react'
import {
  BrowserRouter,
  Route,
  Routes,
  useLocation,
} from 'react-router-dom'

import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import HomeDiscovery from '@/components/HomeDiscovery/HomeDiscovery'
import About from '@/pages/About/About'
import Account from '@/pages/Account/Account'
import Cart from '@/pages/Cart/Cart'
import Checkout from '@/pages/Checkout/Checkout'
import ProductDetails from '@/pages/ProductDetails/ProductDetails'
import Shop from '@/pages/Shop/Shop'
import OrderConfirmation from '@/pages/OrderConfirmation/OrderConfirmation'
import InfoPage from '@/pages/Info/Info'
import Footer from '@/components/Footer/Footer'

function Home() {
  return (
    <>
      <Hero />
      <HomeDiscovery />
    </>
  )
}

function RouteScroll() {
  const { hash, pathname } = useLocation()

  useLayoutEffect(() => {
    if (hash) {
      const target = document.getElementById(
        decodeURIComponent(hash.slice(1)),
      )

      if (target) {
        target.scrollIntoView({ block: 'start' })
        return
      }
    }

    window.scrollTo({ top: 0, left: 0 })
  }, [hash, pathname])

  return null
}

function App() {
  return (
    <BrowserRouter>
      <div className="app">
        <RouteScroll />
        <Header />

        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/shop" element={<Shop />} />
          <Route path="/about" element={<About />} />
          <Route path="/account" element={<Account />} />
          <Route path="/cart" element={<Cart />} />

          <Route
            path="/checkout"
            element={<Checkout />}
          />

          <Route
            path="/product/:slug"
            element={<ProductDetails />}
          />

          <Route
            path="/order-confirmation/:id"
            element={<OrderConfirmation />}
          />
          {['contact', 'delivery', 'returns', 'faq', 'terms', 'privacy'].map((path) => (
            <Route key={path} path={`/${path}`} element={<InfoPage />} />
          ))}
        </Routes>
        <Footer />
      </div>
    </BrowserRouter>
  )
}

export default App
