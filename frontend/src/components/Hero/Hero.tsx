import '@/components/Hero/Hero.scss'
import type { MouseEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ScrollArrow from '@/components/ScrollArrow/ScrollArrow'

function Hero() {
  const navigate = useNavigate()

  const handleShopClick = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    navigate('/shop')
    requestAnimationFrame(() => {
      document.getElementById('shop')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    })
  }

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
            <Link
              to="/shop"
              onClick={handleShopClick}
              className="hero__button hero__button--primary"
            >
              Przejdź do sklepu
            </Link>
          </div>
        </div>

        <div className="hero__image">
          {/* Ścieżka bezpośrednio do folderu public */}
          <img src="/assets/hero.png" alt="Pupilovo" />
        </div>

        <ScrollArrow targetId="about" />
      </div>
    </section>
  )
}

export default Hero