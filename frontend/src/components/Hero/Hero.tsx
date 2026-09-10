import '@/components/Hero/Hero.scss'

function Hero() {
  return (
    <section id="home" className="hero">
      <div className="hero__container">
        <div className="hero__content">
          <span className="hero__eyebrow">Pupilovo — dla Twojego pupila</span>

          <h1 className="hero__title">
            Wszystko, czego potrzebuje
            <span> Twój pupil.</span>
          </h1>

          <p className="hero__description">
            Karma, akcesoria i produkty dla zwierząt w jednym miejscu.
            Wybieramy to, co naprawdę potrzebne.
          </p>

          <div className="hero__actions">
            <a href="#shop" className="hero__button hero__button--primary">
              Przejdź do sklepu
            </a>
          </div>
        </div>

        <div className="hero__image">
          <img src="/src/assets/hero.png" alt="Pupilovo" />
        </div>
      </div>
    </section>
  )
}

export default Hero

