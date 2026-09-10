import '@/pages/Shop/Shop.scss'

function Shop() {
  return (
    <section id="shop" className="shop">
      <section className="shop__hero">
        <div className="shop__container">
          <span className="shop__eyebrow">Sklep Pupilovo</span>

          <h1 className="shop__title">
            Wszystko dla
            <span> Twojego pupila.</span>
          </h1>

          <p className="shop__description">
            Znajdź karmę, przysmaki i akcesoria dopasowane do potrzeb
            Twojego pupila.
          </p>
        </div>
      </section>

      <section className="shop__products">
        <div className="shop__container">
          <div className="shop__toolbar">
            <h2>Produkty</h2>

            <button type="button">
              Sortuj
            </button>
          </div>

          <div className="shop__grid">
            {/* Produkty z WooCommerce */}
          </div>
        </div>
      </section>
    </section>
  )
}

export default Shop