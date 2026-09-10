import { useLayoutEffect } from 'react'

import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import Shop from '@/pages/Shop/Shop'

function App() {
  const path = window.location.pathname

  useLayoutEffect(() => {
    if (!window.location.hash) {
      window.scrollTo(0, 0)
    }
  }, [])

  if (path === '/shop') {
    return (
      <div className="app">
        <Header />
        <Shop />
      </div>
    )
  }

  return (
    <div className="app">
      <Header />

      <main>
        <Hero />
        <Shop />
      </main>
    </div>
  )
}

export default App
