import '@/components/Hero/Hero.scss'
import type { MouseEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ScrollArrow from '@/components/ScrollArrow/ScrollArrow'
import { beginSectionNavigation } from '@/utils/sectionNavigation'

function Hero() {
  const navigate = useNavigate()

  const handleShopClick = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    beginSectionNavigation('/shop')
    navigate('/shop', {
      state: { sectionNavigation: true },
    })
  }

  const handleCategoriesClick = () => {
    document.getElementById('home-categories')?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    })
  }

  return (
    <section id="home" className="hero">
      <div className="hero__container">
        <div className="hero__content">
          <span className="hero__eyebrow">Pupilovo — dla Twojego pupila</span>

          <h1 className="hero__title">
            Dobry wybór dla pupila.
            <span>Prostsze zakupy dla Ciebie.</span>
          </h1>

          <p className="hero__description">
            Odkrywaj karmy, akcesoria i produkty do codziennej opieki
            w przejrzystym katalogu z aktualną informacją o dostępności.
          </p>

          <ul className="hero__benefits" aria-label="Korzyści zakupów w Pupilovo">
            <li>Oferta uporządkowana według potrzeb zwierząt</li>
            <li>Cena i dostępność widoczne przed dodaniem do koszyka</li>
          </ul>

          <div className="hero__actions">
            <Link
              to="/shop"
              onClick={handleShopClick}
              className="hero__button hero__button--primary"
            >
              Zobacz produkty
            </Link>
            <button type="button" className="hero__button hero__button--secondary" onClick={handleCategoriesClick}>
              Wybierz kategorię
            </button>
          </div>
        </div>

        <div className="hero__image">
          {/* Ścieżka bezpośrednio do folderu public */}
          <img src="/assets/hero.png" alt="Pupilovo" />
        </div>

        <ScrollArrow targetId="home-categories" />
      </div>
    </section>
  )
}

export default Hero
