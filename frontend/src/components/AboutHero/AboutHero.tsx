import '@/components/AboutHero/AboutHero.scss'
import ScrollArrow from '@/components/ScrollArrow/ScrollArrow'

function AboutHero() {
  return (
    <section className="about-hero">
      <div className="about-hero__content">
        <p className="about-hero__eyebrow">O nas</p>

        <h1>
          Dla zwierząt.
          <br />
          Z miłości.
        </h1>

        <p className="about-hero__description">
          Pupilovo powstało z prostego pomysłu — wybierać produkty,
          które naprawdę mają sens dla naszych pupili i ich opiekunów.
        </p>
      </div>

      <div className="about-hero__image">
        <img
          src="/assets/about_1.jpg"
          alt="Kot"
        />
      </div>

      <ScrollArrow targetId="shop" />
    </section>
  )
}

export default AboutHero