import '@/components/Header/Header.scss'

function Header() {
  return (
    <header className="header">
      <div className="header__container">
        <a href="#home" className="header__logo">
          <img src="/Pupilovo.svg" alt="" aria-hidden="true" />
        </a>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <a href="#home">Strona główna</a>
          <a href="/shop">Sklep</a>
          <a href="#categories">Kategorie</a>
          <a href="#products">Produkty</a>
          <a href="#about">O nas</a>
        </nav>

        <div className="header__actions">
          <button type="button" aria-label="Szukaj">
            🔍
          </button>

          <a href="/account" aria-label="Konto">
            👤
          </a>

          <a href="/cart" aria-label="Koszyk">
            🛒
          </a>
        </div>
      </div>
    </header>
  )
}

export default Header