import { Link } from 'react-router-dom'

import '@/components/Header/Header.scss'

function Header() {
  return (
    <header className="header">
      <div className="header__container">
        <Link to="/" className="header__logo">
          <img src="/Pupilovo.svg" alt="" aria-hidden="true" />
        </Link>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <Link to="/">Strona główna</Link>
          <Link to="/shop">Sklep</Link>
          <Link to="/about">O nas</Link>
        </nav>

        <div className="header__actions">
          <button type="button" aria-label="Szukaj">
            <span aria-hidden="true">🔍</span>
            <span>Szukaj</span>
          </button>

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