import '@/pages/Shop/Shop.scss'
import ProductCard from '@/components/ProductCard/ProductCard'

function Shop() {
  return (
    <section id="shop" className="shop">
      <section className="shop__products">
        <div className="shop__container">
          <div className="shop__toolbar">
            <h1>Produkty</h1>

            <button type="button">
              Sortuj
            </button>
          </div>

          <div className="shop__grid">
            <ProductCard
              name="Miska spowalniająca Pupilovo"
              price="59,90 zł"
              image="/hero.png"
              description="Pomaga spowolnić jedzenie i wspiera zdrowe nawyki Twojego pupila."
            />
          </div>
        </div>
      </section>
    </section>
  )
}

export default Shop