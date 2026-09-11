import { useLayoutEffect } from 'react'
import {
  BrowserRouter,
  Route,
  Routes,
  useLocation,
} from 'react-router-dom'

import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import About from '@/pages/About/About'
import Account from '@/pages/Account/Account'
import Cart from '@/pages/Cart/Cart'
import Checkout from '@/pages/Checkout/Checkout'
import ProductDetails from '@/pages/ProductDetails/ProductDetails'
import Shop from '@/pages/Shop/Shop'
import OrderConfirmation from '@/pages/OrderConfirmation/OrderConfirmation'

function Home() {
  const location = useLocation()

  useLayoutEffect(() => {
    if (
      location.pathname === '/shop' &&
      location.hash === '#shop'
    ) {
      document.getElementById('shop')?.scrollIntoView({
        behavior: 'instant',
        block: 'start',
      })
    }
  }, [
    location.pathname,
    location.hash,
    location.key,
  ])

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
        </Routes>
      </div>
    </BrowserRouter>
  )
}

export default App