import type { MouseEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'

import '@/components/Header/Header.scss'
import ProductSearch from '@/components/ProductSearch/ProductSearch'

function Header() {
  const navigate = useNavigate()

  const handleHomeClick = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    navigate('/')
    document.scrollingElement?.scrollTo({
      top: 0,
      behavior: 'smooth',
    })
  }

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

  const handleAboutClick = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    navigate('/about')
    requestAnimationFrame(() => {
      document.getElementById('about')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    })
  }

  return (
    <header className="header">
      <div className="header__container">
        <Link to="/" className="header__logo">
          <img src="/Pupilovo.svg" alt="" aria-hidden="true" />
        </Link>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <Link to="/" onClick={handleHomeClick}>
            Strona główna
          </Link>

          <Link to="/about" onClick={handleAboutClick}>
            O nas
          </Link>

          <Link to="/shop" onClick={handleShopClick}>
            Sklep
          </Link>
        </nav>

        <div className="header__actions">
          <ProductSearch />

          <Link to="/account" aria-label="Konto">
            <span aria-hidden="true">👤</span>
            <span>Konto</span>
          </Link>

          <Link to="/cart" aria-label="Koszyk">
            <span aria-hidden="true">🛒</span>
            <span>Koszyk</span>
          </Link>
        </div>
      </div>
    </header>
  )
}

export default Header
