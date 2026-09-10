import { useEffect, useState } from 'react'

import '@/components/Header/Header.scss'

function Header() {
  const [, setLocation] = useState(window.location.href)

  useEffect(() => {
    const handleLocationChange = () => {
      setLocation(window.location.href)
    }

    window.addEventListener('locationchange', handleLocationChange)

    return () => {
      window.removeEventListener('locationchange', handleLocationChange)
    }
  }, [])

  const isShopPage = window.location.pathname === '/shop'

  const currentCategory = new URLSearchParams(
    window.location.search,
  ).get('category')

  const homeHref = isShopPage ? '/' : '#home'

  const shopHref = isShopPage
    ? currentCategory
      ? `/shop?category=${currentCategory}`
      : '/shop'
    : '#shop'

  const aboutHref = isShopPage ? '/#about' : '#about'

  return (
    <header className="header">
      <div className="header__container">
        <a href={homeHref} className="header__logo">
          <img src="/Pupilovo.svg" alt="" aria-hidden="true" />
        </a>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <a href={homeHref}>Strona główna</a>
          <a href={shopHref}>Sklep</a>
          <a href={aboutHref}>O nas</a>
        </nav>

        <div className="header__actions">
          <button type="button" aria-label="Szukaj">
            <span aria-hidden="true">🔍</span>
            <span>Szukaj</span>
          </button>

          <a href="/account" aria-label="Konto">
            <span aria-hidden="true">👤</span>
            <span>Konto</span>
          </a>

          <a href="/cart" aria-label="Koszyk">
            <span aria-hidden="true">🛒</span>
            <span>Koszyk</span>
          </a>
        </div>
      </div>
    </header>
  )
}

export default Header
