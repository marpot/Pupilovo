import Header from '@/components/Header/Header'
import Hero from '@/components/Hero/Hero'
import CategorySection from '@/components/CategorySection/CategorySection'

function App() {
  return (
    <div className="app">
      <Header />

      <main>
        <section id="home">
          <Hero />
        </section>

        <section id="categories">
          <CategorySection />
        </section>
      </main>
    </div>
  )
}

export default App