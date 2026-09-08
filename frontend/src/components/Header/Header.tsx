import '@/components/Header/Header.scss'
function Header() {
  return (
    <header className="header">
      <div className="header__container">
        <a href="/" className="header__logo">
          Pupilovo
        </a>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <a href="/">Strona główna</a>
          <a href="/shop">Sklep</a>
          <a href="/categories">Kategorie</a>
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

