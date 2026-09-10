import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import Shop from '@/pages/Shop/Shop'

function App() {
  const path = window.location.pathname

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
