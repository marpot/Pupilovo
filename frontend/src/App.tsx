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
  const location = useLocation()

  useLayoutEffect(() => {
    const isSectionNavigation = Boolean(
      location.state?.sectionNavigation,
    )
    const behavior = isSectionNavigation ? 'smooth' : 'instant'

    if (location.pathname === '/') {
      document.scrollingElement?.scrollTo({
        top: 0,
        behavior,
      })

      return
    }

    const sectionId =
      location.pathname === '/shop'
        ? 'shop'
        : location.pathname === '/about'
          ? 'about'
          : null

    if (sectionId) {
      document.getElementById(sectionId)?.scrollIntoView({
        behavior,
        block: 'start',
      })
    }
  }, [
    location.pathname,
    location.hash,
    location.key,
    location.state,
  ])

  return (
    <>
      <Hero />
      <HomeDiscovery />
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
